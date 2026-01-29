<?php

namespace App\Http\Controllers\JwtAuth;

use App\Events\LoginEvent;
use App\Events\LogoutEvent;
use Illuminate\Http\Request;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\TokenGeneratorService;
use Tymon\JWTAuth\Exceptions\JWTException;
use Symfony\Component\HttpFoundation\Cookie;
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
        if ($user->hasRoleId(config("role.admin_idp"))) {
            return redirect()->route("admin-board");
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
        // $token = $this->retrieveToken($credentials);
        $credentials["email"] = $credentials["username"];
        unset($credentials["username"]);
        auth()->attempt($credentials);

        $user = Auth::user();
        // dd($user);
        if (!$user->is_verified) {
            return $this->createResponse(403, __("auth.err-verification"));
        }

        $tokenService = new TokenGeneratorService();
        $redirectUrl = $request->input("redirect");
        try {
            $token = $tokenService->generate($user, $redirectUrl);

            if (!$token) {
                // Caso: Credenziali OK, ma utente non autorizzato per quel Provider specifico
                return $this->createResponse(403, "Utente non abilitato per il servizio richiesto.");
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->createResponse(500, __("auth.err-jwt"));
        }
        $userResource = UserResource::make($user);
        event(new LoginEvent($user, $request->ip()));

        return response()
            ->json([
                "user" => $userResource,
                "token" => $token,
            ])
            ->withCookie(new Cookie("token", $token, 0, "/", env("TOKEN_COOKIE_DOMAIN")));
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
        event(new LogoutEvent($user));
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

    /**
     * test swagger with dummy login
     */
    #[
        OA\Post(
            path: "/v1/test-login",
            summary: "generate a JWT token",
            description: "Use to generate access JWT token for user auth",
            operationId: "test_login",
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
    public function test_login(Request $request)
    {
        // return $request->all();
        // return 404
        return response()->json([
            "message" => "Authentication error",
            "status" => 404,
        ]);
    }

    #[
        OA\Get(
            path: "/v1/test-idp",
            summary: "test idp",
            description: "test ipd",
            operationId: "test_ipd",
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
    public function test_idp(Request $request)
    {
        return $request->all();
    }
}
