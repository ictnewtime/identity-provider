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
            "url" => "http://localhost:8000",
            "protocol" => "http",
            "secret_key" => Str::random(32),
            "logoutUrl" => "http://localhost/logout",
            "name" => "IDP",
        ]);

        // 2. Creiamo i Ruoli base legati al Provider
        $adminRole = Role::create([
            "name" => "admin",
            "provider_id" => $provider->id,
        ]);

        $this->command->info("Database popolato con successo! Utente: admin@admin.com / Password: password");
    }
}
