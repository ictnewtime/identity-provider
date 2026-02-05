<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\JwtAuth\LoginController;
use App\Http\Controllers\Manage\UserController;
use App\Http\Controllers\Manage\RoleController;
use App\Http\Controllers\Manage\ProviderUserRoleController;
use App\Http\Controllers\Manage\ProviderController;

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

    Route::middleware(["client"])->group(function () {
        // providers
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
