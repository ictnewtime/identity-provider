<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProviderRequest;
use App\Models\Provider;
// use App\Repositories\RepositoryInterface;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class ProviderController extends Controller
{
    protected $providerRepository;

    public function __construct()
    {
        // $this->providerRepository = $providerRepository;
    }

    #[
        OA\Get(
            path: "/api/v1/providers",
            summary: "list of providers",
            description: "Returns the entire list of providers",
            operationId: "Provider.all",
            tags: ["Providers"],
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
        return Provider::all();
    }

    #[
        OA\Post(
            path: "/api/v1/providers",
            summary: "Create a new provider",
            description: '__*Security:*__ __*can be used only by clients with \'admin\' role*__',
            operationId: "Provider.create",
            tags: ["Providers"],
            security: [["passport" => []]],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\MediaType(
                    mediaType: "application/x-www-form-urlencoded",
                    schema: new OA\Schema(
                        type: "object",
                        properties: [
                            new OA\Property(
                                property: "domain",
                                description: "Provider domain",
                                type: "string",
                                example: "portale.newtimegroup.it",
                            ),
                            new OA\Property(
                                property: "logoutUrl",
                                description: "URL for logout",
                                type: "string",
                                example: "https://portale.newtimegroup.it/logout/",
                            ),
                            new OA\Property(
                                property: "secret_key",
                                description: "secret key for JWT token",
                                type: "string",
                                example: "",
                            ),
                        ],
                        required: ["domain", "logoutUrl", "secret_key"],
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
    public function create(ProviderRequest $request)
    {
        $data = $request->only("domain", "secret_key", "logoutUrl");

        $provider = Provider::create($data);

        if (empty($provider)) {
            return response()->json(["message" => "Error during saving provider"], 500);
        }

        return response()->json(["provider" => $provider], 201);
    }

    // find
    #[
        OA\Get(
            path: "/api/v1/providers/{id}",
            summary: "Returns provider by id",
            description: "Returns provider details by id",
            operationId: "Provider.find",
            tags: ["Providers"],
            security: [["passport" => []]],
            parameters: [
                new OA\Parameter(
                    in: "path",
                    required: true,
                    description: "Provider id",
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
            $provider = Provider::find($id);
        } catch (\Exception $e) {
            return response()->json(["message" => "Invalid id" . $e], 500);
        }

        if (empty($provider)) {
            return response()->json(["message" => "Provider not found"], 404);
        }

        return response()->json(["provider" => $provider], 200);
    }
    // update
    #[
        OA\Put(
            path: "/api/v1/providers/{id}",
            summary: "Update provider by id",
            description: '__*Security:*__ __*can be used only by clients with \'admin\' role*__',
            operationId: "Provider.update",
            tags: ["Providers"],
            security: [["passport" => []]],
            parameters: [
                new OA\Parameter(
                    in: "path",
                    required: true,
                    description: "Provider id",
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
                                property: "domain",
                                description: "Provider domain",
                                type: "string",
                                example: "portale.newtimegroup.it",
                            ),
                            new OA\Property(
                                property: "logoutUrl",
                                description: "URL for logout",
                                type: "string",
                                example: "https://portale.newtimegroup.it/logout/",
                            ),
                            new OA\Property(
                                property: "secret_key",
                                description: "secret key for JWT token",
                                type: "string",
                                example: "123",
                            ),
                        ],
                        required: ["domain", "logoutUrl", "secret_key"],
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
                    description: "Server error",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
            ],
        ),
    ]
    public function update(Request $request, $id)
    {
        $data = $request->only("domain", "secret_key", "logoutUrl");

        try {
            $provider = Provider::find($id);
        } catch (\Exception $e) {
            return response()->json(["message" => "Invalid id" . $e], 500);
        }

        if (empty($provider)) {
            return response()->json(["message" => "Provider not found"], 404);
        }

        $provider->update($data);

        return response()->json(["provider" => $provider], 200);
    }

    // delete
    #[
        OA\Delete(
            path: "/api/v1/providers/{id}",
            summary: "Delete provider by id",
            description: '__*Security:*__ __*can be used only by clients with \'admin\' role*__',
            operationId: "Provider.delete",
            tags: ["Providers"],
            security: [["passport" => []]],
            parameters: [
                new OA\Parameter(
                    in: "path",
                    required: true,
                    description: "Provider id",
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
        try {
            $provider = Provider::find($id);
        } catch (\Exception $e) {
            return response()->json(["message" => "Invalid id" . $e], 500);
        }

        if (empty($provider)) {
            return response()->json(["message" => "Provider not found"], 404);
        }

        $provider->delete($id);

        return response()->json(null, 204);
    }
}
