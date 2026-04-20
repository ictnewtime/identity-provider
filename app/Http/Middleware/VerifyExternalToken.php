<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Providers\JWT\Lcobucci;

class VerifyExternalToken
{
    public function handle(Request $request, Closure $next)
    {
        $tokenString = $request->bearerToken();

        if (empty($tokenString)) {
            Log::error("Token di autorizzazione mancante.");
            return response()->json(["message" => __("auth.token_missing")], 401);
        }

        try {
            $parts = explode(".", $tokenString);
            if (count($parts) !== 3) {
                throw new \Exception("Formato JWT non valido");
            }

            $payload = json_decode(base64_decode(strtr($parts[1], "-_", "+/")), true);
            $providerId = $payload["prv"] ?? null;
            $userId = $payload["sub"] ?? null;

            if (!$providerId || !$userId) {
                Log::error("Token corrotto (claim mancanti).");
                return response()->json(["message" => __("auth.token_invalid")], 401);
            }

            $provider = Provider::find($providerId);
            if (!$provider || empty($provider->secret_key)) {
                Log::error("Provider non valido o chiave mancante.");
                return response()->json(["message" => __("auth.provider_invalid")], 401);
            }

            $algo = config("jwt.algo", "HS256");
            $customProvider = new Lcobucci($provider->secret_key, $algo, []);

            $customProvider->decode($tokenString);

            $request->attributes->set("jwt_user_id", $userId);
            $request->attributes->set("jwt_provider_id", $providerId);
        } catch (\Exception $e) {
            Log::warning(
                "Verifica Token di autorizzazione fallita (Firma token non valida o token scaduto): " .
                    $e->getMessage(),
            );
            return response()->json(["message" => __("auth.token_invalid")], 401);
        }

        return $next($request);
    }
}
