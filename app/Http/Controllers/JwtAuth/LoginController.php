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
        return Inertia::render("Auth/Login", [
            // 'status' => session('status'),
        ]);
    }

    public function login(LoginRequest $request)
    {
        $credentials = $request->only("username", "password");

        if (!Auth::attempt(["username" => $credentials["username"], "password" => $credentials["password"]])) {
            Log::warning("Check Credenziali. Login fallito per utente " . $credentials["username"]);
            return back()->withErrors(["login" => __("auth.err-login")]);
        }

        return $this->processSsoRedirect(
            $request,
            Auth::user(),
            $request->input("provider_id"),
            $request->input("redirect_to"),
        );
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
                ->route("login")
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
                    ->route("login")
                    ->withErrors(["login" => __("auth.google_login_without_account")]);
            }
        }

        Auth::login($user);
        // Recuperia i parametri salvati prima del redirect a Google
        $provider_id = $request->session()->pull("sso_provider_id");
        $redirect_to = $request->session()->pull("sso_redirect_to");

        $request->merge(["provider_id" => $provider_id, "redirect_to" => $redirect_to]);
        return $this->processSsoRedirect($request, $user, $provider_id, $redirect_to);
    }

    private function processSsoRedirect($request, $user, $provider_id, $redirect_to)
    {
        if (is_null($user->password_expires_at) || now()->greaterThanOrEqualTo($user->password_expires_at)) {
            Log::warning("Utente {$user->username} ha la password scaduta. Blocco generazione token.");

            if ($provider_id) {
                $request->session()->put("pending_sso_provider_id", $provider_id);
                $request->session()->put("pending_sso_redirect_to", $request->input("redirect_to"));
            }

            return redirect()->route("password.expired");
        }

        if ($provider_id) {
            $ssoData = TokenProviderService::respondWithSsoRedirect(
                $user,
                $provider_id,
                $request,
                $request->input("redirect_to"),
            );

            if (!$ssoData) {
                // L'utente non ha ruoli validi per quel provider
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return redirect()->route("sso.unauthorized");
            }

            $parsedTargetHost = parse_url($ssoData["url"], PHP_URL_HOST);
            $isLocalhostTarget = TokenProviderService::checkLocalHost($parsedTargetHost);
            if (!$isLocalhostTarget) {
                Cookie::queue($ssoData["cookie"]);
            }
            return Inertia::location($ssoData["url"]);
        }

        // L'utente va verso l'home dell'IdP
        if ($user->isAdmin()) {
            $request->session()->regenerate();

            $idpProviderId = config("idp.provider_id");
            $tokenService = new TokenProviderService();
            $sessionService = new SessionService();

            $ip_address = $request->ip();
            $token = $sessionService->getValidProviderToken(
                $user,
                $idpProviderId,
                $ip_address,
                $request->userAgent(),
                $tokenService,
            );

            if (!$token) {
                // L'utente non ha ruoli validi per quel provider
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return redirect()->route("sso.unauthorized");
            }

            Cookie::queue($tokenService->cookieCretion($token, $idpProviderId));
            return redirect()->route("admin-home");
        }

        // Se utente non admin, lo mandiamo alla pagina non autorizzato
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
        // Se App2 ci passa un URL di ritorno, lo usiamo, altrimenti andiamo al login
        $redirectTo = $request->query("redirect_to", route("loginForm"));

        return $this->performLogout($request, $redirectTo);
    }

    private function performLogout(Request $request, $redirectUrl)
    {
        $idpProviderId = config("idp.provider_id");
        $dynamicCookieName = "idp_token_" . $idpProviderId;
        $provider = Provider::find(config("idp.provider_id"));
        $cookieDomain = $provider->domain; // es. .miosito.it (o null per localhost)

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

            foreach ($sessions as $session) {
                $session->delete();
            }
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $cookiesToForget = [
            Cookie::forget($dynamicCookieName, "/", $cookieDomain),
            Cookie::forget("token", "/", $cookieDomain),
            Cookie::forget("laravel_session", "/", $cookieDomain),
            // Fallback per localhost
            Cookie::forget($dynamicCookieName),
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
