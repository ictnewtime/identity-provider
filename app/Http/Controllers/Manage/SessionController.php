<?php

namespace App\Http\Controllers\Manage;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\SessionService;
use App\Services\TokenProviderService;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Models\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

class SessionController extends Controller
{
    protected $sessionService;
    protected $tokenService;

    public function __construct(SessionService $sessionService, TokenProviderService $tokenService)
    {
        $this->sessionService = $sessionService;
        $this->tokenService = $tokenService;
    }

    public function all(Request $request)
    {
        $query = Session::select(
            "id",
            "user_id",
            "provider_id",
            "ip_address",
            "user_agent",
            "created_at",
            "updated_at",
        )->with(["user:id,username", "provider:id,domain,name"]);

        if ($request->filled("q")) {
            $searchTerm = "%" . $request->q . "%";

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
     * Controlla lo stato di una sessione (Chiamata dall'IdP Extension M2M)
     */
    public function check(Request $request): JsonResponse
    {
        // 1. Recuperiamo i dati ultra-sicuri che il middleware ha estratto dal JWT
        $providerId = $request->attributes->get("jwt_provider_id");
        $userId = $request->attributes->get("jwt_user_id");

        // Se mancano, c'è un problema grave col middleware
        if (!$providerId || !$userId) {
            return response()->json(["valid" => false, "message" => "JWT Claims missing"], 401);
        }

        // 2. Validiamo solo i dati ambientali che arrivano dalla GET di App2
        $validated = $request->validate([
            "ip_address" => "nullable|ip",
            "user_agent" => "nullable|string",
        ]);

        $ip_address = $validated["ip_address"] ?? $request->ip();
        $user_agent = $validated["user_agent"] ?? $request->userAgent();

        // 3. Logica di Business
        $result = $this->sessionService->validateAndRefreshSession(
            $ip_address,
            $providerId,
            $userId,
            $user_agent,
            $this->tokenService,
        );

        if ($result["status"] === 404) {
            return response()->json(
                [
                    "valid" => false,
                    "message" => "Session expired, not found, or access revoked.",
                ],
                404,
            );
        }

        return response()->json(
            [
                "valid" => true,
                "token" => $result["token"] ?? null,
            ],
            200,
        );
    }

    /**
     * Chiamata API M2M da App esterne per innescare il Single Logout (SLO).
     */
    public function logout_session(Request $request): JsonResponse
    {
        $request->validate([
            "user_id" => "required|integer",
            "provider_id" => "required|integer",
        ]);

        $userId = $request->input("user_id");

        // 1. Recuperiamo tutte le sessioni prima di cancellarle
        $sessions = Session::where("user_id", $userId)->get();
        $deletedCount = $sessions->count();
        Log::info("Single Logout eseguito. Sessioni distrutte: " . $deletedCount);

        // 2. Le eliminiamo ciclando sui modelli
        // In questo modo, il Trait CustomAuditable intercetterà l'evento 'deleted' per ciascuna.
        foreach ($sessions as $session) {
            $session->delete();
        }
        return response()->json(
            [
                "success" => true,
                "message" => "Single Logout eseguito.",
            ],
            200,
        );
    }

    /**
     * Chiamata API CRUD dal Pannello Admin IdP.
     */
    public function delete($id)
    {
        Session::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
