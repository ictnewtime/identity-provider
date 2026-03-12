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
            return redirect()->route("sso.unauthorized");
        }

        $ssoData = TokenProviderService::respondWithSsoRedirect(
            Auth::user(),
            $provider_id,
            $request,
            $request->input("redirect_to"),
        );

        if (!$ssoData) {
            // Se non è autorizzato, lo mandiamo alla pagina "Accesso Negato"
            // Passiamo l'ID del provider per fargli capire dove ha provato ad andare
            return redirect()->route("sso.unauthorized");
        }

        Cookie::queue($ssoData["cookie"]);
        return redirect()->away($ssoData["url"])->withCookie($ssoData["cookie"]);
    }
}
