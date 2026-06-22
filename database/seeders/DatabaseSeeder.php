<?php

namespace Database\Seeders;

use App\Models\Parameter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Provider;
use App\Models\Role;
use App\Models\ProviderUserRole;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Creiamo un Provider di default (es. il pannello di amministrazione stesso)
        $provider = Provider::create([
            "domain" => "localhost",
            "url" => "http://localhost:8001",
            "protocol" => "http",
            "secret_key" => Str::random(32),
            "logoutUrl" => "http://localhost:8001/logout",
            "name" => "IDP",
        ]);

        // Creiamo i Ruoli base legati al Provider
        $role = Role::create([
            "name" => "admin",
            "provider_id" => $provider->id,
        ]);

        $user = User::create([
            "username" => "admin.admin",
            "password" => Hash::make("HtZhs96xZ%7LhF"),
            "email" => "admin.admin@example.com",
            "name" => "admin",
            "surname" => "admin",
            "is_verified" => 1,
            "enabled" => 1,
        ]);

        ProviderUserRole::create([
            "provider_id" => $provider->id,
            "user_id" => $user->id,
            "role_id" => $role->id,
        ]);

        $parameters = [
            ["key" => "password-force-reset-day", "value" => 90, "type" => "policy"],
            ["key" => "master-token-exp-time-seconds", "value" => 28800, "type" => "token"],
            ["key" => "app-token-exp-time-seconds", "value" => 1800, "type" => "token"],
        ];

        foreach ($parameters as $parameter) {
            Parameter::updateOrCreate(
                ["key" => $parameter["key"]],
                ["value" => $parameter["value"], "type" => $parameter["type"]],
            );
        }
    }
}
