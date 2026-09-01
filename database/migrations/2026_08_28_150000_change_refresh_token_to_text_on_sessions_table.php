<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table("sessions", function (Blueprint $table) {
            $table->text("refresh_token")->nullable()->change();
        });
    }

    public function down(): void
    {
        \DB::table("sessions")->update(["refresh_token" => null]);

        Schema::table("sessions", function (Blueprint $table) {
            $table->string("refresh_token")->nullable()->change();
        });
    }
};
