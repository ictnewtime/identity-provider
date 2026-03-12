<?php

namespace App\Http\Middleware;

use App\Models\Provider;
use App\Models\Session;
use App\Models\User;
use Closure;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Providers\JWT\Lcobucci;

class Authenticated
{
    public function handle($request, Closure $next)
    {
        $idpProviderId = config("idp.provider_id");
        $cookieName = "idp_token_" . $idpProviderId;

        // Estrazione del token
        $tokenString = $request->cookie($cookieName) ?? $request->bearerToken();

        if (empty($tokenString)) {
            Log::warning("Fallimento: Nessun token trovato nel cookie [{$cookieName}] o nell'header Bearer.");
            return $this->forceLogoutAndRedirect($request, "Token assente. Effettua il login.");
        }

        try {
            $provider = Provider::find($idpProviderId);

            if (!$provider || empty($provider->secret_key)) {
                Log::error("Impossibile validare il token: Provider IdP non trovato o secret_key mancante.");
                return $this->forceLogoutAndRedirect($request, "Configurazione di sicurezza mancante.");
            }

            // VALIDAZIONE MANUALE CON LCOBUCCI (Esattamente come in tokenCretion)
            $algo = config("jwt.algo", "HS256");
            $keys = config("jwt.keys", []);

            // Creiamo il provider specifico al volo per decodificare
            $customProvider = new Lcobucci($provider->secret_key, $algo, $keys);

            // Proviamo a decodificare. Se la firma o la sintassi sono errate, lancerà un'eccezione
            $payload = $customProvider->decode($tokenString);

            // VERIFICA SCADENZA (exp)
            if (isset($payload["exp"]) && $payload["exp"] < time()) {
                throw new TokenExpiredException("Token has expired");
            }

            // ESTRAZIONE UTENTE (sub)
            $userId = $payload["sub"] ?? null;
            if (!$userId) {
                Log::warning("Fallimento: Token decodificato ma claim 'sub' (User ID) mancante.");
                return $this->forceLogoutAndRedirect($request, "Token corrotto (ID utente mancante).");
            }

            $user = User::find($userId);
            if (!$user) {
                Log::warning("Fallimento: Utente ID {$userId} non esiste più nel database.");
                return $this->forceLogoutAndRedirect($request, "Utente non trovato.");
            }

            // Diciamo a Laravel chi è l'utente corrente per questa richiesta,
            // così Auth::user() funzionerà nel resto del codice!
            Auth::login($user);

            // CONTROLLO FISICO SUL DATABASE (La famosa riga di sessione)
            $sessionExists = Session::where("token", $tokenString)->exists();

            if (!$sessionExists) {
                Log::critical(
                    "ACCESSO NEGATO: Il token è valido crittograficamente MA la sessione è stata ELIMINATA dal database!",
                );
                return $this->forceLogoutAndRedirect(
                    $request,
                    'La tua sessione è stata terminata dall\'amministratore.',
                );
            }
        } catch (TokenExpiredException $e) {
            return $this->forceLogoutAndRedirect($request, __("auth.token-expired"));
        } catch (\Exception $e) {
            return $this->forceLogoutAndRedirect($request, __("auth.token-invalid"));
        }

        return $next($request);
    }

    protected function forceLogoutAndRedirect($request, $message)
    {
        $idpProviderId = config("idp.provider_id");
        $cookieName = "idp_token_" . $idpProviderId;

        // Distruggiamo la sessione web nativa di Laravel
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Eliminiamo i cookie accodandoli per la risposta
        $domain = env("PROVIDER_DOMAIN"); // o null
        Cookie::queue(Cookie::forget($cookieName, "/", $domain));
        Cookie::queue(Cookie::forget("token", "/", $domain));

        // Se è una richiesta API pura o AJAX (non Inertia)
        if ($request->expectsJson() && !$request->header("X-Inertia")) {
            return response()->json(["message" => $message], 401);
        }

        return redirect()
            ->route("loginForm")
            ->withErrors([
                "login" => $message,
            ]);
    }
}
