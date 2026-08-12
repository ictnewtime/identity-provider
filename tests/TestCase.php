<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Rifiuta di eseguire un test se il database in uso non e' uno di quelli consentiti.
     *
     * Perche' non basta `phpunit.xml`: se esiste una config cache (`bootstrap/cache/config.php`)
     * `env()` diventa inerte, le variabili dichiarate la' non hanno effetto, e la suite finisce
     * sul database dell'applicazione. `RefreshDatabase` fa `migrate:fresh` — droppa tutto. E' il
     * difetto VDF11, e il 2026-08-12 ha svuotato il database di sviluppo.
     *
     * Questo controllo legge la configurazione RISOLTA a runtime, quindi vale anche a cache
     * presente: e' l'unico punto che non si puo' aggirare per distrazione.
     */
    /**
     * Il controllo sta in `setUpTraits()` e NON in `setUp()`, e la differenza e' tutto.
     *
     * `RefreshDatabase` agisce dentro `setUpTraits()`, che Laravel chiama dentro `setUp()`: un
     * controllo messo dopo `parent::setUp()` parlerebbe **a database gia' ricreato**. Provato:
     * con la versione precedente il `create table "migrations"` veniva tentato prima del
     * messaggio d'errore.
     *
     * Qui l'applicazione esiste gia' — la crea `setUpTheTestEnvironment()` poche righe sopra —
     * quindi la configurazione e' leggibile, e le migrazioni non sono ancora partite.
     */
    protected function setUpTraits()
    {
        $this->assicuraDatabaseDiTest();

        return parent::setUpTraits();
    }

    private function assicuraDatabaseDiTest(): void
    {
        $connessione = config("database.default");
        $database = config("database.connections.{$connessione}.database");

        $consentiti = array_filter(array_map("trim", explode(",", (string) env("TEST_ALLOWED_DATABASES"))));

        if (empty($consentiti)) {
            throw new RuntimeException(
                "TEST_ALLOWED_DATABASES non e' impostata: senza un elenco di database consentiti " .
                    "la suite non parte, perche' non c'e' modo di sapere se sta per cancellare " .
                    "il database sbagliato. Vedi phpunit.xml e .env.test.example.",
            );
        }

        if (in_array($database, $consentiti, true)) {
            return;
        }

        throw new RuntimeException(
            sprintf(
                "La suite sta per girare su '%s' (connessione '%s'), che non e' un database di test.\n" .
                    "Consentiti: %s.\n" .
                    "Causa quasi certa: una config cache rende env() inerte e phpunit.xml non ha effetto.\n" .
                    "Rimedio: 'composer test' (fa config:clear per primo), oppure vedi docs/TEST.md.",
                $database,
                $connessione,
                implode(", ", $consentiti),
            ),
        );
    }
}
