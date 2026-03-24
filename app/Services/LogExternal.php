<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LogExternal
{
    /**
     * Invia il log al servizio esterno.
     * Usiamo 'static' per poterlo richiamare facilmente dal Trait senza istanziarlo.
     */
    public static function logToLogService($username, $tipo, $ip, $appName, $crm = null)
    {
        $url = env("LOG_SERVICE_URL");
        $token = env("LOG_SERVICE_TOKEN");

        if (!$url || !$token) {
            Log::warning("LogExternal: LOG_SERVICE_URL o TOKEN non configurati.");
            return;
        }

        $mutation = [
            "query" => 'mutation saveLogin($title: String, $tipo: String, $ip: String, $crm: String, $app: String) {
                save_loginHistory_loginHistory_Entry(
                    title: $title
                    tipo: $tipo
                    ip: $ip
                    app: $app
                    crm: $crm
                    authorId: "1"
                ) {
                    title
                    tipo
                    dateCreated @formatDateTime(format: "Y-m-d H:i")
                }
            }',
            "variables" => [
                "title" => (string) $username,
                "tipo" => (string) $tipo,
                "ip" => (string) $ip,
                "app" => (string) $appName,
                "crm" => (string) $crm,
            ],
        ];

        try {
            // Bearer token must be added to header
            $response = Http::timeout(2)
                ->withHeaders([
                    "Authorization" => $token,
                ])
                ->post($url, $mutation);

            if ($response->failed()) {
                Log::error("LogExternal API Error: " . $response->body());
            }
        } catch (\Exception $e) {
            // Se c'è un timeout o il server non esiste, logga in locale ma non blocca l'app
            Log::error("LogExternal eccezione di rete: " . $e->getMessage());
        }
    }
}
