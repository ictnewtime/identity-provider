<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Parameter;
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

        $user = User::where("email", $request->email)->first();

        if ($user && Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                "password" => [__("auth.password_same_as_old")],
            ]);
        }

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
            "new_password" => "required|min:12|confirmed",
        ]);

        $user = $request->user();

        if (Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                "new_password" => [__("auth.password_same_as_old")],
            ]);
        }

        $add_day = 90;
        try {
            $add_day = (int) Parameter::where("key", "password-force-reset-day")->first()->value;
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
        try {
            $user->update([
                "password" => Hash::make($request->new_password),
                "password_expires_at" => now()->addDays($add_day),
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }

        Session::where("user_id", $user->id)->delete();

        $cookieName = "idp_token_" . config("idp.provider_id");
        $cookie1 = Cookie::forget($cookieName);
        $cookie2 = Cookie::forget("token");

        $pendingProviderId = $request->session()->pull("pending_sso_provider_id");
        $pendingRedirectTo = $request->session()->pull("pending_sso_redirect_to");

        if ($pendingProviderId) {
            $ssoData = TokenProviderService::respondWithSsoRedirect(
                $user,
                $pendingProviderId,
                $request,
                $pendingRedirectTo,
            );

            if ($ssoData) {
                Cookie::queue($ssoData["cookie"]);
                return Inertia::location($ssoData["url"]);
            }
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($user->isAdmin()) {
            $request->session()->put("success", __("auth.password_reset_success"));
            return redirect()->route("loginForm")->withCookie($cookie1)->withCookie($cookie2);
        }

        return redirect()->route("sso.unauthorized");
    }
}
