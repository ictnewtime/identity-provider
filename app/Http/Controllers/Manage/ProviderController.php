<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProviderRequest;
use App\Models\Provider;
use App\Models\ProviderUserRole;
// use App\Repositories\RepositoryInterface;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
// use Laravel\Passport\ClientRepository;
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
            description: self::OA_DESC_MSG_SUCCESS,
            operationId: "Provider.all",
            tags: ["Providers"],
            security: [["passport" => []]],
            parameters: [
                new OA\Parameter(
                    name: "q",
                    in: "query",
                    required: false,
                    description: "Search term for filtering providers by domain or name",
                    schema: new OA\Schema(type: "string"),
                ),
                new OA\Parameter(
                    name: "show_deleted",
                    in: "query",
                    required: false,
                    description: "Whether to include deleted providers in the results",
                    schema: new OA\Schema(type: "boolean"),
                ),
                new OA\Parameter(
                    name: "sort_by",
                    in: "query",
                    required: false,
                    description: "Field to sort by (id, name, domain, unique_users_count, deleted_at)",
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
        $query = Provider::query();
        if ($request->filled("q")) {
            $query->where("domain", "like", "%" . $request->q . "%");
        }
        // contatore per il numero di utenti univoci per provider
        $query->addSelect([
            "unique_users_count" => ProviderUserRole::selectRaw("count(distinct user_id)")->whereColumn(
                "provider_id",
                "providers.id",
            ),
        ]);

        if ($show_deleted) {
            $query->onlyTrashed();
        }

        if ($request->filled("sort_by")) {
            $field = $request->sort_by;
            $direction = strtolower($request->sort_dir) === "desc" ? "desc" : "asc";
            $allowedSorts = ["id", "name", "domain", "unique_users_count", "deleted_at"];

            if (in_array($field, $allowedSorts)) {
                $query->orderBy($field, $direction);
            }
        } else {
            $query->orderBy("id", "asc");
        }

        $perPage = $request->input("per_page", 25);
        return $query->paginate($perPage);
    }

    #[
        OA\Get(
            path: "/api/v1/providers/{id}",
            summary: "Get provider by id",
            description: self::OA_DESC_MSG_SUCCESS,
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

    #[
        OA\Post(
            path: "/api/v1/providers",
            summary: "Create a new provider",
            description: self::OA_DESC_MSG_SECURITY_ADMIN,
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
                                property: "name",
                                type: "string",
                                example: "Example",
                                description: "Name of the provider",
                            ),
                            new OA\Property(
                                property: "url",
                                type: "string",
                                example: "https://example.com",
                                description: "URL of the provider",
                            ),
                            new OA\Property(
                                property: "domain",
                                type: "string",
                                example: "example.com",
                                description: "Domain of the provider",
                            ),
                            new OA\Property(
                                property: "protocol",
                                type: "string",
                                example: "http",
                                description: "Protocol of the provider",
                            ),
                            new OA\Property(
                                property: "logoutUrl",
                                type: "string",
                                example: "https://example.com/logout",
                                description: "Logout URL of the provider",
                            ),
                            new OA\Property(
                                property: "secret_key",
                                type: "string",
                                example: "2d6f5d6f8d6f2d6f5d6f8d6f2d6f5d6",
                                description: "Signature key of the provider",
                            ),
                        ],
                        required: ["name", "url", "domain", "logoutUrl", "secret_key"],
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
    public function create(ProviderRequest $request)
    {
        $data = $request->only("name", "url", "domain", "protocol", "logoutUrl", "secret_key");

        try {
            $provider = Provider::create($data);

            return response()->json(["provider" => $provider], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(["error" => $e->getMessage()], 500);
        }
    }

    #[
        OA\Put(
            path: "/api/v1/providers/{id}",
            summary: "Update provider by id",
            description: self::OA_DESC_MSG_SECURITY_ADMIN,
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
                                property: "name",
                                type: "string",
                                example: "Portale Newtimegroup",
                                description: "Provider name",
                            ),
                            new OA\Property(
                                property: "url",
                                type: "string",
                                example: "https://portale.newtimegroup.it",
                                description: "Provider url",
                            ),
                            new OA\Property(
                                property: "domain",
                                type: "string",
                                example: "portale.newtimegroup.it",
                                description: "Provider domain",
                            ),
                            new OA\Property(
                                property: "protocol",
                                type: "string",
                                example: "http",
                                description: "Provider protocol",
                            ),
                            new OA\Property(
                                property: "logoutUrl",
                                type: "string",
                                example: "https://portale.newtimegroup.it/logout/",
                                description: "URL for logout",
                            ),
                            new OA\Property(
                                property: "secret_key",
                                type: "string",
                                example: "2d6f5d6f8d6f2d6f5d6f8d6f2d6f5d6",
                                description: "Signature key for JWT token. Must be 32 characters long. Leave empty to not change it.",
                            ),
                        ],
                        required: ["name", "url", "domain", "logoutUrl"],
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
    public function update(Request $request, $id)
    {
        $data = $request->only("name", "url", "domain", "protocol", "logoutUrl", "secret_key");

        try {
            $provider = Provider::find($id);

            if (empty($provider)) {
                return response()->json(["message" => "Provider not found"], 404);
            }

            if (empty($data["secret_key"])) {
                unset($data["secret_key"]);
            }

            $provider->update($data);

            return response()->json(["provider" => $provider], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Errore update Provider: " . $e->getMessage());
            return response()->json(["message" => "Server error: " . $e->getMessage()], 500);
        }
    }

    #[
        OA\Delete(
            path: "/api/v1/providers/{id}",
            summary: "Delete provider by id",
            description: self::OA_DESC_MSG_SECURITY_ADMIN,
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
    public function delete($id)
    {
        try {
            DB::beginTransaction();

            $provider = Provider::find($id);

            if (empty($provider)) {
                return response()->json(["message" => __("admin.providers.not_found_for_delete")], 404);
            }
            // se esistono dei ruoli associati al provider che sono ancora attivi, non permettere l'eliminazione
            if ($provider->roles()->whereNull("deleted_at")->exists()) {
                return response()->json(["message" => __("admin.providers.delete_error_active_roles")], 400);
            }

            DB::table("oauth_clients")->where("id", $provider->id)->delete();

            $provider->delete();

            DB::commit();

            return response()->json(null, 204);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Errore delete Provider: " . $e->getMessage());
            return response()->json(["message" => "Server error: " . $e->getMessage()], 500);
        }
    }

    public function restore($id)
    {
        try {
            $provider = Provider::withTrashed()->find($id);
            if (empty($provider)) {
                return response()->json(["message" => __("provider.not_found")], 404);
            }
            $provider->restore();
            return response()->json(["provider" => $provider], 200);
        } catch (\Exception $e) {
            Log::error("Errore restore Provider: " . $e->getMessage());
            return response()->json(["message" => __("provider.restore_error")], 500);
        }
    }
}
