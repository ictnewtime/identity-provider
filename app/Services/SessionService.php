<?php

namespace App\Services;

use App\Models\Provider;
use App\Models\Session;
use App\Models\User;
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
        $user_agent,
        $token,
        $refresh_token = null,
        Carbon $expires_at = null,
    ) {
        // Cerchiamo la sessione
        $session = Session::where("user_id", $user_id)->where("provider_id", $provider_id)->first();

        if ($session) {
            // Aggiorniamo se esiste (mantenendo lo stesso UUID)
            $session->update([
                "ip_address" => $ip_address,
                "user_agent" => $user_agent,
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
                "user_agent" => $user_agent,
                "token" => $token,
                "refresh_token" => $refresh_token,
                "expires_at" => $expires_at,
                "last_activity" => now(),
            ]);

            // INTEGRAZIONE LOG ESTERNO (GRAPHQL)
            $provider = Provider::find($provider_id);
            $user = User::find($user_id);
            LogExternal::logToLogService($user->username, "login", $ip_address, $provider->name);
        }

        return $session;
    }

    /**
     * Recupera o rigenera il token al login.
     */
    public function getValidProviderToken(
        $user,
        $provider_id,
        $ip_address,
        $user_agent,
        TokenProviderService $tokenService,
    ) {
        // Controllo centralizzato: Abilitazione + Ruoli per il provider specifico
        if (!$user->hasAccessToProvider($provider_id)) {
            Log::warning(
                "Accesso negato: Utente ID {$user->id} disabilitato o senza ruoli per Provider {$provider_id}.",
            );
            return null;
        }

        $existingSession = Session::where("user_id", $user->id)->where("provider_id", $provider_id)->first();

        if ($existingSession) {
            $isNotExpired = !$existingSession->expires_at || $existingSession->expires_at->isFuture();

            // Se l'IP e l'User-Agent è uguale e non è scaduto, restituiamo il token vecchio
            if (
                $existingSession->ip_address === $ip_address &&
                $existingSession->user_agent === $user_agent &&
                $isNotExpired
            ) {
                return $existingSession->token;
            }
        }

        // Creazione Nuova Sessione (se IP cambiato o token scaduto/inesistente)
        $token = $tokenService->tokenCretion($user, $provider_id);

        if (!$token) {
            return null;
        }

        $ttlInSeconds = $tokenService->getTtlInSeconds();
        $expiresAt = now()->addSeconds($ttlInSeconds);

        $this->upsertSession($user->id, $provider_id, $ip_address, $user_agent, $token, null, $expiresAt);

        return $token;
    }

    /**
     * Verifica la sessione per la chiamata middleware dell'extension.
     * Ritorna un array con status HTTP e l'eventuale nuovo token.
     */
    public function validateSession($providerId, $clientId, $user_agent, bool $isApi)
    {
        $session = Session::where("user_id", $clientId)->where("provider_id", $providerId)->first();

        // Se la sessione non esiste, ritorniamo 404
        if (!$session) {
            return ["status" => 404];
        }

        // Se la sessione è scaduta la eliminiamo e ritorniamo 404
        if ($session->expires_at && !$session->expires_at->isFuture()) {
            $session->delete();
            return ["status" => 404];
        }

        // Valida: se lo User Agent è lo stesso oppure facciamo un check con l'API
        // consideriamo la sessione valida
        if ($isApi || empty($user_agent) || $session->user_agent === $user_agent) {
            $session->last_activity = now();
            $session->save();
            return ["status" => 200, "token" => null];
        }

        // Se cambia lo USER AGENT, allora è un cambio dispositivo/browser e termino la sessione
        if ($session->user_agent !== $user_agent) {
            Log::info(
                "Rilevato cambio User Agent sul Provider ID {$providerId} con User ID {$clientId}. Termino la sessione.",
            );
            $session->delete();
            return ["status" => 404];
        }

        return ["status" => 404];
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
