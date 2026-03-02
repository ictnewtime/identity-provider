<?php

namespace App\Http\Controllers\Manage;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\SessionRequest;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use App\Models\Session;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class SessionController extends Controller
{
    #[
        OA\Get(
            path: "/api/v1/sessions",
            summary: "list of sessions",
            description: "Returns the entire list of sessions",
            operationId: "Session.all",
            tags: ["Session"],
            security: [["passport" => []]],
            responses: [
                new OA\Response(
                    response: 200,
                    description: "Operation successful",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
                new OA\Response(
                    response: 500,
                    description: "Internal server error",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
            ],
        ),
    ]
    public function all(Request $request)
    {
        $query = Session::with(["user", "provider"]);

        // Ricerca per user.username o provider.domain
        if ($request->filled("q")) {
            $searchTerm = "%" . $request->q . "%";

            $query
                ->whereHas("user", function ($q) use ($searchTerm) {
                    $q->where("username", "like", $searchTerm);
                })
                ->orWhereHas("provider", function ($q) use ($searchTerm) {
                    $q->where("domain", "like", $searchTerm);
                });
        }

        $perPage = $request->input("per_page", 10);

        return $query->paginate($perPage);
    }

    #[
        OA\Get(
            path: "/api/v1/sessions/{id}",
            summary: "Returns session by id",
            description: "Returns session details by id",
            operationId: "Session.find",
            tags: ["Session"],
            security: [["passport" => []]],
            parameters: [
                new OA\Parameter(
                    in: "path",
                    required: true,
                    description: "Session id",
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
                    description: "Internal server error",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
            ],
        ),
    ]
    function find($id)
    {
        $session = Session::find($id);
        return response()->json($session);
    }

    /**
     * Delete session by id
     */
    #[
        OA\Post(
            path: "/api/v1/sessions",
            summary: "Create new session",
            description: '__*Security:*__ __*can be used only by clients with \'admin\' role*__',
            operationId: "Session.create",
            tags: ["Session"],
            security: [["passport" => []]],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\MediaType(
                    mediaType: "application/json",
                    schema: new OA\Schema(ref: "#/components/schemas/SessionRequest"),
                ),
            ),
            responses: [
                new OA\Response(
                    response: 201,
                    description: "Operation successful",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
                new OA\Response(
                    response: 500,
                    description: "Internal server error",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
            ],
        ),
    ]
    #[
        OA\Delete(
            path: "/api/v1/sessions/{id}",
            summary: "Delete session by id",
            description: '__*Security:*__ __*can be used only by clients with \'admin\' role*__',
            operationId: "Session.delete",
            tags: ["Session"],
            security: [["passport" => []]],
            parameters: [
                new OA\Parameter(
                    in: "path",
                    required: true,
                    description: "Session id",
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
                    description: "Internal server error",
                    content: new OA\MediaType(mediaType: "application/json"),
                ),
            ],
        ),
    ]
    function delete($id)
    {
        $session = Session::findOrFail($id);
        $session->delete();
        return response()->json(null, 204);
    }
}
