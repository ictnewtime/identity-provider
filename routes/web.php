<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use App\Http\Controllers\JwtAuth\LoginController;
use App\Http\Controllers\Manage\ProviderController;
use App\Http\Controllers\Manage\UserController;
// use App\Http\Controllers\Manage\OauthClientsController;
use App\Http\Controllers\Manage\ProviderUserRoleController;
use App\Http\Controllers\Manage\RoleController;
use App\Http\Controllers\Manage\SessionController;
use App\Http\Controllers\Manage\AuditController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Manage\ParametersController;
use Illuminate\Support\Facades\Log;

// Redirect Home -> Login
Route::get("/", function () {
    return redirect()->route("loginForm");
});

// Lingua
Route::get("lang/{locale}", function ($locale) {
    $availableLocales = ["it", "en"];
    $targetLocale = in_array($locale, $availableLocales) ? $locale : "it";
    session(["locale" => $targetLocale]);
    return redirect()->back();
})->name("lang.switch");

// Autenticazione
Route::middleware("guest")->group(function () {
    Route::get("loginForm", [LoginController::class, "showLoginForm"])->name("loginForm");
    Route::get("login", function () {
        return redirect()->route("loginForm");
    })->name("login");
    Route::get("/forgot-password", [PasswordResetController::class, "create"])->name("password.request");
    Route::post("/forgot-password", [PasswordResetController::class, "store"])->name("password.email");
    Route::get("/reset-password/{token}", [PasswordResetController::class, "edit"])->name("password.reset");
    Route::post("/reset-password", [PasswordResetController::class, "update"])->name("password.update");
});

Route::post("v2/login", [LoginController::class, "login"]);
Route::post("logout", [LoginController::class, "logout_web"])->name("logout_web");
Route::get("/sso/logout", [LoginController::class, "logout_sso"])->name("logout_sso");

Route::middleware(["auth"])->group(function () {
    Route::get("/password/expired", [PasswordResetController::class, "expired"])->name("password.expired");

    Route::post("/password/force-update", [PasswordResetController::class, "forceUpdate"])->name(
        "password.force-update",
    );
});

/********* ADMIN ROUTES ************/
Route::prefix("admin")
    ->middleware(["password.expiration", "authenticated", "role:admin"])
    ->group(function () {
        Route::get("/", function () {
            return redirect()->route("web-users");
        })->name("admin-home");

        Route::get("users", function () {
            return Inertia::render("Admin/Users");
        })->name("web-users");

        Route::get("providers", function () {
            return Inertia::render("Admin/Providers");
        })->name("web-providers");

        Route::get("roles", function () {
            return Inertia::render("Admin/Roles");
        })->name("web-roles");

        Route::get("provider-user-roles", function () {
            return Inertia::render("Admin/ProviderUserRoles");
        })->name("web-provider-user-roles");

        Route::get("parameters", function () {
            return Inertia::render("Admin/Parameters");
        })->name("web-parameters");

        Route::get("sessions", function () {
            return Inertia::render("Admin/Sessions");
        })->name("web-sessions");

        Route::get("audits", function () {
            return Inertia::render("Admin/Audits");
        })->name("web-audits");

        Route::prefix("v1")->group(function () {
            // providers
            Route::get("providers", [ProviderController::class, "all"]);
            Route::post("providers", [ProviderController::class, "create"]);
            Route::get("providers/{id}", [ProviderController::class, "find"])->whereNumber("id");
            Route::put("providers/{id}", [ProviderController::class, "update"])->whereNumber("id");
            Route::delete("providers/{id}", [ProviderController::class, "delete"])->whereNumber("id");
            Route::patch("providers/{id}/restore", [ProviderController::class, "restore"])->whereNumber("id");

            // roles
            Route::get("roles", [RoleController::class, "all"]);
            Route::post("roles", [RoleController::class, "create"]);
            Route::get("roles/{id}", [RoleController::class, "find"])->whereNumber("id");
            Route::put("roles/{id}", [RoleController::class, "update"])->whereNumber("id");
            Route::delete("roles/{id}", [RoleController::class, "delete"])->whereNumber("id");
            Route::patch("roles/{id}/restore", [RoleController::class, "restore"])->whereNumber("id");

            // users
            Route::get("users", [UserController::class, "all"]);
            Route::post("users", [UserController::class, "create"]);
            Route::get("users/{id}", [UserController::class, "find"])->whereNumber("id");
            Route::put("users/{id}", [UserController::class, "update"])->whereNumber("id");
            Route::delete("users/{id}", [UserController::class, "delete"])->whereNumber("id");
            Route::patch("users/{id}/restore", [UserController::class, "restore"])->whereNumber("id");

            // provider-user-roles
            Route::get("provider-user-roles", [ProviderUserRoleController::class, "all"]);
            Route::post("provider-user-roles", [ProviderUserRoleController::class, "create"]);
            Route::get("provider-user-roles/{id}", [ProviderUserRoleController::class, "find"])->whereNumber("id");
            Route::put("provider-user-roles/{id}", [ProviderUserRoleController::class, "update"])->whereNumber("id");
            Route::delete("provider-user-roles/{id}", [ProviderUserRoleController::class, "delete"])->whereNumber("id");
            Route::get("provider-user-roles/has-relation", [ProviderUserRoleController::class, "hasRelation"]);
            Route::delete("provider-user-roles/bulk-delete", [ProviderUserRoleController::class, "bulk_delete"]);
            Route::patch("provider-user-roles/{id}/restore", [
                ProviderUserRoleController::class,
                "restore",
            ])->whereNumber("id");
            Route::patch("provider-user-roles/bulk-restore", [ProviderUserRoleController::class, "bulk_restore"]);

            // sessions
            Route::get("sessions", [SessionController::class, "all"]);
            // id is uuid
            Route::delete("sessions/{id}", [SessionController::class, "delete"])->where(
                "id",
                "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}",
            );

            // audit
            Route::get("audits", [AuditController::class, "all"]);

            // parameters
            Route::get("parameters", [ParametersController::class, "all"]);
            Route::post("parameters", [ParametersController::class, "create"]);
            Route::get("parameters/{id}", [ParametersController::class, "find"])->whereNumber("id");
            Route::put("parameters/{id}", [ParametersController::class, "update"])->whereNumber("id");
            Route::delete("parameters/{id}", [ParametersController::class, "delete"])->whereNumber("id");
            Route::patch("parameters/{id}/restore", [ParametersController::class, "restore"])->whereNumber("id");
        });
    });

/********** END ADMIN ROUTES ************/

/********** CLIENT ROUTES ************/

Route::prefix("client")
    ->middleware(["web"])
    ->group(function () {
        Route::prefix("v1")->group(function () {
            Route::get("unauthorized", function () {
                return Inertia::render("Client/Unauthorized");
            })->name("sso.unauthorized");
        });
    });
