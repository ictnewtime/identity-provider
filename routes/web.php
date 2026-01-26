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
use App\Http\Controllers\Manage\RoleController;

Route::get('/', function () {
    return redirect('loginForm');
});

Route::get('locale/{locale}', function ($locale) {
    Session::put('locale', $locale);
    return redirect()->back();
});

Route::middleware('guest')
    ->get('loginForm', [LoginController::class, 'showLoginForm'])
    ->name('loginForm');

Route::get('logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware('web.authenticated')
    ->get('authenticated', [LoginController::class, 'authenticated'])
    ->name('authenticated');

Route::get('complete-registration', function () {
    return view('auth.complete-registration-form');
})->name('complete-registration');

/********* ADMIN ROUTES ************/

Route::prefix('admin')->middleware('role:ADMIN_IDP')->group(function () {

    Route::get('/', function () {
        return redirect()->route('users-panel');
    })->name('admin-board');

    Route::post('users', [UserController::class, 'create']);

    Route::get('users-panel', function () {
        return view('admin.users');
    })->name('users-panel');

    Route::post('providers', [ProviderController::class, 'create'] );

    Route::get('create-provider', function () {
        return view('admin.create-provider');
    })->name('create-provider');

    Route::get('oauth-clients', function () {
        return view('admin.oauth-clients');
    })->name('oauth-clients');

    Route::get('oauth-clients-all', [OauthClientsController::class, 'all']);
    Route::put('update-roles', [OauthClientsController::class, 'updateClientRoles']);

    Route::get('roles', [RoleController::class, 'all'] );
    Route::post('roles', [RoleController::class, 'create'] );
    Route::delete('roles/{id}', [RoleController::class, 'delete'] )->where(['id' => '[0-9]+']);

    Route::get('users', [UserController::class, 'all'] );

    Route::get('manage-role', function () {
        return view('admin.create-role');
    })->name('manage-role');
});
