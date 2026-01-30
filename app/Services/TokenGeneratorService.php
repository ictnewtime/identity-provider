<?php

namespace App\Services;

use App\Models\User;
use App\Models\Provider;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Services\ProviderUserRoleService;

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
    public function generate(User $user, ?string $redirectUrl = null)
    {
        $ttlInMinutes = (int) env("JWT_TTL", 120);
        JWTAuth::factory()->setTTL($ttlInMinutes);
        $provider = Provider::where("domain", $redirectUrl)->first();
        if (empty($provider)) {
            return JWTAuth::fromUser($user);
        }

        // dato un provider e un user, ottengo tutti i ruoli associati
        $tokenBody = $this->providerUserRoleService->getJwtTokenInfo($provider->id, $user->id);

        if (empty($tokenBody)) {
            return null;
        }

        $originalSecret = JWTAuth::getJWTProvider()->getSecret();

        try {
            if (!empty($provider->secret_key)) {
                JWTAuth::getJWTProvider()->setSecret($provider->secret_key);
            }

            $payload = is_object($tokenBody) ? (array) $tokenBody : $tokenBody;

            $token = JWTAuth::claims(["payload" => $payload])->fromUser($user);
        } finally {
            JWTAuth::getJWTProvider()->setSecret($originalSecret);
        }

        return $token;
    }
}
