<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Provider;
use Symfony\Component\HttpFoundation\Response;

class CheckClientCredentials
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $providerId = $request->input("id");
        $secretKey = $request->input("secret_key");

        if (!$providerId || !$secretKey) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Credenziali mancanti (id o secret_key).",
                ],
                401,
            );
        }

        $provider = Provider::find($providerId);

        if (!$provider) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Provider non trovato.",
                ],
                401,
            );
        }

        if (!hash_equals($provider->secret_key, $secretKey)) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Secret Key non valida.",
                ],
                403,
            );
        }

        $request->attributes->add(["provider" => $provider]);

        return $next($request);
    }
}
