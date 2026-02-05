<?php

namespace Tests\Utility;

use App\Models\User;
use App\Models\Role;
// use App\Models\UserRole;
use App\Models\ProviderUserRole;
use App\Models\Provider;
// use Illuminate\Support\Facades\Hash;

class UserUtility
{
    /**
     * Get admin user
     *
     * @return User
     */
    public static function getAdmin()
    {
        // creare ruolo admin
        $role = Role::where("name", "admin")->first();
        if (empty($role)) {
            $role = Role::create(["name" => "admin"]);
        }

        // get Provider from PROVIDER_DOMAIN environment variable
        $provider = Provider::where("domain", env("PROVIDER_DOMAIN"))->first();

        if (empty($provider)) {
            // creare provider
            $provider = Provider::create([
                "domain" => env("PROVIDER_DOMAIN"),
                "logoutUrl" => env("PROVIDER_DOMAIN"),
                "secret_key" => "secret",
            ]);
        }

        // creare user
        $user = User::where("email", "admin@localhost")->first();
        if (empty($user)) {
            $user = User::create([
                "name" => "admin",
                "surname" => "admin",
                "email" => "admin@localhost",
                "username" => "admin.admin",
                "password" => "secret",
                "is_verified" => true,
            ]);
        }

        $providerUserRole = ProviderUserRole::where("provider_id", $provider->id)->where("role_id", $role->id)->first();

        if (empty($providerUserRole)) {
            // creare provider user role
            $providerUserRole = ProviderUserRole::create([
                "provider_id" => $provider->id,
                "user_id" => $user->id,
                "role_id" => $role->id,
            ]);
        }

        $user = User::where("id", $providerUserRole->user_id)->first();

        if (empty($user)) {
            throw new \Exception("User not found");
        }
        return $user;
    }
}
