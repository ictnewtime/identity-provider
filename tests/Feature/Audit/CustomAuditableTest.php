<?php

namespace Tests\Feature\Audit;

use App\Models\Provider;
use App\Models\Role;
use App\Models\Session;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Tests\TestCase;

/**
 * Cosa `CustomAuditable::logAudit()` scrive in `audits` (punto TAC01).
 *
 * QUESTO FILE E' UNA RETE, NON UNA SPECIFICA: fotografa il comportamento di **oggi**, perche' il
 * rifacimento di `TAC02` deve poter dimostrare di non averlo cambiato. Se un'asserzione qui sembra
 * strana, e' perche' descrive cio' che il codice fa, non cio' che sarebbe bello facesse.
 *
 * PERCHE' NON ESISTEVA (fatto `F10` dell'analisi, misurato): `logAudit()` comincia con
 * `if (app()->runningInConsole()) return;`, e sotto PHPUnit quel metodo restituisce **true**. Il trait
 * e' quindi **inerte in tutta la suite**: chi provasse «creo un modello e leggo audits» troverebbe zero
 * righe e concluderebbe che il trait e' rotto. Non lo e': non e' mai stato acceso.
 *
 * COME SI ACCENDE (`F11`): `Application::runningInConsole()` memorizza il proprio esito in una
 * proprieta'. Forzandola a `false` si ottiene il comportamento web. Non si tocca il codice di
 * produzione — e' la stessa leva che Laravel espone con `APP_RUNNING_IN_CONSOLE`.
 */
class CustomAuditableTest extends TestCase
{
    use RefreshDatabase;

    /** Accende l'audit per questo test, disattivando il guardiano della console. */
    private function fuoriDallaConsole(): void
    {
        $proprieta = new \ReflectionProperty($this->app, "isRunningInConsole");
        $proprieta->setValue($this->app, false);
    }

    private function provider(int $id = 1): Provider
    {
        return Provider::forceCreate([
            "id" => $id,
            "domain" => "esempio.it",
            "url" => "https://esempio.it",
            "protocol" => "https",
            "secret_key" => Str::random(32),
            "logoutUrl" => "https://esempio.it/logout",
            "name" => "P{$id}",
        ]);
    }

    /** Le righe di audit, in ordine di scrittura. */
    private function audit(): array
    {
        return DB::table("audits")->orderBy("id")->get()->all();
    }

    private function ultimo(): ?object
    {
        return DB::table("audits")->orderByDesc("id")->first();
    }

    // --- il guardiano della console, che ora e' un caso e non un ostacolo ----------------

    /** Senza la leva non si scrive niente: e' il comportamento di tutta la suite di oggi. */
    public function test_in_console_non_scrive_nessun_audit(): void
    {
        $this->provider();

        $this->assertSame([], $this->audit(), "in console l'audit non deve scrivere");
    }

    // --- gli eventi ----------------------------------------------------------------------

    public function test_la_creazione_scrive_un_audit_created(): void
    {
        $this->fuoriDallaConsole();

        $provider = $this->provider();

        $righe = $this->audit();
        $this->assertCount(1, $righe);
        $this->assertSame("created", $righe[0]->event);
        $this->assertSame(Provider::class, $righe[0]->auditable_type);
        $this->assertSame((string) $provider->id, (string) $righe[0]->auditable_id);
        $this->assertSame("[]", $righe[0]->old_values, "alla creazione i valori precedenti sono vuoti");
        $this->assertStringContainsString("esempio.it", $righe[0]->new_values);
    }

    public function test_laggiornamento_scrive_i_valori_di_prima_e_di_dopo(): void
    {
        $provider = $this->provider();
        $this->fuoriDallaConsole();

        $provider->update(["name" => "Nome nuovo"]);

        $riga = $this->ultimo();
        $this->assertSame("updated", $riga->event);
        $this->assertStringContainsString("P1", $riga->old_values, "manca il valore precedente");
        $this->assertStringContainsString("Nome nuovo", $riga->new_values);
    }

    public function test_la_soft_delete_scrive_deleted(): void
    {
        $provider = $this->provider();
        $this->fuoriDallaConsole();

        $provider->delete();

        $this->assertSame("deleted", $this->ultimo()->event);
    }

    public function test_il_ripristino_scrive_restored(): void
    {
        $provider = $this->provider();
        $provider->delete();
        $this->fuoriDallaConsole();

        $provider->restore();

        $this->assertSame("restored", $this->ultimo()->event);
    }

    public function test_la_cancellazione_definitiva_scrive_force_deleted(): void
    {
        $provider = $this->provider();
        $this->fuoriDallaConsole();

        $provider->forceDelete();

        $this->assertSame("force_deleted", $this->ultimo()->event);
    }

    // --- il rumore che NON si scrive ------------------------------------------------------

    /**
     * Una sessione toccata solo nei campi di servizio non produce audit: senza questa regola ogni
     * richiesta autenticata lascerebbe una riga, e la tabella diventerebbe illeggibile.
     */
    public function test_una_sessione_toccata_solo_nei_campi_di_servizio_non_scrive_niente(): void
    {
        $provider = $this->provider();
        $user = User::factory()->create(["enabled" => 1]);
        $sessione = Session::create([
            "id" => (string) Str::uuid(),
            "user_id" => $user->id,
            "provider_id" => $provider->id,
            "token" => "un.token.qualunque",
            "ip_address" => env("TEST_IP_ADDRESS"),
            "user_agent" => env("TEST_USER_AGENT"),
            "expires_at" => now()->addHour(),
        ]);
        $this->fuoriDallaConsole();

        $sessione->update(["last_activity" => now(), "expires_at" => now()->addHours(2)]);

        $this->assertSame([], $this->audit(), "i campi di servizio della sessione non vanno auditati");
    }

    /** Ma un campo che conta, sulla stessa sessione, si audita. */
    public function test_una_sessione_col_token_cambiato_scrive_un_audit(): void
    {
        $provider = $this->provider();
        $user = User::factory()->create(["enabled" => 1]);
        $sessione = Session::create([
            "id" => (string) Str::uuid(),
            "user_id" => $user->id,
            "provider_id" => $provider->id,
            "token" => "vecchio",
            "ip_address" => env("TEST_IP_ADDRESS"),
            "user_agent" => env("TEST_USER_AGENT"),
            "expires_at" => now()->addHour(),
        ]);
        $this->fuoriDallaConsole();

        $sessione->update(["token" => "nuovo"]);

        $this->assertSame("updated", $this->ultimo()->event);
    }

    // --- chi ha fatto la modifica --------------------------------------------------------

    public function test_lattore_e_lutente_autenticato(): void
    {
        $provider = $this->provider();
        $user = User::factory()->create(["enabled" => 1]);
        Auth::login($user);
        $this->fuoriDallaConsole();

        Role::create(["name" => "un ruolo", "provider_id" => $provider->id]);

        $riga = $this->ultimo();
        $this->assertSame((string) $user->id, (string) $riga->user_id);
        $this->assertSame(User::class, $riga->user_type);
    }

    /**
     * Le chiamate M2M — l'exchange del token — non hanno una sessione di navigazione, quindi `Auth`
     * e' vuoto. L'identita' c'e' comunque: l'ha stabilita il middleware del master token, e la mette
     * negli attributi della richiesta. Prima di `TAC11` questi audit uscivano **senza attore**.
     *
     * In inglese perche' e' la convenzione da qui in avanti (`TTC03` convertira' gli altri).
     */
    public function test_the_actor_comes_from_the_request_identity_when_there_is_no_session(): void
    {
        $provider = $this->provider();
        $user = User::factory()->create(["enabled" => 1]);
        request()->attributes->set("jwt_user_id", $user->id);
        $this->fuoriDallaConsole();

        Role::create(["name" => "un ruolo", "provider_id" => $provider->id]);

        $riga = $this->ultimo();
        $this->assertSame((string) $user->id, (string) $riga->user_id);
        $this->assertSame(User::class, $riga->user_type);
    }

    /** La controprova: senza nessuna identita' la riga resta **senza attore**, che e' la verita'. */
    public function test_without_any_identity_the_audit_row_has_no_actor(): void
    {
        $provider = $this->provider();
        $this->fuoriDallaConsole();

        Role::create(["name" => "un ruolo", "provider_id" => $provider->id]);

        $riga = $this->ultimo();
        $this->assertNull($riga->user_id, "un attore inventato e' peggio di nessun attore");
        $this->assertNull($riga->user_type);
    }

    /**
     * Senza utente ma con un client Passport negli attributi della richiesta, l'attore diventa il
     * client — e `user_id` porta l'id del client, non di un utente.
     */
    public function test_lattore_e_il_client_passport_quando_non_ce_un_utente(): void
    {
        $provider = $this->provider();
        request()->attributes->set("oauth_client_id", 7);
        $this->fuoriDallaConsole();

        Role::create(["name" => "un ruolo", "provider_id" => $provider->id]);

        $riga = $this->ultimo();
        $this->assertSame("7", (string) $riga->user_id);
        $this->assertSame(Client::class, $riga->user_type);
    }
}
