<?php

namespace Tests\Feature\Auth;

use App\Models\Provider;
use App\Models\Role;
use App\Models\Session;
use App\Models\User;
use App\Services\SessionService;
use App\Services\TokenProviderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * La revoca di una sessione deve valere (punti TMT11 e TMT12, difetto VDF14).
 *
 * IL DIFETTO CHE QUESTI TEST TENGONO CHIUSO: un amministratore cancella la sessione, e il client —
 * che ha ancora il master token, valido per ore — se la ricrea alla richiesta successiva. Il pulsante
 * di logout sembrava funzionare e non funzionava.
 */
class SessionRevocationTest extends TestCase
{
    use RefreshDatabase;

    private function providerWithAccess(int $id, User $user): Provider
    {
        $provider = Provider::forceCreate([
            "id" => $id,
            "domain" => "esempio{$id}.it",
            "url" => "https://esempio{$id}.it",
            "protocol" => "https",
            "secret_key" => Str::random(32),
            "logoutUrl" => "https://esempio{$id}.it/logout",
            "name" => "P{$id}",
        ]);

        $role = Role::create(["name" => "admin", "provider_id" => $provider->id]);
        DB::table("provider_user_roles")->insert([
            "user_id" => $user->id,
            "provider_id" => $provider->id,
            "role_id" => $role->id,
        ]);

        return $provider;
    }

    /** `TMT11`: senza `canCreate` una sessione assente si crea — è il primo accesso a un'applicazione. */
    public function test_by_default_a_missing_session_is_created(): void
    {
        $user = User::factory()->create(["enabled" => 1]);
        $provider = $this->providerWithAccess((int) config("idp.provider_id"), $user);

        $token = (new SessionService())->getValidProviderToken(
            $user,
            $provider->id,
            "1.2.3.4",
            "phpunit",
            new TokenProviderService(),
        );

        $this->assertNotNull($token, "il primo accesso deve poter creare la sessione: sennò è VDF16");
        $this->assertSame(1, Session::where("user_id", $user->id)->count());
    }

    /** `TMT11`: con `canCreate: false` una sessione assente **non** si crea — è una revoca. */
    public function test_with_can_create_false_a_missing_session_is_not_recreated(): void
    {
        $user = User::factory()->create(["enabled" => 1]);
        $provider = $this->providerWithAccess((int) config("idp.provider_id"), $user);

        $token = (new SessionService())->getValidProviderToken(
            $user,
            $provider->id,
            "1.2.3.4",
            "phpunit",
            new TokenProviderService(),
            "un.master.token",
            false,
        );

        $this->assertNull($token, "il rinnovo ha ricreato una sessione revocata");
        $this->assertSame(0, Session::where("user_id", $user->id)->count());
    }

    /** `TMT12`: la revoca vale su **tutti** i provider, non solo su quello guardato. */
    public function test_revoking_one_session_destroys_them_all(): void
    {
        $user = User::factory()->create(["enabled" => 1]);
        $primo = $this->providerWithAccess((int) config("idp.provider_id"), $user);
        $secondo = $this->providerWithAccess((int) config("idp.provider_id") + 1, $user);

        $service = new SessionService();
        $tokenService = new TokenProviderService();

        foreach ([$primo, $secondo] as $provider) {
            $service->getValidProviderToken($user, $provider->id, "1.2.3.4", "phpunit", $tokenService);
        }

        $this->assertSame(2, Session::where("user_id", $user->id)->count(), "servono due sessioni per provarlo");

        SessionService::destroyAllUserSessions($user->id);

        $this->assertSame(
            0,
            Session::where("user_id", $user->id)->count(),
            "una sessione è sopravvissuta su un altro provider: da lì l'utente rientra",
        );
    }

    // --- l'exchange dopo TMT27: rinnova e non crea ---------------------------------------------

    /** `TMT27`: dopo una revoca, l'exchange col master token ancora valido **non** ricrea la riga. */
    public function test_the_exchange_does_not_recreate_a_revoked_session(): void
    {
        $user = User::factory()->create(["enabled" => 1]);
        $provider = $this->providerWithAccess((int) config("idp.provider_id"), $user);
        $master = (new TokenProviderService())->generateMasterToken($user, $provider->id);

        // Il login apre la sessione (TMT28), poi l'amministratore la revoca.
        (new SessionService())->openProviderSession($user, $provider->id, "1.2.3.4", "phpunit", $master);
        SessionService::destroyAllUserSessions($user->id);

        $risposta = $this->postJson(
            "/api/v2/token/exchange",
            ["provider_id" => (string) $provider->id],
            ["x-master-token" => $master],
        );

        $risposta->assertStatus(403);
        $this->assertSame(0, Session::where("user_id", $user->id)->count(), "l'exchange ha ricreato la sessione revocata");
    }

    /** `TMT15`: un `provider_id` che non esiste è **404**, non 403: è un identificativo sbagliato. */
    public function test_an_unknown_provider_is_not_found_not_forbidden(): void
    {
        $user = User::factory()->create(["enabled" => 1]);
        $provider = $this->providerWithAccess((int) config("idp.provider_id"), $user);
        $master = (new TokenProviderService())->generateMasterToken($user, $provider->id);

        $this->postJson(
            "/api/v2/token/exchange",
            ["provider_id" => "9999"],
            ["x-master-token" => $master],
        )->assertStatus(404);
    }

    /** `TMT16` e `TMT18`: la v2 risponde con i due token e lascia una riga di audit. */
    public function test_the_v2_returns_both_tokens_and_writes_an_audit_row(): void
    {
        $user = User::factory()->create(["enabled" => 1]);
        $provider = $this->providerWithAccess((int) config("idp.provider_id"), $user);
        $master = (new TokenProviderService())->generateMasterToken($user, $provider->id);

        (new SessionService())->openProviderSession($user, $provider->id, "1.2.3.4", "phpunit", $master);

        $risposta = $this->postJson(
            "/api/v2/token/exchange",
            ["provider_id" => (string) $provider->id],
            ["x-master-token" => $master],
        );

        $risposta->assertStatus(200)->assertJsonStructure(["master_token", "app_token"]);

        $riga = DB::table("audits")->where("auditable_type", "AppToken")->latest("id")->first();

        $this->assertNotNull($riga, "nessuna riga di audit per l'app token staccato");
        $this->assertSame("created", $riga->event);
        $this->assertSame((int) $user->id, (int) $riga->user_id);
        $this->assertStringContainsString('"provider_id"', $riga->new_values);
    }

    /** La `v1` non cambia: risponde con il solo `token`, e non scrive audit. */
    public function test_the_v1_keeps_its_shape(): void
    {
        $user = User::factory()->create(["enabled" => 1]);
        $provider = $this->providerWithAccess((int) config("idp.provider_id"), $user);
        $master = (new TokenProviderService())->generateMasterToken($user, $provider->id);

        (new SessionService())->openProviderSession($user, $provider->id, "1.2.3.4", "phpunit", $master);

        $this->postJson(
            "/api/v1/token/exchange",
            ["provider_id" => (string) $provider->id],
            ["Authorization" => "Bearer {$master}"],
        )
            ->assertStatus(200)
            ->assertJsonStructure(["token"])
            ->assertJsonMissing(["app_token"]);

        $this->assertSame(0, DB::table("audits")->where("auditable_type", "AppToken")->count());
    }

    /**
     * `VDF16`, la prova sul percorso vero: **il login apre la sessione dell'applicazione**.
     *
     * E' il caso da cui nasceva il ciclo di richieste in staging — «Rinnovo rifiutato: nessuna
     * sessione» — e da oggi non deve piu' esserci, perche' la riga la scrive il login e non l'exchange.
     */
    public function test_logging_in_towards_a_provider_opens_its_session(): void
    {
        $user = User::factory()->create([
            "enabled" => 1,
            "password" => bcrypt("una-password-di-prova"),
            "password_expires_at" => now()->addYear(),
        ]);
        $provider = $this->providerWithAccess((int) config("idp.provider_id"), $user);

        $this->assertSame(0, Session::where("user_id", $user->id)->count(), "si parte senza sessioni");

        $this->post("/v2/login", [
            "username" => $user->username,
            "password" => "una-password-di-prova",
            "provider_id" => (string) $provider->id,
        ]);

        $riga = Session::where("user_id", $user->id)->where("provider_id", $provider->id)->first();

        $this->assertNotNull($riga, "il login non ha aperto la sessione: l'applicazione resterebbe fuori");
        $this->assertNotNull($riga->refresh_token, "la riga non porta il master token");
    }

    // --- TMT21: i due comportamenti che nessun test copriva ancora -----------------------------

    /**
     * `TMT03`: anche il login **all'IdP** salva tutti e due i token.
     *
     * Senza, l'IdP sarebbe l'unico posto senza master token nella riga — cioe' l'unico che non puo'
     * rinnovare, che e' esattamente il difetto da cui e' partito tutto (`VDF13`).
     */
    public function test_logging_into_the_idp_saves_both_tokens(): void
    {
        $user = User::factory()->create([
            "enabled" => 1,
            "password" => bcrypt("una-password-di-prova"),
            "password_expires_at" => now()->addYear(),
        ]);

        // Amministratore dell'IdP: il ruolo deve avere l'id che `config("role.admin_id")` dichiara.
        $idpProviderId = (int) config("idp.provider_id");
        $provider = Provider::forceCreate([
            "id" => $idpProviderId,
            "domain" => "esempio.it",
            "url" => "https://esempio.it",
            "protocol" => "https",
            "secret_key" => Str::random(32),
            "logoutUrl" => "https://esempio.it/logout",
            "name" => "IDP",
        ]);
        Role::forceCreate(["id" => (int) config("role.admin_id"), "name" => "admin", "provider_id" => $provider->id]);
        DB::table("provider_user_roles")->insert([
            "user_id" => $user->id,
            "provider_id" => $provider->id,
            "role_id" => (int) config("role.admin_id"),
        ]);

        // Login SENZA `provider_id`: e' l'accesso all'IdP stesso.
        $this->post("/v2/login", ["username" => $user->username, "password" => "una-password-di-prova"]);

        $riga = Session::where("user_id", $user->id)->where("provider_id", $provider->id)->first();

        $this->assertNotNull($riga, "il login all'IdP non ha aperto la sessione");
        $this->assertNotNull($riga->token, "manca l'app token");
        $this->assertNotNull($riga->refresh_token, "manca il master token: l'IdP non potrebbe rinnovare");
    }

    /** `TMT15`: un utente che non ha ruoli su quel provider riceve **403**, non 404 e non un token. */
    public function test_a_user_without_roles_on_the_provider_is_forbidden(): void
    {
        $user = User::factory()->create(["enabled" => 1]);
        $conAccesso = User::factory()->create(["enabled" => 1]);

        // Il provider esiste, e l'altro utente ci ha accesso: cosi' il 403 non puo' venire da altro.
        $provider = $this->providerWithAccess((int) config("idp.provider_id"), $conAccesso);
        $master = (new TokenProviderService())->generateMasterToken($user, $provider->id);

        $this->postJson(
            "/api/v2/token/exchange",
            ["provider_id" => (string) $provider->id],
            ["x-master-token" => $master],
        )->assertStatus(403);
    }
}
