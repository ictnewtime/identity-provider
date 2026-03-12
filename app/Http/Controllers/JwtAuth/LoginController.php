<?php

namespace App\Http\Controllers\JwtAuth;

use App\Events\LoginEvent;
use Illuminate\Http\Request;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Session;
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

    #[
        OA\Post(
            path: "/v2/login",
            summary: "generate a JWT token",
            description: "Use to generate access JWT token for user auth",
            operationId: "v2/login",
            tags: ["JWT Auth"],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\MediaType(
                    mediaType: "application/x-www-form-urlencoded",
                    schema: new OA\Schema(
                        type: "object",
                        properties: [
                            new OA\Property(property: "username", description: "Username", type: "string"),
                            new OA\Property(
                                property: "password",
                                description: "User password",
                                type: "string",
                                format: "password",
                            ),
                        ],
                    ),
                ),
            ),
            responses: [
                new OA\Response(
                    response: 200,
                    description: "Operation successful",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
                new OA\Response(
                    response: 404,
                    description: "Authentication error",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
            ],
        ),
    ]
    public function login(LoginRequest $request)
    {
        $credentials = $request->only("username", "password");

        // 1. Check Credenziali
        if (!Auth::attempt(["username" => $credentials["username"], "password" => $credentials["password"]])) {
            return back()->withErrors(["login" => __("auth.err-login")]);
        }

        $user = Auth::user();
        event(new LoginEvent($user, $request->ip()));

        $provider_id = $request->input("provider_id");

        // 2A. BIVIO SSO: L'utente va verso un'app esterna (es. Telefoni)
        if ($provider_id) {
            $ssoData = \App\Services\TokenProviderService::respondWithSsoRedirect(
                $user,
                $provider_id,
                $request,
                $request->input("redirect_to"),
            );

            if (!$ssoData) {
                Auth::logout();
                return back()->withErrors(["login" => __("auth.err-login")]);
            }

            Cookie::queue($ssoData["cookie"]);
            return Inertia::location($ssoData["url"]);
        }

        // 2B. BIVIO LOCALE: L'utente accede all'IdP (Pannello Admin)
        if ($user->isAdmin()) {
            $request->session()->regenerate();

            $idpProviderId = config("idp.provider_id");
            $tokenService = new \App\Services\TokenProviderService();
            $sessionService = new \App\Services\SessionService();

            $token = $sessionService->getValidProviderToken(
                $user,
                $idpProviderId,
                $request->ip(),
                $request->userAgent(),
                $tokenService,
            );

            if (!$token) {
                Auth::logout();
                return back()->withErrors(["login" => __("auth.err-login")]);
            }

            Cookie::queue($tokenService->cookieCretion($token, $idpProviderId));
            return Inertia::location(route("admin-home"));
        }

        // 3. Se arriva qui, non ha provider_id e NON è admin: Accesso negato
        Auth::logout();
        return back()->withErrors(["login" => __("auth.err-login")]);
    }

    #[
        OA\Get(
            path: "/v1/user",
            summary: "retrieve user info in json format",
            description: "Use to retrieve user info with roles",
            operationId: "userByToken",
            tags: ["JWT Auth"],
            responses: [
                new OA\Response(
                    response: 200,
                    description: "Operation successful",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
            ],
        ),
    ]
    public function userByToken()
    {
        $userResource = UserResource::make(Auth::user());

        return response()->json($userResource);
    }

    #[
        OA\Get(
            path: "/v1/logout",
            summary: "Logout the user and delete his session",
            description: "Logout the user and delete his session",
            operationId: "logout",
            tags: ["JWT Auth"],
            responses: [
                new OA\Response(
                    response: 200,
                    description: "Operation successful",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
            ],
        ),
    ]
    public function logout(Request $request)
    {
        // 1. RECUPERO E DISTRUZIONE TOKEN (DB + JWT)
        $idpProviderId = config("idp.provider_id");
        $dynamicCookieName = "idp_token_" . $idpProviderId;

        // Cerchiamo il token
        $token = $request->cookie($dynamicCookieName) ?? ($request->bearerToken() ?? $request->cookie("token"));

        if ($token) {
            // Eliminiamo FISICAMENTE la sessione dal database
            Session::where("token", $token)->delete();

            // Invalidiamo il token nella blacklist di Tymon (ignorando gli errori di firma custom)
            try {
                JWTAuth::setToken($token)->invalidate();
            } catch (\Exception $e) {
                Log::debug("Tymon Invalidate ignorato: " . $e->getMessage());
            }
        }

        // 2. Esegui il logout fisico di Laravel (Sessione web nativa)
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // 3. DISTRUZIONE COOKIE: L'ATTACCO A TENAGLIA
        $cookieDomain = env("PROVIDER_DOMAIN");

        // A. Dimentichiamo i cookie CON il dominio specifico (es. .newtimegroup.it)
        Cookie::queue(Cookie::forget("token", "/", $cookieDomain));
        Cookie::queue(Cookie::forget($dynamicCookieName, "/", $cookieDomain));

        // B. Dimentichiamo i cookie SENZA il dominio (per localhost o salvataggi diretti su sottodomini esatti)
        Cookie::queue(Cookie::forget("token"));
        Cookie::queue(Cookie::forget($dynamicCookieName));

        // 4. Risposta per chiamate AJAX/JSON
        if (($request->ajax() || $request->wantsJson()) && !$request->header("X-Inertia")) {
            return response()->json(["message" => "Logged out successfully"], 200);
        }

        // 5. Gestione parametri per il redirect
        $allParams = $request->query();
        if ($request->has("redirect")) {
            $allParams["redirect"] = $request->input("redirect");
        }

        return redirect()->route("loginForm", $allParams);
    }

    public function logout_sso(Request $request)
    {
        // 1. Recuperiamo i parametri
        $provider_id = $request->query("provider_id");
        $redirect_to = $request->query("redirect_to", url("/"));

        // 2. Operazioni SULL'UTENTE
        if (Auth::check()) {
            $user = Auth::user();

            if ($provider_id) {
                Session::where("provider_id", $provider_id)->where("user_id", $user->id)->delete();
            } else {
                // Se per qualche motivo non c'è il provider, per sicurezza pialliamo tutte le sue sessioni (Global Logout)
                Session::where("user_id", $user->id)->delete();
            }
        } else {
            Log::warning(
                "Attenzione: Auth::check() è false. Nessun utente loggato su Laravel, salto l'eliminazione a DB.",
            );
        }

        // 3. Eseguiamo il logout nativo di Laravel
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // 4. Prepariamo la risposta di redirect
        $response = redirect($redirect_to);

        // 5. DISTRUZIONE COOKIE
        $cookieDomain = env("TOKEN_COOKIE_DOMAIN");

        // Distruggiamo il cookie generico "token"
        $response->withCookie(Cookie::forget("token", "/", $cookieDomain));

        // Ho de-commentato questa parte: è FONDAMENTALE distruggere il cookie specifico, altrimenti il frontend client si incastra!
        if ($provider_id) {
            $cookie_name = "idp_token_" . $provider_id;
            $response->withCookie(Cookie::forget($cookie_name, "/", $cookieDomain));
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
