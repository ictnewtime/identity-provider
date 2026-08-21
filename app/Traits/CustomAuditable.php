<?php

namespace App\Traits;

use App\Models\Session;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Client;

trait CustomAuditable
{
    public static function bootCustomAuditable()
    {
        static::created(function ($model) {
            self::logAudit($model, "created");
        });

        static::updated(function ($model) {
            self::logAudit($model, "updated");
        });

        static::deleted(function ($model) {
            self::logAudit($model, "deleted");
        });
    }

    protected static function logAudit($model, $originalAction)
    {
        try {
            if (app()->runningInConsole()) {
                return;
            }

            $dirty = self::dirtyAttributes($model);
            $changedFields = array_keys($dirty);
            $action = self::resolveAction($model, $originalAction, $changedFields);

            if (self::isSessionNoise($model, $action, $changedFields)) {
                return;
            }

            $actor = self::resolveActor();

            DB::table("audits")->insert(self::auditRow($model, $action, $originalAction, $dirty, $actor));
        } catch (\Exception $e) {
            Log::error("CRASH AUDIT (ignorato): " . $e->getMessage());
        }
    }

    private static function dirtyAttributes($model): array
    {
        $dirty = $model->getDirty();

        if (empty($dirty) && method_exists($model, "getChanges")) {
            $dirty = $model->getChanges();
        }

        return $dirty;
    }

    private static function resolveAction($model, string $originalAction, array $changedFields): string
    {
        if (!in_array(SoftDeletes::class, class_uses_recursive($model))) {
            return $originalAction;
        }

        if ($originalAction === "updated") {
            if (!in_array("deleted_at", $changedFields)) {
                return $originalAction;
            }

            return is_null($model->deleted_at) ? "restored" : "deleted";
        }

        if ($originalAction === "deleted") {
            return method_exists($model, "isForceDeleting") && $model->isForceDeleting() ? "force_deleted" : "deleted";
        }

        return $originalAction === "restored" ? "restored" : $originalAction;
    }

    private static function isSessionNoise($model, string $action, array $changedFields): bool
    {
        if ($action !== "updated" || !$model instanceof Session) {
            return false;
        }

        return empty(array_diff($changedFields, ["last_activity", "updated_at", "expires_at"]));
    }

    /**
     * Chi ha fatto la modifica: un utente autenticato, oppure un client Passport.
     *
     * L'ordine di ricerca del client non e' arbitrario — attributo `provider_id`, poi
     * `oauth_client_id`, e in ultimo il claim `aud` letto **a mano** dal bearer token, che e' la sola
     * strada quando la richiesta non e' passata da un middleware che li imposta.
     *
     * @return array{userId: mixed, userType: ?string, providerId: mixed}
     */
    private static function resolveActor(): array
    {
        $userId = Auth::id();
        $userType = $userId ? get_class(Auth::user()) : null;

        $providerId = request()->attributes->get("provider_id");
        $clientId = $providerId ?? request()->attributes->get("oauth_client_id");

        if ($userId && !$providerId) {
            $providerId = config("idp.provider_id");
        }

        if (!$clientId && request()->bearerToken()) {
            $clientId = self::clientIdFromBearerToken(request()->bearerToken());
        }

        // Un client senza utente e' l'attore: `user_id` porta l'id del client, e `user_type` lo dice.
        if ($clientId && !$userId) {
            $userId = $clientId;
            $userType = Client::class;
        }

        if (!$userId) {
            $jwtUserId = request()->attributes->get("jwt_user_id");

            if ($jwtUserId) {
                $userId = $jwtUserId;
                $userType = User::class;
            }
        }

        return ["userId" => $userId, "userType" => $userType, "providerId" => $providerId];
    }

    /** Il claim `aud` del bearer token, senza verificarne la firma: serve a dire *chi*, non ad autorizzare. */
    private static function clientIdFromBearerToken(string $token)
    {
        $parts = explode(".", $token);

        if (count($parts) !== 3) {
            return null;
        }

        $payload = json_decode(base64_decode(strtr($parts[1], "-_", "+/")), true);
        $aud = $payload["aud"] ?? null;

        return is_array($aud) ? $aud[0] : $aud;
    }

    /**
     * L'indirizzo di chi ha fatto la modifica.
     *
     * Per un utente si preferisce quello registrato nella sua sessione: la richiesta puo' arrivare
     * da un proxy, la sessione porta l'indirizzo con cui si e' autenticato. Per un client Passport
     * non si cerca nessuna sessione — non ne ha.
     */
    private static function resolveIpAddress(array $actor): ?string
    {
        if ($actor["userType"] === Client::class) {
            return request()->ip();
        }

        $session = Session::where("user_id", $actor["userId"])->where("provider_id", $actor["providerId"])->first();

        return $session->ip_address ?? request()->ip();
    }

    /** La riga da inserire. Qui non si decide niente: si mette in fila cio' che gli altri hanno deciso. */
    private static function auditRow($model, string $action, string $originalAction, array $dirty, array $actor): array
    {
        return [
            "user_type" => $actor["userType"],
            "user_id" => $actor["userId"],
            "event" => $action,
            "auditable_type" => get_class($model),
            "auditable_id" => $model->id,
            "old_values" => $originalAction !== "created" ? json_encode($model->getOriginal()) : json_encode([]),
            "new_values" => $originalAction !== "deleted" ? json_encode($dirty) : json_encode([]),
            "url" => request()->fullUrl(),
            "ip_address" => self::resolveIpAddress($actor),
            "user_agent" => request()->userAgent(),
            "tags" => null,
            "created_at" => now(),
            "updated_at" => now(),
        ];
    }
}
