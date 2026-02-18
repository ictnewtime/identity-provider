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
        $redirect_to = $request->input("redirect_to");
        if (empty($provider_id)) {
            return redirect("authenticated");
        }

        $user = Auth::user();
        $tokenService = new TokenProviderService();
        $token = $tokenService->tokenCretion($user, $provider_id);

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

            // Per localhost mandiamo un cookie senza dominio specifico e NON secure (per HTTP)
            // $localCookie = cookie(
            //     "idp_token_" . $provider_id,
            //     $token,
            //     60 * 24,
            //     "/",
            //     null, // Importante: null su localhost
            //     false, // False se sei su http://localhost
            //     true,
            // );
            $separator = parse_url($redirect_to, PHP_URL_QUERY) ? "&" : "?";
            $redirect_url = $redirect_to . $separator . "token=" . urlencode($token);
            return redirect()->away($redirect_url); //->withCookie($localCookie);
        }

        return redirect()->away($redirect_to)->withCookie($mainCookie);
    }
}
