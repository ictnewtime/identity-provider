<?php

namespace App\Http\Middleware;

use App\Services\TokenProviderService;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;

class RedirectIfAuthenticated
{
    public function handle($request, Closure $next, $guard = null)
    {
        if (!Auth::guard($guard)->check()) {
            return $next($request);
        }

        $provider_id = $request->input("provider_id");

        if (empty($provider_id)) {
            Log::warning("SSO Fallito: Nessun provider_id passato nell'URL.");
            return redirect()->route("sso.unauthorized");
        }

        $ssoData = TokenProviderService::respondWithSsoRedirect(
            Auth::user(),
            $provider_id,
            $request,
            $request->input("redirect_to"),
        );
        if (!$ssoData) {
            Log::warning("SSO Fallito: TokenProviderService ha restituito null. Eseguo redirect a sso.unauthorized.");
            return redirect()->route("sso.unauthorized");
        }

        Cookie::queue($ssoData["cookie"]);
        return redirect()->away($ssoData["url"])->withCookie($ssoData["cookie"]);
    }
}
