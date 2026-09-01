<?php

namespace App\Http\Controllers\JwtAuth;

use App\Events\LoginEvent;
use Illuminate\Http\Request;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\Session;
use App\Models\User;
use App\Services\SessionService;
use App\Services\TokenProviderService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;

class LoginController extends Controller
{
    /**
     * Shows the login form or redirect the user to the application if he is authenticated.
     *
     * @return \Inertia\Response|\Illuminate\Http\RedirectResponse
     */
    public function showLoginForm()
    {
        return Inertia::render("Auth/Login", []);
    }

    public function login(LoginRequest $request)
    {
        $credentials = $request->only("username", "password");

        if (!Auth::attempt(["username" => $credentials["username"], "password" => $credentials["password"]])) {
            Log::warning("[LOGIN] Check Credenziali fallito per " . $credentials["username"]);
            return back()->withErrors(["login" => __("auth.err-login")]);
        }

        $user = Auth::user();

        return $this->processSsoRedirect($request, $user);
    }

    public function redirectToGoogle(Request $request)
    {
        if ($request->has("provider_id")) {
            $request->session()->put("sso_provider_id", $request->input("provider_id"));
            $request->session()->put("sso_redirect_to", $request->input("redirect_to"));
        }

        return Socialite::driver("google")->redirect();
    }

    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver("google")->user();
        } catch (\Exception $e) {
            return redirect()
                ->route("loginForm")
                ->withErrors(["login" => __("auth.google_login_failed")]);
        }

        $user = User::where("google_id", $googleUser->getId())->first();
        if (!$user) {
            $user = User::where("email", $googleUser->getEmail())->first();

            if ($user) {
                // L' user esiste, ma è la prima volta che usa Google.
                $user->update([
                    "google_id" => $googleUser->getId(),
                ]);
            } else {
                // L' user non esiste allinterno dell applicazione IDP.
                return redirect()
                    ->route("loginForm")
                    ->withErrors(["login" => __("auth.google_login_without_account")]);
            }
        }

        Auth::login($user);
        // Recuperia i parametri salvati prima del redirect a Google
        $provider_id = $request->session()->pull("sso_provider_id");
        $redirect_to = $request->session()->pull("sso_redirect_to");

        $request->merge(["provider_id" => $provider_id, "redirect_to" => $redirect_to]);
        return $this->processSsoRedirect($request, $user, true);
    }

    private function processSsoRedirect($request, $user, $skipPasswordExpiryCheck = false)
    {
        $provider_id = $request->input("provider_id");
        $redirect_to = $request->input("redirect_to");

        if (
            !$skipPasswordExpiryCheck &&
            (is_null($user->password_expires_at) || now()->greaterThanOrEqualTo($user->password_expires_at))
        ) {
            Log::warning("Utente {$user->username} ha la password scaduta. Blocco generazione token.");
            if ($provider_id) {
                $request->session()->put("pending_sso_provider_id", $provider_id);
                $request->session()->put("pending_sso_redirect_to", $redirect_to);
            }
            return redirect()->route("password.expired");
        }

        // Check Utente Disabilitato
        if (!$user->enabled) {
            Log::warning("SSO bloccato: Utente {$user->username} è disabilitato.", [
                "is_enabled" => $user->enabled,
            ]);
            Auth::logout();
            return redirect()->route("sso.unauthorized");
        }

        // Risoluzione Provider e Check Autorizzazioni
        // Se provider_id è null, significa che stà facendo acesso al IDP
        $targetProviderId = $provider_id ?? config("idp.provider_id");
        if (!$user->hasAccessToProvider($targetProviderId)) {
            Log::warning("SSO bloccato: Utente {$user->username} non ha accesso al provider ID: {$targetProviderId}.");
            return redirect()->route("sso.unauthorized");
        }

        // dopo essermi autenticato con la login classica o con socialite/google
        // creo il master-token e se dovevo fare una redirect, la effettuo
        // solo quando sono nella App2 chiedo i ruoli in un token-JWT
        $tokenService = new TokenProviderService();
        $idpProviderId = (string) config("idp.provider_id");
        $masterProvider = Provider::find($idpProviderId);

        $masterToken = $tokenService->generateMasterToken($user, $masterProvider->id);

        Log::debug("[LOGIN] Master token generato", [
            "user_id" => $user->id,
            "provider_id_richiesto" => $provider_id,
            "master_token" => SessionService::tokenFingerprint($masterToken),
        ]);
        $provider = Provider::find($provider_id);
        // se il providerIdMaster->domain contiene il provider->doamin allora posso creare il cookie
        // altrimenti usare appendTokenIfLocalUrl( redirect_url, token)
        // così l' idp-extensio prenderà il token e lo trasformerà in cookie
        $redirectUrl = $redirect_to ?? ($provider ? $provider->url : null);

        $ssoData = $tokenService->resolveCrossDomainRedirect($provider, $masterProvider, $redirectUrl, $masterToken);
        $redirectUrl = $ssoData["redirectUrl"];
        if ($ssoData["isSameDomainZone"]) {
            $master_token_name = config("idp.jwt.master_token_name");
            $masterCookie = $tokenService->cookieCretion($masterToken, $masterProvider->id, $master_token_name);
            Cookie::queue($masterCookie);
        }

        if ($provider_id) {
            // La sessione del provider di destinazione la apre il LOGIN (punto TMT28), non piu'
            // l'exchange: cosi' l'exchange puo' rifiutare di creare, e una revoca vale (VDF14).
            // Se fallisce non si blocca il redirect: l'utente entra e il motivo sta nel log.
            (new SessionService())->openProviderSession(
                $user,
                $provider_id,
                $request->ip(),
                $request->userAgent(),
                $masterToken,
            );

            // devo semplicemente ottenere l' url e redirigere l' user
            return Inertia::location($redirectUrl);
        }

        // solo se il provider id è vouto, creo il token App2
        // in quanto è da considerare App2 == IDP
        // L'utente va verso l'home dell'IdP
        if ($user->isAdmin()) {
            $request->session()->regenerate();

            $tokenService = new TokenProviderService();
            $sessionService = new SessionService();

            $ip_address = $request->ip();
            // Il master token va nella riga anche qui (punto TMT03): senza, l'IdP sarebbe l'unico
            // posto che non puo' rinnovare — cioe' proprio quello da cui nasce il difetto delle
            // disconnessioni a trenta minuti.
            $appToken = $sessionService->getValidProviderToken(
                $user,
                $idpProviderId,
                $ip_address,
                $request->userAgent(),
                $tokenService,
                $masterToken,
            );

            Log::debug("[LOGIN] Sessione IdP aperta", [
                "user_id" => $user->id,
                "provider_id" => $idpProviderId,
                "app_token" => SessionService::tokenFingerprint($appToken),
                "master_token" => SessionService::tokenFingerprint($masterToken),
            ]);

            if (!$appToken) {
                Log::warning("AppToken non generato (nessun ruolo valido per admin). Fallback su sso.unauthorized.");
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return redirect()->route("sso.unauthorized");
            }

            $appCookie = $tokenService->cookieCretion($appToken, $idpProviderId);
            Cookie::queue($appCookie);

            return redirect()->route("admin-home");
        }

        // Se utente non è admin, lo mandiamo alla pagina non autorizzato
        Log::warning("Utente non admin senza provider_id. Fallback su sso.unauthorized.");
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route("sso.unauthorized");
    }

    public function logout_web(Request $request)
    {
        return $this->performLogout($request, route("loginForm"));
    }

    /**
     * Logout SSO (Il browser atterra qui dopo essere uscito da App2)
     */
    public function logout_sso(Request $request)
    {
        // Se App2 ci passa URL di ritorno, lo usiamo
        // se ci  passa un provider_id troviamo l' URL e lo usiamo, altrimenti andiamo al login
        $redirectTo = $request->query("redirect_to");
        if (empty($redirectTo) && $request->has("provider_id")) {
            $provider = Provider::find($request->query("provider_id"));
            if ($provider && !empty($provider->url)) {
                $redirectTo = $provider->url;
            }
        }
        if (empty($redirectTo)) {
            $redirectTo = route("loginForm");
        }

        return $this->performLogout($request, $redirectTo);
    }

    private function performLogout(Request $request, $redirectUrl)
    {
        $idpProviderId = config("idp.provider_id");
        $dynamicCookieName = "idp_token_" . $idpProviderId;
        $provider = Provider::find($idpProviderId);
        $cookieDomain = $provider ? $provider->domain : null; // es. .miosito.it (o null per localhost)

        // Prima proviamo con Auth, poi con il cookie (per sicurezza)
        $userId = Auth::id();

        if (!$userId && $request->cookie($dynamicCookieName)) {
            $tokenString = $request->cookie($dynamicCookieName);
            $parts = explode(".", $tokenString);
            if (count($parts) === 3) {
                $payload = json_decode(base64_decode(strtr($parts[1], "-_", "+/")), true);
                $userId = $payload["sub"] ?? null;
            }
        }

        if ($userId) {
            $sessions = Session::where("user_id", $userId)->get();
            // Lascaire foreach se si vuole avere l' audit di ogni sessione cancellata
            // riga per riga
            foreach ($sessions as $session) {
                $session->delete();
            }
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $master_token_name = config("idp.jwt.master_token_name");
        $cookiesToForget = [
            Cookie::forget($dynamicCookieName, "/", $cookieDomain),
            Cookie::forget($master_token_name, "/", $cookieDomain),
            Cookie::forget("token", "/", $cookieDomain),
            Cookie::forget("laravel_session", "/", $cookieDomain),
            // Fallback per localhost
            Cookie::forget($dynamicCookieName),
            Cookie::forget($master_token_name),
            Cookie::forget("token"),
        ];

        if (($request->ajax() || $request->wantsJson()) && !$request->header("X-Inertia")) {
            $response = response()->json(["message" => "Logged out successfully"], 200);
        } else {
            $response = redirect()->away($redirectUrl);

            if (str_contains($redirectUrl, route("loginForm"))) {
                $response->withErrors(["login" => "Disconnessione completata con successo."]);
            }
        }

        // Aggiungo i cookie alla risposta
        foreach ($cookiesToForget as $cookie) {
            $response->withCookie($cookie);
        }

        return $response;
    }
}
