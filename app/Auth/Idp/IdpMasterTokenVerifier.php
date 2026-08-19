<?php

namespace App\Auth\Idp;

use Exception;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Verifica del master token (RS256, chiave pubblica dell'IdP).
 *
 * Sta qui perche' due strade lo verificano: `VerifyMasterToken` sull'exchange e `IdpTokenRenewer`
 * sul rinnovo trasparente. Una sola implementazione significa che un domani la rotazione della
 * chiave si corregge in un punto solo.
 */
class IdpMasterTokenVerifier
{
    /**
     * L'utente a cui il master token appartiene, oppure `null` se il token manca, e' scaduto,
     * e' firmato male o non porta il claim `sub`.
     *
     * Il **motivo preciso** finisce nel log: chi chiama sa solo se puo' proseguire, e le quattro
     * cause non cambiano cio' che deve fare.
     */
    public function userIdFrom(?string $token): ?string
    {
        if (empty($token)) {
            Log::warning("Master Token mancante.");

            return null;
        }

        try {
            $publicKeyPath = storage_path("app/keys/public.key");

            if (!File::exists($publicKeyPath)) {
                throw new Exception("File della chiave pubblica non trovato sul server.");
            }

            $payload = JWT::decode($token, new Key(File::get($publicKeyPath), "RS256"));
        } catch (ExpiredException $e) {
            Log::warning("Master Token scaduto: " . $e->getMessage());

            return null;
        } catch (Exception $e) {
            Log::warning("Verifica Master Token fallita: " . $e->getMessage());

            return null;
        }

        $userId = $payload->sub ?? null;

        if (!$userId) {
            Log::error("Master Token corrotto (claim 'sub' mancante).");

            return null;
        }

        return (string) $userId;
    }
}
