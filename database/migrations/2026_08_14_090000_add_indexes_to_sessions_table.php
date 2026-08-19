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
        if (Schema::hasIndex(self::TABLE, self::TOKEN_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex(self::TOKEN_INDEX);
            });
        }

        if (!Schema::hasIndex(self::TABLE, self::USER_PROVIDER_INDEX)) {
            return;
        }

        if (!$this->isMysql()) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex(self::USER_PROVIDER_INDEX);
            });

            return;
        }

        // Su MySQL/MariaDB questo indice non si toglie e basta (difetto `VDF19`, errore 1553:
        // *needed in a foreign key constraint*). La tabella non ha un indice a se' su `user_id` — c'e'
        // solo il vincolo omonimo — quindi il composto e' l'**unico** con `user_id` a sinistra, e
        // InnoDB rifiuta di togliere l'ultimo indice che regge un vincolo.
        //
        // Si toglie il vincolo, poi l'indice, poi si **rimette** il vincolo: ricreandolo, il motore
        // si riporta dietro il proprio indice, che e' esattamente lo stato di prima della migrazione.
        //
        // Su sqlite non serve — non protegge gli indici delle chiavi esterne — e `dropForeign()` non
        // e' nemmeno supportato: da qui le due strade.
        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropForeign(["user_id"]);
            $table->dropIndex(self::USER_PROVIDER_INDEX);
            $table->foreign("user_id")->references("id")->on("users")->onDelete("cascade");
        });
    }

    private function isMysql(): bool
    {
        return in_array(DB::connection()->getDriverName(), ["mysql", "mariadb"], true);
    }
};
