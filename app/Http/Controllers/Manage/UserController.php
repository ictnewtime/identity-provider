<?php

namespace App\Http\Controllers\Manage;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Services\Mailer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Repositories\RepositoryInterface;
use App\Repositories\UserRepositoryInterface;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
    protected $userRepository;
    protected $verificationTokenRepository;
    protected $mailerService;

    public function __construct(
        UserRepositoryInterface $userRepository,
        RepositoryInterface $verificationToken,
        Mailer $mailerService,
    ) {
        $this->userRepository = $userRepository;
        $this->verificationTokenRepository = $verificationToken;
        $this->mailerService = $mailerService;
    }

    #[
        OA\Get(
            path: "/api/v1/users",
            summary: "get all users",
            description: '__*Security:*__ __*can be used only by clients with \'manager\' role*__',
            operationId: "User.all",
            tags: ["User management"],
            security: [["passport" => []]],
            responses: [
                new OA\Response(
                    response: 200,
                    description: "Returns all users",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
            ],
        ),
    ]
    public function all(Request $request)
    {
        $query = User::query();

        // 1. Ricerca (Filter)
        if ($request->filled("q")) {
            $query->where("email", "like", "%" . $request->q . "%")->orWhere("name", "like", "%" . $request->q . "%");
        }

        // 2. Ordinamento (OrderBy)
        // PrimeVue invia sortField (stringa) e sortOrder (1 per ASC, -1 per DESC)
        if ($request->filled("sortField")) {
            $field = $request->sortField;
            $direction = $request->sortOrder == 1 ? "asc" : "desc";
            $query->orderBy($field, $direction);
        } else {
            $query->orderBy("created_at", "desc");
        }

        // 3. Paginazione
        $perPage = $request->get("per_page", 10);
        $users = $query->paginate($perPage);

        return response()->json($users);
    }

    #[
        OA\Post(
            path: "/api/v1/users",
            summary: "create a new user",
            description: '__*Security:*__ __*can be used only by clients with \'manager\' role*__',
            operationId: "User.create",
            tags: ["User management"],
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
        $credentials = $request->only("username", "password", "password_confirmation", "email", "name", "surname");

        DB::beginTransaction();

        try {
            // unset password_confirmation
            unset($credentials["password_confirmation"]);
            $user = $this->userRepository->create($credentials);
            // TODO: in un secondo momento gestisco la verifica degli utenti
            // $verificationToken = $this->verificationTokenRepository->create([
            //     "token" => Str::random(60),
            //     "user_id" => $user->id,
            // ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::info($e);
            return response()->json(["message" => "Error during saving user"], 500);
        }

        DB::commit();

        // $body = view("mail.complete-registration", ["token" => $verificationToken->token, "user" => $user])->render();
        // $this->mailerService->dispatchEmail($body, [$user->email], "Completa la registrazione");

        return response()->json(["user" => $user], 200);
    }

    #[
        OA\Get(
            path: "/api/v1/users/{id}",
            summary: "Returns user by id",
            description: "Returns user details by id",
            operationId: "find",
            tags: ["User management"],
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
            $user = $this->userRepository->find($id);
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

    // user update
    #[
        OA\Put(
            path: "/api/v1/users/{id}",
            summary: "Update user by id",
            description: '__*Security:*__ __*can be used only by clients with \'admin\' role*__',
            operationId: "User.update",
            tags: ["User management"],
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
        $credentials = $request->only("email", "username", "password", "name", "surname");

        $user = $this->userRepository->find($id);
        if (empty($user)) {
            return response()->json([], 404);
        }
        try {
            $user->update($credentials);
        } catch (\Exception $e) {
            // errore 500
            return response()->json([
                "message" => "Error during updating user",
                "error" => [
                    "code" => 500,
                    "message" => $e->getMessage(),
                ],
            ]);
        }
        return response()->json(
            [
                "user" => UserResource::make($user),
            ],
            200,
        );
    }

    // user delete
    #[
        OA\Delete(
            path: "/api/v1/users/{id}",
            summary: "Delete user by id",
            description: '__*Security:*__ __*can be used only by clients with \'admin\' role*__',
            operationId: "User.delete",
            tags: ["User management"],
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
        $user = $this->userRepository->find($id);
        if (empty($user)) {
            return response()->json([], 404);
        }

        try {
            $user->delete();
        } catch (\Exception $e) {
            // errore 500
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
