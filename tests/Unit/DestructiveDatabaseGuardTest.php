<?php

namespace Tests\Unit;

use App\Support\DestructiveDatabaseGuard;
use App\Exceptions\DestructiveDatabaseException;
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
    private function withDatabase(string $nome, callable $prova): void
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

    public function test_it_lets_an_allowed_database_through(): void
    {
        // `:memory:` e' il database su cui gira questa suite: se la guardia gridasse qui,
        // nessun test partirebbe. E' la meta' del controllo che si dimentica di provare.
        DestructiveDatabaseGuard::ensureTestDatabase();

        $this->assertTrue(true, "la guardia non deve fermare un database consentito");
    }

    public function test_it_also_lets_the_e2e_database_through(): void
    {
        $this->withDatabase("idp_test", function () {
            DestructiveDatabaseGuard::ensureTestDatabase();
            $this->assertTrue(true);
        });
    }

    public function test_it_rejects_the_development_database(): void
    {
        $this->withDatabase("idp_develop", function () {
            $this->expectException(DestructiveDatabaseException::class);
            $this->expectExceptionMessageMatches("/non e' un database di test/");

            DestructiveDatabaseGuard::ensureTestDatabase();
        });
    }

    public function test_the_message_says_which_are_allowed_and_where_to_look(): void
    {
        $this->withDatabase("un_database_qualsiasi", function () {
            try {
                DestructiveDatabaseGuard::ensureTestDatabase();
                $this->fail("la guardia doveva rifiutare");
            } catch (DestructiveDatabaseException $e) {
                // Il messaggio conta quanto il rifiuto: e' quello che dice a chi lo legge cosa fare.
                $this->assertStringContainsString("un_database_qualsiasi", $e->getMessage());
                $this->assertStringContainsString("idp_test", $e->getMessage());
                $this->assertStringContainsString("docs/TEST.md", $e->getMessage());
            }
        });
    }

    public function test_without_an_allow_list_it_fails_closed(): void
    {
        $originale = env(DestructiveDatabaseGuard::ALLOWED_ENV);

        putenv(DestructiveDatabaseGuard::ALLOWED_ENV);
        unset($_ENV[DestructiveDatabaseGuard::ALLOWED_ENV], $_SERVER[DestructiveDatabaseGuard::ALLOWED_ENV]);

        try {
            $this->expectException(DestructiveDatabaseException::class);
            $this->expectExceptionMessageMatches("/non e' impostata/");

            DestructiveDatabaseGuard::ensureTestDatabase();
        } finally {
            putenv(DestructiveDatabaseGuard::ALLOWED_ENV . "=" . $originale);
            $_ENV[DestructiveDatabaseGuard::ALLOWED_ENV] = $originale;
            $_SERVER[DestructiveDatabaseGuard::ALLOWED_ENV] = $originale;
        }
    }
}
