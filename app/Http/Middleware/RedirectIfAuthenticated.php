<?php

namespace App\Http\Middleware;

use App\Models\Provider;
use App\Models\Session;
use App\Services\TokenProviderService;
use App\Services\SessionService;
use Closure;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if (!Auth::guard($guard)->check()) {
            return $next($request);
        }
        // the redirectUrl is the provider
        $provider_id = $request->input("provider_id");
        $redirect_to = $request->input("redirect_to");
        if (empty($provider_id)) {
            return redirect("authenticated");
        }

        $user = Auth::user();
        $ip_address = $request->ip();
        $tokenService = new TokenProviderService();
        $sessionService = new SessionService();

        // Cerchiamo se esiste già una sessione per questo utente e provider
        $existingSession = Session::where("user_id", $user->id)->where("provider_id", $provider_id)->first();

        if ($existingSession) {
            // CASO 1: Esiste e l'IP coincide
            if ($existingSession->ip_address === $ip_address) {
                // Recuperiamo il token esistente dal DB
                $token = $existingSession->token;
            }
            // CASO 2: Esiste ma l'IP è cambiato (possibile furto di sessione o cambio rete)
            else {
                $existingSession->delete(); // Eliminiamo la vecchia sessione non sicura

                $token = $tokenService->tokenCretion($user, $provider_id);
                // Creiamo la nuova sessione (imposto scadenza a 2 ore, modificala a tuo piacimento)
                $sessionService->createSession($user->id, $provider_id, $ip_address, $token, null, now()->addHours(2));
            }
        }
        // CASO 3: Non esiste nessuna sessione
        else {
            $token = $tokenService->tokenCretion($user, $provider_id);
            $sessionService->createSession($user->id, $provider_id, $ip_address, $token, null, now()->addHours(2));
        }

        if (!$token) {
            return redirect("authenticated")->withErrors(["msg" => "Non autorizzato."]);
        }
        $provider = Provider::where("id", $provider_id)->first();

        if (!$redirect_to) {
            $redirect_to = $provider->protocol . $provider->domain;
        }

        $host = parse_url($redirect_to, PHP_URL_HOST);

        $mainCookie = $tokenService->cookieCretion($token, $provider_id);

        if ($host === "localhost" || $host === "127.0.0.1" || str_contains($host, "192.168.")) {
            $separator = parse_url($redirect_to, PHP_URL_QUERY) ? "&" : "?";
            $redirect_url = $redirect_to . $separator . http_build_query(["token" => $token]);

            $separator = parse_url($redirect_to, PHP_URL_QUERY) ? "&" : "?";
            $redirect_url = $redirect_to . $separator . "token=" . urlencode($token);
            return redirect()->away($redirect_url); //->withCookie($localCookie);
        }

        return redirect()->away($redirect_to)->withCookie($mainCookie);
    }
}
