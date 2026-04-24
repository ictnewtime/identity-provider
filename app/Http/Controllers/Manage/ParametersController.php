<?php

namespace App\Http\Controllers\Manage;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\RoleRequest;
use App\Models\Parameter;
use App\Models\ProviderUserRole;
use Illuminate\Database\QueryException;
// use App\Repositories\RoleRepository;
use App\Models\Role;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class ParametersController extends Controller
{
    #[
        OA\Get(
            path: "/api/v1/parameters",
            summary: "list of parameters",
            description: "Returns the entire list of parameters",
            operationId: "Parameter.all",
            tags: ["Parameters"],
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
        $show_deleted = $request->boolean("show_deleted");
        $query = Parameter::query();

        $searchTerm = $request->input("searchTerm");

        if ($searchTerm) {
            $query->where("key", "like", "%" . $searchTerm . "%");
        }

        if ($show_deleted) {
            $query->onlyTrashed();
        }

        $perPage = $request->input("per_page", 50);
        return $query->paginate($perPage);
    }

    #[
        OA\Post(
            path: "/api/v1/parameters",
            summary: "Create a new parameter",
            description: '__*Security:*__ __*can be used only by clients with \'admin\' role*__',
            operationId: "Parameter.create",
            tags: ["Parameters"],
            security: [["passport" => ["manage-idp"]]],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\MediaType(
                    mediaType: "application/x-www-form-urlencoded",
                    schema: new OA\Schema(
                        type: "object",
                        properties: [
                            new OA\Property(
                                property: "key",
                                description: "Parameter key",
                                type: "string",
                                example: "max_login_attempts",
                            ),
                            new OA\Property(
                                property: "value",
                                description: "Parameter value",
                                type: "string",
                                example: "5",
                            ),
                            new OA\Property(
                                property: "type",
                                description: "Parameter type",
                                type: "string",
                                example: "integer",
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
    public function create(Request $request)
    {
        $data = $request->only("key", "value", "type");
        $existingParameter = Parameter::where("key", $data["key"])->first();
        if ($existingParameter) {
            return response()->json(["message" => __("parameter.error.exists")], 422);
        }

        try {
            $parameter = Parameter::create($data);

            return response()->json($parameter, 201);
        } catch (QueryException $e) {
            return response()->json(["message" => __("parameter.error.creating")], 500);
        }
    }

    #[
        OA\Get(
            path: "/api/v1/parameters/{id}",
            summary: "Returns parameter by id",
            description: "Returns parameter details by id",
            operationId: "Parameter.find",
            tags: ["Parameters"],
            security: [["passport" => []]],
            parameters: [
                new OA\Parameter(
                    in: "path",
                    required: true,
                    description: "Parameter id",
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
        $parameter = Parameter::find($id);
        if (empty($parameter)) {
            return response()->json(["message" => __("parameter.error.not_found")], 404);
        }
        return response()->json($parameter);
    }

    #[
        OA\Put(
            path: "/api/v1/parameters/{id}",
            summary: "Update parameter by id",
            description: '__*Security:*__ __*can be used only by clients with \'admin\' role*__',
            operationId: "Parameter.update",
            tags: ["Parameters"],
            security: [["passport" => ["manage-idp"]]],
            parameters: [
                new OA\Parameter(
                    in: "path",
                    required: true,
                    description: "Parameter id",
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
                                property: "key",
                                description: "Parameter key",
                                type: "string",
                                example: "max_login_attempts",
                            ),
                            new OA\Property(
                                property: "value",
                                description: "Parameter value",
                                type: "string",
                                example: "5",
                            ),
                            new OA\Property(
                                property: "type",
                                description: "Parameter type",
                                type: "string",
                                example: "integer",
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
    public function update(Request $request, $id)
    {
        $data = $request->only("key", "value", "type");
        $parameter = Parameter::find($id);

        if (empty($parameter)) {
            return response()->json(["message" => __("parameter.error.not_found")], 404);
        }

        try {
            $parameter->update($data);

            return response()->json($parameter, 200);
        } catch (QueryException $e) {
            return response()->json(["message" => __("parameter.error.updating")], 500);
        }
    }

    #[
        OA\Delete(
            path: "/api/v1/parameters/{id}",
            summary: "Remove parameter by id",
            description: '__*Security:*__ __*can be used only by clients with \'admin\' role*__',
            operationId: "Parameter.delete",
            tags: ["Parameters"],
            security: [["passport" => ["manage-idp"]]],
            parameters: [
                new OA\Parameter(
                    in: "path",
                    required: true,
                    description: "Parameter id",
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
        $parameter = Parameter::find($id);

        if (empty($parameter)) {
            return response()->json(
                [
                    "message" => __("parameter.error.not_found"),
                ],
                404,
            );
        }

        try {
            $parameter->delete();
        } catch (QueryException $e) {
            return response()->json(["message" => $e], 500);
        }

        return response()->json(null, 204);
    }

    public function restore($id)
    {
        $parameter = Parameter::withTrashed()->find($id);
        if (empty($parameter)) {
            return response()->json(["message" => __("parameter.error.not_found")], 404);
        }
        try {
            $parameter->restore();
        } catch (\Exception $e) {
            Log::error("Error on restoring parameter: " . $e);
            return response()->json(["message" => __("parameter.error.restoring")], 500);
        }
        return response()->json($parameter, 200);
    }
}
