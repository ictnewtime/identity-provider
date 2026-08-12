<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Client as PassportClient;
use OwenIt\Auditing\Models\Audit;
use Tests\TestCase;

/**
 * Rete di sicurezza per il refactoring di AuditController::all() (punto TCC02).
 *
 * Copre i comportamenti che la scomposizione deve PRESERVARE: ricerca, ordinamento sulle
 * colonne consentite, paginazione. Devono restare invariati mentre il codice si sposta.
 *
 * Le rotte reali portano `authenticated` + `role:admin`: qui si passa oltre, perche' quei
 * due sono coperti altrove (AuthenticatedTest) e mescolarli renderebbe illeggibile un rosso.
 *
 * L'ultimo test NON descrive il comportamento attuale: e' la regressione del difetto VDF02,
 * e oggi FALLISCE apposta. Passera' con TCC03.
 */
class AuditListTest extends TestCase
{
    use RefreshDatabase;

    private const URI = "/admin/v1/audits";

    private function audit(array $overrides = []): Audit
    {
        return Audit::create(
            array_merge(
                [
                    "user_type" => User::class,
                    "user_id" => null,
                    "event" => "updated",
                    "auditable_type" => User::class,
                    "auditable_id" => "1",
                    "ip_address" => env("TEST_IP_ADDRESS"),
                    "url" => "http://localhost",
                    "user_agent" => env("TEST_USER_AGENT"),
                ],
                $overrides,
            ),
        );
    }

    private function chiama(array $query = [])
    {
        return $this->withoutMiddleware()->getJson(self::URI . "?" . http_build_query($query));
    }

    public function test_elenca_gli_audit_in_forma_paginata(): void
    {
        $this->audit();
        $this->audit();

        $this->chiama()
            ->assertStatus(200)
            ->assertJsonStructure(["data", "meta" => ["current_page", "per_page", "total"]]);
    }

    public function test_rispetta_per_page(): void
    {
        foreach (range(1, 5) as $i) {
            $this->audit();
        }

        $this->chiama(["per_page" => 2])
            ->assertStatus(200)
            ->assertJsonPath("meta.per_page", 2)
            ->assertJsonCount(2, "data");
    }

    public function test_la_ricerca_filtra_per_indirizzo_ip(): void
    {
        $this->audit(["ip_address" => env("TEST_IP_ADDRESS_ALT")]);
        $this->audit(["ip_address" => env("TEST_IP_ADDRESS_OTHER")]);

        $this->chiama(["q" => env("TEST_IP_ADDRESS_ALT")])
            ->assertStatus(200)
            ->assertJsonCount(1, "data");
    }

    public function test_la_ricerca_filtra_per_evento(): void
    {
        $this->audit(["event" => "created"]);
        $this->audit(["event" => "deleted"]);

        $this->chiama(["q" => "creat"])
            ->assertStatus(200)
            ->assertJsonCount(1, "data");
    }

    public function test_la_ricerca_filtra_per_username_dellattore(): void
    {
        $utente = User::factory()->create(["username" => "mario.rossi", "enabled" => 1]);

        $this->audit(["user_type" => User::class, "user_id" => $utente->id]);
        $this->audit();

        $this->chiama(["q" => "mario"])
            ->assertStatus(200)
            ->assertJsonCount(1, "data");
    }

    public function test_ordina_sulle_colonne_consentite(): void
    {
        $this->audit(["event" => "zeta"]);
        $this->audit(["event" => "alfa"]);

        $risposta = $this->chiama(["sort_by" => "event", "sort_dir" => "asc"])->assertStatus(200);

        $this->assertSame("alfa", $risposta->json("data.0.event"));
    }

    public function test_ignora_una_colonna_non_consentita(): void
    {
        $this->audit();

        // `id` non e' fra le colonne ammesse: la richiesta non deve fallire, solo ignorarla.
        $this->chiama(["sort_by" => "id", "sort_dir" => "asc"])->assertStatus(200);
    }

    public function test_lordine_predefinito_e_il_piu_recente_per_primo(): void
    {
        $vecchio = $this->audit(["event" => "vecchio"]);
        $vecchio->forceFill(["created_at" => now()->subDay()])->save();

        $this->audit(["event" => "recente"]);

        $this->assertSame("recente", $this->chiama()->json("data.0.event"));
    }

    public function test_la_risposta_espone_solo_i_campi_dichiarati(): void
    {
        $utente = User::factory()->create(["username" => "mario.rossi", "enabled" => 1]);
        $this->audit(["user_type" => User::class, "user_id" => $utente->id]);

        $riga = $this->chiama()->json("data.0");

        $this->assertEqualsCanonicalizing(
            [
                "id",
                "created_at",
                "event",
                "auditable_type",
                "auditable_id",
                "ip_address",
                "user_agent",
                "url",
                "old_values",
                "new_values",
                "user",
            ],
            array_keys($riga),
        );

        // Dell'attore esce il solo nome: niente email, niente stato dell'account.
        $this->assertSame(["username"], array_keys($riga["user"]));
        $this->assertSame("mario.rossi", $riga["user"]["username"]);
    }

    public function test_lattore_di_un_audit_di_sistema_e_nullo(): void
    {
        $this->audit(["user_id" => null]);

        $this->assertNull($this->chiama()->json("data.0.user"));
    }

    /**
     * Punto 1 della checklist perf/leak: nessuna query dentro la API Resource.
     *
     * Il numero di query non deve dipendere da quanti audit ci sono. Se AuditResource
     * risolvesse l'attore riga per riga, questo test lo direbbe subito.
     */
    public function test_il_numero_di_query_non_cresce_con_le_righe(): void
    {
        $utente = User::factory()->create(["username" => "mario.rossi", "enabled" => 1]);

        $this->audit(["user_type" => User::class, "user_id" => $utente->id]);
        $conUno = $this->contaQuery();

        foreach (range(1, 9) as $i) {
            $this->audit(["user_type" => User::class, "user_id" => $utente->id]);
        }
        $conDieci = $this->contaQuery();

        $this->assertSame($conUno, $conDieci, "il numero di query cresce con le righe: e' un N+1");
    }

    private function contaQuery(): int
    {
        \DB::flushQueryLog();
        \DB::enableQueryLog();

        $this->chiama()->assertStatus(200);

        $numero = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        return $numero;
    }

    public function test_ordinare_per_username_non_deve_perdere_ne_falsare_righe(): void
    {
        $utente = User::factory()->create(["username" => "mario.rossi", "enabled" => 1]);
        $this->audit(["user_type" => User::class, "user_id" => $utente->id]);

        // Un audit di sistema: nessun attore.
        $this->audit(["user_id" => null, "event" => "audit-di-sistema"]);

        // Un client Passport con lo STESSO id numerico dell'utente.
        $client = PassportClient::forceCreate([
            "id" => $utente->id,
            "name" => "client-di-prova",
            "secret" => Str::random(40),
            "redirect" => "http://localhost",
            "personal_access_client" => false,
            "password_client" => false,
            "revoked" => false,
        ]);
        $this->audit(["user_type" => PassportClient::class, "user_id" => $client->id, "event" => "audit-di-client"]);

        $righe = $this->chiama(["sort_by" => "user.username", "sort_dir" => "asc"])->json("data");
        $eventi = collect($righe)->pluck("event");

        $this->assertContains("audit-di-sistema", $eventi, "gli audit senza utente non devono sparire");
        $this->assertContains("audit-di-client", $eventi, "gli audit dei client Passport non devono sparire");
        $this->assertCount(3, $eventi, "nessuna riga deve essere persa ordinando per username");

        // L'attore mostrato e' quello vero: il nome del client, non l'username dell'utente
        // che per caso ha lo stesso id.
        $rigaDelClient = collect($righe)->firstWhere("event", "audit-di-client");
        $this->assertSame("client-di-prova", $rigaDelClient["user"]["username"]);
    }
}
