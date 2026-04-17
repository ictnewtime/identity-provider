<?php

namespace App\Http\Middleware;

use App\Services\TokenProviderService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Providers\JWT\Lcobucci;
use App\Models\Session;
use App\Models\User;
use App\Models\Provider;

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

        // check scadenza Password
        if (is_null($user->password_expires_at) || now()->greaterThanOrEqualTo($user->password_expires_at)) {
            Log::info("Seamless SSO bloccato: Utente {$user->username} ha la password scaduta.");

            if ($providerId) {
                $request->session()->put("pending_sso_provider_id", $providerId);
                $request->session()->put("pending_sso_redirect_to", $redirectTo);
            }

            return redirect()->route("password.expired");
        }

        // Check provider_id e autorizzazioni
        if (empty($providerId)) {
            if ($user->isAdmin()) {
                return redirect()->route("admin-home");
            }

            Log::warning("Accesso negato: Utente loggato ma nessun provider_id richiesto.");
            return $this->forceLogoutAndShowLogin($request, $cookieName, "Nessuna applicazione specificata.");
        }

        // Se l'utente è admin, lo mandiamo alla dashboard dell'IdP
        $ssoData = TokenProviderService::respondWithSsoRedirect($user, $providerId, $request, $redirectTo);

        if (!$ssoData) {
            return $this->handleSsoFailure($request, $providerId);
        }

        // Log::info("Seamless SSO Response: " . json_encode($ssoData));
        Cookie::queue($ssoData["cookie"]);

        return redirect()->away($ssoData["url"])->withCookie($ssoData["cookie"]);
    }

    /**
     * Tenta di recuperare l'utente autenticato dalla sessione o dal JWT (Grant Token)
     */
    private function resolveAuthenticatedUser(Request $request, $guard, $idpProviderId, $cookieName)
    {
        // 1. Controllo standard Sessione Laravel
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
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("JWT IdP non valido durante redirect SSO: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Gestisce il fallimento dell'autorizzazione per l'App esterna
     */
    private function handleSsoFailure(Request $request, string $providerId)
    {
        Log::warning("Seamless SSO Fallito: L'utente non ha i permessi per l'App {$providerId}.");

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $cookieName = "idp_token_" . config("idp.provider_id");
        $provider = Provider::find(config("idp.provider_id"));

        Log::warning("Seamless SSO Fallito: provider-domain: " . $provider->domain);

        Cookie::queue(Cookie::forget($cookieName, "/"));
        if ($provider && $provider->domain) {
            Cookie::queue(Cookie::forget($cookieName, "/", $provider->domain));
        }

        return redirect()->route("sso.unauthorized");
    }

    /**
     * Pulisce la sessione corrente e fa passare la richiesta verso il form di login
     * evitando il famigerato loop di redirect.
     */
    private function forceLogoutAndShowLogin(Request $request, string $cookieName, string $errorMessage)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $provider_idp = Provider::find(config("idp.provider_id"));

        Cookie::queue(Cookie::forget($cookieName, "/"));
        Cookie::queue(Cookie::forget("token", "/"));

        if ($provider_idp && $provider_idp->domain) {
            Cookie::queue(Cookie::forget($cookieName, "/", $provider_idp->domain));
            Cookie::queue(Cookie::forget("token", "/", $provider_idp->domain));
        }

        // Ricarichiamo la pagina di login
        return redirect()
            ->route("loginForm", $request->query())
            ->withErrors(["login" => $errorMessage]);
    }
}
