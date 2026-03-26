<?php

namespace App\Services;

use App\Models\User;
use App\Models\Provider;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Services\ProviderUserRoleService;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Providers\JWT\Lcobucci;
use Lcobucci\JWT\Configuration;

class TokenProviderService
{
    protected $providerUserRoleService;
    protected $ttlInSeconds;

    public function __construct()
    {
        $this->providerUserRoleService = new ProviderUserRoleService();
        $this->ttlInSeconds = (int) env("JWT_TTL", 24 * 60 * 60); // default 24 ore
    }

    public function getTtlInSeconds(): int
    {
        return $this->ttlInSeconds;
    }

    /**
     * Genera un token JWT.
     * Se viene passato un $redirectUrl (dominio), cerca il provider e applica la logica custom (Secret Key, Ruoli, TTL).
     * Altrimenti genera un token standard.
     * * @return string|null Ritorna il token stringa, o null se l'utente non è abilitato per quel provider.
     */
    public function tokenCretion(User $user, ?string $redirectId = null)
    {
        // --- INIZIO DEBUG STAGING ---
        Log::info("=== START TOKEN CREATION DEBUG (Staging) ===");
        Log::info("User ID: " . $user->id . " | Provider ID: " . $redirectId);

        // Stampiamo i valori esatti con var_export per vedere se sono null, int(0) o stringhe vuote
        Log::info("DEBUG TTL - \$this->ttlInSeconds is: " . var_export($this->ttlInSeconds, true));
        Log::info("DEBUG TTL - env('JWT_TTL') is: " . var_export(env("JWT_TTL"), true));
        Log::info("DEBUG TTL - config('jwt.ttl') is: " . var_export(config("jwt.ttl"), true));
        // --- FINE DEBUG STAGING ---

        // $ttlInMinutes = $this->ttlInSeconds / 60;
        // JWTAuth::factory()->setTTL accetta minuti, quindi convertiamo i secondi in minuti
        JWTAuth::factory()->setTTL($this->ttlInSeconds);
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
                // Errore, secret key empty
                Log::error("Provider " . $provider->id . " has empty secret key.");
                throw new \Exception("Provider misconfigured.");
            }

            // --- DEBUG CALCOLO TEMPO ---
            $currentTime = time();
            $fallbackTriggered = $this->ttlInSeconds === null || $this->ttlInSeconds === 0;
            $calculatedTtl = $this->ttlInSeconds ?? 3600;

            Log::info("DEBUG TIME - Current time: {$currentTime} (" . date("Y-m-d H:i:s", $currentTime) . ")");
            Log::info(
                "DEBUG TIME - Was the fallback (3600) triggered?: " .
                    ($fallbackTriggered ? "YES! ttlInSeconds was empty" : "NO, using {$calculatedTtl}"),
            );

            // Definiamo i claims
            $expirationTime = $currentTime + $calculatedTtl;
            Log::info(
                "DEBUG TIME - Expiration time (exp): {$expirationTime} (" . date("Y-m-d H:i:s", $expirationTime) . ")",
            );

            $payloadData = array_merge(
                [
                    "iss" => url("/"),
                    "iat" => $currentTime,
                    "exp" => $expirationTime,
                    "nbf" => $currentTime,
                    "jti" => bin2hex(random_bytes(10)),
                    "sub" => (string) $user->id,
                    "prv" => $provider->id,
                ],
                ["payload" => $payload],
            );

            // Stampiamo l'intero payload
            Log::debug("DEBUG PAYLOAD - Final Payload Data: ", $payloadData);

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

            Log::info("=== END TOKEN CREATION DEBUG: Success ===");
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

    public function cookieCretion(string $token, string $provider_id)
    {
        // creo un cookie con il token
        $cookie_name = "idp_token_" . $provider_id;
        $provider = Provider::where("id", $provider_id)->first();
        $domain = env("PROVIDER_DOMAIN", null);
        $is_https = str_starts_with($provider->protocol, "https");
        $cookie = cookie(
            $cookie_name, // Nome del cookie
            $token, // Il token JWT stringa
            $this->ttlInSeconds, // Durata in secondi
            "/", // Path
            $domain, // Domain (null = automatico)
            $is_https, // Secure (true = solo HTTPS, metti env('APP_SECURE', false) per locale)
            true, // HttpOnly (FONDAMENTALE: true = JS non può leggerlo)
            false, // Raw
            "Lax", // SameSite (Lax va bene per i redirect, Strict per API pure)
        );
        return $cookie;
    }

    /**
     * Accoda il token all'URL se l'host di destinazione è un ambiente locale.
     * Necessario per aggirare i blocchi dei cookie cross-domain in sviluppo.
     *
     * @param string $redirect_url
     * @param string $token
     * @return string
     */
    public function appendTokenIfLocalUrl(string $redirect_url, string $token): string
    {
        $host = parse_url($redirect_url, PHP_URL_HOST);

        if (in_array($host, ["localhost", "127.0.0.1"]) || str_contains($host, "192.168.")) {
            $separator = parse_url($redirect_url, PHP_URL_QUERY) ? "&" : "?";
            return $redirect_url . $separator . "token=" . urlencode($token);
        }

        return $redirect_url;
    }

    public function appendTokenToUrl(string $redirect_url, string $token): string
    {
        // Accoda sempre il token in query string, che sia localhost o produzione
        $separator = parse_url($redirect_url, PHP_URL_QUERY) ? "&" : "?";
        return $redirect_url . $separator . "token=" . urlencode($token);
    }

    // Esempio di funzione unificata da mettere in un Service o in un Trait
    public static function respondWithSsoRedirect($user, $providerId, $request, $redirectToParam = null)
    {
        $tokenService = new TokenProviderService();
        $sessionService = new SessionService();

        // L'UNICA fonte di verità per l'abilitazione
        $token = $sessionService->getValidProviderToken(
            $user,
            $providerId,
            $request->ip(),
            $request->header("User-Agent"),
            $tokenService,
        );

        if (!$token) {
            return null; // Segnale che l'utente non è autorizzato
        }

        $provider = Provider::find($providerId);
        if (!$provider) {
            return null;
        }

        $redirectUrl = $provider->url;

        // Gestione sicura del redirect_to
        if ($redirectToParam) {
            $parsedHost = parse_url($redirectToParam, PHP_URL_HOST);
            if ($parsedHost === $provider->domain || in_array($parsedHost, ["localhost", "127.0.0.1"])) {
                $redirectUrl = $redirectToParam;
            }
        }

        $finalUrl = $tokenService->appendTokenToUrl($redirectUrl, $token);
        $cookie = $tokenService->cookieCretion($token, $providerId);

        return [
            "url" => $finalUrl,
            "cookie" => $cookie,
        ];
    }
}
