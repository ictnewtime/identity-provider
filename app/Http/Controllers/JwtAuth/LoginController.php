<?php

namespace App\Http\Controllers\JwtAuth;

use App\Events\LoginEvent;
use Illuminate\Http\Request;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Provider;
use App\Models\Session;
use App\Services\SessionService;
use App\Services\TokenProviderService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;
use Tymon\JWTAuth\Facades\JWTAuth;

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

        $user = Auth::user();
        event(new LoginEvent($user, $request->ip()));

        $provider_id = $request->input("provider_id");

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

            Cookie::queue($ssoData["cookie"]);
            return Inertia::location($ssoData["url"]);
        }

        // L'utente va verso l'home dell'IdP
        if ($user->isAdmin()) {
            $request->session()->regenerate();

            $idpProviderId = config("idp.provider_id");
            $tokenService = new TokenProviderService();
            $sessionService = new SessionService();

            $ip_address = $request->ip();
            // Verifichia l' ambiente è local (dal file .env)
            // e verifichia se l'IP è un indirizzo privato (172.x, 192.x, 10.x)
            if (app()->environment("local")) {
                $isPrivate = !filter_var(
                    $ip_address,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
                );
                if ($isPrivate) {
                    // Se l'IP è privato o locale, lo normalizziamo a 127.0.0.1
                    // per mantenere coerenza nei log di sviluppo
                    $ip_address = "127.0.0.1";
                }
            }
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

        // Utente normale, lo mandiamo alla home dell'IdP
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route("sso.unauthorized");
    }

    public function userByToken()
    {
        $userResource = UserResource::make(Auth::user());

        return response()->json($userResource);
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
