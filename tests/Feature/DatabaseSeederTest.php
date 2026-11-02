<?php

namespace Tests\Feature;

use App\Models\Parameter;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Exceptions\SeedingException;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Il seeder si esegue una volta sola.
 *
 * Sta in `Feature` e non in `Unit` perche' tocca il database: la regola del progetto tiene in
 * `Unit` la logica pura, senza filesystem ne' database.
 *
 * Le due proprieta' che questi test fissano, e che vanno insieme:
 *   1. la seconda esecuzione fallisce con un errore GESTITO, che dice cosa fare;
 *   2. non lascia niente a meta' — la transazione annulla anche cio' che era gia' passato.
 */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Il nome della variabile sta in una costante, e la costante finisce **sia** nell'ambiente
     * che il test prepara **sia** nel controllo del messaggio d'errore del seeder.
     *
     * Non e' pignoleria: scrivendo il nome due volte, il giorno che cambia si aggiorna la lettura e
     * si dimentica il messaggio — e chi legge l'errore va a cercare una variabile che non esiste piu'.
     * E' lo stesso difetto di `session.error.access_denied.userdisabled_or_missing_roles`, il refuso
     * del 2026-08-13: un nome ripetuto a mano in due posti.
     */
    private const PASSWORD_VARIABLE = "SEED_ADMIN_PASSWORD";

    /**
     * La password dell'amministratore di prova **la genera il test**: non sta nel sorgente e non
     * arriva da fuori, quindi non c'e' niente da preparare prima di lanciare la suite.
     *
     * Ricordata in una proprieta' statica perche' `test_without_the_password_…` la cancella di
     * proposito: `setUp()` la rimette a ogni test.
     */
    private static ?string $password = null;

    protected function setUp(): void
    {
        parent::setUp();

        self::$password ??= Str::password(24);

        $this->setPassword(self::$password);
    }

    protected function tearDown(): void
    {
        // Si toglie dall'ambiente del processo: e' del test, e non deve arrivare ai file eseguiti dopo.
        putenv(self::PASSWORD_VARIABLE);
        unset($_ENV[self::PASSWORD_VARIABLE], $_SERVER[self::PASSWORD_VARIABLE]);

        parent::tearDown();
    }

    private function setPassword(string $password): void
    {
        putenv(self::PASSWORD_VARIABLE . "=" . $password);
        $_ENV[self::PASSWORD_VARIABLE] = $password;
        $_SERVER[self::PASSWORD_VARIABLE] = $password;
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
            // Aggiunto il 2026-08-31: dopo quanto la v2 rigenera il master token.
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

    public function test_without_the_password_the_seeder_stops_and_writes_nothing(): void
    {
        putenv(self::PASSWORD_VARIABLE);
        unset($_ENV[self::PASSWORD_VARIABLE], $_SERVER[self::PASSWORD_VARIABLE]);

        try {
            $this->seed(DatabaseSeeder::class);
            $this->fail("senza SEED_ADMIN_PASSWORD il seeder doveva fermarsi");
        } catch (SeedingException $e) {
            $this->assertStringContainsString(self::PASSWORD_VARIABLE, $e->getMessage());
        }

        $this->assertSame(
            ["providers" => 0, "roles" => 0, "users" => 0, "provider_user_roles" => 0],
            $this->counts(),
        );
    }
}
