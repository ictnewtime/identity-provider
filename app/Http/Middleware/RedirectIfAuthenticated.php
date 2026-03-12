<?php

namespace App\Http\Middleware;

use App\Services\TokenProviderService;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\MessageBag;
use Tymon\JWTAuth\Providers\JWT\Lcobucci;
use App\Models\Session;
use App\Models\User;
use App\Models\Provider;

class RedirectIfAuthenticated
{
    public function handle($request, Closure $next, $guard = null)
    {
        $isAuthenticated = false;
        $user = null;
        $idpProviderId = config("idp.provider_id");
        $cookieName = "idp_token_" . $idpProviderId;
        $tokenString = $request->cookie($cookieName) ?? $request->bearerToken();

        // 1. Controllo standard di Laravel
        if (Auth::guard($guard)->check()) {
            $isAuthenticated = true;
            $user = Auth::guard($guard)->user();
        }
        // 2. Controllo del nostro JWT dell'IdP ("Grant Token")
        elseif ($tokenString) {
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
                                $isAuthenticated = true;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::debug("JWT IdP non valido durante redirect SSO: " . $e->getMessage());
            }
        }

        if (!$isAuthenticated) {
            return $next($request);
        }

        $provider_id = $request->input("provider_id");

        // Se non ha richiesto un'app esterna
        if (empty($provider_id)) {
            if ($user && $user->isAdmin()) {
                return redirect()->route("admin-home");
            }
            Log::warning("Accesso negato: Utente loggato ma nessun provider_id richiesto.");
            return $this->forceLogoutAndShowLogin($request, $cookieName, "Nessuna applicazione specificata.");
        }

        Log::info("Seamless SSO innescato! Rinnovo accesso automatico per Provider ID: {$provider_id}");

        $ssoData = TokenProviderService::respondWithSsoRedirect(
            $user,
            $provider_id,
            $request,
            $request->input("redirect_to"),
        );

        if (!$ssoData) {
            Log::warning("Seamless SSO Fallito: L'utente non ha i permessi. Lascio caricare il form di login.");

            // Iniettiamo un messaggio di errore nella sessione per la richiesta corrente.
            // Inertia lo leggerà in automatico mettendolo in $page.props.errors.login
            $request
                ->session()
                ->now(
                    "errors",
                    (new MessageBag())->add(
                        "login",
                        'Non hai i permessi per accedere a questa applicazione. Accedi con un altro account o attendi l\'abilitazione.',
                    ),
                );

            // Invece di fare un redirect, "apriamo le porte" e lasciamo che
            // la richiesta arrivi al LoginController che mostrerà la pagina!
            return $next($request);
        }

        Cookie::queue($ssoData["cookie"]);
        return redirect()->away($ssoData["url"])->withCookie($ssoData["cookie"]);
    }

    /**
     * Pulisce la sessione corrente e fa passare la richiesta verso il form di login
     * evitando il famigerato loop di redirect.
     */
    private function forceLogoutAndShowLogin($request, $cookieName, $errorMessage)
    {
        // 1. Distruggiamo la sessione Laravel
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // 2. Prepariamo la distruzione dei cookie IdP
        $domain = env("PROVIDER_DOMAIN");
        $cookie1 = Cookie::forget($cookieName, "/", $domain);
        $cookie2 = Cookie::forget("token", "/", $domain);

        $queryParams = $request->query();

        // 3. Ricarichiamo la pagina di login passandogli I PARAMETRI, i messaggi e uccidendo i cookie
        return redirect()
            ->route("loginForm", $queryParams) // <-- Passiamo l'array qui!
            ->withErrors(["login" => $errorMessage])
            ->withCookie($cookie1)
            ->withCookie($cookie2);
    }
}
