<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

            $action = $originalAction;
            $isSoftDeletable = in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($model));

            $dirty = $model->getDirty();
            if (empty($dirty) && method_exists($model, "getChanges")) {
                $dirty = $model->getChanges();
            }
            $changedFields = array_keys($dirty);

            if ($isSoftDeletable) {
                if ($originalAction === "updated") {
                    if (in_array("deleted_at", $changedFields)) {
                        $action = is_null($model->deleted_at) ? "restored" : "deleted";
                    }
                } elseif ($originalAction === "deleted") {
                    if (method_exists($model, "isForceDeleting") && !$model->isForceDeleting()) {
                        return;
                    }
                } elseif ($originalAction === "restored") {
                    return;
                }
            }

            if ($action === "updated" && $model instanceof \App\Models\Session) {
                $ignoredFields = ["last_activity", "updated_at", "expires_at"];
                if (empty(array_diff($changedFields, $ignoredFields))) {
                    return;
                }
            }

            $userId = Auth::id();
            $userType = $userId ? get_class(Auth::user()) : null;

            $clientId = request()->attributes->get("provider_id") ?? request()->attributes->get("oauth_client_id");

            if (!$clientId && request()->bearerToken()) {
                $token = request()->bearerToken();
                $parts = explode(".", $token);
                if (count($parts) === 3) {
                    $payload = json_decode(base64_decode(strtr($parts[1], "-_", "+/")), true);
                    $aud = $payload["aud"] ?? null;
                    $clientId = is_array($aud) ? $aud[0] : $aud;
                }
            }

            if ($clientId && !$userId) {
                $userId = $clientId;
                $userType = \Laravel\Passport\Client::class;
            }

            $oldValues = $originalAction !== "created" ? json_encode($model->getOriginal()) : json_encode([]);
            $newValues = $originalAction !== "deleted" ? json_encode($dirty) : json_encode([]);

            DB::table("audits")->insert([
                "user_type" => $userType,
                "user_id" => $userId,
                "event" => $action,
                "auditable_type" => get_class($model),
                "auditable_id" => $model->id,
                "old_values" => $oldValues,
                "new_values" => $newValues,
                "url" => request()->fullUrl(),
                "ip_address" => request()->ip(),
                "user_agent" => request()->userAgent(),
                "tags" => null,
                "created_at" => now(),
                "updated_at" => now(),
            ]);
        } catch (\Exception $e) {
            Log::error("CRASH AUDIT (ignorato): " . $e->getMessage());
        }
    }
}
