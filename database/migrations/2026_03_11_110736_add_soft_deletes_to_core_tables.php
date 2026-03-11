<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Aggiungiamo la colonna deleted_at a tutte e 4 le tabelle
        Schema::table("users", function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table("roles", function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table("providers", function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table("provider_user_roles", function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        // Rimuoviamo la colonna in caso di rollback
        Schema::table("users", function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table("roles", function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table("providers", function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table("provider_user_roles", function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
