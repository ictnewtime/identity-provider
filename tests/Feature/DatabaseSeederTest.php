<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
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

    private const PASSWORD_DI_PROVA = "SeedProva1!";

    protected function setUp(): void
    {
        parent::setUp();

        putenv("SEED_ADMIN_PASSWORD=" . self::PASSWORD_DI_PROVA);
        $_ENV["SEED_ADMIN_PASSWORD"] = self::PASSWORD_DI_PROVA;
        $_SERVER["SEED_ADMIN_PASSWORD"] = self::PASSWORD_DI_PROVA;
    }

    protected function tearDown(): void
    {
        putenv("SEED_ADMIN_PASSWORD");
        unset($_ENV["SEED_ADMIN_PASSWORD"], $_SERVER["SEED_ADMIN_PASSWORD"]);

        parent::tearDown();
    }

    /** Le righe che il seeder crea, nell'ordine in cui le crea. */
    private function conteggi(): array
    {
        return [
            "providers" => \DB::table("providers")->count(),
            "roles" => \DB::table("roles")->count(),
            "users" => \DB::table("users")->count(),
            "provider_user_roles" => \DB::table("provider_user_roles")->count(),
        ];
    }

    public function test_la_prima_esecuzione_popola_il_database(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(
            ["providers" => 1, "roles" => 1, "users" => 1, "provider_user_roles" => 1],
            $this->conteggi(),
        );
    }

    public function test_la_seconda_esecuzione_fallisce_con_un_errore_gestito(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->expectException(RuntimeException::class);
        // Il messaggio conta quanto l'eccezione: e' quello che dice a chi lo legge come riseminare.
        $this->expectExceptionMessageMatches("/db:seed non e' rieseguibile/");

        $this->seed(DatabaseSeeder::class);
    }

    public function test_la_seconda_esecuzione_non_lascia_niente_a_meta(): void
    {
        $this->seed(DatabaseSeeder::class);
        $prima = $this->conteggi();

        try {
            $this->seed(DatabaseSeeder::class);
            $this->fail("la seconda esecuzione doveva fallire");
        } catch (RuntimeException $e) {
            // atteso: e' il caso di sopra. Qui interessa solo cosa resta nel database.
        }

        $this->assertSame($prima, $this->conteggi(), "la seconda esecuzione ha lasciato righe dietro di se'");
    }

    public function test_senza_la_password_il_seeder_si_ferma_e_non_scrive_niente(): void
    {
        putenv("SEED_ADMIN_PASSWORD");
        unset($_ENV["SEED_ADMIN_PASSWORD"], $_SERVER["SEED_ADMIN_PASSWORD"]);

        try {
            $this->seed(DatabaseSeeder::class);
            $this->fail("senza SEED_ADMIN_PASSWORD il seeder doveva fermarsi");
        } catch (RuntimeException $e) {
            $this->assertStringContainsString("SEED_ADMIN_PASSWORD", $e->getMessage());
        }

        $this->assertSame(
            ["providers" => 0, "roles" => 0, "users" => 0, "provider_user_roles" => 0],
            $this->conteggi(),
        );
    }
}
