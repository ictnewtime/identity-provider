<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table("users", function (Blueprint $table) {
            // Aggiunge il flag abilitato. Di default true (o false se preferisci l'attivazione manuale)
            $table->boolean("enabled")->after("password");
        });
    }

    public function down()
    {
        Schema::table("users", function (Blueprint $table) {
            $table->dropColumn("enabled");
        });
    }
};
