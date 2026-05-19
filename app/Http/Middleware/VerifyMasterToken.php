<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Exception;

class VerifyMasterToken
{
    public function handle(Request $request, Closure $next)
    {
        $tokenString = $request->bearerToken();

        if (empty($tokenString)) {
            Log::error("Master Token mancante nell'header di autorizzazione.");
            return response()->json(["message" => __("auth.token_missing")], 401);
        }

        try {
            $publicKeyPath = storage_path("app/keys/public.key");
            $publicKeyPem = cache()->remember("idp_public_key_pem", now()->addDays(30), function () use (
                $publicKeyPath,
            ) {
                if (!File::exists($publicKeyPath)) {
                    throw new Exception("File della chiave pubblica non trovato sul server.");
                }
                return File::get($publicKeyPath);
            });

            $decodedPayload = JWT::decode($tokenString, new Key($publicKeyPem, "RS256"));

            $userId = $decodedPayload->sub ?? null;

            if (!$userId) {
                Log::error("Master Token corrotto (claim 'sub' mancante).");
                return response()->json(["message" => __("auth.token_invalid")], 401);
            }

            $request->attributes->set("jwt_user_id", $userId);

            if ($request->has("provider_id")) {
                $request->attributes->set("jwt_provider_id", $request->input("provider_id"));
            }
        } catch (ExpiredException $e) {
            Log::warning("Master Token scaduto: " . $e->getMessage());
            return response()->json(["message" => __("auth.token_invalid")], 401);
        } catch (Exception $e) {
            Log::warning("Verifica Master Token fallita: " . $e->getMessage());
            return response()->json(["message" => __("auth.token_invalid")], 401);
        }

        return $next($request);
    }
}
