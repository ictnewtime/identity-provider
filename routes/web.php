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
use Illuminate\Support\Facades\Log;

// 1. Redirect Home -> Login
Route::get("/", function () {
    return redirect()->route("loginForm");
});

// 2. Lingua
Route::get("locale/{locale}", function ($locale) {
    Session::put("locale", $locale);
    return redirect()->back();
});

// 3. Autenticazione
Route::middleware("guest")->group(function () {
    Route::get("loginForm", [LoginController::class, "showLoginForm"])->name("loginForm");
    Route::get("login", function () {
        return redirect()->route("loginForm");
    });
});

Route::post("v2/login", [LoginController::class, "login"])->name("login");
Route::post("logout", [LoginController::class, "logout_web"])->name("logout_web");
Route::get("/sso/logout", [LoginController::class, "logout_sso"])->name("logout_sso");

// Route::middleware("web.authenticated")
//     ->get("authenticated", [LoginController::class, "authenticated"])
//     ->name("authenticated");

// Inertia: Pagina completamento registrazione
Route::get("complete-registration", function () {
    return Inertia::render("Auth/CompleteRegistration");
})->name("complete-registration");

/********* ADMIN ROUTES ************/

Route::prefix("admin")
    ->middleware(["authenticated", "role:admin"])
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

        Route::get("sessions", function () {
            return Inertia::render("Admin/Sessions");
        })->name("web-sessions");

        Route::get("audits", function () {
            return Inertia::render("Admin/Audits");
        })->name("web-audits");

        // Route::get("oauth-clients", function () {
        //     return Inertia::render("Admin/OauthClients");
        // })->name("oauth-clients");

        Route::prefix("v1")->group(function () {
            // providers
            Route::get("providers", [ProviderController::class, "all"]);
            Route::post("providers", [ProviderController::class, "create"]);
            Route::get("providers/{id}", [ProviderController::class, "find"])->whereNumber("id");
            Route::put("providers/{id}", [ProviderController::class, "update"])->whereNumber("id");
            Route::delete("providers/{id}", [ProviderController::class, "delete"])->whereNumber("id");

            // roles
            Route::get("roles", [RoleController::class, "all"]);
            Route::post("roles", [RoleController::class, "create"]);
            Route::get("roles/{id}", [RoleController::class, "find"])->whereNumber("id");
            Route::put("roles/{id}", [RoleController::class, "update"])->whereNumber("id");
            Route::delete("roles/{id}", [RoleController::class, "delete"])->whereNumber("id");

            // users
            Route::get("users", [UserController::class, "all"]);
            Route::post("users", [UserController::class, "create"]);
            Route::get("users/{id}", [UserController::class, "find"])->whereNumber("id");
            Route::put("users/{id}", [UserController::class, "update"])->whereNumber("id");
            Route::delete("users/{id}", [UserController::class, "delete"])->whereNumber("id");

            // provider-user-roles
            Route::get("provider-user-roles", [ProviderUserRoleController::class, "all"]);
            Route::post("provider-user-roles", [ProviderUserRoleController::class, "create"]);
            Route::get("provider-user-roles/{id}", [ProviderUserRoleController::class, "find"])->whereNumber("id");
            Route::put("provider-user-roles/{id}", [ProviderUserRoleController::class, "update"])->whereNumber("id");
            Route::delete("provider-user-roles/{id}", [ProviderUserRoleController::class, "delete"])->whereNumber("id");
            Route::get("provider-user-roles/has-relation", [ProviderUserRoleController::class, "hasRelation"]);

            // sessions
            Route::get("sessions", [SessionController::class, "all"]);
            // id is uuid
            Route::delete("sessions/{id}", [SessionController::class, "delete"])->where(
                "id",
                "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}",
            );

            // audit
            Route::get("audits", [AuditController::class, "all"]);
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
