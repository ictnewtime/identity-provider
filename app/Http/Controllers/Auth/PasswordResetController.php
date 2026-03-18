<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
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
        // La validazione finale usa l'email (che passiamo nascosta nella form)
        $request->validate([
            "token" => "required",
            "email" => "required|email",
            "password" => "required|min:12|confirmed",
            "password_confirmation" => "required|min:12",
        ]);

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
}
