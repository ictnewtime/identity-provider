<?php

namespace App\Http\Middleware;

use App\Models\Provider;
use App\Services\TokenProviderService;
use App\Services\SessionService;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RedirectIfAuthenticated
{
    public function handle($request, Closure $next, $guard = null)
    {
        // Se non è autenticato sull'IdP, prosegue normalmente (verso il form di login)
        if (!Auth::guard($guard)->check()) {
            return $next($request);
        }

        $provider_id = $request->input("provider_id");
        $redirect_to = $request->input("redirect_to");

        // Se l'utente è andato sulla root dell'IdP senza richiedere un'app specifica
        if (empty($provider_id)) {
            return redirect("authenticated"); // O la dashboard dell'IdP
        }

        // 1. PREVENZIONE CRASH: Verifichiamo che il provider esista prima di usarlo
        $provider = Provider::where("id", $provider_id)->first();
        if (!$provider) {
            Log::error("Middleware SSO: Provider ID $provider_id non trovato.");
            return redirect("authenticated")->withErrors(["msg" => "Provider non valido."]);
        }

        $user = Auth::user();
        $ip_address = $request->ip();

        $tokenService = new TokenProviderService();
        $sessionService = new SessionService();

        // 2. Otteniamo il token per questo utente/provider/IP
        $token = $sessionService->getValidProviderToken($user, $provider_id, $ip_address, $tokenService);

        if (!$token) {
            return redirect("authenticated")->withErrors(["msg" => "Non autorizzato."]);
        }

        // 3. SICUREZZA: Prevenzione Open Redirect
        $redirect_url = $provider->protocol . $provider->domain;

        if ($redirect_to) {
            $parsedHost = parse_url($redirect_to, PHP_URL_HOST);
            // Il redirect è permesso solo verso il dominio del provider (o localhost)
            if ($parsedHost === $provider->domain || in_array($parsedHost, ["localhost", "127.0.0.1"])) {
                $redirect_url = $redirect_to;
            } else {
                Log::warning("Middleware SSO: Tentativo di Open Redirect bloccato verso: " . $redirect_to);
            }
        }

        // 4. LOGICA SSO: Accodiamo SEMPRE il token (usa il nuovo metodo aggiornato)
        $final_url = $tokenService->appendTokenToUrl($redirect_url, $token);

        // 5. Creiamo/Aggiorniamo il cookie dell'IdP
        $mainCookie = $tokenService->cookieCretion($token, $provider_id);

        // 6. Redirect finale verso l'app client (portando sempre con sé il cookie IdP)
        return redirect()->away($final_url)->withCookie($mainCookie);
    }
}
