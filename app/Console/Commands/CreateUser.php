<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateUser extends Command
{
    protected $signature = "app:create-user";
    protected $description = "Crea un nuovo utente nel sistema";

    public function handle()
    {
        $this->info("Creazione nuovo Utente...");

        $username = $this->ask("Username");
        $email = $this->ask("Email");
        $name = $this->ask("Nome");
        $surname = $this->ask("Cognome");
        $password = $this->secret("Password");

        $user = User::create([
            "username" => $username,
            "email" => $email,
            "name" => $name,
            "surname" => $surname,
            "password" => Hash::make($password),
            "is_verified" => true,
            "enabled" => true,
        ]);

        $this->info("Utente {$user->username} creato con successo! (ID: {$user->id})");
    }
}
