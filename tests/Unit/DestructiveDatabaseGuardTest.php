<?php

namespace Tests\Unit;

use App\Support\DestructiveDatabaseGuard;
use RuntimeException;
use Tests\TestCase;

/**
 * La guardia contro le operazioni distruttive sul database sbagliato (punto TVF05).
 *
 * Sta in `Unit` e non in `Feature` perche' non tocca il database: legge la configurazione
 * risolta e decide. E' esattamente il tipo di logica che la regola del progetto tiene qui.
 *
 * Perche' questi test esistono: una guardia che non e' mai stata vista fallire non e' una
 * guardia. In questo progetto e' gia' successo due volte che un controllo tacesse per sempre —
 * il guardiano che interveniva dopo il danno, e uno script la cui regex non trovava mai niente.
 */
class DestructiveDatabaseGuardTest extends TestCase
{
    private function conDatabase(string $nome, callable $prova): void
    {
        $connessione = config("database.default");
        $originale = config("database.connections.{$connessione}.database");

        config(["database.connections.{$connessione}.database" => $nome]);

        try {
            $prova();
        } finally {
            config(["database.connections.{$connessione}.database" => $originale]);
        }
    }

    public function test_lascia_passare_un_database_consentito(): void
    {
        // `:memory:` e' il database su cui gira questa suite: se la guardia gridasse qui,
        // nessun test partirebbe. E' la meta' del controllo che si dimentica di provare.
        DestructiveDatabaseGuard::ensureTestDatabase();

        $this->assertTrue(true, "la guardia non deve fermare un database consentito");
    }

    public function test_lascia_passare_anche_il_database_degli_e2e(): void
    {
        $this->conDatabase("idp_test", function () {
            DestructiveDatabaseGuard::ensureTestDatabase();
            $this->assertTrue(true);
        });
    }

    public function test_rifiuta_il_database_di_sviluppo(): void
    {
        $this->conDatabase("idp_develop", function () {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageMatches("/non e' un database di test/");

            DestructiveDatabaseGuard::ensureTestDatabase();
        });
    }

    public function test_il_messaggio_dice_quali_sono_consentiti_e_dove_guardare(): void
    {
        $this->conDatabase("un_database_qualsiasi", function () {
            try {
                DestructiveDatabaseGuard::ensureTestDatabase();
                $this->fail("la guardia doveva rifiutare");
            } catch (RuntimeException $e) {
                // Il messaggio conta quanto il rifiuto: e' quello che dice a chi lo legge cosa fare.
                $this->assertStringContainsString("un_database_qualsiasi", $e->getMessage());
                $this->assertStringContainsString("idp_test", $e->getMessage());
                $this->assertStringContainsString("docs/TEST.md", $e->getMessage());
            }
        });
    }

    public function test_senza_elenco_di_consentiti_fallisce_chiusa(): void
    {
        $originale = env(DestructiveDatabaseGuard::ALLOWED_ENV);

        putenv(DestructiveDatabaseGuard::ALLOWED_ENV);
        unset($_ENV[DestructiveDatabaseGuard::ALLOWED_ENV], $_SERVER[DestructiveDatabaseGuard::ALLOWED_ENV]);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageMatches("/non e' impostata/");

            DestructiveDatabaseGuard::ensureTestDatabase();
        } finally {
            putenv(DestructiveDatabaseGuard::ALLOWED_ENV . "=" . $originale);
            $_ENV[DestructiveDatabaseGuard::ALLOWED_ENV] = $originale;
            $_SERVER[DestructiveDatabaseGuard::ALLOWED_ENV] = $originale;
        }
    }
}
