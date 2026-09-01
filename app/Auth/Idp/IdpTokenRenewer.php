<?php

namespace App\Auth\Idp;

use App\Models\Provider;
use App\Models\User;
use App\Services\SessionService;
use App\Services\TokenProviderService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Il rinnovo dell'app token dell'IdP a partire dal master token (punto TMT08, difetto VDF13).
 *
 * IL DIFETTO CHE CHIUDE: l'app token vale trenta minuti, il master token otto ore, e l'IdP —
 * che il master token ce l'ha nel proprio cookie — era **l'unico** a non usarlo per rinnovare.
 * I client esterni lo fanno da sempre, chiamando `token/exchange`: qui si fa la stessa cosa senza
 * uscire dal processo.
 *
 * QUEL CHE NON FA, ED E' LA PARTE DELICATA: **non ricrea una sessione che non c'e'**. Se
 * l'amministratore ha revocato la sessione, il rinnovo deve rifiutare — sennò la sessione si
 * ricrea da sola alla richiesta successiva e la revoca non revoca niente (difetto `VDF14`). Il
 * controllo e' qui e non in `getValidProviderToken()`, che ha altri chiamanti: renderlo generale
 * e' il punto `TMT11`.
 */
class IdpTokenRenewer
{
    public const OUTCOME_RENEWED = "renewed";
    public const OUTCOME_MASTER_MISSING = "master-missing";
    public const OUTCOME_REFUSED = "refused";

    /**
     * Prova a rinnovare. Restituisce l'esito e, se e' andata, il token nuovo.
     *
     * @return array{outcome: string, token: string|null, user: User|null}
     */
    public function renew(Request $request, Provider $provider): array
    {
        $masterToken = $request->cookie(config("idp.jwt.master_token_name"));

        if (empty($masterToken)) {
            Log::info("[RINNOVO] nessun master token nel cookie: la sessione e' finita davvero.");
            return $this->outcome(self::OUTCOME_MASTER_MISSING);
        }

        $userId = $this->userIdFromMasterToken($masterToken);

        if ($userId === null) {
            return $this->outcome(self::OUTCOME_MASTER_MISSING);
        }

        $user = User::find($userId);

        if (!$user) {
            Log::warning("[RINNOVO] il master token nomina un utente che non esiste piu'.", ["user_id" => $userId]);
            return $this->outcome(self::OUTCOME_REFUSED);
        }

        // `canCreate: false` — il rinnovo rinnova, non crea: se l'amministratore ha revocato la
        // sessione, la revoca deve valere (VDF14). La guardia sta nel service (punto TMT11) e **non
        // anche qui**: due difese che coprono lo stesso caso non sono piu' sicure, sono una difesa e
        // una bugia sul fatto di averla provata — lo dice la voce `VDF14`, dove era gia' successo.
        $tokenService = new TokenProviderService();
        $newToken = (new SessionService())->getValidProviderToken(
            $user,
            $provider->id,
            $request->ip(),
            $request->userAgent(),
            $tokenService,
            $masterToken,
            false,
        );

        if (!$newToken) {
            Log::warning("[RINNOVO] rifiutato: sessione revocata, oppure l'utente non ha piu' accesso al provider.", [
                "user_id" => $user->id,
                "provider_id" => $provider->id,
            ]);
            return $this->outcome(self::OUTCOME_REFUSED);
        }

        Log::info("[RINNOVO] riuscito", [
            "user_id" => $user->id,
            "provider_id" => $provider->id,
            "app_token" => SessionService::tokenFingerprint($newToken),
            "master_token" => SessionService::tokenFingerprint($masterToken),
        ]);

        return ["outcome" => self::OUTCOME_RENEWED, "token" => $newToken, "user" => $user];
    }

    /** Il `sub` del master token, se la firma RS256 regge e non e' scaduto. */
    private function userIdFromMasterToken(string $masterToken): ?int
    {
        try {
            $publicKeyPath = storage_path("app/keys/public.key");

            if (!File::exists($publicKeyPath)) {
                Log::error("[RINNOVO] chiave pubblica assente: non si puo' verificare il master token.");
                return null;
            }

            $payload = JWT::decode($masterToken, new Key(File::get($publicKeyPath), "RS256"));
            $userId = $payload->sub ?? null;

            if (!$userId) {
                Log::warning("[RINNOVO] master token senza claim `sub`.");
                return null;
            }

            return (int) $userId;
        } catch (\Throwable $e) {
            // Scaduto, manomesso o illeggibile: per il rinnovo sono lo stesso caso.
            Log::info("[RINNOVO] master token non valido: " . $e->getMessage());
            return null;
        }
    }

    private function outcome(string $outcome): array
    {
        return ["outcome" => $outcome, "token" => null, "user" => null];
    }
}
