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

    protected static function logAudit($model, $action)
    {
        try {
            if (app()->runningInConsole()) {
                return;
            }
            // CONTROLLO: IGNORA GLI UPDATE INUTILI DELLE SESSIONI
            if ($action === "updated" && $model instanceof \App\Models\Session) {
                // Prendiamo i nomi di tutti i campi che stanno per essere salvati
                $changedFields = array_keys($model->getDirty());

                // Definiamo quali campi consideriamo "spazzatura" per l'audit
                $ignoredFields = ["last_activity", "updated_at", "expires_at"];

                // Rimuoviamo i campi ignorati da quelli cambiati.
                // Se l'array rimane vuoto, significa che è cambiato SOLO il battito cardiaco!
                if (empty(array_diff($changedFields, $ignoredFields))) {
                    return; // Usciamo senza loggare nulla
                }
            }

            // 1. Setup di base per l'Umano (Web)
            $userId = Auth::id();
            $userType = $userId ? get_class(Auth::user()) : null;

            // 2. Estrazione Macchina (Passport API)
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

            // 3. SE È UN CLIENT M2M, sovrascriviamo l'attore!
            if ($clientId && !$userId) {
                $userId = $clientId;
                $userType = \Laravel\Passport\Client::class;
            }

            // 4. Formattazione payload (usiamo '[]' come nell'esempio del tuo DB se è vuoto)
            $oldValues = $action !== "created" ? json_encode($model->getOriginal()) : json_encode([]);
            $newValues = $action !== "deleted" ? json_encode($model->getDirty()) : json_encode([]);

            // 5. Inserimento a DB con i nomi corretti
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
            // Se fallisce, logga ma non blocca l'app
            Log::error("CRASH AUDIT (ignorato): " . $e->getMessage());
        }
    }
}
