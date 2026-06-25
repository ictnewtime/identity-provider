<?php

namespace App\Services;

use App\Models\Parameter;
use App\Models\User;
use App\Models\Provider;
use App\Services\ProviderUserRoleService;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Providers\JWT\Lcobucci;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\File;

class TokenProviderService
{
    protected $providerUserRoleService;
    protected $expirationTimeInSeconds;
    protected $appTokenFallbackSeconds;
    protected $masterTokenFallbackSeconds;

    public function __construct()
    {
        $this->providerUserRoleService = new ProviderUserRoleService();
        $this->expirationTimeInSeconds = (int) env("JWT_TTL", 24 * 60 * 60); // default 24 ore
        // Fallback SEPARATI: se i Parameter a DB mancano, il master DEVE comunque
        // durare piu' dell'app token, altrimenti il refresh silenzioso e' impossibile.
        $this->appTokenFallbackSeconds = (int) env("JWT_APP_TTL", 1800); // 30 min
        $this->masterTokenFallbackSeconds = (int) env("JWT_MASTER_TTL", 28800); // 8 ore
    }

    public function getAppTokenExpiredAt(): int
    {
        $parameter = Parameter::where("key", "app-token-exp-time-seconds")->first();
        $seconds = $parameter ? $parameter->value : $this->appTokenFallbackSeconds;
        return (int) $seconds;
    }

    public function getMasterTokenExpiredAt(): int
    {
        $parameter = Parameter::where("key", "master-token-exp-time-seconds")->first();
        $seconds = $parameter ? $parameter->value : $this->masterTokenFallbackSeconds;
        return (int) $seconds;
    }

    /**
     * Genera un token JWT.
     * Se viene passato un $redirectUrl (dominio), cerca il provider e applica la logica custom (Secret Key, Ruoli, TTL).
     * Altrimenti genera un token standard.
     * * @return string|null Ritorna il token stringa, o null se l'utente non è abilitato per quel provider.
     */
    public function generateAppToken(User $user, ?string $redirectId = null)
    {
        $jwt_exp_seconds = $this->getAppTokenExpiredAt();
        $ttlInMinutes = $jwt_exp_seconds / 60;

        // JWTAuth::factory()->setTTL accetta minuti, quindi convertiamo i secondi in minuti
        JWTAuth::factory()->setTTL($ttlInMinutes);
        $provider = Provider::where("id", $redirectId)->first();
        if (empty($provider)) {
            Log::warning("TokenCreation - Provider not found: " . $redirectId);
            return null;
        }

        // dato un provider e un user, ottengo tutti i ruoli associati
        $tokenBody = $this->providerUserRoleService->getJwtTokenInfo($provider->id, $user->id);
        if (empty($tokenBody)) {
            Log::warning("TokenCreation - Empty token body for User: {$user->id}, Provider: {$provider->id}");
            return null;
        }

        $originalSecret = JWTAuth::getJWTProvider()->getSecret();
        $payload = is_object($tokenBody) ? (array) $tokenBody : $tokenBody;

        try {
            if (empty($provider->secret_key)) {
                Log::error("Provider " . $provider->id . " has empty secret key.");
                throw new \Exception("Provider misconfigured.");
            }

            $currentTime = time();
            $expirationTime = $currentTime + $jwt_exp_seconds;

            $payloadData = array_merge(
                [
                    "iss" => $provider->url,
                    "iat" => $currentTime,
                    "exp" => $expirationTime,
                    "nbf" => $currentTime,
                    "jti" => bin2hex(random_bytes(10)),
                    "sub" => (string) $user->id,
                    "prv" => $provider->id,
                    "aud" => $provider->domain,
                ],
                ["payload" => $payload],
            );

            // Creazione di istanze "usa e getta" per firmare il token,
            // con la secret key specifica del provider,
            // senza toccare la configurazione globale.
            $algo = config("jwt.algo", "HS256");
            $keys = config("jwt.keys", []);

            // Creiamo il provider specifico al volo
            $customProvider = new Lcobucci($provider->secret_key, $algo, $keys);

            // Firmiamo il token usando esclusivamente questo provider temporaneo
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

    public function generateMasterToken(User $user, $providerId)
    {
        $jwt_exp_seconds = $this->getMasterTokenExpiredAt();
        $expiration_seconds = time() + $jwt_exp_seconds;
        $provider = Provider::where("id", $providerId)->first();

        $payload = [
            "iss" => $provider->url,
            "iat" => time(),
            "exp" => $expiration_seconds,
            "sub" => (string) $user->id,
            "payload" => [
                "user" => [
                    "id" => $user->id,
                    "username" => $user->username,
                    "email" => $user->email,
                    "name" => $user->name,
                    "surname" => $user->surname,
                ],
            ],
        ];

        $privateKey = File::get(storage_path("app/keys/private.key"));
        $keyId = config("idp.jwt.master_key_id");
        return JWT::encode($payload, $privateKey, "RS256", $keyId);
    }

    public function cookieCretion(string $token, string $provider_id, $cookie_name = null)
    {
        $master_token_name = config("idp.jwt.master_token_name");
        $expiration_seconds = $this->getAppTokenExpiredAt();
        // creo un cookie con il token
        if (empty($cookie_name)) {
            $cookie_name = "idp_token_" . $provider_id;
        }
        if ($cookie_name == $master_token_name) {
            $expiration_seconds = $this->getMasterTokenExpiredAt();
        }
        $provider = Provider::where("id", $provider_id)->first();
        $domain = $provider->domain;
        $is_https = str_starts_with($provider->protocol, "https");
        $cookie = cookie(
            $cookie_name, // Nome del cookie
            $token, // Il token JWT stringa
            $expiration_seconds, // Durata in secondi
            "/", // Path
            $domain, // Domain (null = automatico)
            $is_https, // Secure (true = solo HTTPS)
            true, // HttpOnly (true = JS non può leggerlo)
            false, // Raw
            "Lax", // SameSite (Lax va bene per i redirect, Strict per API pure)
        );
        return $cookie;
    }

    public static function checkLocalHost($host): bool
    {
        $valid_hosts = ["localhost", "127.0.0.1", "::1"];
        $valid_partial_hosts = ["192.168."];
        foreach ($valid_hosts as $valid_host) {
            if (str_contains($host, $valid_host)) {
                return true;
            }
        }
        foreach ($valid_partial_hosts as $valid_host) {
            if (str_contains($host, $valid_host)) {
                return true;
            }
        }
        return false;
    }

    public function resolveCrossDomainRedirect(
        ?Provider $provider,
        ?Provider $masterProvider,
        ?string $redirectUrl,
        ?string $masterToken,
    ): array {
        $isSameDomainZone = false;

        // Se non c'è un redirectUrl, l'utente rimane sull'IDP
        if (empty($redirectUrl)) {
            $isSameDomainZone = true;
        } elseif ($provider && $masterProvider) {
            $targetDomain = strtolower(trim($provider->domain, "."));
            $masterDomain = strtolower(trim($masterProvider->domain, "."));

            if (
                $targetDomain === $masterDomain ||
                str_contains($masterDomain, $targetDomain) ||
                str_contains($targetDomain, $masterDomain)
            ) {
                $isSameDomainZone = true;
            }
        }

        // In cross-domain il master-token NON viene piu' appeso all'URL: viene
        // restituito a parte e inoltrato dal controller come header x-master-token
        // sulla risposta SSO (handoff via fetch lato client di destinazione).
        $crossDomainMasterToken =
            !$isSameDomainZone && !empty($masterToken) && !empty($redirectUrl) ? $masterToken : null;

        return [
            "isSameDomainZone" => $isSameDomainZone,
            "redirectUrl" => $redirectUrl,
            "crossDomainMasterToken" => $crossDomainMasterToken,
        ];
    }

    /**
     * Prepara il redirect SSO verso un provider riusando lo stesso handoff del login:
     * genera il master-token e lo veicola via cookie (same-domain) o header
     * x-master-token (cross-domain). L'app-token viene poi emesso dalla destinazione
     * tramite l'exchange. Il master-token NON viene mai messo in query string.
     *
     * Ritorna null se il provider non esiste o l'utente non vi ha accesso.
     */
    public static function respondWithSsoRedirect($user, $providerId, $redirectToParam = null)
    {
        $tokenService = new TokenProviderService();

        $provider = Provider::find($providerId);
        if (!$provider) {
            return null;
        }

        // Autorizzazione utente sul provider di destinazione
        if (!$user->hasAccessToProvider($providerId)) {
            Log::warning("respondWithSsoRedirect: utente {$user->id} senza accesso al provider {$providerId}.");
            return null;
        }

        // Master-token: l'app-token verra' emesso dalla destinazione via exchange.
        $masterProviderId = (string) config("idp.provider_id");
        $masterProvider = Provider::find($masterProviderId);
        $masterToken = $tokenService->generateMasterToken($user, $masterProvider->id);

        // Risoluzione sicura dell'URL di destinazione (deve appartenere al dominio del provider)
        $redirectUrl = $provider->url;
        if ($redirectToParam) {
            $parsedHost = parse_url($redirectToParam, PHP_URL_HOST);
            $matchesProviderDomain =
                $parsedHost && !empty($provider->domain) && str_ends_with($parsedHost, $provider->domain);
            if ($matchesProviderDomain || ($parsedHost && self::checkLocalHost($parsedHost))) {
                $redirectUrl = $redirectToParam;
            }
        }

        $ssoData = $tokenService->resolveCrossDomainRedirect($provider, $masterProvider, $redirectUrl, $masterToken);

        return [
            "url" => $ssoData["redirectUrl"],
            "isSameDomainZone" => $ssoData["isSameDomainZone"],
            "masterToken" => $masterToken,
            "crossDomainMasterToken" => $ssoData["crossDomainMasterToken"],
        ];
    }
}
