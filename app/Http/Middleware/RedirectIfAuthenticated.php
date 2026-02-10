<?php

namespace App\Http\Middleware;

use App\Models\Provider;
use App\Models\ProviderUserRole;
use App\Services\ProviderUserRoleService;
use App\Services\TokenGeneratorService;
use Closure;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;

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
        $redirect_to = $request->query("redirect_to");
        if (empty($provider_id)) {
            return redirect("authenticated");
        }

        $user = Auth::user();
        $tokenService = new TokenGeneratorService();
        $token = $tokenService->generate($user, $provider_id);

        if (!$token) {
            return redirect("authenticated")->withErrors(["msg" => "Non autorizzato per questo servizio."]);
        }
        // ottengo l' url di origine
        $provider = Provider::where("id", $provider_id)->first();
        // creo un cookie con il token
        $cookie_name = "idp_token_" . $provider_id;
        $cookie = cookie(
            $cookie_name, // Nome del cookie
            $token, // Il token JWT stringa
            60 * 24, // Durata in minuti (es. 24 ore)
            "/", // Path
            null, // Domain (null = automatico)
            false, // Secure (true = solo HTTPS, metti env('APP_SECURE', false) per locale)
            true, // HttpOnly (FONDAMENTALE: true = JS non può leggerlo)
            false, // Raw
            "Lax", // SameSite (Lax va bene per i redirect, Strict per API pure)
        );
        if (!$redirect_to) {
            $redirect_to = $provider->protocol . $provider->domain;
        }
        return redirect()->away($redirect_to)->withCookie($cookie);
    }
}
