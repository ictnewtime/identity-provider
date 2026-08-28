<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\SessionService;
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
        $tokenString = self::masterTokenFrom($request);

        if (empty($tokenString)) {
            Log::error("Master Token mancante: ne' in Authorization ne' in x-master-token.", [
                "url" => $request->fullUrl(),
                "ip" => $request->ip(),
            ]);
            return response()->json(["message" => __("auth.token_missing")], 401);
        }

        Log::debug("[MASTER TOKEN] ricevuto", [
            "da" => $request->bearerToken() ? "Authorization" : "x-master-token",
            "token" => SessionService::tokenFingerprint($tokenString),
            "url" => $request->fullUrl(),
        ]);

        try {
            $publicKeyPath = storage_path("app/keys/public.key");
            if (!File::exists($publicKeyPath)) {
                throw new Exception("File della chiave pubblica non trovato sul server.");
            }
            $publicKeyPem = File::get($publicKeyPath);

            $decodedPayload = JWT::decode($tokenString, new Key($publicKeyPem, "RS256"));

            $userId = $decodedPayload->sub ?? null;

            if (!$userId) {
                Log::error("Master Token corrotto (claim 'sub' mancante).");
                return response()->json(["message" => __("auth.token_invalid")], 401);
            }

            $request->attributes->set("jwt_user_id", $userId);

            Log::debug("[MASTER TOKEN] valido", ["user_id" => $userId]);

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

    /**
     * Il master token, da `Authorization: Bearer` **o** da `x-master-token` (punto TMT04).
     *
     * Perche' due posti: `Authorization` e' spesso gia' occupato da chi chiama — un'applicazione che
     * autentica se stessa verso il proprio backend e vuole passare **anche** il master token dell'IdP —
     * e senza un header suo dovrebbe inventarsi qualcosa. `x-master-token` si accetta con o senza
     * `Bearer ` davanti, perche' chi lo scrive a mano lo mette meta' delle volte.
     */
    public static function masterTokenFrom(Request $request): ?string
    {
        $bearer = $request->bearerToken();

        if (!empty($bearer)) {
            return $bearer;
        }

        $header = trim((string) $request->header("x-master-token"));

        if ($header === "") {
            return null;
        }

        // `Bearer xxx` oppure `xxx`: si toglie il prefisso se c'e', senza distinguere maiuscole.
        if (stripos($header, "bearer ") === 0) {
            $header = trim(substr($header, 7));
        }

        return $header !== "" ? $header : null;
    }
}
