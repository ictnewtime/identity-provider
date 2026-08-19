<?php

namespace Database\Seeders;

use App\Models\Parameter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Provider;
use App\Models\Role;
use App\Models\ProviderUserRole;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /** L'amministratore creato dal seeder: e' anche il segno che il seeder e' gia' passato. */
    private const ADMIN_USERNAME = "admin.admin";

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminPassword = $this->getAdminPassword();

        DB::transaction(function () use ($adminPassword) {
            $this->seeding($adminPassword);
        });
    }

    private function getAdminPassword(): string
    {
        $password = env("SEED_ADMIN_PASSWORD");

        if (empty($password)) {
            throw new RuntimeException(
                "SEED_ADMIN_PASSWORD non impostata: il seeder non inventa la password di un amministratore. " .
                    "Impostarla nell'ambiente prima di eseguire db:seed (docs/setup.db.md).",
            );
        }

        return $password;
    }

    /**
     * Il seeder si esegue UNA volta sola: la seconda deve fallire in modo comprensibile.
     *
     * Senza questo controllo il rifiuto arriva lo stesso, ma dal database — un
     * `UNIQUE constraint failed: users.username` che dice cosa e' successo e non cosa fare.
     * Qui arriva dal codice, e porta con se' il rimedio.
     */
    private function assertNotAlreadySeeded(): void
    {
        if (!User::where("username", self::ADMIN_USERNAME)->exists()) {
            return;
        }

        throw new RuntimeException(
            "Il database contiene gia' i dati iniziali (utente '" .
                self::ADMIN_USERNAME .
                "'): db:seed non e' rieseguibile.\n" .
                "Per riseminare da zero — ATTENZIONE, cancella tutti i dati:\n" .
                "  php artisan migrate:fresh --force\n" .
                "  SEED_ADMIN_PASSWORD='...' php artisan db:seed",
        );
    }

    private function seeding(string $adminPassword): void
    {
        // Prima riga della transazione: se scatta, non e' ancora stato scritto niente.
        $this->assertNotAlreadySeeded();

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
            "username" => self::ADMIN_USERNAME,
            "password" => Hash::make($adminPassword),
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
