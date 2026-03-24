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

        // 1. Risoluzione dell'Utente (Sessione standard o validazione JWT)
        $user = $this->resolveAuthenticatedUser($request, $guard, $idpProviderId, $cookieName);

        // Se non è loggato in nessun modo, lo lasciamo passare verso la pagina richiesta (es. il Login)
        if (!$user) {
            return $next($request);
        }

        // Recuperiamo i parametri una volta sola per tutto il flusso
        $providerId = $request->input("provider_id");
        $redirectTo = $request->input("redirect_to");

        // 2. CONTROLLO SICUREZZA: Scadenza Password
        if (is_null($user->password_expires_at) || now()->greaterThanOrEqualTo($user->password_expires_at)) {
            Log::info("Seamless SSO bloccato: Utente {$user->username} ha la password scaduta.");

            if ($providerId) {
                $request->session()->put("pending_sso_provider_id", $providerId);
                $request->session()->put("pending_sso_redirect_to", $redirectTo);
            }

            return redirect()->route("password.expired");
        }

        // 3. BIVIO LOCALE: Accesso diretto all'IdP
        if (empty($providerId)) {
            if ($user->isAdmin()) {
                return redirect()->route("admin-home");
            }

            Log::warning("Accesso negato: Utente loggato ma nessun provider_id richiesto.");
            return $this->forceLogoutAndShowLogin($request, $cookieName, "Nessuna applicazione specificata.");
        }

        // 4. BIVIO SSO: Seamless SSO verso App esterna
        Log::info("Seamless SSO innescato! Rinnovo accesso automatico per Provider ID: {$providerId}");

        $ssoData = TokenProviderService::respondWithSsoRedirect($user, $providerId, $request, $redirectTo);

        if (!$ssoData) {
            return $this->handleSsoFailure($request, $providerId);
        }

        Log::info("Seamless SSO Response: " . json_encode($ssoData));
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

        // 2. Controllo Token JWT custom dell'IdP
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
            Log::debug("JWT IdP non valido durante redirect SSO: " . $e->getMessage());
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
        $cookieIdp = Cookie::forget($cookieName, "/", $provider->domain);

        return redirect()->route("sso.unauthorized")->withCookie($cookieIdp);
    }

    /**
     * Pulisce la sessione corrente e fa passare la richiesta verso il form di login
     * evitando il famigerato loop di redirect.
     */
    private function forceLogoutAndShowLogin(Request $request, string $cookieName, string $errorMessage)
    {
        // 1. Distruggiamo la sessione Laravel
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // 2. Prepariamo la distruzione dei cookie IdP
        $provider_idp = Provider::find(config("idp.provider_id"));
        $cookie1 = Cookie::forget($cookieName, "/", $provider_idp->domain);
        $cookie2 = Cookie::forget("token", "/", $provider_idp->domain);

        // 3. Ricarichiamo la pagina di login passandogli i parametri originali
        return redirect()
            ->route("loginForm", $request->query())
            ->withErrors(["login" => $errorMessage])
            ->withCookie($cookie1)
            ->withCookie($cookie2);
    }
}
