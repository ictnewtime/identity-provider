<?php

namespace App\Http\Controllers\Manage;

use Illuminate\Http\Request;
// use App\Http\Services\Mailer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
// use App\Repositories\RepositoryInterface;
// use App\Repositories\UserRepositoryInterface;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
    // protected $userRepository;
    protected $verificationTokenRepository;
    // protected $mailerService;

    public function __construct()
    {
        // Mailer $mailerService,
        // $this->userRepository = $userRepository;
        // $this->verificationTokenRepository = $verificationToken;
        // $this->mailerService = $mailerService;
    }
    // UserRepositoryInterface $userRepository,
    // RepositoryInterface $verificationToken,

    #[
        OA\Get(
            path: "/api/v1/users",
            summary: "Get all users",
            description: "Get all users with pagination. __*Security: Richiede token M2M Passport*__",
            operationId: "User.all",
            tags: ["Users"],
            security: [["passport" => []]],
            parameters: [
                new OA\Parameter(
                    name: "q",
                    in: "query",
                    required: false,
                    description: "Termine di ricerca (nome o email)",
                    schema: new OA\Schema(type: "string"),
                ),
                new OA\Parameter(
                    name: "sortField",
                    in: "query",
                    required: false,
                    description: "Campo per ordinamento",
                    schema: new OA\Schema(type: "string"),
                ),
                new OA\Parameter(
                    name: "sortOrder",
                    in: "query",
                    required: false,
                    description: "Direzione (1 asc, -1 desc)",
                    schema: new OA\Schema(type: "integer"),
                ),
                new OA\Parameter(
                    name: "per_page",
                    in: "query",
                    required: false,
                    description: "Elementi per pagina",
                    schema: new OA\Schema(type: "integer", default: 10),
                ),
            ],
            responses: [
                new OA\Response(
                    response: 200,
                    description: "Operation successful",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
                new OA\Response(
                    response: 401,
                    description: "Unauthorized",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
            ],
        ),
    ]
    public function all(Request $request)
    {
        $query = User::select("id", "username", "name", "surname", "email", "enabled");

        if ($request->filled("q")) {
            $query->where("email", "like", "%" . $request->q . "%")->orWhere("name", "like", "%" . $request->q . "%");
        }

        if ($request->filled("sortField")) {
            $field = $request->sortField;
            $direction = $request->sortOrder == 1 ? "asc" : "desc";
            $query->orderBy($field, $direction);
        } else {
            $query->orderBy("created_at", "asc");
        }

        $perPage = $request->input("per_page", 10);
        $users = $query->paginate($perPage);

        return response()->json($users);
    }

    #[
        OA\Post(
            path: "/api/v1/users",
            summary: "create a new user",
            description: '__*Security:*__ __*can be used only by clients with \'manager\' role*__',
            operationId: "User.create",
            tags: ["Users"],
            security: [["passport" => []]],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\MediaType(
                    mediaType: "application/x-www-form-urlencoded",
                    schema: new OA\Schema(
                        type: "object",
                        properties: [
                            new OA\Property(
                                property: "username",
                                description: "User username",
                                type: "string",
                                example: "mario.rossi",
                            ),
                            new OA\Property(
                                property: "password",
                                description: "User password",
                                type: "string",
                                format: "password",
                            ),
                            new OA\Property(
                                property: "password_confirmation",
                                description: "User password confirmation",
                                type: "string",
                                format: "password",
                            ),
                            new OA\Property(
                                property: "email",
                                description: "User e-mail. It is not mandatory",
                                type: "string",
                                example: "mario.rossi@email.com",
                            ),
                            new OA\Property(
                                property: "name",
                                description: "User name",
                                type: "string",
                                example: "mario",
                            ),
                            new OA\Property(
                                property: "surname",
                                description: "User surname",
                                type: "string",
                                example: "rossi",
                            ),
                            new OA\Property(
                                property: "enabled",
                                description: "User enabled",
                                type: "boolean",
                                example: true,
                            ),
                            new OA\Property(
                                property: "password_expires_at",
                                description: "User password expires at, format: Y-m-d H:i:s. If null, means the user is at his first login, so the password must be changed.",
                                type: "string",
                                format: "date-time",
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
                    response: 422,
                    description: "Validation error",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
                new OA\Response(
                    response: 500,
                    description: "Server error",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
            ],
        ),
    ]
    public function create(UserRequest $request)
    {
        $credentials = $request->only([
            "username",
            "password",
            "email",
            "name",
            "surname",
            "enabled",
            "password_expires_at",
        ]);

        if (array_key_exists("password_expires_at", $credentials)) {
            $credentials["password_expires_at"] = $credentials["password_expires_at"]
                ? Carbon::parse($credentials["password_expires_at"])
                    ->setTimezone(config("app.timezone"))
                    ->format("Y-m-d H:i:s")
                : null;
        }

        try {
            $credentials["password"] = Hash::make($credentials["password"]);

            $credentials["enabled"] = $request->input("enabled", true);
            $credentials["is_verified"] = true;

            $user = User::create($credentials);

            return response()->json($user, 200);
        } catch (\Exception $e) {
            Log::error("Errore creazione utente: " . $e->getMessage());
            return response()->json(["message" => $e->getMessage()], 500);
        }
    }

    #[
        OA\Get(
            path: "/api/v1/users/{id}",
            summary: "Returns user by id",
            description: "Returns user details by id",
            operationId: "User.find",
            tags: ["Users"],
            security: [["passport" => []]],
            parameters: [
                new OA\Parameter(
                    in: "path",
                    required: true,
                    description: "User id",
                    name: "id",
                    schema: new OA\Schema(type: "string"),
                ),
            ],
            responses: [
                new OA\Response(
                    response: 200,
                    description: "Operation successful",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
                new OA\Response(
                    response: 404,
                    description: "Not found",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
                new OA\Response(
                    response: 500,
                    description: "Server error",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
            ],
        ),
    ]
    public function find($id)
    {
        try {
            $user = User::find($id);
        } catch (\Exception $e) {
            return response()->json([
                "message" => "Error during finding user",
                "error" => [
                    "code" => 500,
                    "message" => $e->getMessage(),
                ],
            ]);
        }
        if (empty($user)) {
            return response()->json(["message" => "User not found"], 404);
        }
        return response()->json($user);
    }

    #[
        OA\Put(
            path: "/api/v1/users/{id}",
            summary: "Update user by id",
            description: '__*Security:*__ __*can be used only by clients with \'admin\' role*__',
            operationId: "User.update",
            tags: ["Users"],
            security: [["passport" => []]],
            parameters: [
                new OA\Parameter(
                    in: "path",
                    required: true,
                    description: "User id",
                    name: "id",
                    schema: new OA\Schema(type: "string"),
                ),
            ],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\MediaType(
                    mediaType: "application/x-www-form-urlencoded",
                    schema: new OA\Schema(
                        type: "object",
                        properties: [
                            new OA\Property(
                                property: "email",
                                description: "User email",
                                type: "string",
                                example: "mario.rossi@example.com",
                            ),
                            new OA\Property(
                                property: "username",
                                description: "User username",
                                type: "string",
                                example: "mario.rossi",
                            ),
                            new OA\Property(
                                property: "password",
                                description: "User password",
                                type: "string",
                                format: "password",
                            ),
                            new OA\Property(
                                property: "password_confirmation",
                                description: "User password confirmation",
                                type: "string",
                                format: "password",
                            ),
                            new OA\Property(
                                property: "name",
                                description: "User name",
                                type: "string",
                                example: "Mario",
                            ),
                            new OA\Property(
                                property: "surname",
                                description: "User surname",
                                type: "string",
                                example: "Rossi",
                            ),
                            new OA\Property(
                                property: "enabled",
                                description: "User enabled",
                                type: "boolean",
                                example: true,
                            ),
                            new OA\Property(
                                property: "password_expires_at",
                                description: "User password expires at, format: Y-m-d H:i:s.",
                                type: "string",
                                format: "date-time",
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
                    description: "Not found",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
                new OA\Response(
                    response: 500,
                    description: "Server error",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
            ],
        ),
    ]
    public function update(UserRequest $request, $id)
    {
        $credentials = $request->only("email", "username", "name", "surname", "enabled", "password_expires_at");

        if (array_key_exists("password_expires_at", $credentials)) {
            $credentials["password_expires_at"] = $credentials["password_expires_at"]
                ? Carbon::parse($credentials["password_expires_at"])
                    ->setTimezone(config("app.timezone"))
                    ->format("Y-m-d H:i:s")
                : null;
        }

        if ($request->filled("password")) {
            $credentials["password"] = Hash::make($request->password);
        }
        $user = User::find($id);

        if (empty($user)) {
            return response()->json([], 404);
        }

        try {
            $user->update($credentials);
        } catch (\Exception $e) {
            return response()->json(
                [
                    "message" => "Error during updating user",
                    "error" => [
                        "code" => 500,
                        "message" => $e->getMessage(),
                    ],
                ],
                500,
            );
        }

        return response()->json($user, 200);
    }

    #[
        OA\Delete(
            path: "/api/v1/users/{id}",
            summary: "Delete user by id",
            description: '__*Security:*__ __*can be used only by clients with \'admin\' role*__',
            operationId: "User.delete",
            tags: ["Users"],
            security: [["passport" => []]],
            parameters: [
                new OA\Parameter(
                    in: "path",
                    required: true,
                    description: "User id",
                    name: "id",
                    schema: new OA\Schema(type: "string"),
                ),
            ],
            responses: [
                new OA\Response(
                    response: 204,
                    description: "Operation successful",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
                new OA\Response(
                    response: 404,
                    description: "Not found",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
                new OA\Response(
                    response: 500,
                    description: "Server error",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
            ],
        ),
    ]
    public function delete($id)
    {
        $user = User::find($id);
        if (empty($user)) {
            return response()->json([], 404);
        }

        try {
            $user->delete();
        } catch (\Exception $e) {
            return response()->json([
                "message" => "Error during deleting user",
                "error" => [
                    "code" => 500,
                    "message" => $e->getMessage(),
                ],
            ]);
        }
        return response()->json([], 204);
    }
}
