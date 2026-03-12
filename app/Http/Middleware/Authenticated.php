<?php

namespace App\Http\Middleware;

use Closure;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\Session;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class Authenticated
{
    public function handle($request, Closure $next)
    {
        Log::info("--- INIZIO CONTROLLO MIDDLEWARE AUTHENTICATED ---");

        $idpProviderId = config("idp.provider_id");
        $cookieName = "idp_token_" . $idpProviderId;

        // 1. Estrazione manuale del token
        $tokenString = $request->cookie($cookieName) ?? $request->bearerToken();

        if (empty($tokenString)) {
            Log::warning("Fallimento: Nessun token trovato nel cookie [{$cookieName}] o nell'header Bearer.");
            return $this->forceLogoutAndRedirect($request, "Token assente. Effettua il login.");
        }

        Log::info("Token estratto con successo (Primi 20 caratteri): " . substr($tokenString, 0, 20) . "...");

        try {
            // 2. RECUPERO IL PROVIDER E LA SUA SECRET KEY
            $provider = \App\Models\Provider::find($idpProviderId);

            if (!$provider || empty($provider->secret_key)) {
                Log::error("Impossibile validare il token: Provider IdP non trovato o secret_key mancante.");
                return $this->forceLogoutAndRedirect($request, "Configurazione di sicurezza mancante.");
            }

            Log::debug("Provider IdP trovato. Preparazione istanza Lcobucci custom per la validazione.");

            // 3. VALIDAZIONE MANUALE CON LCOBUCCI (Esattamente come in tokenCretion)
            $algo = config("jwt.algo", "HS256");
            $keys = config("jwt.keys", []);

            // Creiamo il provider specifico al volo per decodificare
            $customProvider = new \Tymon\JWTAuth\Providers\JWT\Lcobucci($provider->secret_key, $algo, $keys);

            // Proviamo a decodificare. Se la firma o la sintassi sono errate, lancerà un'eccezione
            $payload = $customProvider->decode($tokenString);

            Log::info("Decodifica Lcobucci Riuscita! Payload estratto.");

            // 4. VERIFICA SCADENZA (exp)
            if (isset($payload["exp"]) && $payload["exp"] < time()) {
                throw new TokenExpiredException("Token has expired");
            }

            // 5. ESTRAZIONE UTENTE (sub)
            $userId = $payload["sub"] ?? null;
            if (!$userId) {
                Log::warning("Fallimento: Token decodificato ma claim 'sub' (User ID) mancante.");
                return $this->forceLogoutAndRedirect($request, "Token corrotto (ID utente mancante).");
            }

            $user = \App\Models\User::find($userId);
            if (!$user) {
                Log::warning("Fallimento: Utente ID {$userId} non esiste più nel database.");
                return $this->forceLogoutAndRedirect($request, "Utente non trovato.");
            }

            // Diciamo a Laravel chi è l'utente corrente per questa richiesta,
            // così Auth::user() funzionerà nel resto del codice!
            \Illuminate\Support\Facades\Auth::login($user);

            Log::info("Autenticazione Riuscita! Utente: {$user->username} (ID: {$user->id})");

            // 6. CONTROLLO FISICO SUL DATABASE (La famosa riga di sessione)
            $sessionExists = \App\Models\Session::where("token", $tokenString)->exists();

            Log::info("La sessione esiste fisicamente nel database? -> " . ($sessionExists ? "SI" : "NO"));

            if (!$sessionExists) {
                Log::critical(
                    "ACCESSO NEGATO: Il token è valido crittograficamente MA la sessione è stata ELIMINATA dal database!",
                );
                return $this->forceLogoutAndRedirect(
                    $request,
                    'La tua sessione è stata terminata dall\'amministratore.',
                );
            }
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            Log::warning("Fallimento: Token Scaduto (Expired) - Eccezione lanciata manualmente");
            return $this->forceLogoutAndRedirect($request, __("auth.token-expired"));
        } catch (\Exception $e) {
            // Catturiamo QUALSIASI eccezione di decodifica lanciata da Lcobucci (Firma errata, Token malformato, ecc.)
            Log::warning("Fallimento Decodifica Lcobucci: " . $e->getMessage());
            return $this->forceLogoutAndRedirect($request, __("auth.token-invalid"));
        }

        Log::info("--- CONTROLLO SUPERATO. Accesso consentito. ---");
        return $next($request);
    }

    /**
     * Esegue un logout "tabula rasa" e gestisce correttamente Inertia/API
     */
    protected function forceLogoutAndRedirect($request, $message)
    {
        $idpProviderId = config("idp.provider_id");
        $cookieName = "idp_token_" . $idpProviderId;

        // Distruggiamo la sessione web nativa di Laravel (Fondamentale!)
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Eliminiamo i cookie accodandoli per la risposta
        $domain = env("PROVIDER_DOMAIN"); // o null
        Cookie::queue(Cookie::forget($cookieName, "/", $domain));
        Cookie::queue(Cookie::forget("token", "/", $domain)); // Pulizia vecchio cookie

        Log::info("Sessione nativa distrutta e cookie accodati per l'eliminazione.");

        // Se è una richiesta API pura o AJAX (non Inertia)
        if ($request->expectsJson() && !$request->header("X-Inertia")) {
            return response()->json(["message" => $message], 401);
        }

        // Se è Inertia o navigazione Web standard
        return redirect()
            ->route("loginForm")
            ->withErrors([
                "login" => $message,
            ]);
    }
}
