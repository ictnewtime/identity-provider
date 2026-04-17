<?php

namespace App\Http\Middleware;

use App\Models\Provider;
use App\Models\Session;
use App\Models\User;
use Closure;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Providers\JWT\Lcobucci;
use Lcobucci\JWT\Configuration;

class Authenticated
{
    public function handle($request, Closure $next)
    {
        $idpProviderId = config("idp.provider_id");
        $cookieName = "idp_token_" . $idpProviderId;

        // Estrazione del token
        $tokenString = $request->cookie($cookieName) ?? $request->bearerToken();

        if (empty($tokenString)) {
            Log::warning("Fallimento: Nessun token trovato nel cookie [{$cookieName}] o nell'header Bearer.");
            return $this->forceLogoutAndRedirect($request, "Token assente. Effettua il login.");
        }

        try {
            $provider = Provider::find($idpProviderId);

            if (!$provider || empty($provider->secret_key)) {
                Log::error("Impossibile validare il token: Provider IdP non trovato o secret_key mancante.");
                return $this->forceLogoutAndRedirect($request, "Configurazione di sicurezza mancante.");
            }

            $algo = config("jwt.algo", "HS256");
            $keys = config("jwt.keys", []);

            $customProvider = new Lcobucci($provider->secret_key, $algo, $keys);

            $payload = $customProvider->decode($tokenString);

            if (isset($payload["exp"])) {
                $currentTime = time();

                if ($payload["exp"] < $currentTime) {
                    Log::warning("Fallimento: Il token è scaduto!");
                    throw new TokenExpiredException("Token has expired");
                }
            } else {
                Log::warning("Attenzione: Il token decodificato NON ha il claim 'exp' (scadenza).");
            }

            $userId = $payload["sub"] ?? null;
            if (!$userId) {
                Log::warning("Fallimento: Token decodificato ma claim 'sub' (User ID) mancante.");
                return $this->forceLogoutAndRedirect($request, "Token corrotto (ID utente mancante).");
            }

            $user = User::find($userId);
            if (!$user) {
                Log::warning("Fallimento: Utente ID {$userId} non esiste più nel database.");
                return $this->forceLogoutAndRedirect($request, "Utente non trovato.");
            }

            Auth::login($user);
            $sessionExists = Session::where("token", $tokenString)->exists();

            if (!$sessionExists) {
                Log::critical(
                    "ACCESSO NEGATO: Il token è valido crittograficamente MA la sessione è stata ELIMINATA dal database!",
                );
                return $this->forceLogoutAndRedirect(
                    $request,
                    'La tua sessione è stata terminata dall\'amministratore.',
                );
            }
        } catch (TokenExpiredException $e) {
            Log::warning("Eccezione catturata: TokenExpiredException.");
            return $this->forceLogoutAndRedirect($request, __("auth.token-expired"));
        } catch (\Exception $e) {
            Log::error("Errore decodifica JWT: " . $e->getMessage());
            return $this->forceLogoutAndRedirect($request, __("auth.token-invalid"));
        }

        return $next($request);
    }

    protected function forceLogoutAndRedirect($request, $message)
    {
        $idpProviderId = config("idp.provider_id");
        $cookieName = "idp_token_" . $idpProviderId;
        $provider = Provider::find($idpProviderId);

        Cookie::queue(Cookie::forget($cookieName, "/", $provider->domain));
        Cookie::queue(Cookie::forget("token", "/", $provider->domain));

        if ($request->expectsJson() && !$request->header("X-Inertia")) {
            return response()->json(["message" => $message], 401);
        }

        if ($request->hasSession()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()
            ->route("loginForm")
            ->withErrors([
                "login" => $message,
            ]);
    }
}
