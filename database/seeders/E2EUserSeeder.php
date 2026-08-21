<?php

namespace Database\Seeders;

use App\Models\Provider;
use App\Models\ProviderUserRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Exceptions\SeedingException;

/**
 * Crea gli utenti dedicati ai test E2E leggendo le password dall'ambiente.
 *
 * Le password NON stanno qui e non hanno un valore di ripiego: le genera
 * scripts/prepare-e2e-credentials.sh a ogni preparazione dell'ambiente, e le passa
 * come variabili. Un segreto che non esiste prima dell'esecuzione non si puo' esporre.
 *
 * Punto TPU/TSA11 di docs/task/done/20260812-static-analysis-findings-v1/.
 */
class E2EUserSeeder extends Seeder
{
    /**
     * Nomi delle variabili attese. I valori non compaiono mai in questo file (R6).
     */
    private const REQUIRED_ENV = ["E2E_ADMIN_USERNAME", "E2E_ADMIN_PASSWORD", "E2E_USER_USERNAME", "E2E_USER_PASSWORD"];

    public function run(): void
    {
        $env = $this->readRequiredEnv();

        $provider = Provider::find(config("idp.provider_id"));

        if (empty($provider)) {
            throw new SeedingException(
                "Provider IdP " . config("idp.provider_id") . " non trovato: eseguire prima DatabaseSeeder.",
            );
        }

        $adminRole = Role::where("provider_id", $provider->id)->where("name", "admin")->first();

        if (empty($adminRole)) {
            throw new SeedingException("Ruolo 'admin' del provider IdP non trovato: eseguire prima DatabaseSeeder.");
        }

        // L'utente amministratore dei test: stesso ruolo dell'admin reale, identita' separata,
        // cosi' le tracce di audit dei test non si mescolano con quelle di una persona (D8).
        $admin = $this->upsertUser($env["E2E_ADMIN_USERNAME"], $env["E2E_ADMIN_PASSWORD"]);

        ProviderUserRole::updateOrCreate([
            "provider_id" => $provider->id,
            "user_id" => $admin->id,
            "role_id" => $adminRole->id,
        ]);

        // L'utente non amministratore: nessun ruolo assegnato. E' la definizione piu' semplice
        // di "non admin" e non inventa ruoli che il dominio non ha.
        $this->upsertUser($env["E2E_USER_USERNAME"], $env["E2E_USER_PASSWORD"]);
    }

    /**
     * Legge le variabili obbligatorie. Se ne manca una si ferma: inventare una password
     * qui riporterebbe il difetto che questo seeder esiste per togliere (VDF08).
     */
    private function readRequiredEnv(): array
    {
        $values = [];
        $missing = [];

        foreach (self::REQUIRED_ENV as $name) {
            $value = env($name);

            if (empty($value)) {
                $missing[] = $name;
                continue;
            }

            $values[$name] = $value;
        }

        if (!empty($missing)) {
            throw new SeedingException(
                "Variabili mancanti: " .
                    implode(", ", $missing) .
                    ". Eseguire ./scripts/prepare-e2e-credentials.sh, che le genera e le esporta.",
            );
        }

        return $values;
    }

    /**
     * Crea o aggiorna l'utente: rieseguire il seeder rigenera la password senza duplicare l'utente.
     */
    private function upsertUser(string $username, string $password): User
    {
        return User::updateOrCreate(
            ["username" => $username],
            [
                "password" => Hash::make($password),
                "email" => $username . "@e2e.local",
                "name" => "E2E",
                "surname" => $username,
                "is_verified" => 1,
                "enabled" => 1,
            ],
        );
    }
}
