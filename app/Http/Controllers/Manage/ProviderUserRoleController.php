<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProviderUserRoleRequest;
use App\Models\ProviderUserRole;
use Illuminate\Http\Request;
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
    public function all()
    {
        return ProviderUserRole::all();
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
            return response()->json(["message" => "Error creating the relation between provider user role"], 500);
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

    // update
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
}
