<?php

namespace App\Http\Middleware;

use App\Models\Provider;
use App\Models\ProviderUserRole;
use App\Services\ProviderUserRoleService;
use App\Services\TokenProviderService;
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
        $tokenService = new TokenProviderService();
        $token = $tokenService->tokenCretion($user, $provider_id);

        if (!$token) {
            return redirect("authenticated")->withErrors(["msg" => "Non autorizzato per questo servizio."]);
        }
        $provider = Provider::where("id", $provider_id)->first();
        $cookie = $tokenService->cookieCretion($token, $provider_id);
        if (!$redirect_to) {
            $redirect_to = $provider->protocol . $provider->domain;
        }
        return redirect()->away($redirect_to)->withCookie($cookie);
    }
}
