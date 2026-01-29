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
        // add column provider_id
        Schema::table("roles", function (Blueprint $table) {
            // name can be duplicated
            // $table->string('name', 20)->unique(); to remove unique
            // no index, but remove unique
            $table->string("name", 20)->unique(false)->change();
            $table->unsignedBigInteger("provider_id")->after("name")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // drop column provider_id
        Schema::table("roles", function (Blueprint $table) {
            $table->dropColumn("provider_id");
        });
    }
};
