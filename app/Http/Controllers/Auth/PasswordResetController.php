<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\Session;
use App\Models\User;
use App\Services\TokenProviderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PasswordResetController extends Controller
{
    public function create()
    {
        return Inertia::render("Auth/ForgotPassword", [
            "status" => session("status"),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(
            [
                "username" => "required|string|exists:users,username",
            ],
            [
                "username.exists" => __("auth.username_not_found"),
            ],
        );

        $user = User::where("username", $request->username)->first();

        if (empty($user->email)) {
            return back()->withErrors([
                "username" => __("auth.no_email_configured"),
            ]);
        }

        $status = Password::broker()->sendResetLink(["email" => $user->email]);

        return back()->with("status", __("auth.recovery_link_sent"));
    }

    public function edit(Request $request, $token)
    {
        $user = clone User::where("email", $request->email)->first();

        return Inertia::render("Auth/ResetPassword", [
            "email" => $request->email,
            "username" => $user->username ?? "Utente",
            "token" => $token,
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            "token" => "required",

            "email" => "required|email",

            "password" => "required|min:12|confirmed",

            "password_confirmation" => "required|min:12",
        ]);

        // Check if the new password is the same as the old one

        $user = User::where("email", $request->email)->first();

        if ($user && Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                "password" => [__("auth.password_same_as_old")],
            ]);
        }

        // Reset password

        $status = Password::broker()->reset(
            $request->only("email", "password", "password_confirmation", "token"),

            function ($user, $password) {
                $user->password = Hash::make($password);

                $user->save();
            },
        );

        if ($status == Password::PASSWORD_RESET) {
            return redirect()
                ->route("loginForm")

                ->withErrors(["login" => __("auth.password_reset_success")]);
        }

        throw ValidationException::withMessages([
            "password" => [__($status)],
        ]);
    }

    public function expired(Request $request)
    {
        return Inertia::render("Auth/ForcePasswordChange", [
            "username" => $request->user()->username,
            "csrf_token" => csrf_token(),
        ]);
    }

    public function forceUpdate(Request $request)
    {
        $request->validate([
            "current_password" => "required|current_password",
            "password" => "required|min:12|confirmed",
        ]);

        $user = $request->user();

        if (Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                "password" => [__("auth.password_same_as_old")],
            ]);
        }

        // AGGIORNAMENTO AVVENUTO
        $user->update([
            "password" => Hash::make($request->password),
            "password_expires_at" => now()->addDays(90),
        ]);

        // 1. KILLER DELLE VECCHIE SESSIONI
        // Eliminiamo dal database tutti i vecchi token JWT associati a questo utente
        Session::where("user_id", $user->id)->delete();

        // Eliminiamo preventivamente i vecchi cookie (verranno sovrascritti o distrutti)
        // $provider = Provider::find(config("idp.provider_id"));
        $cookieName = "idp_token_" . config("idp.provider_id");
        $cookie1 = Cookie::forget($cookieName);
        $cookie2 = Cookie::forget("token");

        // 2. RIPRENDIAMO IL FLUSSO INTERROTTO
        $pendingProviderId = $request->session()->pull("pending_sso_provider_id");
        $pendingRedirectTo = $request->session()->pull("pending_sso_redirect_to");

        // Caso A: L'utente stava per andare su un'app esterna (SSO)
        if ($pendingProviderId) {
            $ssoData = TokenProviderService::respondWithSsoRedirect(
                $user,
                $pendingProviderId,
                $request,
                $pendingRedirectTo,
            );

            if ($ssoData) {
                // Genera e accoda il NUOVO cookie valido
                Cookie::queue($ssoData["cookie"]);
                // Usiamo Inertia location perché stiamo uscendo dal dominio dell'IdP
                return Inertia::location($ssoData["url"]);
            }
        }

        // Caso B: L'utente stava accedendo localmente all'IdP come Admin
        if ($user->isAdmin()) {
            $request->session()->put("success", __("auth.password_reset_success"));
            return redirect()->route("loginForm")->withCookie($cookie1)->withCookie($cookie2);
        }

        // Caso C: Qualcosa è andato storto (non è admin e non ha provider esterni autorizzati)
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route("sso.unauthorized");
    }
}
