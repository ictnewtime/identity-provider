<?php

namespace App\Http\Middleware;

use App\Auth\Idp\IdpProviderResolver;
use App\Auth\Idp\IdpSessionValidator;
use App\Auth\Idp\IdpTokenRenewer;
use App\Auth\Idp\IdpTokenExtractor;
use App\Models\Provider;
use App\Models\User;
use Closure;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Services\TokenProviderService;
use Tymon\JWTAuth\Providers\JWT\Lcobucci;

class Authenticated
{
    public function __construct(
        private readonly IdpTokenExtractor $tokenExtractor,
        private readonly IdpProviderResolver $providerResolver,
        private readonly IdpSessionValidator $sessionValidator,
        private readonly IdpTokenRenewer $renewer,
    ) {}

    public function handle($request, Closure $next)
    {
        $tokenString = $this->tokenExtractor->extract($request);

        if (empty($tokenString)) {
            Log::warning(
                "Fallimento: Nessun token trovato nel cookie [{$this->tokenExtractor->cookieName()}] o nell'header Bearer.",
                $this->tokenExtractor->missingTokenContext($request),
            );

            return $this->forceLogoutAndRedirect($request, "Token assente. Effettua il login.");
        }

        $provider = $this->providerResolver->resolve();

        if (!$this->providerResolver->isUsable($provider)) {
            Log::error("Impossibile validare il token: Provider IdP non trovato o secret_key mancante.");

            return $this->forceLogoutAndRedirect($request, "Configurazione di sicurezza mancante.");
        }

        try {
            $payload = $this->decode($provider->secret_key, $tokenString);
        } catch (TokenExpiredException $e) {
            Log::warning("App token scaduto: provo il rinnovo col master token.");

            $renewal = $this->renewer->renew($request, $provider);

            if ($renewal["outcome"] === IdpTokenRenewer::OUTCOME_MASTER_MISSING) {
                return $this->refuse($request, __("auth.renew-failed"));
            }

            if ($renewal["outcome"] === IdpTokenRenewer::OUTCOME_REFUSED) {
                return $this->refuse($request, __("auth.renew-refused"));
            }

            // Rinnovato: si prosegue con il token nuovo, e il cookie lo porta al browser.
            $tokenString = $renewal["token"];
            $payload = $this->decode($provider->secret_key, $tokenString);

            Cookie::queue((new TokenProviderService())->cookieCretion($tokenString, (string) $provider->id));
        } catch (\Exception $e) {
            Log::error("Errore decodifica JWT: " . $e->getMessage());

            return $this->forceLogoutAndRedirect($request, __("auth.token-invalid"));
        }

        $user = $this->resolveUser($payload);

        if ($user === null) {
            // Il motivo preciso — claim mancante o utente sparito — l'ha gia' scritto nel log
            // `resolveUser()`. Qui interessa solo che non si passa.
            return $this->forceLogoutAndRedirect($request, "Token corrotto o utente non trovato.");
        }

        Auth::login($user);

        if (!$this->sessionValidator->isAlive($tokenString)) {
            Log::critical(
                "Accesso negato: Il token è valido crittograficamente ma la sessione è stata eliminata dal database",
            );

            return $this->forceLogoutAndRedirect($request, 'La tua sessione è stata terminata dall\'amministratore.');
        }

        return $next($request);
    }

    /**
     * Decodifica e scadenza. La verifica di `exp` resta esplicita: un token senza scadenza non e'
     * un errore per la libreria, ma qui va almeno registrato.
     *
     * @throws TokenExpiredException se il token e' scaduto
     */
    private function decode(string $secretKey, string $tokenString): array
    {
        $jwt = new Lcobucci($secretKey, config("jwt.algo", "HS256"), config("jwt.keys", []));

        $payload = $jwt->decode($tokenString);

        if (!isset($payload["exp"])) {
            Log::warning("Attenzione: Il token decodificato NON ha il claim 'exp' (scadenza).");

            return $payload;
        }

        if ($payload["exp"] < time()) {
            Log::warning("Fallimento: Il token è scaduto!");

            throw new TokenExpiredException("Token has expired");
        }

        return $payload;
    }

    private function resolveUser(array $payload): ?User
    {
        $userId = $payload["sub"] ?? null;

        if (!$userId) {
            Log::warning("Fallimento: Token decodificato ma claim 'sub' (User ID) mancante.");

            return null;
        }

        $user = User::find($userId);

        if (!$user) {
            Log::warning("Fallimento: Utente ID {$userId} non esiste più nel database.");
        }

        return $user;
    }

    /**
     * Una chiamata API, e non una navigazione: chiede JSON e non e' Inertia.
     *
     * E' lo stesso criterio che `forceLogoutAndRedirect()` usa piu' sotto per decidere se rispondere
     * in JSON invece di reindirizzare — scritto qui una volta perche' ora serve a due decisioni.
     *
     * Rifiuta senza toccare i cookie, se e' una chiamata API.
     * A una navigazione si risponde come sempre — logout e ritorno al login — perche' li' il browser
     * deve ripartire da capo. A una chiamata API si risponde **401 e basta**: quella richiesta
     * fallisce, e la sessione del browser resta quella che era.
     *
     * PERCHE' CONTA: `forceLogoutAndRedirect()` accoda `Cookie::forget`, quindi una XHR rifiutata
     * portava via il cookie del **browser** — e la navigazione successiva non aveva piu' niente in
     * mano. Era quello il meccanismo dello sloggamento, non il 401.
     */
    private function refuse($request, string $message)
    {
        if ($this->isApiCall($request)) {
            Log::info("[RINNOVO] rifiutato a una chiamata API: 401, e i cookie non si toccano.");

            return response()->json(["message" => $message], 401);
        }

        return $this->forceLogoutAndRedirect($request, $message);
    }

    private function isApiCall($request): bool
    {
        return $request->expectsJson() && !$request->header("X-Inertia");
    }

    protected function forceLogoutAndRedirect($request, $message)
    {
        $idpProviderId = config("idp.provider_id");
        $cookieName = "idp_token_" . $idpProviderId;
        $provider = Provider::find($idpProviderId);

        $domain = $provider?->domain;

        Cookie::queue(Cookie::forget($cookieName, "/", $domain));
        Cookie::queue(Cookie::forget("token", "/", $domain));

        if ($request->expectsJson() && !$request->header("X-Inertia")) {
            return response()->json(["message" => $message], 401);
        }

        if ($request->hasSession()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()
            ->route("loginForm")
            ->withErrors([
                "login" => $message,
            ]);
    }
}
