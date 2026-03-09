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
                    "login" => "Utente non abilitato per il servizio richiesto.",
                ]);
            }

            // Accodiamo il cookie in modo che venga inviato con la risposta HTTP
            Cookie::queue($ssoData["cookie"]);

            // Inertia::location è FONDAMENTALE qui: dice ad Inertia di forzare
            // il browser a fare un redirect "reale" verso un dominio/app esterna.
            return Inertia::location($ssoData["url"]);
        }

        // 3. FLUSSO DIRETTO (Login locale all'IdP, es. per il pannello Admin)
        // Invece di restituire l'utente in JSON, facciamo un redirect HTTP standard
        // verso la dashboard interna di Laravel. Inertia capirà e caricherà la nuova pagina Vue.
        return redirect()->intended("/admin/users");
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

        // 1. Esegui il logout fisico
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // 2. Prepariamo la distruzione del cookie (con il dominio corretto)
        $cookie = FacadeCookie::forget("token", "/", env("TOKEN_COOKIE_DOMAIN"));

        // 3. Risposta per chiamate AJAX/JSON (escludiamo Inertia che necessita del redirect)
        if (($request->ajax() || $request->wantsJson()) && !$request->header("X-Inertia")) {
            return $this->createResponse(200, null, $cookie);
        }

        /**
         * 4. Gestione Redirect con mantenimento Query Params
         * Recuperiamo tutti i parametri attuali (provider_id, redirect_to, ecc.)
         * per passarli alla rotta loginForm.
         */
        $allParams = $request->query(); // Prende tutto ciò che c'è dopo il '?'

        // Se preferisci usare un parametro specifico 'redirect' come nel tuo vecchio codice:
        if ($request->has("redirect")) {
            $allParams["redirect"] = $request->input("redirect");
        }

        // Reindirizziamo alla rotta 'loginForm' passando l'intero array di parametri
        return redirect()->route("loginForm", $allParams)->withCookie($cookie);
    }

    public function logout_sso(Request $request)
    {
        // 1. Recuperiamo i parametri
        $provider_id = $request->query("provider_id");
        $redirect_to = $request->query("redirect_to", url("/"));

        Log::info("Richiesta di logout. Provider ID: " . ($provider_id ?? "Nullo") . " | Redirect: " . $redirect_to);

        // 2. Operazioni SULL'UTENTE (Prima di sloggarlo!)
        if (Auth::check()) {
            $user = Auth::user();

            if ($provider_id) {
                $deletedCount = Session::where("provider_id", $provider_id)->where("user_id", $user->id)->delete();
            } else {
                // Se per qualche motivo non c'è il provider, per sicurezza pialliamo tutte le sue sessioni (Global Logout)
                $deletedCount = Session::where("user_id", $user->id)->delete();
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
