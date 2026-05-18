<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\User;
use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;
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

        // check scadenza Password
        if (is_null($user->password_expires_at) || now()->greaterThanOrEqualTo($user->password_expires_at)) {
            Log::warning("Seamless SSO bloccato: Utente {$user->username} ha la password scaduta.");

            if ($providerId) {
                $request->session()->put("pending_sso_provider_id", $providerId);
                $request->session()->put("pending_sso_redirect_to", $redirectTo);
            }

            return redirect()->route("password.expired");
        }

        // Check provider_id e autorizzazioni base
        if (empty($providerId)) {
            if ($user->isAdmin()) {
                return redirect()->route("admin-home");
            }

            Log::warning("Accesso negato: Utente loggato ma nessun provider_id richiesto.");
            return $this->forceLogoutAndShowLogin($request, $cookieName, __("auth.password_same_as_old"));
        }
        // Se non c'è master token (idp-master-token)
        // fare forceLogoutAndShowLogin
        $master_token_name = config("idp.jwt.master_token_name");
        $hasRequestMasterToken = $request->hasCookie($master_token_name);
        $hasQueuedMasterToken = Cookie::hasQueued($master_token_name);
        if (!$hasRequestMasterToken && !$hasQueuedMasterToken) {
            Log::warning("Master Token assente per l'utente loggato ({$user->id}). Effettuare Logout");
            return $this->forceLogoutAndShowLogin($request, $cookieName, __("auth.no_application_specified"));
        }
        return redirect()->away($redirectTo);
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
                    }
                }
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
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $provider_idp = Provider::find(config("idp.provider_id"));

        Cookie::queue(Cookie::forget($cookieName, "/"));
        Cookie::queue(Cookie::forget("token", "/"));
        Cookie::queue(Cookie::forget("idp-master-token", "/"));

        if ($provider_idp && $provider_idp->domain) {
            Cookie::queue(Cookie::forget($cookieName, "/", $provider_idp->domain));
            Cookie::queue(Cookie::forget("token", "/", $provider_idp->domain));
            Cookie::queue(Cookie::forget("idp-master-token", "/", $provider_idp->domain));
        }

        // Ricarichiamo la pagina di login
        return redirect()
            ->route("loginForm", $request->query())
            ->withErrors(["login" => $errorMessage]);
    }
}
