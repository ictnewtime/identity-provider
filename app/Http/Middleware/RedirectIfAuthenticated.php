<?php

namespace App\Http\Middleware;

use App\Models\Provider;
use App\Services\TokenProviderService;
use App\Services\SessionService;
use Closure;
use Doctrine\Common\Lexer\Token;
use Illuminate\Support\Facades\Auth;
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
            return redirect("authenticated");
        }

        $ssoData = TokenProviderService::respondWithSsoRedirect(
            Auth::user(),
            $provider_id,
            $request,
            $request->input("redirect_to"),
        );

        if (!$ssoData) {
            // Se arrivo qui, l'utente è loggato all'IdP ma NON può entrare in questa App
            // Eseguiamo il logout per sicurezza o rimandiamo a una pagina di errore
            Auth::logout();
            return redirect("login")->withErrors(["msg" => "Accesso negato all'applicazione."]);
        }

        return redirect()->away($ssoData["url"])->withCookie($ssoData["cookie"]);
    }
}
