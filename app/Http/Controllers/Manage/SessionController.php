<?php

namespace App\Http\Controllers\Manage;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\SessionService;
use App\Services\TokenProviderService;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Models\Session;
use App\Models\User;
use Illuminate\Http\JsonResponse as HttpJsonResponse;

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
    public function check(Request $request): HttpJsonResponse
    {
        $providerId = $request->attributes->get("jwt_provider_id");
        $userId = $request->attributes->get("jwt_user_id");

        if (!$providerId || !$userId) {
            return response()->json(["valid" => false, "message" => "JWT Claims missing"], 401);
        }

        $user = User::find($userId);

        if (!$user) {
            return response()->json(["valid" => false, "message" => "User not found"], 404);
        }

        // Se la password è scaduta o deve essere forzata, terminiamo la sessione esterna
        if (is_null($user->password_expires_at) || now()->greaterThanOrEqualTo($user->password_expires_at)) {
            return response()->json(
                [
                    "valid" => false,
                    "message" => "Password expired. User must authenticate and change password.",
                ],
                401,
            );
        }

        $validated = $request->validate([
            "ip_address" => "nullable|ip",
            "user_agent" => "nullable|string",
        ]);

        $ip_address = $validated["ip_address"];
        $user_agent = $validated["user_agent"];

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

        $sessions = Session::where("user_id", $userId)->get();
        $deletedCount = $sessions->count();

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
