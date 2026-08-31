<?php

namespace App\Http\Controllers\Manage;

use App\Models\Provider;
use Illuminate\Support\Facades\DB;
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
            return response()->json(["valid" => false, "message" => __("session.error.jwt_claims_missing")], 401);
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
                    "message" => __("session.error.expired"),
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
            return response()->json(["message" => __("session.error.master_token_claims_missing")], 401);
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

        // Un provider che non esiste e' un identificativo sbagliato
        // nella richiesta — 404 — mentre 403 vuol dire «esiste, e tu non puoi».
        if (!Provider::find($providerId)) {
            Log::warning("[EXCHANGE] provider inesistente", ["provider_id" => $providerId, "user_id" => $userId]);

            return $this->notFound("session.error.provider_not_found");
        }

        $tokenService = new TokenProviderService();
        $sessionService = $this->sessionService ?? new SessionService();

        // Il master token che ha autorizzato questa richiesta finisce nella riga (TMT02): e' cio' che
        // la riga rappresenta, ed e' quello che permettera' il rinnovo.
        $masterToken = $request->bearerToken() ?: $request->header("x-master-token");

        Log::debug("[EXCHANGE] richiesta", [
            "user_id" => $userId,
            "provider_id" => $providerId,
            "master_token" => SessionService::tokenFingerprint($masterToken),
            "ip_address" => $validated["ip_address"] ?? $request->ip(),
        ]);

        // la rotazione, e **solo sulla v2**. Se il master token presentato ha piu' di un'ora se
        // ne genera uno nuovo, e da qui in poi e' quello che vale: lo salva la riga e lo riceve il
        // chiamante. Il vecchio **non viene invalidato** — chi non sa leggere l'header nuovo continua
        // a funzionare fino alla sua scadenza, che e' l'unico modo di non disconnettere in massa al
        // primo rilascio. La v1 non ruota: la sua riga scade alle otto ore e viene cancellata.
        $masterToken = $this->rotateMasterTokenIfNeeded($request, $user, $masterToken, $tokenService);

        $appToken = $sessionService->getValidProviderToken(
            $user,
            $providerId,
            $validated["ip_address"] ?? $request->ip(),
            $validated["user_agent"] ?? $request->userAgent(),
            $tokenService,
            $masterToken,
            false,
        );

        if (!$appToken) {
            return response()->json(
                [
                    "message" => __("session.error.access_denied.user_disabled_or_missing_roles", [
                        "providerId" => $providerId,
                    ]),
                ],
                403,
            );
        }

        if ($this->isV2($request)) {
            // sulla v2 la traccia di quali applicazioni sono state aperte non sta in una riga
            // per provider — sta qui.
            $this->auditAppToken($request, $userId, $providerId, $appToken);

            // la v2 risponde con TUTTI E DUE i token, ma solo in header (x-master-token, x-app-token).
            return response()
                ->json([], 200)
                ->withHeaders([
                    "x-master-token" => $masterToken,
                    "x-app-token" => $appToken,
                ]);
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
                "message" => __("session.success.single_logout"),
            ],
            200,
        );
    }

    /**
     * Chiamata API CRUD dal Pannello Admin IdP.
     */
    /**
     * Il master token da usare da qui in avanti: quello presentato, oppure uno nuovo se e' vecchio.
     *
     * Ruota **solo sulla v2**: la v1 ha client che non sanno leggere un token nuovo e continuerebbero
     * a mandare il vecchio senza accorgersi di niente. L'eta' si legge dal claim `iat`, che
     * `VerifyMasterToken` ha gia' verificato e messo fra gli attributi della richiesta.
     */
    private function rotateMasterTokenIfNeeded(
        Request $request,
        User $user,
        ?string $masterToken,
        TokenProviderService $tokenService,
    ): ?string {
        if (!$this->isV2($request) || empty($masterToken)) {
            return $masterToken;
        }

        $iat = $request->attributes->get("jwt_master_iat");

        if (!$iat) {
            Log::warning("[EXCHANGE] master token senza `iat`: non si puo' sapere se ruotarlo.");
            return $masterToken;
        }

        $eta = time() - (int) $iat;

        // La soglia sta nei parametri, con ripiego a un'ora: e' un numero che si vorra' cambiare
        // senza un rilascio, come le due durate dei token.
        $tokenTheshold = $tokenService->getMasterTokenRotateAfter();

        if ($eta < $tokenTheshold) {
            return $masterToken;
        }

        $newMasterToekn = $tokenService->generateMasterToken($user, (string) config("idp.provider_id"));

        if (!$newMasterToekn) {
            // Meglio continuare col vecchio, che e' ancora valido, che rifiutare l'accesso.
            Log::error("[EXCHANGE] rotazione fallita: si prosegue col master token presentato.");
            return $masterToken;
        }

        Log::info("[EXCHANGE] master token ruotato", [
            "user_id" => $user->id,
            "eta_secondi" => $eta,
            "soglia_secondi" => $tokenTheshold,
            "vecchio" => SessionService::tokenFingerprint($masterToken),
            "newMasterToekn" => SessionService::tokenFingerprint($newMasterToekn),
        ]);

        return $newMasterToekn;
    }

    /** La richiesta arriva dalla rotta `v2`? Le due rotte puntano allo stesso metodo (TMT05). */
    private function isV2(Request $request): bool
    {
        return $request->is("api/v2/*");
    }

    /**
     * Una riga di `audits` per l'app token appena staccato (punto TMT18).
     *
     * `AppToken` non e' un modello e non lo diventa: qui `auditable_id` e' una **stringa**
     * (`create_audits_table.php:25`), quindi un'entita' senza tabella ci sta. Serve a rispondere alla
     * domanda «a quali applicazioni e' entrato questo utente, e quando», che sulla v2 la tabella delle
     * sessioni non sapra' piu' dire.
     */
    private function auditAppToken(Request $request, $userId, $providerId, string $appToken): void
    {
        try {
            DB::table("audits")->insert([
                "user_type" => User::class,
                "user_id" => $userId,
                "event" => "created",
                "auditable_type" => "AppToken",
                "auditable_id" => (string) $providerId,
                "old_values" => json_encode([]),
                "new_values" => json_encode([
                    "provider_id" => (string) $providerId,
                    "token" => $appToken,
                    "created_at" => now()->toDateTimeString(),
                ]),
                "url" => $request->fullUrl(),
                "ip_address" => $request->ip(),
                "user_agent" => $request->userAgent(),
                "tags" => null,
                "created_at" => now(),
                "updated_at" => now(),
            ]);
        } catch (\Throwable $e) {
            // L'audit non deve mai impedire l'accesso: si perde la riga, non la sessione.
            Log::error("[EXCHANGE] audit dell'app token non scritto: " . $e->getMessage());
        }
    }

    public function delete(string $id)
    {
        $sessionById = Session::findOrFail($id);
        $this->sessionService->destroyAllUserSessions($sessionById->user_id);

        return response()->json(
            [
                "success" => true,
                "message" => __("session.success.all_destroyed"),
            ],
            200,
        );
    }
}
