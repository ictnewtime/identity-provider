<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        //
        Schema::table("users", function (Blueprint $table) {
            // email can be duplicated
            $table->string("email")->unique(false)->change();
            // creo il campo username e copio i valori della colonna email nella colonna username
            // con il comando
            // php artisan app:copy-column table:users email username
            $table->string("username")->after("id");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::table("users", function (Blueprint $table) {
            $table->dropColumn("username");
            $table->string("email")->unique()->change();
        });
    }
};
