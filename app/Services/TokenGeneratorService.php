<?php

namespace App\Services;

use App\Models\User;
use App\Models\Provider;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Services\ProviderUserRoleService;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTFactory;
use Tymon\JWTAuth\Providers\JWT\Lcobucci;

class TokenGeneratorService
{
    protected $providerUserRoleService;

    public function __construct()
    {
        $this->providerUserRoleService = new ProviderUserRoleService();
    }

    /**
     * Genera un token JWT.
     * Se viene passato un $redirectUrl (dominio), cerca il provider e applica la logica custom (Secret Key, Ruoli, TTL).
     * Altrimenti genera un token standard.
     * * @return string|null Ritorna il token stringa, o null se l'utente non è abilitato per quel provider.
     */
    public function generate(User $user, ?string $redirectId = null)
    {
        $ttlInMinutes = (int) env("JWT_TTL", 120);
        JWTAuth::factory()->setTTL($ttlInMinutes);
        $provider = Provider::where("id", $redirectId)->first();
        if (empty($provider)) {
            return JWTAuth::fromUser($user);
        }

        // dato un provider e un user, ottengo tutti i ruoli associati
        $tokenBody = $this->providerUserRoleService->getJwtTokenInfo($provider->id, $user->id);

        if (empty($tokenBody)) {
            return null;
        }

        $originalSecret = JWTAuth::getJWTProvider()->getSecret();
        $payload = is_object($tokenBody) ? (array) $tokenBody : $tokenBody;

        try {
            if (empty($provider->secret_key)) {
                // Errore, secret key empty
                Log::error("Provider " . $provider->id . " has empty secret key.");
                throw new \Exception("Provider misconfigured.");
            }

            // Definiamo i claims
            $payloadData = array_merge(
                [
                    "iss" => url("/"),
                    "iat" => time(),
                    "exp" => time() + $ttlInMinutes * 60,
                    "nbf" => time(),
                    "jti" => bin2hex(random_bytes(10)),
                    "sub" => $user->id,
                    "prv" => $provider->id,
                ],
                ["payload" => $payload],
            );

            /**
             * Creazione di istanze "usa e getta" per firmare il token,
             * con la secret key specifica del provider,
             * senza toccare la configurazione globale.
             */
            $algo = config("jwt.algo", "HS256");
            $keys = config("jwt.keys", []);

            // Creiamo il provider specifico al volo
            $customProvider = new Lcobucci($provider->secret_key, $algo, $keys);

            // Firmiamo il token usando ESCLUSIVAMENTE questo provider temporaneo
            $token = $customProvider->encode($payloadData);
        } catch (\Exception $e) {
            Log::error(
                "Error generating token for user " .
                    $user->id .
                    " and provider " .
                    $provider->id .
                    ": " .
                    $e->getMessage(),
            );
            throw $e;
        } finally {
            JWTAuth::getJWTProvider()->setSecret($originalSecret);
        }

        return $token;
    }
}
