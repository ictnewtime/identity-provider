<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Provider;

class CreateProvider extends Command
{
    protected $signature = "app:create-provider";
    protected $description = "Crea un nuovo provider";

    public function handle()
    {
        $this->info("Creazione nuovo Provider...");

        $name = $this->ask("Nome Provider");
        $domain = $this->ask("Dominio (es. example.com)");
        $url = $this->ask("URL");
        $protocol = $this->choice("Protocollo", ["http", "https"], 1);
        $secretKey = $this->ask("Secret Key (lascia vuoto per generarne una casuale)");
        $logoutUrl = $this->ask("Logout URL");

        $provider = Provider::create([
            "name" => $name,
            "domain" => $domain,
            "url" => $url,
            "protocol" => $protocol,
            "secret_key" => $secretKey ?: \Illuminate\Support\Str::random(32),
            "logoutUrl" => $logoutUrl,
        ]);

        $this->info("Provider {$provider->name} creato con successo! (ID: {$provider->id})");
    }
}
