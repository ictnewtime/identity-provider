<?php

namespace App\Services;

use App\Models\Session;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SessionService
{
    /**
     * Gestisce l'upsert della sessione in modo sicuro (senza sovrascrivere l'UUID).
     */
    public function upsertSession(
        $user_id,
        $provider_id,
        $ip_address,
        $token,
        $refresh_token = null,
        Carbon $expires_at = null,
    ) {
        // Cerchiamo la sessione
        Log::debug("SessionService.upsertSession user_id: " . $user_id);
        $session = Session::where("user_id", $user_id)->where("provider_id", $provider_id)->first();

        Log::debug("SessionService.upsertSession session: " . ($session ? "Yes" : "No"));
        if ($session) {
            // Aggiorniamo se esiste (mantenendo lo stesso UUID)
            $session->update([
                "ip_address" => $ip_address,
                "token" => $token,
                "refresh_token" => $refresh_token,
                "expires_at" => $expires_at,
                "last_activity" => now(),
            ]);
        } else {
            // Creiamo se non esiste (generando l'UUID)
            $session = Session::create([
                "id" => (string) Str::uuid(),
                "user_id" => $user_id,
                "provider_id" => $provider_id,
                "ip_address" => $ip_address,
                "token" => $token,
                "refresh_token" => $refresh_token,
                "expires_at" => $expires_at,
                "last_activity" => now(),
            ]);
        }

        return $session;
    }

    /**
     * Recupera o rigenera il token al login.
     */
    public function getValidProviderToken($user, $provider_id, $ip_address, TokenProviderService $tokenService)
    {
        // 1. & 2. Controllo centralizzato: Abilitazione + Ruoli per il provider specifico
        if (!$user->hasAccessToProvider($provider_id)) {
            Log::warning(
                "Accesso negato: Utente ID {$user->id} disabilitato o senza ruoli per Provider {$provider_id}.",
            );
            return null;
        }

        // 3. Gestione Sessione Esistente
        $existingSession = Session::where("user_id", $user->id)->where("provider_id", $provider_id)->first();

        if ($existingSession) {
            $isNotExpired = !$existingSession->expires_at || $existingSession->expires_at->isFuture();

            // Se l'IP è uguale e non è scaduto, restituiamo il token vecchio
            if ($existingSession->ip_address === $ip_address && $isNotExpired) {
                return $existingSession->token;
            }
        }

        // 4. Creazione Nuova Sessione (se IP cambiato o token scaduto/inesistente)
        $token = $tokenService->tokenCretion($user, $provider_id);

        if (!$token) {
            return null;
        }

        $ttlInSeconds = $tokenService->getTtlInSeconds();
        $expiresAt = now()->addSeconds($ttlInSeconds);

        $this->upsertSession($user->id, $provider_id, $ip_address, $token, null, $expiresAt);

        return $token;
    }

    /**
     * NUOVO: Verifica la sessione per la chiamata middleware dell'extension.
     * Ritorna un array con status HTTP e l'eventuale nuovo token.
     */
    public function validateAndRefreshSession($clientIp, $providerId, $clientId, TokenProviderService $tokenService)
    {
        $session = Session::where("user_id", $clientId)->where("provider_id", $providerId)->first();
        Log::debug("SessionService.validateAndRefreshSession session: " . $session ? "Yes" : "No");
        Log::debug("SessionService.validateAndRefreshSession clientIp: " . $clientIp);
        // 1. Sessione non trovata
        if (!$session) {
            return ["status" => 404];
        }

        // 2. Sessione scaduta
        if ($session->expires_at && !$session->expires_at->isFuture()) {
            $session->delete(); // Pulizia DB
            return ["status" => 404];
        }

        // 3. Valida e IP coincidente: tutto ok
        if ($session->ip_address === $clientIp) {
            // Aggiorniamo solo il last_activity
            $session->update(["last_activity" => now()]);
            return ["status" => 200, "token" => null];
        }

        // 4. Valida ma IP CAMBIATO: rigeneriamo il token
        $user = $session->user; // Assicurati di avere la relation belongsTo 'user' nel Model Session

        $newToken = $tokenService->tokenCretion($user, $providerId);

        if (!$newToken) {
            return ["status" => 404]; // Se fallisce la creazione per qualche motivo
        }

        $ttlInSeconds = $tokenService->getTtlInSeconds();

        $session->update([
            "ip_address" => $clientIp,
            "token" => $newToken,
            "expires_at" => now()->addSeconds($ttlInSeconds),
            "last_activity" => now(),
        ]);

        return ["status" => 200, "token" => $newToken];
    }

    public function destroySession($userId, $providerId): bool
    {
        $session = Session::where("user_id", $userId)->where("provider_id", $providerId)->first();

        if ($session) {
            $session->delete();
            return true;
        }

        return false;
    }
}
