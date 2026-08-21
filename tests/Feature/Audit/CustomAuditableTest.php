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
 * COME SI ACCENDE (`F11`, punto `TRC05`): `Application::runningInConsole()` **memorizza** il proprio
 * esito, quindi va deciso PRIMA che l'applicazione nasca — cioe' prima di `parent::setUp()`. La leva e'
 * la variabile `APP_RUNNING_IN_CONSOLE`, quella che Laravel legge da se': niente riflessione su
 * proprieta' private, e niente da giustificare a uno strumento di analisi.
 *
 * VALE PER TUTTA LA CLASSE, ed e' il motivo per cui il test «in console non scrive niente» **non sta
 * qui**: quello ha bisogno del guardiano ACCESO, cioe' del comportamento predefinito sotto PHPUnit, e
 * vive in `ConsoleGuardTest`. Due condizioni diverse, due classi — invece di una leva accesa e spenta
 * dentro lo stesso file.
 */
class CustomAuditableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // PRIMA di `parent::setUp()`, che e' dove l'applicazione nasce: dopo sarebbe tardi, perche'
        // `runningInConsole()` memorizza l'esito alla prima chiamata. Tutte e tre le forme, perche'
        // `Env` puo' leggere da una qualunque delle tre a seconda dell'adattatore attivo — provato
        // il 2026-08-21: ognuna da sola basta, e metterle tutte non dipende dalla configurazione.
        putenv("APP_RUNNING_IN_CONSOLE=false");
        $_ENV["APP_RUNNING_IN_CONSOLE"] = "false";
        $_SERVER["APP_RUNNING_IN_CONSOLE"] = "false";

        parent::setUp();
    }

    protected function tearDown(): void
    {
        // Si rimette come si e' trovato: la variabile non deve valere per i test che vengono dopo,
        // che si aspettano il guardiano acceso.
        putenv("APP_RUNNING_IN_CONSOLE");
        unset($_ENV["APP_RUNNING_IN_CONSOLE"], $_SERVER["APP_RUNNING_IN_CONSOLE"]);

        parent::tearDown();
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

    /**
     * Un ruolo qualunque, che serve solo a **provocare un evento**: questi test guardano chi risulta
     * aver fatto la modifica, non cosa e' stato modificato. Il nome era ripetuto in quattro test —
     * ora sta qui, e con esso la forma della creazione.
     */
    private function aRole(Provider $provider): Role
    {
        return Role::create(["name" => "role-under-audit", "provider_id" => $provider->id]);
    }

    /** Le righe di audit, in ordine di scrittura. */
    private function auditRows(): array
    {
        return DB::table("audits")->orderBy("id")->get()->all();
    }

    private function lastRow(): ?object
    {
        return DB::table("audits")->orderByDesc("id")->first();
    }

    // --- gli eventi ----------------------------------------------------------------------

    public function test_a_creation_writes_a_created_audit(): void
    {

        $provider = $this->provider();

        $righe = $this->auditRows();
        $this->assertCount(1, $righe);
        $this->assertSame("created", $righe[0]->event);
        $this->assertSame(Provider::class, $righe[0]->auditable_type);
        $this->assertSame((string) $provider->id, (string) $righe[0]->auditable_id);
        $this->assertSame("[]", $righe[0]->old_values, "alla creazione i valori precedenti sono vuoti");
        $this->assertStringContainsString("esempio.it", $righe[0]->new_values);
    }

    public function test_an_update_writes_the_values_before_and_after(): void
    {
        $provider = $this->provider();

        $provider->update(["name" => "Nome nuovo"]);

        $riga = $this->lastRow();
        $this->assertSame("updated", $riga->event);
        $this->assertStringContainsString("P1", $riga->old_values, "manca il valore precedente");
        $this->assertStringContainsString("Nome nuovo", $riga->new_values);
    }

    public function test_a_soft_delete_writes_deleted(): void
    {
        $provider = $this->provider();

        $provider->delete();

        $this->assertSame("deleted", $this->lastRow()->event);
    }

    public function test_a_restore_writes_restored(): void
    {
        $provider = $this->provider();
        $provider->delete();

        $provider->restore();

        $this->assertSame("restored", $this->lastRow()->event);
    }

    public function test_a_force_delete_writes_force_deleted(): void
    {
        $provider = $this->provider();

        $provider->forceDelete();

        $this->assertSame("force_deleted", $this->lastRow()->event);
    }

    // --- il rumore che NON si scrive ------------------------------------------------------

    /**
     * Una sessione toccata solo nei campi di servizio non produce audit: senza questa regola ogni
     * richiesta autenticata lascerebbe una riga, e la tabella diventerebbe illeggibile.
     */
    public function test_a_session_touched_only_in_service_fields_writes_nothing(): void
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

        // Le righe che i dati di partenza hanno prodotto. Prima di `TRC05` la leva si accendeva
        // **dopo** aver creato provider, utente e sessione, quindi qui bastava `assertSame([])`; ora
        // la leva vale per tutta la classe e anche le creazioni lasciano un audit. Cio' che questo
        // test verifica non e' cambiato: che l'`update` sui campi di servizio non ne aggiunga NESSUNO.
        $primaDellUpdate = count($this->auditRows());

        $sessione->update(["last_activity" => now(), "expires_at" => now()->addHours(2)]);

        $this->assertCount(
            $primaDellUpdate,
            $this->auditRows(),
            "i campi di servizio della sessione non vanno auditati",
        );
    }

    /** Ma un campo che conta, sulla stessa sessione, si audita. */
    public function test_a_session_with_a_changed_token_writes_an_audit(): void
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

        $sessione->update(["token" => "nuovo"]);

        $this->assertSame("updated", $this->lastRow()->event);
    }

    // --- chi ha fatto la modifica --------------------------------------------------------

    public function test_the_actor_is_the_authenticated_user(): void
    {
        $provider = $this->provider();
        $user = User::factory()->create(["enabled" => 1]);
        Auth::login($user);

        $this->aRole($provider);

        $riga = $this->lastRow();
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

        $this->aRole($provider);

        $riga = $this->lastRow();
        $this->assertSame((string) $user->id, (string) $riga->user_id);
        $this->assertSame(User::class, $riga->user_type);
    }

    /** La controprova: senza nessuna identita' la riga resta **senza attore**, che e' la verita'. */
    public function test_without_any_identity_the_audit_row_has_no_actor(): void
    {
        $provider = $this->provider();

        $this->aRole($provider);

        $riga = $this->lastRow();
        $this->assertNull($riga->user_id, "un attore inventato e' peggio di nessun attore");
        $this->assertNull($riga->user_type);
    }

    /**
     * Senza utente ma con un client Passport negli attributi della richiesta, l'attore diventa il
     * client — e `user_id` porta l'id del client, non di un utente.
     */
    public function test_the_actor_is_the_passport_client_when_there_is_no_user(): void
    {
        $provider = $this->provider();
        request()->attributes->set("oauth_client_id", 7);

        $this->aRole($provider);

        $riga = $this->lastRow();
        $this->assertSame("7", (string) $riga->user_id);
        $this->assertSame(Client::class, $riga->user_type);
    }
}
