<?php

namespace App\Http\Middleware;

use App\Auth\Idp\IdpMasterTokenVerifier;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerifyMasterToken
{
    public function __construct(private readonly IdpMasterTokenVerifier $verifier) {}

    public function handle(Request $request, Closure $next)
    {
        $tokenString = $request->bearerToken();

        if (empty($tokenString)) {
            Log::error("Master Token mancante nell'header di autorizzazione.");
            return response()->json(["message" => __("auth.token_missing")], 401);
        }

        // La verifica vera — chiave pubblica, RS256, claim `sub` — sta in `IdpMasterTokenVerifier`,
        // insieme al rinnovo trasparente che la usa allo stesso modo. Il motivo del rifiuto lo
        // scrive lei nel log: qui la risposta e' comunque 401.
        $userId = $this->verifier->userIdFrom($tokenString);

        if (!$userId) {
            return response()->json(["message" => __("auth.token_invalid")], 401);
        }

        $request->attributes->set("jwt_user_id", $userId);

        if ($request->has("provider_id")) {
            $request->attributes->set("jwt_provider_id", $request->input("provider_id"));
        }

        return $next($request);
    }
}
