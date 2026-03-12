<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Role;
use App\Models\Provider;
use function Laravel\Prompts\text;
use function Laravel\Prompts\search;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

class CreateRole extends Command
{
    protected $signature = "app:create-role";
    protected $description = "Crea un ruolo associato a un provider";

    public function handle()
    {
        if (Provider::count() === 0) {
            error("Nessun Provider nel database. Crea prima un Provider!");
            return Command::FAILURE;
        }

        info("Creazione nuovo Ruolo...");

        $name = text(label: "Nome del Ruolo", required: true);

        $providerId = search(
            label: "Cerca e seleziona il Provider (digita per cercare)",
            options: fn(string $value) => strlen($value) > 0
                ? Provider::where("name", "like", "%{$value}%")
                    ->pluck("name", "id")
                    ->all()
                : Provider::limit(10)->pluck("name", "id")->all(),
        );

        $role = Role::create([
            "name" => $name,
            "provider_id" => $providerId,
        ]);

        info("Ruolo '{$role->name}' creato con successo per il provider selezionato!");
        return Command::SUCCESS;
    }
}
