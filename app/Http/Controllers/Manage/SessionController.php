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

        if ($request->filled("sort_by")) {
            $field = $request->sort_by;
            $direction = strtolower($request->sort_dir) === "desc" ? "desc" : "asc";
            $allowedSorts = ["id", "user.username", "provider.name", "ip_address", "user_agent", "updated_at"];

            if (in_array($field, $allowedSorts)) {
                if (str_starts_with($field, "provider.")) {
                    $sortColumn = str_replace("provider.", "providers.", $field);
                    $query
                        ->join("providers", "sessions.provider_id", "=", "providers.id")
                        ->select("sessions.*")
                        ->orderBy($sortColumn, $direction);
                } elseif (str_starts_with($field, "user.")) {
                    $sortColumn = str_replace("user.", "users.", $field);
                    $query
                        ->join("users", "sessions.user_id", "=", "users.id")
                        ->select("sessions.*")
                        ->orderBy($sortColumn, $direction);
                } else {
                    $query->orderBy("sessions." . $field, $direction);
                }
            }
        } else {
            $query->orderBy("updated_at", "desc");
        }

        $perPage = $request->input("per_page", 25);
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
            // Non passa da `notFound()`: questa risposta porta anche `valid`, che il chiamante
            // legge. L'helper darebbe il solo `message` e cambierebbe il contratto.
            return response()->json(["valid" => false, "message" => __("user.error.not_found")], 404);
        }

        $validated = $request->validate([
            "user_agent" => "nullable|string",
            "is_api" => "nullable|boolean",
        ]);

        $user_agent = $validated["user_agent"] ?? null;
        $isApi = $validated["is_api"] ?? false;

        $result = $this->sessionService->validateSession($providerId, $userId, $user_agent, $isApi);

        if ($result["status"] === 404) {
            return response()->json(
                [
                    "valid" => false,
                    "message" => "Session expired.",
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

    public function get_token(Request $request): JsonResponse
    {
        $userId = $request->attributes->get("jwt_user_id");

        if (!$userId) {
            return response()->json(["message" => "Master Token claims missing"], 401);
        }

        $validated = $request->validate([
            "provider_id" => "required|string",
            "ip_address" => "nullable|string",
            "user_agent" => "nullable|string",
        ]);

        $providerId = $validated["provider_id"];

        $user = User::find($userId);

        if (!$user) {
            return $this->notFound("user.error.not_found");
        }

        $tokenService = new TokenProviderService();
        $sessionService = $this->sessionService ?? new SessionService();

        $appToken = $sessionService->getValidProviderToken(
            $user,
            $providerId,
            $validated["ip_address"] ?? $request->ip(),
            $validated["user_agent"] ?? $request->userAgent(),
            $tokenService,
        );

        if (!$appToken) {
            return response()->json(
                [
                    "message" => __("session.error.access_denied.userdisabled_or_missing_roles", [
                        "providerId" => $providerId,
                    ]),
                ],
                403,
            );
        }

        return response()->json(
            [
                "token" => $appToken,
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
    public function delete(string $id)
    {
        $sessionById = Session::findOrFail($id);
        $this->sessionService->destroyAllUserSessions($sessionById->user_id);

        return response()->json(
            [
                "success" => true,
                "message" => "Delete all session by userId",
            ],
            200,
        );
    }
}
