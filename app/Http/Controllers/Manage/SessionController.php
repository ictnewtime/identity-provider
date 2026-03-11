<?php

namespace App\Http\Controllers\Manage;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\SessionRequest;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use App\Models\Session;
use App\Models\User;
use App\Services\SessionService;
use App\Services\TokenProviderService;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;

class SessionController extends Controller
{
    protected $sessionService;
    protected $tokenService;

    public function __construct(SessionService $sessionService, TokenProviderService $tokenService)
    {
        $this->sessionService = $sessionService;
        $this->tokenService = $tokenService;
    }

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
        // 1. Selezioniamo solo i campi necessari della Sessione (id, e le due chiavi esterne per i collegamenti)
        $query = Session::select(
            "id",
            "user_id",
            "provider_id",
            "ip_address",
            "user_agent",
            "created_at",
            "updated_at",
        )->with([
            // 2. Limitiamo le colonne delle relazioni (l'ID serve sempre per il legame)
            "user:id,username",
            "provider:id,domain,name", // Se hai anche il nome metti: id,domain,name
        ]);

        // Ricerca per user.username o provider.domain
        if ($request->filled("q")) {
            $searchTerm = "%" . $request->q . "%";

            // 3. Racchiudiamo la ricerca in una funzione per raggruppare le condizioni OR
            // SQL risultante: WHERE (user.username LIKE ? OR provider.domain LIKE ?)
            $query->where(function ($q) use ($searchTerm) {
                $q->whereHas("user", function ($subQuery) use ($searchTerm) {
                    $subQuery->where("username", "like", $searchTerm);
                })->orWhereHas("provider", function ($subQuery) use ($searchTerm) {
                    $subQuery->where("domain", "like", $searchTerm);
                });
            });
        }

        $perPage = $request->input("per_page", 10);
        return $query->paginate($perPage);
    }

    /**
     * Controlla lo stato di una sessione (Chiamata dall'IdP Extension)
     */
    #[
        OA\Get(
            path: "/api/v1/sessions/{id}",
            summary: "Returns session by id",
            description: "Returns session details by id",
            operationId: "Session.check",
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
    public function check(Request $request): JsonResponse
    {
        $ip_address = $request->query("ip_address", $request->ip());
        $provider_id = $request->query("provider_id");
        $user_id = $request->query("user_id");
        $user_agent = $request->query("user_agent");

        // 1. Delegato TUTTO al SessionService.
        // Se l'utente è stato eliminato, disabilitato o gli hanno tolto il ruolo,
        // la sessione non esisterà più nel DB (grazie agli Eventi) e ci tornerà 404.
        $result = $this->sessionService->validateAndRefreshSession(
            $ip_address,
            $provider_id,
            $user_id,
            $user_agent,
            $this->tokenService,
        );

        // 2. Se non c'è sessione, sbarriamo la porta
        if ($result["status"] === 404) {
            return response()->json(
                [
                    "valid" => false,
                    "message" => "Session expired, not found, or access revoked.",
                ],
                404, // Il middleware sull'app client intercetterà il 404 e forzerà il logout!
            );
        }

        // 3. Via libera
        return response()->json(
            [
                "valid" => true,
                "token" => $result["token"], // Null se l'IP non cambia, JWT nuovo se cambia
            ],
            200,
        );
    }

    public function logout(Request $request): JsonResponse
    {
        Log::info("=== API /sessions/logout CHIAMATA ===");
        Log::info("Dati ricevuti dal client: ", $request->all());

        // Validazione base di sicurezza
        $request->validate([
            "user_id" => "required|integer",
            "provider_id" => "required|integer",
        ]);

        $userId = $request->input("user_id");
        $providerId = $request->input("provider_id");

        // Chiamiamo il service per eliminare la riga dal DB
        $deleted = $this->sessionService->destroySession($userId, $providerId);

        Log::info("Risultato destroySession: " . ($deleted ? "CANCELLATA" : "NON TROVATA"));

        if (!$deleted) {
            Log::warning("Restituisco 404: Sessione già cancellata o inesistente.");
            return response()->json(
                [
                    "success" => false,
                    "message" => "Session not found or already deleted.",
                ],
                404,
            );
        }

        Log::info("Restituisco 200: Sessione distrutta con successo.");
        return response()->json(
            [
                "success" => true,
                "message" => "Session successfully destroyed.",
            ],
            200,
        );
    }

    /**
     * Delete session by id
     */
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
