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
        $secret_key = env("JWT_SECRET", "1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef");
        DB::table("providers")->insert([
            "domain" => "idp-staging.newtimegroup.it",
            "logout_url" => "idp-staging.newtimegroup.it/logout",
            "secret_key" => $secret_key,
        ]);
    }
}
