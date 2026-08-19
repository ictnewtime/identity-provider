<?php

namespace App\Http\Controllers\Manage;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\RoleRequest;
use App\Models\ProviderUserRole;
use Illuminate\Database\QueryException;
// use App\Repositories\RoleRepository;
use App\Models\Role;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class RoleController extends Controller
{
    private const OA_PATH = "/api/v1/roles";
    private const OA_PATH_ID = self::OA_PATH . "/{id}";

    // protected $roleRepository;

    // public function __construct(RoleRepository $roleRepository)
    // {
    //     $this->roleRepository = $roleRepository;
    // }

    #[
        OA\Get(
            path: self::OA_PATH,
            summary: "list of roles",
            description: self::OA_DESC_MSG_SECURITY_ADMIN,
            operationId: "Role.all",
            tags: ["Roles"],
            security: [["passport" => []]],
            parameters: [
                new OA\Parameter(
                    in: "query",
                    name: "q",
                    required: false,
                    schema: new OA\Schema(type: "string"),
                    description: "Search term to filter roles by name or provider domain",
                ),
                new OA\Parameter(
                    in: "query",
                    name: "provider_id",
                    required: false,
                    schema: new OA\Schema(type: "integer"),
                    description: "Filter roles by provider id",
                ),
                new OA\Parameter(
                    in: "query",
                    name: "show_deleted",
                    required: false,
                    schema: new OA\Schema(type: "boolean"),
                    description: "Whether to include deleted roles in the results",
                ),
                new OA\Parameter(
                    in: "query",
                    name: "sort_by",
                    required: false,
                    schema: new OA\Schema(type: "string"),
                    description: "Field to sort by (id, name, provider.domain, provider.name, deleted_at)",
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
                    schema: new OA\Schema(type: "integer"),
                    description: "Number of items per page for pagination",
                ),
                new OA\Parameter(
                    in: "query",
                    name: "page",
                    required: false,
                    schema: new OA\Schema(type: "integer", default: 10),
                    description: "Page number for pagination",
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
        $show_deleted = $request->boolean("show_deleted");
        // eager load nello scope: si serializzano solo i campi del provider usati dal
        // frontend (name, domain) evitando di esporre l'intero oggetto provider
        $query = Role::with("provider:id,name,domain");

        $provider_id = $request->input("provider_id");

        if ($provider_id) {
            $query->whereHas("provider", function ($q) use ($provider_id) {
                $q->where("id", $provider_id);
            });
        }

        if ($request->filled("q")) {
            $searchTerm = "%" . $request->q . "%";
            $query->where(function ($qBuilder) use ($searchTerm) {
                $qBuilder->where("name", "like", $searchTerm)->orWhereHas("provider", function ($q) use ($searchTerm) {
                    $q->where("domain", "like", $searchTerm);
                });
            });
        }

        if ($show_deleted) {
            $query->onlyTrashed();
        }

        if ($request->filled("sort_by")) {
            $field = $request->sort_by;
            $direction = strtolower($request->sort_dir) === "desc" ? "desc" : "asc";
            $allowedSorts = ["id", "name", "provider.domain", "provider.name", "deleted_at"];

            if (in_array($field, $allowedSorts)) {
                if (str_starts_with($field, "provider.")) {
                    $sortColumn = str_replace("provider.", "providers.", $field);
                    $query
                        ->join("providers", "roles.provider_id", "=", "providers.id")
                        ->select("roles.*")
                        ->orderBy($sortColumn, $direction);
                } else {
                    $query->orderBy("roles." . $field, $direction);
                }
            }
        } else {
            $query->orderBy("id", "asc");
        }

        // cap massimo per evitare payload/memory abuse (per_page=999999)
        $perPage = min(max((int) $request->input("per_page", 25), 1), 100);
        return $query->paginate($perPage);
    }

    #[
        OA\Post(
            path: self::OA_PATH,
            summary: "Create a new role",
            description: self::OA_DESC_MSG_SECURITY_ADMIN,
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
                                description: self::OA_DESC_PROVIDER_ID,
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
                    description: self::OA_DESC_MSG_SUCCESS,
                    content: new OA\MediaType(mediaType: self::MEDIA_TYPE_JSON),
                ),
                new OA\Response(
                    response: 401,
                    description: self::UNAUTHORIZED,
                    content: new OA\MediaType(mediaType: self::MEDIA_TYPE_JSON),
                ),
                new OA\Response(
                    response: 422,
                    description: self::OA_DESC_MSG_UNPROCESSABLE_ENTITY,
                    content: new OA\MediaType(mediaType: self::MEDIA_TYPE_JSON),
                ),
                new OA\Response(
                    response: 403,
                    description: self::OA_DESC_MSG_FORBIDDEN,
                    content: new OA\MediaType(mediaType: self::MEDIA_TYPE_JSON),
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
            $role = Role::create($data);

            return response()->json($role, 201);
        } catch (QueryException $e) {
            return response()->json(["message" => "Error on saving role"], 500);
        }
    }

    #[
        OA\Get(
            path: self::OA_PATH_ID,
            summary: "Returns role by id",
            description: self::OA_DESC_MSG_SECURITY_ADMIN,
            operationId: "Role.find",
            tags: ["Roles"],
            security: [["passport" => []]],
            parameters: [
                new OA\Parameter(
                    in: "path",
                    required: true,
                    description: self::OA_DESC_ROLE_ID,
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
        $role = Role::withTrashed()->find($id);
        if (empty($role)) {
            return $this->notFound("role.error.not_found");
        }
        return response()->json($role);
    }

    #[
        OA\Put(
            path: self::OA_PATH_ID,
            summary: "Update role by id",
            description: self::OA_DESC_MSG_SECURITY_ADMIN,
            operationId: "Role.update",
            tags: ["Roles"],
            security: [["passport" => ["manage-idp"]]],
            parameters: [
                new OA\Parameter(
                    in: "path",
                    required: true,
                    description: self::OA_DESC_ROLE_ID,
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
                                description: self::OA_DESC_PROVIDER_ID,
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
    public function update(RoleRequest $request, $id)
    {
        $data = $request->only("name", "provider_id");
        $role = Role::find($id);

        if (empty($role)) {
            return $this->notFound("role.error.not_found");
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
            path: self::OA_PATH_ID,
            summary: "Remove role by id",
            description: self::OA_DESC_MSG_SECURITY_ADMIN,
            operationId: "Role.delete",
            tags: ["Roles"],
            security: [["passport" => ["manage-idp"]]],
            parameters: [
                new OA\Parameter(
                    in: "path",
                    required: true,
                    description: self::OA_DESC_ROLE_ID,
                    name: "id",
                    schema: new OA\Schema(type: "integer", minimum: 1),
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
    public function delete(int $id)
    {
        $role = Role::find($id);

        if (empty($role)) {
            // Era `"Role id not found"`: lo stesso messaggio degli altri undici, scritto male.
            return $this->notFound("role.error.not_found");
        }

        $providerUserRole = ProviderUserRole::where("role_id", $role->id)->first();
        if ($providerUserRole) {
            return response()->json(
                [
                    "message" => __("role.error.is_in_use"),
                ],
                409,
            );
        }
        try {
            $role->delete();
        } catch (QueryException $e) {
            return response()->json(["message" => $e], 500);
        }

        return response()->json(null, 204);
    }

    public function restore($id)
    {
        $role = Role::withTrashed()->find($id);
        if (empty($role)) {
            return response()->json(["message" => __("role.error.not_found")], 404);
        }
        try {
            $role->restore();
        } catch (\Exception $e) {
            Log::error("Error on restoring role: " . $e);
            return response()->json(["message" => __("role.error.restoring")], 500);
        }
        return response()->json($role, 200);
    }
}
