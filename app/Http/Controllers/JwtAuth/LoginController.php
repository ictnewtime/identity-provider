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

        if (!Auth::attempt(["username" => $credentials["username"], "password" => $credentials["password"]])) {
            return back()->withErrors([
                "login" => __("auth.err-login"),
            ]);
        }

        $user = Auth::user();
        $ip_address = $request->ip();

        // L'evento di login va scatenato SEMPRE, a prescindere dal provider
        event(new LoginEvent($user, $ip_address));
        $provider_id = $request->input("provider_id");

        if ($provider_id) {
            $ssoData = TokenProviderService::respondWithSsoRedirect(
                $user,
                $provider_id,
                $request,
                $request->input("redirect_to"),
            );

            if (!$ssoData) {
                Auth::logout();
                return back()->withErrors([
                    "login" => __("auth.err-login"),
                ]);
            }

            // Accodiamo il cookie in modo che venga inviato con la risposta HTTP
            Cookie::queue($ssoData["cookie"]);

            // Inertia::location è FONDAMENTALE qui: dice ad Inertia di forzare
            // il browser a fare un redirect "reale" verso un dominio/app esterna.
            return Inertia::location($ssoData["url"]);
        } else {
            // L'utente sta tentando di accedere direttamente al pannello IdP (nessun provider_id esterno)

            if ($user->isAdmin()) {
                // È un admin: rigeneriamo la sessione web base
                $request->session()->regenerate();

                $idpProviderId = config("idp.provider_id");

                // Istanziamo i tuoi service
                $tokenService = new \App\Services\TokenProviderService();
                $sessionService = new \App\Services\SessionService();

                // Facciamo fare tutto al SessionService!
                // Genererà il token custom firmato e salverà la riga nella tabella `sessions`
                $token = $sessionService->getValidProviderToken(
                    $user,
                    $idpProviderId,
                    $request->ip(),
                    $request->userAgent(),
                    $tokenService,
                );

                if (!$token) {
                    Auth::logout();
                    return back()->withErrors([
                        "login" => __("auth.err-login"),
                    ]);
                }

                // Usiamo il tuo metodo cookieCretion per creare il cookie formattato bene
                $cookie = $tokenService->cookieCretion($token, $idpProviderId);

                // Accodiamo il cookie alla risposta
                Cookie::queue($cookie);

                // Usiamo Inertia::location per l'hard redirect della SPA
                return Inertia::location(route("admin-home"));
            }

            // Non è un admin (o è disabilitato): niente accesso al pannello IdP
            Auth::logout();
            return back()->withErrors([
                "login" => __("auth.err-login"),
            ]);
        }

        // 3. FLUSSO DIRETTO (Login locale all'IdP, es. per il pannello Admin)
        // Invece di restituire l'utente in JSON, facciamo un redirect HTTP standard
        // verso la dashboard interna di Laravel. Inertia capirà e caricherà la nuova pagina Vue.
        $request->session()->regenerate();
        return Inertia::location(route("admin-home"));
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
        $user = Auth::user();

        // --- 1. NUOVA LOGICA: RECUPERO E DISTRUZIONE TOKEN (DB + JWT) ---
        $idpProviderId = config("idp.provider_id");
        $dynamicCookieName = "idp_token_" . $idpProviderId;

        // Cerchiamo il token nel cookie dinamico, nel Bearer o nel vecchio cookie "token"
        $token = $request->cookie($dynamicCookieName) ?? ($request->bearerToken() ?? $request->cookie("token"));

        if ($token) {
            // Eliminiamo FISICAMENTE la sessione dal database.
            // Questo fa scattare il blocco immediato nel middleware Authenticated.
            \App\Models\Session::where("token", $token)->delete();

            // Opzionale: Invalidiamo il token nella blacklist di Tymon
            try {
                \Tymon\JWTAuth\Facades\JWTAuth::setToken($token)->invalidate();
            } catch (\Exception $e) {
                Log::error("Impossibile inserire in blacklist Tymon: " . $e->getMessage());
            }
        }
        // 2. Esegui il logout fisico di Laravel (Sessione web nativa)
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // 3. Prepariamo la distruzione dei cookie
        // Dimentichiamo sia il vecchio cookie generico sia quello dinamico creato dal Service
        $cookieDomain = env("PROVIDER_DOMAIN") ?? env("TOKEN_COOKIE_DOMAIN");
        $legacyCookie = Cookie::forget("token", "/", $cookieDomain);
        $dynamicCookie = Cookie::forget($dynamicCookieName, "/", $cookieDomain);

        // Li accodiamo per sicurezza, in modo che Laravel li distrugga a prescindere dal tipo di response
        Cookie::queue($legacyCookie);
        Cookie::queue($dynamicCookie);

        // 4. Risposta per chiamate AJAX/JSON (escludiamo Inertia che necessita del redirect)
        if (($request->ajax() || $request->wantsJson()) && !$request->header("X-Inertia")) {
            // Usa il cookie dinamico per la response AJAX
            return $this->createResponse(200, null, $dynamicCookie);
        }

        $allParams = $request->query();

        if ($request->has("redirect")) {
            $allParams["redirect"] = $request->input("redirect");
        }

        // Reindirizziamo alla rotta 'loginForm' passando l'intero array di parametri
        // e allegando i comandi di distruzione dei cookie
        return redirect()->route("loginForm", $allParams)->withCookie($legacyCookie)->withCookie($dynamicCookie);
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
                $deletedCount = Session::where("provider_id", $provider_id)->where("user_id", $user->id)->delete();
            } else {
                // Se per qualche motivo non c'è il provider, per sicurezza pialliamo tutte le sue sessioni (Global Logout)
                $deletedCount = Session::where("user_id", $user->id)->delete();
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
