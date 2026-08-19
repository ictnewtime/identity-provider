<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\JwtAuth\LoginController;
use App\Http\Controllers\Manage\UserController;
use App\Http\Controllers\Manage\RoleController;
use App\Http\Controllers\Manage\ProviderUserRoleController;
use App\Http\Controllers\Manage\ProviderController;
use App\Http\Controllers\Manage\SessionController;
use App\Support\RoutePaths;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix("v1")->group(function () {
    // Routes to manage idp
    // middleware client per le rotte protetto dalla classe CheckClientCredentials
    // di Passport

    Route::middleware(["password.expiration", "authenticated", "role:admin"])->group(function () {
        // providers
        Route::get("providers", [ProviderController::class, "all"]);
        Route::post("providers", [ProviderController::class, "create"]);
        Route::put(RoutePaths::PROVIDERS_ID, [ProviderController::class, "update"])->whereNumber("id");
        Route::delete(RoutePaths::PROVIDERS_ID, [ProviderController::class, "delete"])->whereNumber("id");

        // roles
        Route::get("roles", [RoleController::class, "all"]);
        Route::post("roles", [RoleController::class, "create"]);
        Route::get(RoutePaths::ROLES_ID, [RoleController::class, "find"])->whereNumber("id");
        Route::put(RoutePaths::ROLES_ID, [RoleController::class, "update"])->whereNumber("id");
        Route::delete(RoutePaths::ROLES_ID, [RoleController::class, "delete"])->whereNumber("id");

        // users
        Route::get("users", [UserController::class, "all"]);
        Route::post("users", [UserController::class, "create"]);
        Route::get(RoutePaths::USERS_ID, [UserController::class, "find"])->whereNumber("id");
        Route::put(RoutePaths::USERS_ID, [UserController::class, "update"])->whereNumber("id");
        Route::delete(RoutePaths::USERS_ID, [UserController::class, "delete"])->whereNumber("id");

        // provider-user-roles
        Route::get("provider-user-roles", [ProviderUserRoleController::class, "all"]);
        Route::post("provider-user-roles", [ProviderUserRoleController::class, "create"]);
        Route::get(RoutePaths::PROVIDER_USER_ROLES_ID, [ProviderUserRoleController::class, "find"])->whereNumber("id");
        Route::put(RoutePaths::PROVIDER_USER_ROLES_ID, [ProviderUserRoleController::class, "update"])->whereNumber("id");
        Route::delete(RoutePaths::PROVIDER_USER_ROLES_ID, [ProviderUserRoleController::class, "delete"])->whereNumber("id");
        // provider-user-roles/has-relation?provider_id=1&user_id=1
        // provider-user-roles/has-relation?role_id=1
        Route::get("provider-user-roles/has-relation", [ProviderUserRoleController::class, "hasRelation"]);
    });

    // Route::middleware(["client"])
    Route::middleware(["client"])->group(function () {
        // USERS
        Route::get("users", [UserController::class, "all"]);
        Route::post("users", [UserController::class, "create"]);
        Route::get(RoutePaths::USERS_ID, [UserController::class, "find"])->whereNumber("id");
        Route::put(RoutePaths::USERS_ID, [UserController::class, "update"])->whereNumber("id");
        Route::delete(RoutePaths::USERS_ID, [UserController::class, "delete"])->whereNumber("id");

        // ROLES
        Route::get("roles", [RoleController::class, "all"]);
        Route::post("roles", [RoleController::class, "create"]);
        Route::get(RoutePaths::ROLES_ID, [RoleController::class, "find"])->whereNumber("id");
        Route::put(RoutePaths::ROLES_ID, [RoleController::class, "update"])->whereNumber("id");
        Route::delete(RoutePaths::ROLES_ID, [RoleController::class, "delete"])->whereNumber("id");

        // PROVIDERS
        Route::get("providers", [ProviderController::class, "all"]);
        Route::post("providers", [ProviderController::class, "create"]);
        Route::get(RoutePaths::PROVIDERS_ID, [ProviderController::class, "find"])->whereNumber("id");
        Route::put(RoutePaths::PROVIDERS_ID, [ProviderController::class, "update"])->whereNumber("id");
        Route::delete(RoutePaths::PROVIDERS_ID, [ProviderController::class, "delete"])->whereNumber("id");

        // PROVIDER-USER-ROLES
        Route::get("provider-user-roles", [ProviderUserRoleController::class, "all"]);
        Route::post("provider-user-roles", [ProviderUserRoleController::class, "create"]);
        Route::get(RoutePaths::PROVIDER_USER_ROLES_ID, [ProviderUserRoleController::class, "find"])->whereNumber("id");
        Route::put(RoutePaths::PROVIDER_USER_ROLES_ID, [ProviderUserRoleController::class, "update"])->whereNumber("id");
        Route::delete(RoutePaths::PROVIDER_USER_ROLES_ID, [ProviderUserRoleController::class, "delete"])->whereNumber("id");
    });

    // sessions
    Route::middleware(["verify_external_token"])->group(function () {
        Route::get("sessions/check", [SessionController::class, "check"]);
        // Route::post("sessions/logout", [SessionController::class, "logout_session"]);
    });
    Route::middleware(["verify_master_token"])->group(function () {
        Route::post("token/exchange", [SessionController::class, "get_token"]);
        // Route::post("sessions/logout", [SessionController::class, "logout_session"]);
    });
});
