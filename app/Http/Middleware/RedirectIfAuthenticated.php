<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\User;
use App\Models\Provider;
use App\Services\TokenProviderService;
use App\Services\SessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use App\Models\Session;
use Tymon\JWTAuth\Providers\JWT\Lcobucci;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, $guard = null)
    {
        $idpProviderId = config("idp.provider_id");
        $cookieName = "idp_token_" . $idpProviderId;

        $user = $this->resolveAuthenticatedUser($request, $guard, $idpProviderId, $cookieName);

        // Se non è loggato , lo lasciamo passare verso la pagina richiesta
        if (!$user) {
            return $next($request);
        }

        // Recuperiamo i parametri una volta sola per tutto il flusso
        $providerId = $request->input("provider_id");
        $redirectTo = $request->input("redirect_to");

        // Check provider_id e autorizzazioni base
        if (empty($providerId)) {
            if ($user->isAdmin()) {
                return redirect()->route("admin-home");
            }

            Log::warning(
                "Accesso negato: Utente {$user->username} loggato, non admin, ma nessun provider_id richiesto. Force logout.",
            );
            return $this->forceLogoutAndShowLogin($request, $cookieName, __("auth.no_application_specified"));
        }
        // Se non c'è master token (idp-master-token)
        // fare forceLogoutAndShowLogin
        $master_token_name = config("idp.jwt.master_token_name");
        $hasRequestMasterToken = $request->hasCookie($master_token_name);
        $hasQueuedMasterToken = Cookie::hasQueued($master_token_name);
        if (!$hasRequestMasterToken && !$hasQueuedMasterToken) {
            Log::warning(
                "Master Token assente (né in request né in coda) per l'utente loggato ({$user->username}). Effettuare Logout.",
            );
            return $this->forceLogoutAndShowLogin($request, $cookieName, __("auth.missing_master_token"));
        }

        $tokenService = app(TokenProviderService::class);
        $idpProviderIdMaster = config("idp.provider_id");
        $masterProvider = Provider::find($idpProviderIdMaster);
        $provider = Provider::find($providerId);

        if (!$user->hasAccessToProvider($providerId)) {
            Log::warning(
                "Seamless SSO bloccato: Utente {$user->username} non ha accesso al provider ID: {$providerId}.",
            );
            return redirect()->route("sso.unauthorized");
        }

        $redirectUrl = $provider->url;
        if ($redirectTo) {
            $host = parse_url($redirectTo, PHP_URL_HOST);
            $matchesProviderDomain = $host && !empty($provider->domain) && str_ends_with($host, $provider->domain);
            if ($matchesProviderDomain || ($host && TokenProviderService::checkLocalHost($host))) {
                $redirectUrl = $redirectTo;
            }
        }

        $masterToken = $request->cookie($master_token_name);
        //  ?: $tokenService->generateMasterToken($user, $masterProvider->id)

        $ssoData = $tokenService->resolveCrossDomainRedirect($provider, $masterProvider, $redirectUrl, $masterToken);
        $redirectUrl = $ssoData["redirectUrl"];

        // Anche qui la sessione la apre l'ingresso, non l'exchange: questo e' il caso
        // di chi e' gia' dentro e apre una seconda applicazione ore dopo, che il login esplicito non
        // vede. Senza, quel caso resterebbe senza riga e l'exchange lo lascerebbe fuori.
        (new SessionService())->openProviderSession(
            $user,
            $providerId,
            $request->ip(),
            $request->userAgent(),
            $masterToken,
        );

        Log::info("Controlli SSO superati per utente {$user->username}. Redirect finale.", [
            "redirect_away_url" => $ssoData["redirectUrl"],
            "is_cross_domain" => !$ssoData["isSameDomainZone"],
        ]);

        return redirect()->away($redirectUrl);
    }

    /**
     * Tenta di recuperare l'utente autenticato dalla sessione o dal JWT interno dell'IDP
     * (Usa HS256 per idp_token_1)
     */
    private function resolveAuthenticatedUser(Request $request, $guard, $idpProviderId, $cookieName)
    {
        if (Auth::guard($guard)->check()) {
            return Auth::guard($guard)->user();
        }

        $tokenString = $request->cookie($cookieName) ?? $request->bearerToken();
        if (!$tokenString) {
            return null;
        }

        try {
            $provider = Provider::find($idpProviderId);

            if ($provider && !empty($provider->secret_key)) {
                $algo = config("jwt.algo", "HS256");
                $keys = config("jwt.keys", []);

                $customProvider = new Lcobucci($provider->secret_key, $algo, $keys);
                $payload = $customProvider->decode($tokenString);

                if (isset($payload["exp"]) && $payload["exp"] > time()) {
                    $userId = $payload["sub"] ?? null;

                    if ($userId && Session::where("token", $tokenString)->exists()) {
                        $user = User::find($userId);
                        if ($user) {
                            Auth::login($user);
                            return $user;
                        }
                    } else {
                        Log::warning(
                            "resolveAuthenticatedUser: Token JWT valido ma utente non trovato o sessione su DB inesistente.",
                            [
                                "user_id_in_payload" => $userId,
                                "session_exists" => Session::where("token", $tokenString)->exists(),
                            ],
                        );
                    }
                } else {
                    Log::warning("resolveAuthenticatedUser: Token JWT scaduto o payload non valido.");
                }
            } else {
                Log::warning("resolveAuthenticatedUser: Provider IDP non trovato o secret_key mancante.", [
                    "idp_provider_id" => $idpProviderId,
                ]);
            }
        } catch (\Exception $e) {
            Log::error("JWT IdP non valido durante redirect SSO: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Pulisce la sessione corrente e fa passare la richiesta verso il form di login
     */
    private function forceLogoutAndShowLogin(Request $request, string $cookieName, string $errorMessage)
    {
        // Catturiamo l'id PRIMA del logout, per poter eliminare le sue sessioni DB.
        $userId = Auth::id();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $master_token_name = config("idp.jwt.master_token_name");
        $provider_idp = Provider::find(config("idp.provider_id"));

        Cookie::queue(Cookie::forget($cookieName, "/"));
        Cookie::queue(Cookie::forget("token", "/"));
        Cookie::queue(Cookie::forget($master_token_name, "/"));

        if ($provider_idp && $provider_idp->domain) {
            Cookie::queue(Cookie::forget($cookieName, "/", $provider_idp->domain));
            Cookie::queue(Cookie::forget("token", "/", $provider_idp->domain));
            Cookie::queue(Cookie::forget($master_token_name, "/", $provider_idp->domain));
        }

        if ($userId) {
            SessionService::destroyAllUserSessions((int) $userId);
        }

        // Ricarichiamo la pagina di login
        return redirect()
            ->route("loginForm", $request->query())
            ->withErrors(["login" => $errorMessage]);
    }
}
