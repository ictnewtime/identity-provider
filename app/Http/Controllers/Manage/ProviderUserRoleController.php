<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProviderUserRoleRequest;
use App\Models\ProviderUserRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use OwenIt\Auditing\Models\Audit;

class ProviderUserRoleController extends Controller
{
    private const OA_TAG = "Provider User Roles";
    private const OA_PATH = "/api/v1/provider-user-roles";
    private const OA_PATH_ID = self::OA_PATH . "/{id}";

    #[
        OA\Get(
            path: self::OA_PATH,
            summary: "Get list of provider user roles",
            description: self::OA_DESC_MSG_SECURITY_ADMIN,
            operationId: "ProviderUserRole.all",
            tags: [self::OA_TAG],
            security: [["passport" => []]],
            parameters: [
                new OA\Parameter(
                    name: "q",
                    in: "query",
                    required: false,
                    description: "Search term to filter provider user roles by user username, provider domain or name, role name",
                    schema: new OA\Schema(type: "string"),
                ),
                new OA\Parameter(
                    name: "show_deleted",
                    in: "query",
                    required: false,
                    description: "Whether to include deleted provider user roles in the results",
                    schema: new OA\Schema(type: "boolean"),
                ),
                new OA\Parameter(
                    name: "sort_by",
                    in: "query",
                    required: false,
                    description: "Field to sort by (id, provider.name, user.username, role.name, deleted_at)",
                    schema: new OA\Schema(type: "string"),
                ),
                new OA\Parameter(
                    name: "sort_dir",
                    in: "query",
                    required: false,
                    description: "Sort direction (asc or desc)",
                    schema: new OA\Schema(type: "string"),
                ),
                new OA\Parameter(
                    name: "per_page",
                    in: "query",
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
            ],
        ),
    ]
    public function all(Request $request)
    {
        $show_deleted = $request->boolean("show_deleted");
        $query = ProviderUserRole::with(["user:id,username", "provider:id,name", "role:id,name"]);
        $baseTable = (new ProviderUserRole())->getTable();

        if ($request->filled("q")) {
            $searchTerm = "%" . $request->q . "%";

            $query->where(function ($mainQuery) use ($searchTerm) {
                $mainQuery
                    ->whereHas("user", function ($q) use ($searchTerm) {
                        $q->where("username", "like", $searchTerm);
                    })
                    ->orWhereHas("provider", function ($q) use ($searchTerm) {
                        $q->where("domain", "like", $searchTerm);
                    })
                    ->orWhereHas("provider", function ($q) use ($searchTerm) {
                        $q->where("name", "like", $searchTerm);
                    })
                    ->orWhereHas("role", function ($q) use ($searchTerm) {
                        $q->where("name", "like", $searchTerm);
                    });
            });
        }

        if ($show_deleted) {
            $query->onlyTrashed();
        }

        if ($request->filled("sort_by")) {
            $field = $request->sort_by;
            $direction = strtolower($request->sort_dir) === "desc" ? "desc" : "asc";
            $allowedSorts = ["id", "provider.name", "user.username", "role.name", "deleted_at"];

            if (in_array($field, $allowedSorts)) {
                if ($field === "provider.name") {
                    $query
                        ->join("providers", "{$baseTable}.provider_id", "=", "providers.id")
                        ->select("{$baseTable}.*")
                        ->orderBy("providers.name", $direction);
                } elseif ($field === "user.username") {
                    $query
                        ->join("users", "{$baseTable}.user_id", "=", "users.id")
                        ->select("{$baseTable}.*")
                        ->orderBy("users.username", $direction);
                } elseif ($field === "role.name") {
                    $query
                        ->join("roles", "{$baseTable}.role_id", "=", "roles.id")
                        ->select("{$baseTable}.*")
                        ->orderBy("roles.name", $direction);
                } else {
                    $query->orderBy("{$baseTable}.{$field}", $direction);
                }
            }
        } else {
            $query->orderBy("id", "asc");
        }

        $perPage = $request->input("per_page", 25);
        return $query->paginate($perPage);
    }

    #[
        OA\Post(
            path: self::OA_PATH,
            summary: "Create a new provider user role",
            description: self::OA_DESC_MSG_SECURITY_ADMIN,
            operationId: "ProviderUserRole.create",
            tags: [self::OA_TAG],
            security: [["passport" => []]],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\MediaType(
                    mediaType: "application/x-www-form-urlencoded",
                    schema: new OA\Schema(
                        type: "object",
                        properties: [
                            new OA\Property(
                                property: "provider_id",
                                description: self::OA_DESC_PROVIDER_ID,
                                type: "integer",
                            ),
                            new OA\Property(property: "user_id", description: self::OA_DESC_USER_ID, type: "integer"),
                            new OA\Property(property: "role_id", description: self::OA_DESC_ROLE_ID, type: "integer"),
                        ],
                    ),
                ),
            ),
            responses: [
                new OA\Response(
                    response: 201,
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
    public function create(ProviderUserRoleRequest $request)
    {
        $data = $request->validated();

        $providerUserRole = ProviderUserRole::create($data);
        if (empty($providerUserRole)) {
            return response()->json(["message" => __("admin.provider_user_roles.errors.creation")], 500);
        }

        return response()->json(["providerUserRole" => $providerUserRole], 201);
    }

    #[
        OA\Get(
            path: self::OA_PATH_ID,
            summary: "Returns provider user role by id",
            description: self::OA_DESC_MSG_SUCCESS,
            operationId: "ProviderUserRole.find",
            tags: [self::OA_TAG],
            security: [["passport" => []]],
            parameters: [
                new OA\Parameter(
                    in: "path",
                    required: true,
                    description: self::OA_DESC_PROVIDER_USER_ROLE_ID,
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
            ],
        ),
    ]
    public function find($id)
    {
        // `withTrashed()` in lettura e non in scrittura, ed e' una scelta: un'associazione
        // cancellata logicamente si consulta, non si modifica. `update()` e `delete()` qui sotto
        // usano di proposito la forma senza — chi legge non deve prenderla per una svista.
        $providerUserRole = ProviderUserRole::withTrashed()->find($id);
        if (empty($providerUserRole)) {
            return $this->notFound("provider_user_roles.not_found");
        }
        return response()->json(["providerUserRole" => $providerUserRole], 200);
    }

    #[
        OA\Put(
            path: self::OA_PATH_ID,
            summary: "Update provider user role by id",
            description: self::OA_DESC_MSG_SECURITY_ADMIN,
            operationId: "ProviderUserRole.update",
            tags: [self::OA_TAG],
            security: [["passport" => []]],
            parameters: [
                new OA\Parameter(
                    in: "path",
                    required: true,
                    description: self::OA_DESC_PROVIDER_USER_ROLE_ID,
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
                                property: "provider_id",
                                description: self::OA_DESC_PROVIDER_ID,
                                type: "integer",
                                example: "1",
                            ),
                            new OA\Property(
                                property: "user_id",
                                description: self::OA_DESC_USER_ID,
                                type: "integer",
                                example: "1",
                            ),
                            new OA\Property(
                                property: "role_id",
                                description: self::OA_DESC_ROLE_ID,
                                type: "integer",
                                example: "1",
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
    public function update($id, ProviderUserRoleRequest $request)
    {
        $data = $request->validated();

        // Senza `withTrashed()`: un'associazione cancellata logicamente non si modifica. E' la
        // stessa scelta dichiarata in `find()`, vista dall'altro lato.
        $providerUserRole = ProviderUserRole::where("id", $id)->first();
        if (empty($providerUserRole)) {
            return $this->notFound("provider_user_roles.not_found");
        }
        $providerUserRole->update($data);
        return response()->json(["message" => "Provider user role updated"], 200);
    }

    #[
        OA\Delete(
            path: self::OA_PATH_ID,
            summary: "Delete provider user role by id",
            description: self::OA_DESC_MSG_SECURITY_ADMIN,
            operationId: "ProviderUserRole.delete",
            tags: [self::OA_TAG],
            security: [["passport" => []]],
            parameters: [
                new OA\Parameter(
                    in: "path",
                    required: true,
                    description: self::OA_DESC_PROVIDER_USER_ROLE_ID,
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
                    response: 404,
                    description: self::OA_DESC_MSG_NOT_FOUND,
                    content: new OA\MediaType(mediaType: self::MEDIA_TYPE_JSON),
                ),
            ],
        ),
    ]
    public function delete($id)
    {
        // Senza `withTrashed()`: cio' che e' gia' cancellato non si cancella di nuovo.
        $providerUserRole = ProviderUserRole::find($id);
        if (empty($providerUserRole)) {
            return $this->notFound("provider_user_roles.not_found");
        }
        $providerUserRole->delete();

        return response()->noContent();
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            "ids" => "required|array",
            "ids.*" => "integer|exists:provider_user_roles,id",
        ]);

        try {
            DB::beginTransaction();
            $rolesToDelete = ProviderUserRole::whereIn("id", $request->ids)->get();
            foreach ($rolesToDelete as $role) {
                $role->delete();
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(["message" => __("provider_user_roles.bulk_delete_error")], 500);
        }

        return response()->noContent();
    }

    public function restore(Request $request)
    {
        $providerUserRole = ProviderUserRole::withTrashed()->find($request->id);
        if (empty($providerUserRole)) {
            return response()->json(["message" => __("provider_user_roles.not_found")], 404);
        }
        $providerUserRoleActive = ProviderUserRole::where("provider_id", $providerUserRole->provider_id)
            ->where("user_id", $providerUserRole->user_id)
            ->where("role_id", $providerUserRole->role_id)
            ->first();
        if (!empty($providerUserRoleActive)) {
            return response()->json(["message" => __("provider_user_roles.conflict_unique")], 422);
        }
        $providerUserRole->restore();
        return response()->json(["message" => __("provider_user_roles.restore_success")], 200);
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
                    "message" => __("provider_user_roles.bulk_restore_error"),
                    "errors" => $validator->errors(),
                ],
                422,
            );
        }

        $rolesToRestore = ProviderUserRole::withTrashed()->whereIn("id", $request->ids)->get();

        if ($rolesToRestore->isEmpty()) {
            return response()->json(["message" => __("provider_user_roles.not_found_multiple")], 404);
        }

        // Controlliamo che non ci siano duplicati
        $duplicatesQuery = ProviderUserRole::query();

        $duplicatesQuery->where(function ($query) use ($rolesToRestore) {
            foreach ($rolesToRestore as $role) {
                $query->orWhere(function ($q) use ($role) {
                    $q->where("provider_id", $role->provider_id)
                        ->where("user_id", $role->user_id)
                        ->where("role_id", $role->role_id);
                });
            }
        });

        if ($duplicatesQuery->exists()) {
            return response()->json(["message" => __("provider_user_roles.conflict_unique_multiple")], 422);
        }

        try {
            DB::beginTransaction();
            foreach ($rolesToRestore as $role) {
                $role->restore();
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(["message" => $e->getMessage()], 500);
        }

        return response()->json(["message" => __("provider_user_roles.bulk_restore_success")], 200);
    }

    public function bulkAddRolesToUsers(Request $request)
    {
        $request->validate([
            "user_ids" => "required|array",
            "user_ids.*" => "integer|exists:users,id",
            "roles" => "required|array",
            "roles.*.role_id" => "required|integer|exists:roles,id",
            "roles.*.provider_id" => "required|integer|exists:providers,id",
            "is_all_selected" => "required|boolean",
            "only_user_deleted" => "required|boolean",
        ]);

        $userIds = [];
        if ($request->is_all_selected) {
            if ($request->only_user_deleted) {
                $userIds = User::onlyTrashed()->pluck("id")->toArray();
            } else {
                $userIds = User::pluck("id")->toArray();
            }
        } else {
            $userIds = $request->user_ids;
        }

        try {
            DB::beginTransaction();

            $insertData = [];
            $now = now();

            foreach ($userIds as $userId) {
                foreach ($request->roles as $roleData) {
                    $insertData[] = [
                        "user_id" => $userId,
                        "role_id" => $roleData["role_id"],
                        "provider_id" => $roleData["provider_id"],
                        "created_at" => $now,
                        "updated_at" => $now,
                    ];
                }
            }
            foreach (array_chunk($insertData, 1000) as $chunk) {
                ProviderUserRole::insertOrIgnore($chunk);
            }

            $userId = Auth::id();
            $userType = $userId ? get_class(Auth::user()) : null;
            Audit::create([
                "event" => "bulk_assign_roles",
                "auditable_type" => User::class,
                "auditable_id" => 0,
                "user_type" => $userType,
                "user_id" => $userId,
                "url" => request()->fullUrl(),
                "ip_address" => request()->ip(),
                "user_agent" => request()->userAgent(),
                "old_values" => [],
                "new_values" => [
                    "assigned_roles" => $request->roles,
                    "users_affected" => $userIds,
                ],
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(
                [
                    "message" => __("provider_user_roles.bulk_add_error"),
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }

        return response()->json(["message" => __("provider_user_roles.bulk_add_success")], 200);
    }

    public function hasRelation(Request $request)
    {
        // checkprovider_id or user_id or role_id possono essere null
        $provider_id = $request->input("provider_id");
        $user_id = $request->input("user_id");
        $role_id = $request->input("role_id");

        // se tutti sono null
        if (empty($provider_id) && empty($user_id) && empty($role_id)) {
            return response()->json(["message" => "All fields are null"], 400);
        }

        // se ance solo un campo non e' null
        if (!empty($provider_id) || !empty($user_id) || !empty($role_id)) {
            $providerUserRole = ProviderUserRole::where("provider_id", $provider_id)
                ->where("user_id", $user_id)
                ->where("role_id", $role_id)
                ->get();

            return response()->json(["data" => $providerUserRole], 200);
        }
    }
}
