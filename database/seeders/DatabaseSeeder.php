<?php

namespace Database\Seeders;

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
        // 1. Creiamo un Provider di default (es. il pannello di amministrazione stesso)
        $provider = Provider::create([
            "domain" => "localhost",
            "logoutUrl" => "http://localhost/logout",
            "secret_key" => Str::random(32),
            "protocol" => "http",
        ]);

        // 2. Creiamo i Ruoli base legati al Provider
        $adminRole = Role::create([
            "name" => "admin",
            "provider_id" => $provider->id,
        ]);

        $userRole = Role::create([
            "name" => "user",
            "provider_id" => $provider->id,
        ]);

        // 3. Creiamo l'Utente Amministratore
        $adminUser = User::create([
            "username" => "admin",
            "email" => "admin@admin.com",
            "password" => Hash::make("password");
            "name" => "Admin",
            "surname" => "System",
            "is_verified" => true,
            "enabled" => true,
        ]);

        // 4. Colleghiamo l'Utente al Provider con il Ruolo di Admin
        ProviderUserRole::create([
            "user_id" => $adminUser->id,
            "provider_id" => $provider->id,
            "role_id" => $adminRole->id,
        ]);

        $this->command->info("Database popolato con successo! Utente: admin@admin.com / Password: password");
    }
}
