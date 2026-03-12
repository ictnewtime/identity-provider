<?php

namespace App\Http\Controllers\Manage;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\RoleRequest;
use Illuminate\Database\QueryException;
use App\Repositories\RoleRepository;
use Illuminate\Support\Facades\Validator;
// Role
use App\Models\Role;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class RoleController extends Controller
{
    protected $roleRepository;

    public function __construct(RoleRepository $roleRepository)
    {
        $this->roleRepository = $roleRepository;
    }

    #[
        OA\Get(
            path: "/api/v1/roles",
            summary: "list of roles",
            description: "Returns the entire list of roles",
            operationId: "Role.all",
            tags: ["Roles"],
            security: [["passport" => []]],
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
        $query = Role::with("provider");
        $provider_id = $request->input("provider_id");

        if ($provider_id) {
            $query->whereHas("provider", function ($q) use ($provider_id) {
                $q->where("id", $provider_id);
            });
        }

        if ($request->filled("q")) {
            $searchTerm = "%" . $request->q . "%";

            $query->where("name", "like", $searchTerm)->orWhereHas("provider", function ($q) use ($searchTerm) {
                $q->where("domain", "like", $searchTerm);
            });
        }

        $perPage = $request->input("per_page", 10);
        return $query->paginate($perPage);
    }

    #[
        OA\Post(
            path: "/api/v1/roles",
            summary: "Create a new role",
            description: '__*Security:*__ __*can be used only by clients with \'admin\' role*__',
            operationId: "Role.create",
            tags: ["Roles"],
            security: [["passport" => ["manage-idp"]]],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\MediaType(
                    mediaType: "application/x-www-form-urlencoded",
                    schema: new OA\Schema(
                        type: "object",
                        properties: [
                            new OA\Property(
                                property: "name",
                                description: "Role name",
                                type: "string",
                                example: "admin of this application/provider",
                            ),
                            new OA\Property(
                                property: "provider_id",
                                description: "Provider id",
                                type: "integer",
                                example: "1",
                            ),
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
                    response: 401,
                    description: "Unauthorized",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
                new OA\Response(
                    response: 422,
                    description: "Validation error",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
                new OA\Response(
                    response: 403,
                    description: "Invalid scope or client role, Forbidden",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
            ],
        ),
    ]
    public function create(RoleRequest $request)
    {
        $data = $request->only("name", "provider_id");
        $existingRole = Role::where("name", $data["name"])->where("provider_id", $data["provider_id"])->first();
        if ($existingRole) {
            return response()->json(["message" => "Role with this name already exists for this provider"], 422);
        }

        try {
            // $role = $this->roleRepository->create($data);
            $role = Role::create($data);

            return response()->json($role, 201);
        } catch (QueryException $e) {
            return response()->json(["message" => "Error on saving role"], 500);
        }
    }

    // find
    #[
        OA\Get(
            path: "/api/v1/roles/{id}",
            summary: "Returns role by id",
            description: "Returns role details by id",
            operationId: "Role.find",
            tags: ["Roles"],
            security: [["passport" => []]],
            parameters: [
                new OA\Parameter(
                    in: "path",
                    required: true,
                    description: "Role id",
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
                    description: "Error on finding",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
            ],
        ),
    ]
    public function find($id)
    {
        $role = Role::find($id);
        if (empty($role)) {
            return response()->json(["message" => "Role not found"], 404);
        }
        return response()->json($role);
    }

    // update
    #[
        OA\Put(
            path: "/api/v1/roles/{id}",
            summary: "Update role by id",
            description: '__*Security:*__ __*can be used only by clients with \'admin\' role*__',
            operationId: "Role.update",
            tags: ["Roles"],
            security: [["passport" => ["manage-idp"]]],
            parameters: [
                new OA\Parameter(
                    in: "path",
                    required: true,
                    description: "Role id",
                    name: "id",
                    schema: new OA\Schema(type: "integer", minimum: 1),
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
                                property: "name",
                                description: "Role name",
                                type: "string",
                                example: "admin of this application/provider",
                            ),
                            new OA\Property(
                                property: "provider_id",
                                description: "Provider id",
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
                    description: "Validation error",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
                new OA\Response(
                    response: 500,
                    description: "Error on updating",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
            ],
        ),
    ]
    public function update(RoleRequest $request, $id)
    {
        $data = $request->only("name", "provider_id");
        // find role
        $role = Role::find($id);

        if (empty($role)) {
            return response()->json(["message" => "Role not found"], 404);
        }

        try {
            $role->update($data);

            return response()->json($role, 200);
        } catch (QueryException $e) {
            return response()->json(["message" => "Error on updating role"], 500);
        }
    }

    #[
        OA\Delete(
            path: "/api/v1/roles/{id}",
            summary: "Remove role by id",
            description: '__*Security:*__ __*can be used only by clients with \'admin\' role*__',
            operationId: "Role.delete",
            tags: ["Roles"],
            security: [["passport" => ["manage-idp"]]],
            parameters: [
                new OA\Parameter(
                    in: "path",
                    required: true,
                    description: "Role id",
                    name: "id",
                    schema: new OA\Schema(type: "integer", minimum: 1),
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
                    description: "Error on deleting",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
            ],
        ),
    ]
    public function delete(int $id)
    {
        // $role = $this->roleRepository->find($id);
        $role = Role::find($id);

        if (empty($role)) {
            return response()->json(
                [
                    "message" => "Role id not found",
                ],
                404,
            );
        }

        if (!$this->roleRepository->delete($role)) {
            return response()->json(
                [
                    "message" => "Error on deleting",
                ],
                500,
            );
        }

        return response()->json([], 204);
    }
}
