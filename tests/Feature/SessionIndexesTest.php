<?php

namespace Tests\Feature;

use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Gli indici sulla tabella delle sessioni (punto TTR10, difetto VDF15).
 *
 * Verificare che l'indice **esista** non basta: un indice puo' esserci e non essere usato, perche'
 * la colonna e' di un tipo che il motore non sa cercare o perche' la query filtra su altro. Qui si
 * chiede al database, con `EXPLAIN`, se per **quelle due query** lo usa davvero.
 *
 * Il test e' scritto per due motori: sqlite (i test backend) e MySQL/MariaDB (l'esercizio). Sono le
 * stesse due strade della migrazione, ed e' l'unico modo di accorgersi che su MariaDB l'indice su
 * una colonna `text` va dichiarato con una lunghezza.
 */
class SessionIndexesTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN_INDEX = "sessions_token_index";
    private const USER_PROVIDER_INDEX = "sessions_user_id_provider_id_index";

    public function test_i_due_indici_esistono(): void
    {
        $this->assertTrue(Schema::hasIndex("sessions", self::TOKEN_INDEX), "manca l'indice su token");
        $this->assertTrue(
            Schema::hasIndex("sessions", self::USER_PROVIDER_INDEX),
            "manca l'indice composto (user_id, provider_id)",
        );
    }

    /** La query di `IdpSessionValidator`: gira a ogni richiesta autenticata. */
    public function test_la_ricerca_per_token_non_scandisce_la_tabella(): void
    {
        $this->assertUsaUnIndice(Session::where("token", "un-token-qualunque")->toBase());
    }

    /** La query di `SessionService`: gira a ogni login, exchange e rinnovo. */
    public function test_la_ricerca_per_utente_e_provider_non_scandisce_la_tabella(): void
    {
        $this->assertUsaUnIndice(Session::where("user_id", 1)->where("provider_id", 1)->toBase());
    }

    private function assertUsaUnIndice($query): void
    {
        $driver = DB::connection()->getDriverName();
        $sql = $query->toSql();
        $bindings = $query->getBindings();

        if ($driver === "sqlite") {
            $piano = collect(DB::select("EXPLAIN QUERY PLAN " . $sql, $bindings))
                ->pluck("detail")
                ->implode(" | ");

            $this->assertStringContainsString("USING", $piano, "sqlite non usa nessun indice: {$piano}");
            $this->assertStringContainsString("INDEX", $piano, "sqlite non usa nessun indice: {$piano}");

            return;
        }

        if (in_array($driver, ["mysql", "mariadb"], true)) {
            $piano = DB::select("EXPLAIN " . $sql, $bindings);

            $this->assertNotSame("ALL", $piano[0]->type ?? "ALL", "MariaDB scandisce la tabella intera");

            return;
        }

        $this->markTestSkipped("Motore non previsto da questo controllo: {$driver}");
    }
}
