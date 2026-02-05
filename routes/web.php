<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\JwtAuth\LoginController;
use App\Http\Controllers\Manage\ProviderController;
use App\Http\Controllers\Manage\UserController;
use App\Http\Controllers\Manage\OauthClientsController;
use App\Http\Controllers\Manage\ProviderUserRoleController;
use App\Http\Controllers\Manage\RoleController;

Route::get("/", function () {
    return redirect("loginForm");
});

Route::get("locale/{locale}", function ($locale) {
    Session::put("locale", $locale);
    return redirect()->back();
});

Route::middleware("guest")
    ->get("loginForm", [LoginController::class, "showLoginForm"])
    ->name("loginForm");

Route::get("logout", [LoginController::class, "logout"])->name("logout");

Route::middleware("web.authenticated")
    ->get("authenticated", [LoginController::class, "authenticated"])
    ->name("authenticated");

Route::get("complete-registration", function () {
    return view("auth.complete-registration-form");
})->name("complete-registration");

/********* ADMIN ROUTES ************/

Route::prefix("admin")
    ->middleware("role:admin")
    ->group(function () {
        Route::get("/", function () {
            return redirect()->route("web-users");
        })->name("board");

        Route::get("users", function () {
            return view("admin.users");
        })->name("web-users");

        Route::get("providers", function () {
            return view("admin.create-provider");
        })->name("web-providers");

        Route::get("roles", function () {
            return view("admin.create-role");
        })->name("web-roles");

        Route::get("oauth-clients", function () {
            return view("admin.oauth-clients");
        })->name("oauth-clients");

        // Route::get("oauth-clients-all", [OauthClientsController::class, "all"]);
        // Route::put("update-roles", [OauthClientsController::class, "updateClientRoles"]);

        Route::prefix("v1")->group(function () {
            Route::get("providers", [ProviderController::class, "all"]);
            Route::post("providers", [ProviderController::class, "create"]);
            Route::get("providers/{id}", [ProviderController::class, "find"])->where(["id" => "[0-9]+"]);
            Route::put("providers/{id}", [ProviderController::class, "update"])->where(["id" => "[0-9]+"]);
            Route::delete("providers/{id}", [ProviderController::class, "delete"])->where(["id" => "[0-9]+"]);

            // roles
            Route::get("roles", [RoleController::class, "all"]);
            Route::post("roles", [RoleController::class, "create"]);
            Route::get("roles/{id}", [RoleController::class, "find"])->where(["id" => "[0-9]+"]);
            Route::put("roles/{id}", [RoleController::class, "update"])->where(["id" => "[0-9]+"]);
            Route::delete("roles/{id}", [RoleController::class, "delete"])->where(["id" => "[0-9]+"]);

            // users
            Route::get("users", [UserController::class, "all"]);
            Route::post("users", [UserController::class, "create"]);
            Route::get("users/{id}", [UserController::class, "find"])->where(["id" => "[0-9]+"]);
            Route::put("users/{id}", [UserController::class, "update"])->where(["id" => "[0-9]+"]);
            Route::delete("users/{id}", [UserController::class, "delete"])->where(["id" => "[0-9]+"]);

            // provider-user-roles
            Route::get("provider-user-roles", [ProviderUserRoleController::class, "all"]);
            Route::post("provider-user-roles", [ProviderUserRoleController::class, "create"]);
            Route::get("provider-user-roles/{id}", [ProviderUserRoleController::class, "find"])->where([
                "id" => "[0-9]+",
            ]);
            Route::put("provider-user-roles/{id}", [ProviderUserRoleController::class, "update"])->where([
                "id" => "[0-9]+",
            ]);
            Route::delete("provider-user-roles/{id}", [ProviderUserRoleController::class, "delete"])->where([
                "id" => "[0-9]+",
            ]);
            // provider-user-roles/has-relation?provider_id=1&user_id=1
            // provider-user-roles/has-relation?role_id=1
            Route::get("provider-user-roles/has-relation", [ProviderUserRoleController::class, "hasRelation"]);
        });
    });

Route::prefix("v2")->group(function () {
    Route::middleware("web")
        ->post("login", [LoginController::class, "login"])
        ->name("login");
});
