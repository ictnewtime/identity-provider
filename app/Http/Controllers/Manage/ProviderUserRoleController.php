<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\ProviderUserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class ProviderUserRoleController extends Controller
{
    // public function __construct() {}

    #[
        OA\Post(
            path: "/v1/provider-user-roles",
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
        ),
    ]
    public function create(Request $request)
    {
        $data = $request->input(["provider_id", "user_id", "role_id"]);
        $validator = $this->validator($data);

        if ($validator->fails()) {
            return response()->json(
                [
                    "message" => "The given data is invalid",
                    "errors" => $validator->errors(),
                ],
                422,
            );
        }

        ProviderUserRole::create($data);
        if (empty($provider)) {
            return response()->json(["message" => "Error creating the relation between provider user role"], 500);
        }

        return response()->json(["provider" => $provider], 201);
    }

    #[
        OA\Get(
            path: "/v1/provider-user-roles",
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
        OA\Get(
            path: "/v1/provider-user-roles/{id}",
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

    /**
     * update
     */
    #[
        OA\Put(
            path: "/v1/provider-user-roles/{id}",
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
                            new OA\Property(property: "provider_id", description: "Provider id", type: "integer"),
                            new OA\Property(property: "user_id", description: "User id", type: "integer"),
                            new OA\Property(property: "role_id", description: "Role id", type: "integer"),
                        ],
                    ),
                ),
            ),
        ),
    ]
    public function update($id, Request $request)
    {
        $data = $request->input(["provider_id", "user_id", "role_id"]);
        $validator = $this->validator($data);

        if ($validator->fails()) {
            return response()->json(
                [
                    "message" => "The given data is invalid",
                    "errors" => $validator->errors(),
                ],
                422,
            );
        }

        ProviderUserRole::where("id", $id)->update($data);
        return response()->json(["message" => "Provider user role updated"], 200);
    }

    #[
        OA\Delete(
            path: "/v1/provider-user-roles/{id}",
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

    /*
     * Returns the validator for user data
     */
    private function validator(array $data)
    {
        return Validator::make($data, [
            "provider_id" => "required|exists:providers,id",
            "user_id" => "required|exists:users,id",
            "role_id" => "required|exists:roles,id",
        ]);
    }
}
