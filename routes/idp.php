<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ClientRoleController;
use App\Http\Controllers\JwtAuth\LoginController;
use App\Http\Controllers\JwtAuth\RegisterController;
use App\Http\Controllers\JwtAuth\VerificationController;
use App\Http\Controllers\Manage\UserController;
use App\Http\Controllers\Manage\UserRoleController;
use App\Http\Controllers\Manage\RoleController;
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
    Route::middleware(["api", "authenticated"])->get("user", [LoginController::class, "userByToken"]);
    Route::middleware(["api", "authenticated"])->get("loginWithToken", [LoginController::class, "userByToken"]); // TODO da cancellare dopo allineamento
    Route::middleware(["api", "authenticated"])->get("logout", [LoginController::class, "logout"]);

    Route::middleware("web")
        ->get("roles/{id}/user-roles", [UserRoleController::class, "getRoles"])
        ->where(["id" => "[0-9]+"]);
    Route::middleware("web")->get("client-roles", [ClientRoleController::class, "all"]);
    Route::post("complete-registration", [VerificationController::class, "verify"]);
    // TODO: valurate se eliminare dato che la registrazione è già gestita
    // nella rotta user
    // Route::post("register", [RegisterController::class, "register"]);

    // Routes to manage users
    Route::middleware(["client", "checkclientrole:manager"])->group(function () {
        Route::post("user", [UserController::class, "create"]);
        Route::post("users/{id}/user-roles", [UserRoleController::class, "create"])->where(["id" => "[0-9]+"]);
        Route::delete("user-role/{id}", [UserRoleController::class, "delete"])->where(["id" => "[0-9]+"]);
    });

    // Routes to manage idp
    Route::middleware(["client", "checkclientrole:admin"])->group(function () {
        Route::post("providers", [ProviderController::class, "create"]);
        Route::post("roles", [RoleController::class, "create"]);
        Route::delete("roles/{id}", [RoleController::class, "delete"])->where(["id" => "[0-9]+"]);
    });

    // middleware client per le rotte protetto dalla classe CheckClientCredentials
    // di Passport
    Route::middleware("client")->group(function () {
        Route::get("roles", [RoleController::class, "all"]);
        Route::get("providers", [ProviderController::class, "all"]);
        Route::get("users/{id}", [UserController::class, "find"])->where("id", "[0-9]+");
        Route::get("users/{id}/user-roles", [UserRoleController::class, "getUserRole"])->where(["id" => "[0-9]+"]);

        // Route::get("refresh-token", [LoginController::class, "refreshToken"]);
    });
});

Route::prefix("v2")->group(function () {
    Route::middleware("web")
        ->post("login", [LoginController::class, "login"])
        ->name("login");
});
