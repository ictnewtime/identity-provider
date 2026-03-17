<?php

namespace App\Http\Controllers\JwtAuth;

use App\Events\LoginEvent;
use Illuminate\Http\Request;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Session;
use App\Services\SessionService;
use App\Services\TokenProviderService;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;
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
        // 2. Sostituisci view() con Inertia::render()
        return Inertia::render("Auth/Login", [
            // Qui in futuro potrai passare dati alla pagina Vue, ad esempio:
            // 'status' => session('status'),
        ]);
    }

    /**
     * Shows page for authenticated user.
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    // public function authenticated()
    // {
    //     $user = Auth::user();
    //     // TODO: verificare che il ruolo dia all' interno della applicazione,
    //     // tramite controllo del provider
    //     $is_role_admin = $user->hasRole(config("role.admin"));
    //     if ($is_role_admin) {
    //         return redirect()->route("web-users");
    //     }
    //     return redirect()->route("sso.unauthorized");
    // }

    public function login(LoginRequest $request)
    {
        $credentials = $request->only("username", "password");

        if (!Auth::attempt(["username" => $credentials["username"], "password" => $credentials["password"]])) {
            Log::warning("Check Credenziali. Login fallito per utente " . $credentials["username"]);
            return back()->withErrors(["login" => __("auth.err-login")]);
        }

        $user = Auth::user();
        Log::info("Login effettuato per utente " . $user->id);
        event(new LoginEvent($user, $request->ip()));

        $provider_id = $request->input("provider_id");

        // 2A. BIVIO SSO: L'utente va verso un'app esterna (es. App2)
        if ($provider_id) {
            $ssoData = TokenProviderService::respondWithSsoRedirect(
                $user,
                $provider_id,
                $request,
                $request->input("redirect_to"),
            );

            if (!$ssoData) {
                // UTENTE ESISTE MA NON È AUTORIZZATO PER QUESTA APP!
                // Lo slogghiamo per non lasciargli sessioni pendenti e lo mandiamo alla pagina di blocco
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return redirect()->route("sso.unauthorized");
            }

            Cookie::queue($ssoData["cookie"]);
            return Inertia::location($ssoData["url"]);
        }

        // 2B. BIVIO LOCALE: L'utente accede all'IdP (Pannello Admin)
        Log::info("2B. BIVIO LOCALE");
        if ($user->isAdmin()) {
            $request->session()->regenerate();

            $idpProviderId = config("idp.provider_id");
            $tokenService = new TokenProviderService();
            $sessionService = new SessionService();

            $token = $sessionService->getValidProviderToken(
                $user,
                $idpProviderId,
                $request->ip(),
                $request->userAgent(),
                $tokenService,
            );

            if (!$token) {
                // Ha il ruolo Admin, ma per qualche motivo la generazione del token IdP è fallita
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return redirect()->route("sso.unauthorized");
            }

            Log::info("2B. BIVIO LOCALE: Token creato");
            Cookie::queue($tokenService->cookieCretion($token, $idpProviderId));
            return redirect()->route("admin-home");
        }

        // 2C. UTENTE SENZA RUOLI O NON ADMIN CHE CERCA DI ACCEDERE DIRETTAMENTE ALL'IDP
        // Distruggiamo tutto. Non merita nessun Grant Token.
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

    /**
     * LOGICA CENTRALIZZATA: Pialla Database, Sessione Laravel e Cookie
     */
    private function performLogout(Request $request, $redirectUrl)
    {
        $idpProviderId = config("idp.provider_id");
        $dynamicCookieName = "idp_token_" . $idpProviderId;
        $cookieDomain = env("PROVIDER_DOMAIN"); // es. .miosito.it (o null per localhost)

        // 1. RECUPERO USER ID (Da sessione o decodificando il cookie)
        $userId = Auth::id();

        if (!$userId && $request->cookie($dynamicCookieName)) {
            $tokenString = $request->cookie($dynamicCookieName);
            $parts = explode(".", $tokenString);
            if (count($parts) === 3) {
                $payload = json_decode(base64_decode(strtr($parts[1], "-_", "+/")), true);
                $userId = $payload["sub"] ?? null;
            }
        }

        // 2. PULIZIA DATABASE (Single Logout Assoluto)
        if ($userId) {
            // Sostituisci il Bulk Delete con il fetch + delete sui singoli modelli
            $sessions = Session::where("user_id", $userId)->get();

            foreach ($sessions as $session) {
                $session->delete();
            }
        }

        // 3. PULIZIA SESSIONE WEB LARAVEL
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // 4. PREPARAZIONE DISTRUZIONE COOKIE (Con e senza dominio per sicurezza)
        $cookiesToForget = [
            Cookie::forget($dynamicCookieName, "/", $cookieDomain),
            Cookie::forget("token", "/", $cookieDomain),
            Cookie::forget("laravel_session", "/", $cookieDomain),
            // Fallback per localhost
            Cookie::forget($dynamicCookieName),
            Cookie::forget("token"),
        ];

        // 5. RISPOSTA AL CLIENT
        if (($request->ajax() || $request->wantsJson()) && !$request->header("X-Inertia")) {
            $response = response()->json(["message" => "Logged out successfully"], 200);
        } else {
            $response = redirect()->away($redirectUrl);

            // Se andiamo al login, aggiungiamo il messaggio di successo
            if (str_contains($redirectUrl, route("loginForm"))) {
                $response->withErrors(["login" => "Disconnessione completata con successo."]);
            }
        }

        // Attacchiamo tutti i cookie "tossici" da distruggere alla risposta
        foreach ($cookiesToForget as $cookie) {
            $response->withCookie($cookie);
        }

        return $response;
    }

    private function createResponse(int $status = 200, string $message = null, $cookie = null)
    {
        if (empty($message)) {
            $response = response()->json([], $status);
        }

        $response = response()->json(
            [
                "message" => $message,
            ],
            $status,
        );

        if ($cookie) {
            return $response->withCookie($cookie);
        }
        return $response;
    }
}
