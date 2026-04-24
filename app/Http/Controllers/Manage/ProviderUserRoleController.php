<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProviderUserRoleRequest;
use App\Models\ProviderUserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class ProviderUserRoleController extends Controller
{
    public function __construct() {}

    #[
        OA\Get(
            path: "/api/v1/provider-user-roles",
            summary: "list of provider user roles",
            description: "Returns the entire list of provider user roles",
            operationId: "ProviderUserRole.all",
            tags: ["Provider User Roles"],
            security: [["passport" => []]],
            responses: [
                new OA\Response(
                    response: 200,
                    description: "Operation successful",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
            ],
        ),
    ]
    public function all(Request $request)
    {
        $show_deleted = $request->boolean("show_deleted");
        $query = ProviderUserRole::with(["user:id,username", "provider:id,name", "role:id,name"]);

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
        $perPage = $request->input("per_page", 10);
        return $query->paginate($perPage);
    }

    #[
        OA\Post(
            path: "/api/v1/provider-user-roles",
            summary: "Create a new provider user role",
            description: '__*Security:*__ __*can be used only by clients with \'admin\' role*__',
            operationId: "ProviderUserRole.create",
            tags: ["Provider User Roles"],
            security: [["passport" => []]],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\MediaType(
                    mediaType: "application/x-www-form-urlencoded",
                    schema: new OA\Schema(
                        type: "object",
                        properties: [
                            new OA\Property(property: "provider_id", description: "Provider id", type: "integer"),
                            new OA\Property(property: "user_id", description: "User id", type: "integer"),
                            new OA\Property(property: "role_id", description: "Role id", type: "integer"),
                        ],
                    ),
                ),
            ),
            responses: [
                new OA\Response(
                    response: 201,
                    description: "Operation successful",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
                new OA\Response(
                    response: 422,
                    description: "Unprocessable Entity",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
                new OA\Response(
                    response: 500,
                    description: "Internal Server Error",
                    content: new OA\MediaType(mediaType: "application/json"),
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
            path: "/api/v1/provider-user-roles/{id}",
            summary: "Returns provider user role by id",
            description: "Returns provider user role details by id",
            operationId: "ProviderUserRole.find",
            tags: ["Provider User Roles"],
            security: [["passport" => []]],
            parameters: [
                new OA\Parameter(
                    in: "path",
                    required: true,
                    description: "Provider user role id",
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
            ],
        ),
    ]
    public function find($id)
    {
        $providerUserRole = ProviderUserRole::find($id);
        if (empty($providerUserRole)) {
            return response()->json(["message" => "Provider user role not found"], 404);
        }
        return response()->json(["providerUserRole" => $providerUserRole], 200);
    }

    #[
        OA\Put(
            path: "/api/v1/provider-user-roles/{id}",
            summary: "Update provider user role by id",
            description: '__*Security:*__ __*can be used only by clients with \'admin\' role*__',
            operationId: "ProviderUserRole.update",
            tags: ["Provider User Roles"],
            security: [["passport" => []]],
            parameters: [
                new OA\Parameter(
                    in: "path",
                    required: true,
                    description: "Provider user role id",
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
                                description: "Provider id",
                                type: "integer",
                                example: "1",
                            ),
                            new OA\Property(property: "user_id", description: "User id", type: "integer", example: "1"),
                            new OA\Property(property: "role_id", description: "Role id", type: "integer", example: "1"),
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
                    response: 422,
                    description: "Unprocessable Entity",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
                new OA\Response(
                    response: 500,
                    description: "Internal Server Error",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
            ],
        ),
    ]
    public function update($id, ProviderUserRoleRequest $request)
    {
        $data = $request->validated();

        $providerUserRole = ProviderUserRole::where("id", $id)->first();
        if (empty($providerUserRole)) {
            return response()->json(["message" => "Provider user role not found"], 404);
        }
        $providerUserRole->update($data);
        return response()->json(["message" => "Provider user role updated"], 200);
    }

    #[
        OA\Delete(
            path: "/api/v1/provider-user-roles/{id}",
            summary: "Delete provider user role by id",
            description: '__*Security:*__ __*can be used only by clients with \'admin\' role*__',
            operationId: "ProviderUserRole.delete",
            tags: ["Provider User Roles"],
            security: [["passport" => []]],
            parameters: [
                new OA\Parameter(
                    in: "path",
                    required: true,
                    description: "Provider user role id",
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
            ],
        ),
    ]
    public function delete($id)
    {
        $providerUserRole = ProviderUserRole::find($id);
        if (empty($providerUserRole)) {
            return response()->json(["message" => "Provider user role not found"], 404);
        }
        $providerUserRole->delete();
        return response()->json(["message" => "Provider user role deleted"], 204);
    }

    public function bulk_delete(Request $request)
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

        return response()->json(["message" => __("provider_user_roles.bulk_delete_success")], 204);
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

    public function bulk_restore(Request $request)
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

    public function bulk_add(Request $request)
    {
        Log::info($request->all());
        $request->validate([
            "user_ids" => "required|array",
            "user_ids.*" => "integer|exists:users,id",
            "roles" => "required|array",
            "roles.*.role_id" => "required|integer|exists:roles,id",
            "roles.*.provider_id" => "required|integer|exists:providers,id",
        ]);

        try {
            DB::beginTransaction();

            foreach ($request->user_ids as $userId) {
                foreach ($request->roles as $roleData) {
                    ProviderUserRole::firstOrCreate([
                        "user_id" => $userId,
                        "role_id" => $roleData["role_id"],
                        "provider_id" => $roleData["provider_id"],
                    ]);
                }
            }

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
