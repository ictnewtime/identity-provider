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
        // 1. Recuperiamo i dati (Laravel controllerà sia l'URL ?id=... sia il body JSON/POST)
        $providerId = $request->input("id");
        $secretKey = $request->input("secret_key");

        // 2. Se mancano i parametri, blocchiamo subito
        if (!$providerId || !$secretKey) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Credenziali mancanti (id o secret_key).",
                ],
                401,
            );
        }

        // 3. Cerchiamo il Provider nel database
        $provider = Provider::find($providerId);

        if (!$provider) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Provider non trovato.",
                ],
                401,
            ); // 401 = Unauthorized
        }

        // 4. Confrontiamo la Secret Key
        // Usiamo hash_equals invece di "==" per prevenire i "Timing Attacks" (attacchi crittografici)
        if (!hash_equals($provider->secret_key, $secretKey)) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Secret Key non valida.",
                ],
                403,
            ); // 403 = Forbidden
        }

        // (Opzionale ma molto utile): Salviamo il provider verificato nella Request
        // Così nel tuo Controller potrai fare: $request->attributes->get('provider')
        // senza doverlo interrogare di nuovo dal database
        $request->attributes->add(["provider" => $provider]);

        // 5. Tutto ok, facciamo passare la richiesta
        return $next($request);
    }
}
