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

    /**
     * Quante righe serve seminare, e perche' proprio quel numero. Prima erano tre `range(1, N)` con il
     * numero nudo dentro il test, e una variabile `$i` che nessuno usava (punto TRC04).
     */
    private const ROWS_FOR_A_SECOND_PAGE = 5;
    private const EXTRA_ROWS_FOR_THE_N_PLUS_ONE_CHECK = 9;
    private const ROWS_ABOVE_THE_CEILING = 12;

    /** Semina `$howMany` righe di audit. Il contatore vive qui e non si vede nei test. */
    private function audits(int $howMany, array $overrides = []): void
    {
        for ($written = 0; $written < $howMany; $written++) {
            $this->audit($overrides);
        }
    }

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

    private function callIndex(array $query = [])
    {
        return $this->withoutMiddleware()->getJson(self::URI . "?" . http_build_query($query));
    }

    public function test_it_lists_audits_paginated(): void
    {
        $this->audit();
        $this->audit();

        $this->callIndex()
            ->assertStatus(200)
            ->assertJsonStructure(["data", "meta" => ["current_page", "per_page", "total"]]);
    }

    public function test_it_honours_per_page(): void
    {
        $this->audits(self::ROWS_FOR_A_SECOND_PAGE);

        $this->callIndex(["per_page" => 2])
            ->assertStatus(200)
            ->assertJsonPath("meta.per_page", 2)
            ->assertJsonCount(2, "data");
    }

    public function test_the_search_filters_by_ip_address(): void
    {
        $this->audit(["ip_address" => env("TEST_IP_ADDRESS_ALT")]);
        $this->audit(["ip_address" => env("TEST_IP_ADDRESS_OTHER")]);

        $this->callIndex(["q" => env("TEST_IP_ADDRESS_ALT")])
            ->assertStatus(200)
            ->assertJsonCount(1, "data");
    }

    public function test_the_search_filters_by_event(): void
    {
        $this->audit(["event" => "created"]);
        $this->audit(["event" => "deleted"]);

        $this->callIndex(["q" => "creat"])
            ->assertStatus(200)
            ->assertJsonCount(1, "data");
    }

    public function test_the_search_filters_by_actor_username(): void
    {
        $utente = User::factory()->create(["username" => "mario.rossi", "enabled" => 1]);

        $this->audit(["user_type" => User::class, "user_id" => $utente->id]);
        $this->audit();

        $this->callIndex(["q" => "mario"])
            ->assertStatus(200)
            ->assertJsonCount(1, "data");
    }

    public function test_it_sorts_on_the_allowed_columns(): void
    {
        $this->audit(["event" => "zeta"]);
        $this->audit(["event" => "alfa"]);

        $risposta = $this->callIndex(["sort_by" => "event", "sort_dir" => "asc"])->assertStatus(200);

        $this->assertSame("alfa", $risposta->json("data.0.event"));
    }

    public function test_it_ignores_a_column_that_is_not_allowed(): void
    {
        $this->audit();

        // `id` non e' fra le colonne ammesse: la richiesta non deve fallire, solo ignorarla.
        $this->callIndex(["sort_by" => "id", "sort_dir" => "asc"])->assertStatus(200);
    }

    public function test_the_default_order_is_most_recent_first(): void
    {
        $vecchio = $this->audit(["event" => "vecchio"]);
        $vecchio->forceFill(["created_at" => now()->subDay()])->save();

        $this->audit(["event" => "recente"]);

        $this->assertSame("recente", $this->callIndex()->json("data.0.event"));
    }

    public function test_the_response_exposes_only_the_declared_fields(): void
    {
        $utente = User::factory()->create(["username" => "mario.rossi", "enabled" => 1]);
        $this->audit(["user_type" => User::class, "user_id" => $utente->id]);

        $riga = $this->callIndex()->json("data.0");

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

    public function test_the_actor_of_a_system_audit_is_null(): void
    {
        $this->audit(["user_id" => null]);

        $this->assertNull($this->callIndex()->json("data.0.user"));
    }

    /**
     * Punto 1 della checklist perf/leak: nessuna query dentro la API Resource.
     *
     * Il numero di query non deve dipendere da quanti audit ci sono. Se AuditResource
     * risolvesse l'attore riga per riga, questo test lo direbbe subito.
     */
    public function test_the_number_of_queries_does_not_grow_with_the_rows(): void
    {
        $utente = User::factory()->create(["username" => "mario.rossi", "enabled" => 1]);

        $this->audit(["user_type" => User::class, "user_id" => $utente->id]);
        $conUno = $this->countQueries();

        $this->audits(self::EXTRA_ROWS_FOR_THE_N_PLUS_ONE_CHECK, ["user_type" => User::class, "user_id" => $utente->id]);
        $conDieci = $this->countQueries();

        $this->assertSame($conUno, $conDieci, "il numero di query cresce con le righe: e' un N+1");
    }

    private function countQueries(): int
    {
        \DB::flushQueryLog();
        \DB::enableQueryLog();

        $this->callIndex()->assertStatus(200);

        $numero = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        return $numero;
    }


    /**
     * `per_page` ha un tetto (punto TCC04, difetto VDF03).
     *
     * Il valore arriva dal client: senza limite, una richiesta sola caricherebbe in memoria
     * l'intera tabella degli audit, che e' quella che cresce piu' in fretta.
     */
    public function test_per_page_has_a_ceiling(): void
    {
        $this->audits(self::ROWS_ABOVE_THE_CEILING);

        $risposta = $this->callIndex(["per_page" => 1000000])->assertStatus(200);

        $this->assertLessThanOrEqual(200, $risposta->json("meta.per_page"), "per_page non ha un tetto");
        $this->assertLessThanOrEqual(200, count($risposta->json("data")));
    }

    public function test_an_absurd_per_page_does_not_mean_no_limit(): void
    {
        $this->audit();

        // `0` e i negativi non devono valere «tutte le righe»: e' l'errore classico di un tetto
        // messo solo verso l'alto.
        foreach ([0, -1, -1000] as $valore) {
            $letto = $this->callIndex(["per_page" => $valore])->assertStatus(200)->json("meta.per_page");

            $this->assertGreaterThanOrEqual(1, $letto, "per_page={$valore} non deve dare zero righe");
        }
    }

    /**
     * L'ordinamento esplicito non viene disturbato da un criterio aggiunto in coda (punto TCC05).
     *
     * Prima c'era un `->latest()` dopo l'`orderBy`: nel ramo predefinito ripeteva quello appena
     * impostato, in quello esplicito aggiungeva un secondo criterio che nessuno aveva chiesto.
     */
    public function test_an_explicit_sort_is_not_disturbed(): void
    {
        $vecchio = $this->audit(["event" => "alfa"]);
        $vecchio->forceFill(["created_at" => now()->subDay()])->save();

        $this->audit(["event" => "zeta"]);

        $eventi = collect($this->callIndex(["sort_by" => "event", "sort_dir" => "asc"])->json("data"))->pluck("event");

        $this->assertSame(["alfa", "zeta"], $eventi->all());
    }

    /**
     * Ordinare per username con la join attiva non deve dare errore di ambiguita' su `created_at`.
     *
     * Era la domanda D4 dell'analisi: `users` ha anch'essa `created_at`, e un criterio non
     * qualificato aggiunto in coda poteva renderlo ambiguo. Su MariaDB la risposta si vede solo
     * eseguendolo li' — questo test gira in entrambi gli ambienti.
     */
    public function test_sorting_by_username_with_the_join_is_not_ambiguous(): void
    {
        $utente = User::factory()->create(["username" => "mario.rossi", "enabled" => 1]);
        $this->audit(["user_type" => User::class, "user_id" => $utente->id]);

        $this->callIndex(["sort_by" => "user.username", "sort_dir" => "desc"])->assertStatus(200);
    }

    public function test_sorting_by_username_must_not_lose_or_falsify_rows(): void
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

        $righe = $this->callIndex(["sort_by" => "user.username", "sort_dir" => "asc"])->json("data");
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
