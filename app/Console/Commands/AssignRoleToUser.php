<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Provider;
use App\Models\Role;
use App\Models\ProviderUserRole;
use function Laravel\Prompts\search;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

class AssignRoleToUser extends Command
{
    protected $signature = "app:assign-role";
    protected $description = "Associa un ruolo a un utente per un determinato provider";

    public function handle()
    {
        if (User::count() === 0 || Provider::count() === 0 || Role::count() === 0) {
            error("Assicurati che ci sia almeno 1 Utente, 1 Provider e 1 Ruolo nel database.");
            return Command::FAILURE;
        }

        info("Assegnazione Ruolo a Utente...");

        $userId = search(
            label: 'Cerca l\'Utente (digita username o email)',
            options: fn(string $value) => strlen($value) > 0
                ? User::where("username", "like", "%{$value}%")
                    ->orWhere("email", "like", "%{$value}%")
                    ->selectRaw("CONCAT(username, ' (', email, ')') as label, id")
                    ->pluck("label", "id")
                    ->all()
                : User::limit(10)
                    ->selectRaw("CONCAT(username, ' (', email, ')') as label, id")
                    ->pluck("label", "id")
                    ->all(),
        );

        $providerId = search(
            label: "Cerca il Provider",
            options: fn(string $value) => strlen($value) > 0
                ? Provider::where("name", "like", "%{$value}%")
                    ->pluck("name", "id")
                    ->all()
                : Provider::limit(10)->pluck("name", "id")->all(),
        );

        if (Role::where("provider_id", $providerId)->doesntExist()) {
            error("Questo provider non ha ancora ruoli associati. Creane prima uno!");
            return Command::FAILURE;
        }

        $roleId = search(
            label: "Cerca il Ruolo da assegnare",
            options: fn(string $value) => strlen($value) > 0
                ? Role::where("provider_id", $providerId)
                    ->where("name", "like", "%{$value}%")
                    ->pluck("name", "id")
                    ->all()
                : Role::where("provider_id", $providerId)->limit(10)->pluck("name", "id")->all(),
        );

        $alreadyAssigned = ProviderUserRole::where([
            "user_id" => $userId,
            "provider_id" => $providerId,
            "role_id" => $roleId,
        ])->exists();

        if ($alreadyAssigned) {
            error('Attenzione: L\'utente possiede già questo ruolo per questo provider!');
            return Command::FAILURE;
        }

        ProviderUserRole::create([
            "user_id" => $userId,
            "provider_id" => $providerId,
            "role_id" => $roleId,
        ]);

        info("Successo! Assegnazione completata e salvata nel database.");
        return Command::SUCCESS;
    }
}
