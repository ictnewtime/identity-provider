<?php

namespace Tests\Feature\Audit;

use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Il guardiano della console: `CustomAuditable::logAudit()` esce subito se `runningInConsole()`.
 *
 * PERCHE' STA IN UN FILE SUO (punto `TRC05`): qui serve il guardiano **acceso**, che sotto PHPUnit e'
 * il comportamento predefinito. `CustomAuditableTest` fa l'opposto — spegne il guardiano per tutta la
 * classe impostando `APP_RUNNING_IN_CONSOLE` prima che l'applicazione nasca — e le due cose non
 * convivono nello stesso file: la variabile si decide una volta, prima di `setUp()`.
 *
 * Prima del 2026-08-21 convivevano, al prezzo di una riflessione su una proprieta' privata
 * dell'applicazione (`ReflectionProperty` su `isRunningInConsole`) chiamata test per test. Quella
 * riflessione era anche un rilievo di SonarQube — «assicurati che questo bypass sia sicuro» — e la
 * risposta migliore non era giustificarlo: era non averne bisogno.
 *
 * Cosa dimostra questo test, ed e' il motivo per cui non si cancella: che il silenzio dell'audit in
 * console e' **voluto e verificato**, non un caso. E' anche la ragione per cui nessun seeder e nessun
 * comando `artisan` lascia traccia negli audit — dubbio `BDB35`, ancora aperto.
 */
class ConsoleGuardTest extends TestCase
{
    use RefreshDatabase;

    /** Senza la leva non si scrive niente: e' il comportamento di tutta la suite. */
    public function test_in_console_it_writes_no_audit(): void
    {
        Provider::forceCreate([
            "id" => 1,
            "domain" => "esempio.it",
            "url" => "https://esempio.it",
            "protocol" => "https",
            "secret_key" => Str::random(32),
            "logoutUrl" => "https://esempio.it/logout",
            "name" => "P1",
        ]);

        $this->assertSame([], DB::table("audits")->orderBy("id")->get()->all(), "in console non si deve scrivere");
    }
}
