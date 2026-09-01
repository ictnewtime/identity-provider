<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `sessions.provider_id` diventa opzionale.
 *
 * PERCHE': la rotta `v2` dell'exchange scrive **una riga sola per utente**, che rappresenta il master
 * token e non un'applicazione — quindi un provider non ce l'ha. Oggi la colonna e' `unsignedInteger`
 * **non nullable** con chiave esterna verso `providers` (`2026_03_02_142301_alter_table_session.php`),
 * e quella riga non si potrebbe scrivere.
 *
 * IL GUADAGNO SECONDARIO, che vale quanto il primo: `provider_id IS NULL` diventa **il marcatore** che
 * distingue una riga della v2 da una della v1, senza aggiungere una colonna «tipo» che poi qualcuno
 * dovrebbe tenere allineata.
 *
 * La chiave esterna resta e continua a valere per le righe che un provider ce l'hanno: in SQL un
 * valore nullo non viene verificato contro la tabella di riferimento.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table("sessions", function (Blueprint $table) {
            $table->unsignedInteger("provider_id")->nullable()->change();
        });
    }

    /**
     * Tornare indietro **cancella le righe senza provider**, e non c'e' altro modo: sono proprio le
     * righe che la colonna non nullable non ammette. Chi torna indietro sta tornando al modello in cui
     * la v2 non esiste, quindi quelle righe non servono a nessuno — ma va detto, perche' significa che
     * i client della v2 rifaranno il login.
     */
    public function down(): void
    {
        \DB::table("sessions")->whereNull("provider_id")->delete();

        Schema::table("sessions", function (Blueprint $table) {
            $table->unsignedInteger("provider_id")->nullable(false)->change();
        });
    }
};
