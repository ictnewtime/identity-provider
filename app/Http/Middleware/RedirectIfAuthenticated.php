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
        $provider = Provider::where("id", $providerId)->first();
        $url = $provider->protocol . $provider->domain . "?token=" . $token;
        // $url = "http://localhost?token=" . $token;
        // dd("Redirecting to: " . $url);
        return redirect()->away($url);
    }
}
