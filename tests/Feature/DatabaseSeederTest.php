<?php

namespace Tests\Feature;

use App\Models\Parameter;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Exceptions\SeedingException;
use Tests\TestCase;

/**
 * Il seeder si esegue una volta sola (punto TLE11).
 *
 * Sta in `Feature` e non in `Unit` perche' tocca il database: la regola del progetto tiene in
 * `Unit` la logica pura, senza filesystem ne' database (docs/ai/abstract/testing.md).
 *
 * Le due proprieta' che questi test fissano, e che vanno insieme:
 *   1. la seconda esecuzione fallisce con un errore GESTITO, che dice cosa fare;
 *   2. non lascia niente a meta' — la transazione annulla anche cio' che era gia' passato.
 */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Il nome della variabile sta in una costante, e la costante finisce **sia** nella lettura
     * dell'ambiente **sia** nel messaggio d'errore (punto `TAC10`).
     *
     * Non e' pignoleria: scrivendo il nome due volte, il giorno che cambia si aggiorna la lettura e
     * si dimentica il messaggio — e chi legge l'errore va a cercare una variabile che non esiste piu'.
     * E' lo stesso difetto di `session.error.access_denied.userdisabled_or_missing_roles`, il refuso
     * del 2026-08-13: un nome ripetuto a mano in due posti.
     */
    private const PASSWORD_VARIABLE = "SEED_ADMIN_PASSWORD";

    /** Lo script che prepara l'ambiente. Il test qui sotto verifica che **esista davvero**. */
    private const SETUP_SCRIPT = "scripts/setup-env-for-test-backend.sh";

    /**
     * La password dell'amministratore di prova **non sta nel sorgente**: arriva dall'ambiente
     * (punto `TAC05`). Nel modello `.env.test.backend.example` la variabile e' dichiarata senza
     * valore, e lo script di preparazione la genera e la passa al container.
     *
     * Ricordata in una proprieta' statica perche' `test_senza_la_password_…` la cancella di
     * proposito: senza questa copia, dal secondo test in poi non ci sarebbe piu'.
     */
    private static ?string $password = null;

    private static function missingPasswordMessage(): string
    {
        return sprintf(
            "Manca %s nell'ambiente: questo test semina un amministratore e la password non se la " .
                "inventa. Eseguire ./%s, che la genera una volta e la scrive in .env.test.backend.",
            self::PASSWORD_VARIABLE,
            self::SETUP_SCRIPT,
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        self::$password ??= (string) env(self::PASSWORD_VARIABLE);

        if (self::$password === "") {
            $this->fail(self::missingPasswordMessage());
        }

        $this->setPassword(self::$password);
    }

    protected function tearDown(): void
    {
        // Si **ripristina**, non si cancella: il valore viene dall'ambiente del processo, e
        // cancellarlo lo toglierebbe anche agli altri file di test eseguiti dopo.
        $this->setPassword(self::$password ?? "");

        parent::tearDown();
    }

    private function setPassword(string $password): void
    {
        putenv("SEED_ADMIN_PASSWORD=" . $password);
        $_ENV["SEED_ADMIN_PASSWORD"] = $password;
        $_SERVER["SEED_ADMIN_PASSWORD"] = $password;
    }

    /** Le righe che il seeder crea, nell'ordine in cui le crea. */
    private function counts(): array
    {
        return [
            "providers" => \DB::table("providers")->count(),
            "roles" => \DB::table("roles")->count(),
            "users" => \DB::table("users")->count(),
            "provider_user_roles" => \DB::table("provider_user_roles")->count(),
        ];
    }

    public function test_the_first_run_populates_the_database(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(
            ["providers" => 1, "roles" => 1, "users" => 1, "provider_user_roles" => 1],
            $this->counts(),
        );
    }

    public function test_it_creates_the_initial_parameters(): void
    {
        $this->seed(DatabaseSeeder::class);

        $attesi = [
            "password-force-reset-day" => ["value" => "90", "type" => "policy"],
            "master-token-exp-time-seconds" => ["value" => "28800", "type" => "token"],
            "app-token-exp-time-seconds" => ["value" => "1800", "type" => "token"],
            // Aggiunto il 2026-08-31 col punto TMT17: dopo quanto la v2 rigenera il master token.
            // Il valore e' lo stesso del ripiego nel codice (un'ora), e devono restare uguali: se
            // divergono, un ambiente senza questa riga si comporta diversamente da uno con la riga.
            "master-token-rotate-after-seconds" => ["value" => "3600", "type" => "token"],
        ];

        foreach ($attesi as $key => $atteso) {
            $riga = Parameter::where("key", $key)->first();

            $this->assertNotNull($riga, "manca il parametro '{$key}'");
            $this->assertSame($atteso["value"], (string) $riga->value, "valore sbagliato per '{$key}'");
            $this->assertSame($atteso["type"], $riga->type, "tipo sbagliato per '{$key}'");
        }

        $this->assertSame(count($attesi), Parameter::count(), "ci sono parametri oltre a quelli attesi");
    }

    public function test_the_second_run_fails_with_a_handled_error(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->expectException(SeedingException::class);
        // Il messaggio conta quanto l'eccezione: e' quello che dice a chi lo legge come riseminare.
        $this->expectExceptionMessageMatches("/db:seed non e' rieseguibile/");

        $this->seed(DatabaseSeeder::class);
    }

    public function test_the_second_run_leaves_nothing_half_done(): void
    {
        $this->seed(DatabaseSeeder::class);
        $prima = $this->counts();

        try {
            $this->seed(DatabaseSeeder::class);
            $this->fail("la seconda esecuzione doveva fallire");
        } catch (SeedingException $e) {
            // atteso: e' il caso di sopra. Qui interessa solo cosa resta nel database.
        }

        $this->assertSame($prima, $this->counts(), "la seconda esecuzione ha lasciato righe dietro di se'");
    }

    /**
     * Il messaggio del guardiano vale quanto la sua precisione: e' l'unica cosa che chi lo incontra
     * legge. Questo test lo tiene attaccato alla realta' in due modi — nomina la **stessa** variabile
     * che il codice legge (e' la stessa costante), e lo script che indica **esiste sul disco**.
     *
     * In inglese perche' e' la convenzione da qui in avanti (`TTC03` convertira' gli altri).
     */
    public function test_the_missing_password_message_names_the_variable_and_an_existing_script(): void
    {
        $message = self::missingPasswordMessage();

        $this->assertStringContainsString(self::PASSWORD_VARIABLE, $message);
        $this->assertStringContainsString(self::SETUP_SCRIPT, $message);
        $this->assertFileExists(
            base_path(self::SETUP_SCRIPT),
            "il messaggio indica uno script che non esiste: chi lo legge non ha una via d'uscita",
        );
    }

    public function test_without_the_password_the_seeder_stops_and_writes_nothing(): void
    {
        putenv("SEED_ADMIN_PASSWORD");
        unset($_ENV["SEED_ADMIN_PASSWORD"], $_SERVER["SEED_ADMIN_PASSWORD"]);

        try {
            $this->seed(DatabaseSeeder::class);
            $this->fail("senza SEED_ADMIN_PASSWORD il seeder doveva fermarsi");
        } catch (SeedingException $e) {
            $this->assertStringContainsString("SEED_ADMIN_PASSWORD", $e->getMessage());
        }

        $this->assertSame(
            ["providers" => 0, "roles" => 0, "users" => 0, "provider_user_roles" => 0],
            $this->counts(),
        );
    }
}
