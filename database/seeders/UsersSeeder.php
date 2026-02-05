<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        DB::table("users")->insert([
            "email" => "mario.rossi@example.com",
            "username" => "mario.rossi",
            "password" => Hash::make("secret"),
            "is_verified" => true,
            "name" => "Mario",
            "surname" => "Rossi",
        ]);
    }
}
