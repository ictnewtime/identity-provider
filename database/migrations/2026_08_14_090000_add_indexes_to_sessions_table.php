<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const TABLE = "sessions";
    private const TOKEN_INDEX = "sessions_token_index";
    private const USER_PROVIDER_INDEX = "sessions_user_id_provider_id_index";
    private const TOKEN_PREFIX_LENGTH = 191;

    public function up(): void
    {
        if (!Schema::hasIndex(self::TABLE, self::USER_PROVIDER_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index(["user_id", "provider_id"], self::USER_PROVIDER_INDEX);
            });
        }

        if (Schema::hasIndex(self::TABLE, self::TOKEN_INDEX)) {
            return;
        }

        if ($this->isMysql()) {
            DB::statement(
                sprintf(
                    "ALTER TABLE %s ADD INDEX %s (token(%d))",
                    self::TABLE,
                    self::TOKEN_INDEX,
                    self::TOKEN_PREFIX_LENGTH,
                ),
            );

            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->index("token", self::TOKEN_INDEX);
        });
    }

    public function down(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table) {
            if (Schema::hasIndex(self::TABLE, self::TOKEN_INDEX)) {
                $table->dropIndex(self::TOKEN_INDEX);
            }

            if (Schema::hasIndex(self::TABLE, self::USER_PROVIDER_INDEX)) {
                $table->dropIndex(self::USER_PROVIDER_INDEX);
            }
        });
    }

    private function isMysql(): bool
    {
        return in_array(DB::connection()->getDriverName(), ["mysql", "mariadb"], true);
    }
};
