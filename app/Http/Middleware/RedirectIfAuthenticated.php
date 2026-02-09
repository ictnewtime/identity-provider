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
        $providerId = $request->input("redirect");
        if (empty($providerId)) {
            return redirect("authenticated");
        }

        $user = Auth::user();
        $tokenService = new TokenGeneratorService();
        $token = $tokenService->generate($user, $providerId);
        if (!$token) {
            return redirect("authenticated")->withErrors(["msg" => "Non autorizzato per questo servizio."]);
        }
        $provider = Provider::where("domain", $providerId)->first();
        $separator = parse_url($provider->domain, PHP_URL_QUERY) == null ? "?" : "&";
        $url = $provider->domain . $separator . "token=" . $token;

        return redirect()->away($url);
    }
}
