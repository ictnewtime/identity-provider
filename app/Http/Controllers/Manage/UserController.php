<?php

namespace App\Http\Controllers\Manage;

use Illuminate\Http\Request;
// use App\Http\Services\Mailer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use Illuminate\Support\Facades\Validator;
use App\Models\ProviderUserRole;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
    #[
        OA\Get(
            path: "/api/v1/users",
            summary: "Get all users",
            description: self::OA_DESC_MSG_SECURITY_ADMIN,
            operationId: "User.all",
            tags: ["Users"],
            security: [["passport" => []]],
            parameters: [
                new OA\Parameter(
                    in: "query",
                    name: "q",
                    required: false,
                    description: "Search term for filtering users by username or email",
                    schema: new OA\Schema(type: "string"),
                ),
                new OA\Parameter(
                    in: "query",
                    name: "sort_by",
                    required: false,
                    description: "Field to sort by (id, username, email, enabled, deleted_at)",
                    schema: new OA\Schema(type: "string"),
                ),
                new OA\Parameter(
                    in: "query",
                    name: "sort_dir",
                    required: false,
                    schema: new OA\Schema(type: "string"),
                    description: "Sort direction (asc or desc)",
                ),
                new OA\Parameter(
                    in: "query",
                    name: "per_page",
                    required: false,
                    description: "Number of items per page for pagination",
                    schema: new OA\Schema(type: "integer", default: 10),
                ),
            ],
            responses: [
                new OA\Response(
                    response: 200,
                    description: self::OA_DESC_MSG_SUCCESS,
                    content: new OA\MediaType(mediaType: self::MEDIA_TYPE_JSON),
                ),
                new OA\Response(
                    response: 401,
                    description: self::UNAUTHORIZED,
                    content: new OA\MediaType(mediaType: self::MEDIA_TYPE_JSON),
                ),
            ],
        ),
    ]
    public function all(Request $request)
    {
        $user_select_columns = ["id", "username", "email", "enabled"];
        $show_deleted = $request->boolean("show_deleted");
        if ($show_deleted) {
            $user_select_columns[] = "deleted_at";
        }
        $query = User::select($user_select_columns);

        if ($request->filled("q")) {
            $query->where(function ($q) use ($request) {
                $q->where("email", "like", "%" . $request->q . "%")->orWhere(
                    "username",
                    "like",
                    "%" . $request->q . "%",
                );
            });
        }

        if ($show_deleted) {
            $query->onlyTrashed();
        }

        if ($request->filled("sort_by")) {
            $field = $request->sort_by;
            $direction = strtolower($request->sort_dir) === "desc" ? "desc" : "asc";
            $allowedSorts = ["id", "username", "email", "enabled", "deleted_at"];
            if (in_array($field, $allowedSorts)) {
                $query->orderBy($field, $direction);
            }
        } else {
            $query->orderBy("created_at", "asc");
        }
        $perPage = $request->input("per_page", 25);
        $users = $query->paginate($perPage);

        return response()->json($users);
    }

    #[
        OA\Post(
            path: "/api/v1/users",
            summary: "Create a new user",
            description: self::OA_DESC_MSG_SECURITY_ADMIN,
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
                    description: self::OA_DESC_MSG_SUCCESS,
                    content: new OA\MediaType(mediaType: self::MEDIA_TYPE_JSON),
                ),
                new OA\Response(
                    response: 422,
                    description: self::OA_DESC_MSG_UNPROCESSABLE_ENTITY,
                    content: new OA\MediaType(mediaType: self::MEDIA_TYPE_JSON),
                ),
                new OA\Response(
                    response: 500,
                    description: self::OA_DESC_MSG_INTERNAL_SERVER_ERROR,
                    content: new OA\MediaType(mediaType: self::MEDIA_TYPE_JSON),
                ),
            ],
        ),
    ]
    public function create(UserRequest $request)
    {
        $data = $request->only(["username", "password", "email", "name", "surname", "enabled", "password_expires_at"]);

        $data["password"] = Hash::make($data["password"]);

        $data["enabled"] = $request->boolean("enabled", true);
        $data["is_verified"] = true;

        if (!empty($data["password_expires_at"])) {
            $data["password_expires_at"] = Carbon::parse($data["password_expires_at"])
                ->setTimezone(config("app.timezone"))
                ->format("Y-m-d H:i:s");
        }

        try {
            $user = User::create($data);

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
            description: self::OA_DESC_MSG_SECURITY_ADMIN,
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
                    description: self::OA_DESC_MSG_SUCCESS,
                    content: new OA\MediaType(mediaType: self::MEDIA_TYPE_JSON),
                ),
                new OA\Response(
                    response: 404,
                    description: self::OA_DESC_MSG_NOT_FOUND,
                    content: new OA\MediaType(mediaType: self::MEDIA_TYPE_JSON),
                ),
                new OA\Response(
                    response: 500,
                    description: self::OA_DESC_MSG_INTERNAL_SERVER_ERROR,
                    content: new OA\MediaType(mediaType: self::MEDIA_TYPE_JSON),
                ),
            ],
        ),
    ]
    public function find($id)
    {
        $user = User::find($id);
        if (empty($user)) {
            return response()->json(["message" => __("user.error.not_found")], 404);
        }
        return response()->json($user);
    }

    #[
        OA\Put(
            path: "/api/v1/users/{id}",
            summary: "Update user by id",
            description: self::OA_DESC_MSG_SECURITY_ADMIN,
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
                    description: self::OA_DESC_MSG_SUCCESS,
                    content: new OA\MediaType(mediaType: self::MEDIA_TYPE_JSON),
                ),
                new OA\Response(
                    response: 404,
                    description: self::OA_DESC_MSG_NOT_FOUND,
                    content: new OA\MediaType(mediaType: self::MEDIA_TYPE_JSON),
                ),
                new OA\Response(
                    response: 500,
                    description: self::OA_DESC_MSG_INTERNAL_SERVER_ERROR,
                    content: new OA\MediaType(mediaType: self::MEDIA_TYPE_JSON),
                ),
            ],
        ),
    ]
    public function update(UserRequest $request, $id)
    {
        $user = User::find($id);

        if (empty($user)) {
            return response()->json(["message" => "User not found"], 404);
        }

        $data = $request->only("email", "username", "name", "surname", "password_expires_at");

        if ($request->has("enabled")) {
            $data["enabled"] = $request->boolean("enabled");
        }

        if (array_key_exists("password_expires_at", $data)) {
            $data["password_expires_at"] = $data["password_expires_at"]
                ? Carbon::parse($data["password_expires_at"])
                    ->setTimezone(config("app.timezone"))
                    ->format("Y-m-d H:i:s")
                : null;
        }

        if ($request->filled("password")) {
            $data["password"] = Hash::make($request->password);
        }

        try {
            $user->update($data);
            return response()->json($user, 200);
        } catch (\Exception $e) {
            Log::error("Errore aggiornamento utente ID $id: " . $e->getMessage());
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
    }

    #[
        OA\Delete(
            path: "/api/v1/users/{id}",
            summary: "Delete user by id",
            description: self::OA_DESC_MSG_SECURITY_ADMIN,
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
                    description: self::OA_DESC_MSG_SUCCESS,
                    content: new OA\MediaType(mediaType: self::MEDIA_TYPE_JSON),
                ),
                new OA\Response(
                    response: 400,
                    description: self::OA_DESC_MSG_BAD_REQUEST,
                    content: new OA\MediaType(mediaType: self::MEDIA_TYPE_JSON),
                ),
                new OA\Response(
                    response: 404,
                    description: self::OA_DESC_MSG_NOT_FOUND,
                    content: new OA\MediaType(mediaType: self::MEDIA_TYPE_JSON),
                ),
                new OA\Response(
                    response: 500,
                    description: self::OA_DESC_MSG_INTERNAL_SERVER_ERROR,
                    content: new OA\MediaType(mediaType: self::MEDIA_TYPE_JSON),
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
        // Se l'utente ha ruoli associati, non permettere la cancellazione
        $hasRoles = ProviderUserRole::where("user_id", $id)->exists();
        if ($hasRoles) {
            return response()->json(["message" => __("user.delete_has_roles_error")], 400);
        }

        try {
            $user->delete();
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(["message" => __("user.delete_error")], 500);
        }
        return response()->json([], 204);
    }

    public function restore($id)
    {
        $user = User::withTrashed()->find($id);
        if (empty($user)) {
            return response()->json([], 404);
        }

        try {
            $user->restore();
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(["message" => __("user.restore_error")], 500);
        }
        return response()->json($user, 200);
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            "ids" => "required|array",
            "ids.*" => "integer|exists:users,id",
        ]);

        try {
            DB::beginTransaction();
            $usersToDelete = User::whereIn("id", $request->ids)->get();
            foreach ($usersToDelete as $user) {
                $user->delete();
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(["message" => __("user.bulk_delete_error")], 500);
        }

        return response()->json(["message" => __("user.bulk_delete_success")], 200);
    }

    public function bulkRestore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "ids" => "required|array",
            "ids.*" => "integer",
        ]);

        if ($validator->fails()) {
            return response()->json(
                [
                    "message" => __("users.bulk_restore_error"),
                    "errors" => $validator->errors(),
                ],
                422,
            );
        }

        $usersToRestore = User::withTrashed()->whereIn("id", $request->ids)->get();

        if ($usersToRestore->isEmpty()) {
            return response()->json(["message" => __("users.not_found_multiple")], 404);
        }

        try {
            DB::beginTransaction();
            foreach ($usersToRestore as $user) {
                $user->restore();
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(["message" => $e->getMessage()], 500);
        }

        return response()->json(["message" => __("users.bulk_restore_success")], 200);
    }

    public function getUserRoles(Request $request, int $id)
    {
        $user = User::find($id);
        if (empty($user)) {
            return response()->json([], 404);
        }
        $providerUserRoles = ProviderUserRole::where("user_id", $id)
            ->with(["role", "provider:id,name"])
            ->get();
        $userRoles = $providerUserRoles->map(function ($relation) {
            return [
                "id" => $relation->role_id,
                "name" => $relation->role->name,
                "provider_id" => $relation->provider_id,
                "provider_name" => $relation->provider->name,
            ];
        });
        return response()->json($userRoles, 200);
    }
}
