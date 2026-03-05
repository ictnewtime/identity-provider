<?php

namespace App\Http\Controllers\JwtAuth;

use App\Events\LoginEvent;
use App\Events\LogoutEvent;
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
use Illuminate\Support\Facades\Cookie as FacadeCookie;
use OpenApi\Attributes as OA;

class LoginController extends Controller
{
    /**
     * Shows the login form or redirect the user to the application if he is authenticated.
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function showLoginForm()
    {
        return view("auth.login");
    }

    /**
     * Shows page for authenticated user.
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function authenticated()
    {
        $user = Auth::user();
        // TODO: verificare che il ruolo dia all' interno della applicazione,
        // tramite controllo del provider
        $is_role_admin = $user->hasRole(config("role.admin"));
        if ($is_role_admin) {
            return redirect()->route("web-users");
        }
        return view("auth.logged");
    }

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
            return $this->createResponse(403, __("auth.err-verification"));
        }

        $user = Auth::user();
        $ip_address = $request->ip();

        // L'evento di login va scatenato SEMPRE, a prescindere dal provider
        event(new LoginEvent($user, $ip_address));

        $provider_id = $request->input("provider_id");
        $redirect_to = $request->input("redirect_to");

        if ($provider_id) {
            $tokenService = new TokenProviderService();
            $sessionService = new SessionService();

            try {
                $token = $sessionService->getValidProviderToken($user, $provider_id, $ip_address, $tokenService);
                Log::debug("Token: " . $token);

                if (!$token) {
                    return $this->createResponse(403, "Utente non abilitato per il servizio richiesto.");
                }

                $provider = Provider::where("id", $provider_id)->first();
                if (!$provider) {
                    return $this->createResponse(404, "Provider non valido.");
                }

                // URL base del provider
                $redirect_url = $provider->protocol . $provider->domain;
                Log::debug("Provider base URL: " . $redirect_url);

                // SICUREZZA: Se c'è un redirect_to, verifichiamo che appartenga al dominio autorizzato!
                if ($redirect_to) {
                    $parsedHost = parse_url($redirect_to, PHP_URL_HOST);
                    // Accettiamo il redirect solo se è sullo stesso dominio del provider (o è localhost per i test)
                    if ($parsedHost === $provider->domain || in_array($parsedHost, ["localhost", "127.0.0.1"])) {
                        $redirect_url = $redirect_to;
                    } else {
                        Log::warning("Tentativo di Open Redirect bloccato verso: " . $redirect_to);
                    }
                }

                // Logica SSO: accodiamo SEMPRE il token all'URL per passarlo all'App Client
                $redirect_url = $tokenService->appendTokenToUrl($redirect_url, $token);

                Log::debug("Redirect finale con token: " . $redirect_url);
                Log::debug("Cookie creation IdP");

                $cookie = $tokenService->cookieCretion($token, $provider_id);

                return response()
                    ->json(["redirect_url" => $redirect_url])
                    ->withCookie($cookie);
            } catch (\Exception $e) {
                Log::error($e->getMessage());
                return $this->createResponse(500, __("auth.err-jwt"));
            }
        }

        $userResource = UserResource::make($user);
        return response()->json(["user" => $userResource]);
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
        // event(new LogoutEvent($user));
        // auth()->logout(true);
        Auth::logout();

        $cookie = FacadeCookie::forget("token", "/", env("TOKEN_COOKIE_DOMAIN"));

        if ($request->ajax() || $request->wantsJson()) {
            return $this->createResponse(200, null, $cookie);
        }

        if ($request->input("redirect")) {
            return redirect(
                route("loginForm", [
                    "redirect" => $request->input("redirect"),
                ]),
            )->withCookie($cookie);
        }

        return redirect("loginForm")->withCookie($cookie);
    }

    public function logout_sso(Request $request)
    {
        Log::info("--- INIZIO LOGOUT SSO (Lato IdP) ---");

        // 1. Recuperiamo i parametri
        $provider_id = $request->query("provider_id");
        $redirect_to = $request->query("redirect_to", url("/"));

        Log::info("Richiesta di logout. Provider ID: " . ($provider_id ?? "Nullo") . " | Redirect: " . $redirect_to);

        // 2. Operazioni SULL'UTENTE (Prima di sloggarlo!)
        if (Auth::check()) {
            $user = Auth::user();
            Log::info("Utente riconosciuto: ID " . $user->id);

            // Lanciamo l'evento
            // event(new LogoutEvent($user));

            // CANCELLAZIONE DAL DATABASE (Spostata qui dentro!)
            if ($provider_id) {
                $deletedCount = \App\Models\Session::where("provider_id", $provider_id)
                    ->where("user_id", $user->id)
                    ->delete();
                Log::info(
                    "Eliminate $deletedCount sessioni dal DB per l'utente " . $user->id . " sul provider $provider_id",
                );
            } else {
                // Se per qualche motivo non c'è il provider, per sicurezza pialliamo tutte le sue sessioni (Global Logout)
                $deletedCount = \App\Models\Session::where("user_id", $user->id)->delete();
                Log::info(
                    "Nessun provider specificato. Eliminate TUTTE le ($deletedCount) sessioni dal DB per l'utente " .
                        $user->id,
                );
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
        Log::info("Logout di sistema eseguito (sessione e token rigenerati).");

        // 4. Prepariamo la risposta di redirect
        $response = redirect($redirect_to);

        // 5. DISTRUZIONE COOKIE
        $cookieDomain = env("TOKEN_COOKIE_DOMAIN");
        Log::info("Eliminazione cookie. Dominio usato: " . ($cookieDomain ?? "Nessuno (default)"));

        // Distruggiamo il cookie generico "token"
        $response->withCookie(FacadeCookie::forget("token", "/", $cookieDomain));

        // Ho de-commentato questa parte: è FONDAMENTALE distruggere il cookie specifico, altrimenti il frontend client si incastra!
        if ($provider_id) {
            $cookie_name = "idp_token_" . $provider_id;
            Log::info("Accodata distruzione del cookie client: " . $cookie_name);
            $response->withCookie(FacadeCookie::forget($cookie_name, "/", $cookieDomain));
        }

        Log::info("--- FINE LOGOUT SSO ---");
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
