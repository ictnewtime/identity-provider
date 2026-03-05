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
        Schema::table("sessions", function (Blueprint $table) {
            // user_agent delete
            $table->dropColumn("user_agent");
            $table->dropColumn("last_activity");

            // al posto di user_id, role_id e provider_id uso provider_user_role_id
            $table->unsignedInteger("provider_id")->after("user_id");
            $table->foreign("provider_id")->references("id")->on("providers")->onDelete("cascade");

            // ip_address a string 255
            $table->string("ip_address", 255)->nullable()->change();
            // cambio il payload in token
            $table->renameColumn("payload", "token")->nullable()->after("provider_id");
            // aggiungo il refresh_token
            $table->string("refresh_token")->nullable()->after("token");
            // aggiungo il expires_at e last_activity
            $table->timestamp("expires_at")->nullable()->after("refresh_token");
            $table->timestamp("last_activity")->nullable()->after("refresh_token");

            // the column updated_at and created_at can be implemented by default
            $table->timestamp("created_at")->useCurrent();
            $table->timestamp("updated_at")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table("sessions", function (Blueprint $table) {
            // $table->unsignedInteger("user_id")->nullable();
            // $table->foreign("user_id")->references("id")->on("users")->onDelete("cascade")->after("id");
            $table->text("user_agent")->nullable();

            $table->dropForeign(["provider_id"]);
            $table->dropColumn("provider_id");
            $table->dropColumn("refresh_token");
            $table->dropColumn("expires_at");
            $table->integer("last_activity");

            // cambio il token in payload
            $table->renameColumn("token", "payload");
            // ip_address a string 45
            $table->string("ip_address", 45)->nullable()->change();

            $table->dropColumn("created_at");
            $table->dropColumn("updated_at");
        });
    }
};
