<?php

namespace Tests\Feature\Auth;

use App\Models\Parameter;
use App\Models\Provider;
use App\Models\Role;
use App\Models\Session;
use App\Models\User;
use App\Services\SessionService;
use App\Services\TokenProviderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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

    /**
     * Le due rotte dello scambio, tenute distinte apposta: meta' di questi test esiste per provare
     * che la `v1` e la `v2` si comportano in modo diverso, e una costante sola con la versione
     * infilata dentro nasconderebbe proprio quella differenza.
     */
    private const EXCHANGE_V1 = "/api/v1/token/exchange";
    private const EXCHANGE_V2 = "/api/v2/token/exchange";

    /**
     * L'indirizzo di chi apre la sessione. Non e' locale apposta: nella riga di sessione si
     * distingue a colpo d'occhio da un `127.0.0.1` messo li' da qualcos'altro.
     *
     * PERCHE' PROPRIO QUESTO: `203.0.113.0/24` e' la rete **riservata alla documentazione** dalla
     * RFC 5737 — esiste per essere scritta negli esempi e non appartiene a nessuno. Prima qui c'era
     * un indirizzo pubblico **vero e instradabile**, di qualcun altro: l'analisi statica lo
     * segnalava («Make sure using this hardcoded IP address is safe here») e aveva ragione, perche'
     * un indirizzo altrui scritto in un test un giorno finisce in una chiamata vera.
     */
    private const CLIENT_IP = "203.0.113.4";

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
            self::CLIENT_IP,
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
            self::CLIENT_IP,
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
            $service->getValidProviderToken($user, $provider->id, self::CLIENT_IP, "phpunit", $tokenService);
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
        (new SessionService())->openProviderSession($user, $provider->id, self::CLIENT_IP, "phpunit", $master);
        SessionService::destroyAllUserSessions($user->id);

        $risposta = $this->postJson(
            self::EXCHANGE_V2,
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
            self::EXCHANGE_V2,
            ["provider_id" => "9999"],
            ["x-master-token" => $master],
        )->assertStatus(404);
    }

    /**
     * `TMT16` e `TMT18`: la v2 restituisce i due token **negli header** e lascia una riga di audit.
     *
     * Forma decisa dal developer il 2026-08-28: `x-master-token` e `x-app-token` negli header, corpo
     * vuoto. E' simmetrica alla richiesta, che i token li manda negli header a sua volta.
     */
    public function test_the_v2_returns_both_tokens_and_writes_an_audit_row(): void
    {
        $user = User::factory()->create(["enabled" => 1]);
        $provider = $this->providerWithAccess((int) config("idp.provider_id"), $user);
        $master = (new TokenProviderService())->generateMasterToken($user, $provider->id);

        (new SessionService())->openProviderSession($user, $provider->id, self::CLIENT_IP, "phpunit", $master);

        $risposta = $this->postJson(
            self::EXCHANGE_V2,
            ["provider_id" => (string) $provider->id],
            ["x-master-token" => $master],
        );

        $risposta->assertStatus(200);
        $this->assertNotEmpty($risposta->headers->get("x-master-token"), "manca l'header x-master-token");
        $this->assertNotEmpty($risposta->headers->get("x-app-token"), "manca l'header x-app-token");
        $this->assertSame($master, $risposta->headers->get("x-master-token"), "il master token dell'header non e' quello presentato");
        $this->assertSame([], $risposta->json(), "il corpo della v2 deve essere vuoto: i token stanno negli header");

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

        (new SessionService())->openProviderSession($user, $provider->id, self::CLIENT_IP, "phpunit", $master);

        $this->postJson(
            self::EXCHANGE_V1,
            ["provider_id" => (string) $provider->id],
            ["Authorization" => "Bearer {$master}"],
        )
            ->assertStatus(200)
            ->assertJsonStructure(["token"])
            ->assertHeaderMissing("x-app-token")
            ->assertHeaderMissing("x-master-token");

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
            self::EXCHANGE_V2,
            ["provider_id" => (string) $provider->id],
            ["x-master-token" => $master],
        )->assertStatus(403);
    }

    // --- TMT17: la rotazione del master token, solo sulla v2 -----------------------------------

    /**
     * Un master token **emesso `$oreFa` ore fa**, firmato a mano con la stessa chiave del servizio.
     *
     * PERCHE' NON SI USA `travelTo()`: `generateMasterToken()` scrive `iat` con `time()` di PHP, che
     * il viaggio nel tempo di Laravel **non falsifica** — quello sposta solo Carbon. Un token generato
     * "nel passato" avrebbe comunque `iat` di adesso, e la rotazione non scatterebbe: il test
     * passerebbe senza provare niente.
     */
    private function masterTokenIssuedHoursAgo(User $user, Provider $provider, int $oreFa): string
    {
        $emesso = time() - $oreFa * 3600;

        return JWT::encode(
            [
                "iss" => $provider->url,
                "iat" => $emesso,
                "exp" => $emesso + 28800,
                "sub" => (string) $user->id,
                "payload" => ["user" => ["id" => $user->id, "username" => $user->username]],
            ],
            File::get(storage_path("app/keys/private.key")),
            "RS256",
            config("idp.jwt.master_key_id"),
        );
    }

    /**
     * Un master token con **emissione e scadenza scelte**, firmato a mano con la stessa chiave.
     *
     * PERCHE' A MANO E NON CON UNA DURATA DI UN SECONDO: un token che vive un secondo obbliga la
     * prima chiamata ad arrivare entro quel secondo, e su una macchina carica non ci arriva — il
     * test falliva a intermittenza (osservato il 2026-08-31). Scegliendo `exp` non c'e' nessuna
     * corsa: il token e' scaduto o non lo e', sempre, e non serve nessuna attesa.
     */
    private function masterTokenLasting(User $user, Provider $provider, int $emessoSecondiFa, int $scadeFraSecondi): string
    {
        $emesso = time() - $emessoSecondiFa;

        return JWT::encode(
            [
                "iss" => $provider->url,
                "iat" => $emesso,
                "exp" => time() + $scadeFraSecondi,
                "sub" => (string) $user->id,
                "payload" => ["user" => ["id" => $user->id, "username" => $user->username]],
            ],
            File::get(storage_path("app/keys/private.key")),
            "RS256",
            config("idp.jwt.master_key_id"),
        );
    }

    /** `TMT17`: sulla v2, un master token di piu' di un'ora viene **rigenerato**. */
    public function test_the_v2_rotates_a_master_token_older_than_an_hour(): void
    {
        $user = User::factory()->create(["enabled" => 1]);
        $provider = $this->providerWithAccess((int) config("idp.provider_id"), $user);
        $vecchio = $this->masterTokenIssuedHoursAgo($user, $provider, 2);

        (new SessionService())->openProviderSession($user, $provider->id, self::CLIENT_IP, "phpunit", $vecchio);

        $risposta = $this->postJson(
            self::EXCHANGE_V2,
            ["provider_id" => (string) $provider->id],
            ["x-master-token" => $vecchio],
        )->assertStatus(200);

        $nuovo = $risposta->headers->get("x-master-token");

        $this->assertNotSame($vecchio, $nuovo, "il master token vecchio non e' stato ruotato");

        // La riga da guardare e' quella **del master token** — senza provider — e va nominata: con un
        // `first()` si prenderebbe la prima riga qualunque, e il test passerebbe o no secondo l'ordine.
        $this->assertSame(
            $nuovo,
            (new SessionService())->masterSessionFor($user->id)->refresh_token,
            "la riga del master token non porta quello nuovo",
        );
    }

    /** `TMT17`: un master token fresco **non** si tocca. */
    public function test_a_fresh_master_token_is_not_rotated(): void
    {
        $user = User::factory()->create(["enabled" => 1]);
        $provider = $this->providerWithAccess((int) config("idp.provider_id"), $user);
        $fresco = (new TokenProviderService())->generateMasterToken($user, $provider->id);

        (new SessionService())->openProviderSession($user, $provider->id, self::CLIENT_IP, "phpunit", $fresco);

        $risposta = $this->postJson(
            self::EXCHANGE_V2,
            ["provider_id" => (string) $provider->id],
            ["x-master-token" => $fresco],
        )->assertStatus(200);

        $this->assertSame($fresco, $risposta->headers->get("x-master-token"), "un token fresco non va ruotato");
    }

    /**
     * `TMT17`, la prova che conta: **il master token vecchio continua a funzionare** dopo la rotazione.
     *
     * Se non fosse cosi', al primo rilascio ogni client che non sa leggere l'header nuovo verrebbe
     * disconnesso — ed e' la ragione per cui la rotazione **non invalida** il precedente.
     */
    public function test_the_old_master_token_keeps_working_after_a_rotation(): void
    {
        $user = User::factory()->create(["enabled" => 1]);
        $provider = $this->providerWithAccess((int) config("idp.provider_id"), $user);
        $vecchio = $this->masterTokenIssuedHoursAgo($user, $provider, 2);

        (new SessionService())->openProviderSession($user, $provider->id, self::CLIENT_IP, "phpunit", $vecchio);

        $corpo = ["provider_id" => (string) $provider->id];
        $this->postJson(self::EXCHANGE_V2, $corpo, ["x-master-token" => $vecchio])->assertStatus(200);

        // Seconda chiamata con lo **stesso** token vecchio: deve funzionare ancora.
        $this->postJson(self::EXCHANGE_V2, $corpo, ["x-master-token" => $vecchio])->assertStatus(200);
    }

    /** `TMT17`: la `v1` non ruota — la sua riga tiene il master token che le e' stato dato. */
    public function test_the_v1_does_not_rotate(): void
    {
        $user = User::factory()->create(["enabled" => 1]);
        $provider = $this->providerWithAccess((int) config("idp.provider_id"), $user);
        $vecchio = $this->masterTokenIssuedHoursAgo($user, $provider, 2);

        (new SessionService())->openProviderSession($user, $provider->id, self::CLIENT_IP, "phpunit", $vecchio);

        $this->postJson(
            self::EXCHANGE_V1,
            ["provider_id" => (string) $provider->id],
            ["Authorization" => "Bearer {$vecchio}"],
        )->assertStatus(200);

        $this->assertSame(
            $vecchio,
            Session::where("user_id", $user->id)->first()->refresh_token,
            "la v1 ha ruotato il master token: non deve",
        );
    }

    // --- TMT17, la scadenza vera: un master token che dura un secondo -------------------------

    /** Mette un parametro a un valore, come farebbe l'amministratore da `/admin/parameters`. */
    private function parameter(string $key, string $value): void
    {
        Parameter::updateOrCreate(["key" => $key], ["value" => $value, "type" => "int"]);
    }

    /**
     * `TMT17` sulla `v1`: **il master token scade e la sessione non si rinnova piu'**.
     *
     * Con la durata a un secondo, l'exchange funziona subito e non funziona piu' un istante dopo. La
     * v1 non ruota, quindi qui non c'e' scampo — ed e' il comportamento voluto: e' la scadenza che fa
     * il suo lavoro.
     */
    public function test_on_v1_an_expired_master_token_cannot_renew_anymore(): void
    {
        $user = User::factory()->create(["enabled" => 1]);
        $provider = $this->providerWithAccess((int) config("idp.provider_id"), $user);

        $vivo = $this->masterTokenLasting($user, $provider, 0, 3600);
        $scaduto = $this->masterTokenLasting($user, $provider, 10, -1);

        (new SessionService())->openProviderSession($user, $provider->id, self::CLIENT_IP, "phpunit", $vivo);

        $corpo = ["provider_id" => (string) $provider->id];

        // Finche' e' vivo: si rinnova.
        $this->postJson(self::EXCHANGE_V1, $corpo, ["Authorization" => "Bearer {$vivo}"])->assertStatus(200);

        // Scaduto: `VerifyMasterToken` lo rifiuta, e la v1 non ruota, quindi non c'e' scampo.
        $this->postJson(self::EXCHANGE_V1, $corpo, ["Authorization" => "Bearer {$scaduto}"])->assertStatus(401);
    }

    /** La durata del master token e' un **parametro**: qui si legge nel token che ne esce. */
    public function test_the_master_token_duration_comes_from_the_parameter(): void
    {
        $this->parameter("master-token-exp-time-seconds", "1");

        $user = User::factory()->create(["enabled" => 1]);
        $provider = $this->providerWithAccess((int) config("idp.provider_id"), $user);
        $master = (new TokenProviderService())->generateMasterToken($user, $provider->id);

        $payload = json_decode(base64_decode(strtr(explode(".", $master)[1], "-_", "+/")), true);

        $this->assertSame(1, $payload["exp"] - $payload["iat"], "la durata non viene dal parametro");
    }

    /**
     * `TMT17` sulla `v2` con la rotazione **a un'ora**: la scadenza vince lo stesso.
     *
     * E' il caso che si potrebbe dare per scontato al contrario: «la v2 ruota, quindi non scade mai».
     * Non e' cosi' — con la soglia a un'ora e la durata a un secondo, il token muore **prima** che la
     * rotazione lo consideri vecchio.
     */
    public function test_on_v2_the_expiry_wins_when_rotation_is_far_away(): void
    {
        $this->parameter("master-token-rotate-after-seconds", "3600");

        $user = User::factory()->create(["enabled" => 1]);
        $provider = $this->providerWithAccess((int) config("idp.provider_id"), $user);
        $master = (new TokenProviderService())->generateMasterToken($user, $provider->id);

        (new SessionService())->openProviderSession($user, $provider->id, self::CLIENT_IP, "phpunit", $master);

        $corpo = ["provider_id" => (string) $provider->id];

        $risposta = $this->postJson(self::EXCHANGE_V2, $corpo, ["x-master-token" => $master])->assertStatus(200);
        $this->assertSame($master, $risposta->headers->get("x-master-token"), "non doveva ruotare: ha meno di un'ora");

        // Lo stesso utente, con un master token scaduto: la rotazione lontana non lo salva.
        $scaduto = $this->masterTokenLasting($user, $provider, 10, -1);
        $this->postJson(self::EXCHANGE_V2, $corpo, ["x-master-token" => $scaduto])->assertStatus(401);
    }

    /**
     * `TMT17` sulla `v2` con la rotazione **piu' corta della scadenza**: il token si rinnova da se'.
     *
     * E' il caso per cui la rotazione esiste. La soglia a un secondo e la durata a dieci: al secondo
     * exchange il token ha passato la soglia, ne arriva uno nuovo, e **quello nuovo funziona** anche
     * quando il primo sarebbe morto.
     */
    public function test_on_v2_a_short_rotation_keeps_the_session_alive(): void
    {
        $this->parameter("master-token-exp-time-seconds", "600");
        $this->parameter("master-token-rotate-after-seconds", "1");

        $user = User::factory()->create(["enabled" => 1]);
        $provider = $this->providerWithAccess((int) config("idp.provider_id"), $user);

        // Emesso cinque secondi fa e vivo per altri dieci minuti: ha gia' passato la soglia di
        // rotazione senza essere vicino alla scadenza. Firmato a mano, cosi' non serve aspettare.
        $master = $this->masterTokenLasting($user, $provider, 5, 600);

        (new SessionService())->openProviderSession($user, $provider->id, self::CLIENT_IP, "phpunit", $master);

        $corpo = ["provider_id" => (string) $provider->id];

        $risposta = $this->postJson(self::EXCHANGE_V2, $corpo, ["x-master-token" => $master])
            ->assertStatus(200);

        $nuovo = $risposta->headers->get("x-master-token");
        $this->assertNotSame($master, $nuovo, "la soglia era passata: doveva ruotare");

        // E il token nuovo vale: e' la ragione per cui la rotazione tiene viva la sessione.
        $this->postJson(self::EXCHANGE_V2, $corpo, ["x-master-token" => $nuovo])->assertStatus(200);
    }

    /** La soglia e' un **parametro**, e il ripiego e' un'ora: senza riga a database vale 3600. */
    public function test_the_rotation_threshold_is_a_parameter_with_an_hour_fallback(): void
    {
        $service = new TokenProviderService();

        $this->assertSame(3600, $service->getMasterTokenRotateAfter(), "senza parametro il ripiego non e' un'ora");

        $this->parameter("master-token-rotate-after-seconds", "120");

        $this->assertSame(120, (new TokenProviderService())->getMasterTokenRotateAfter(), "il parametro non viene letto");
    }

    // --- TMT22 e TMT23: il modello della v2 ----------------------------------------------------

    /** `TMT23`: il login scrive **anche** la riga del master token, che e' quella senza provider. */
    public function test_the_login_writes_the_master_session_row(): void
    {
        $user = User::factory()->create(["enabled" => 1]);
        $provider = $this->providerWithAccess((int) config("idp.provider_id"), $user);
        $master = (new TokenProviderService())->generateMasterToken($user, $provider->id);

        (new SessionService())->openProviderSession($user, $provider->id, self::CLIENT_IP, "phpunit", $master);

        $riga = (new SessionService())->masterSessionFor($user->id);

        $this->assertNotNull($riga, "manca la riga del master token: la v2 non avrebbe niente da guardare");
        $this->assertNull($riga->provider_id, "la riga del master token non deve avere un provider (TMT22)");
        $this->assertSame($master, $riga->refresh_token);
    }

    /**
     * `TMT23`: un exchange sulla `v2` **non crea righe per provider**.
     *
     * E' la differenza fra i due modelli, e si vede contando: dopo il login c'e' la riga del provider
     * (la scrive il login, per la v1) e quella del master token. Un exchange v2 su un **secondo**
     * provider non ne aggiunge una terza.
     */
    public function test_a_v2_exchange_does_not_create_a_provider_row(): void
    {
        $user = User::factory()->create(["enabled" => 1]);
        $primo = $this->providerWithAccess((int) config("idp.provider_id"), $user);
        $secondo = $this->providerWithAccess((int) config("idp.provider_id") + 1, $user);
        $master = (new TokenProviderService())->generateMasterToken($user, $primo->id);

        (new SessionService())->openProviderSession($user, $primo->id, self::CLIENT_IP, "phpunit", $master);

        $prima = Session::where("user_id", $user->id)->count();

        $this->postJson(
            self::EXCHANGE_V2,
            ["provider_id" => (string) $secondo->id],
            ["x-master-token" => $master],
        )->assertStatus(200);

        $this->assertSame($prima, Session::where("user_id", $user->id)->count(), "la v2 ha creato una riga per provider");
        $this->assertSame(
            0,
            Session::where("user_id", $user->id)->where("provider_id", $secondo->id)->count(),
            "la v2 non deve avere una riga per il provider chiesto",
        );
    }

    /** `TMT23`: se la riga del master token non c'e', la `v2` rifiuta — e' una revoca. */
    public function test_the_v2_refuses_when_the_master_session_is_gone(): void
    {
        $user = User::factory()->create(["enabled" => 1]);
        $provider = $this->providerWithAccess((int) config("idp.provider_id"), $user);
        $master = (new TokenProviderService())->generateMasterToken($user, $provider->id);

        (new SessionService())->openProviderSession($user, $provider->id, self::CLIENT_IP, "phpunit", $master);
        SessionService::destroyAllUserSessions($user->id);

        $this->postJson(
            self::EXCHANGE_V2,
            ["provider_id" => (string) $provider->id],
            ["x-master-token" => $master],
        )->assertStatus(403);
    }

    // --- TMT32: la riga senza provider e' la prova, le altre nascono su richiesta --------------

    /**
     * `TMT32`: il login verso un'applicazione **esterna** scrive **una sola** riga, quella senza
     * provider. La riga del provider nasce quando una chiamata `v1` la chiede.
     */
    public function test_the_login_towards_an_external_app_writes_only_the_master_row(): void
    {
        $user = User::factory()->create(["enabled" => 1]);
        $esterna = $this->providerWithAccess((int) config("idp.provider_id") + 1, $user);
        $master = (new TokenProviderService())->generateMasterToken($user, $esterna->id);

        (new SessionService())->openProviderSession($user, $esterna->id, self::CLIENT_IP, "phpunit", $master);

        $this->assertSame(1, Session::where("user_id", $user->id)->count(), "il login ha scritto piu' di una riga");
        $this->assertNotNull(
            (new SessionService())->masterSessionFor($user->id),
            "la riga scritta non e' quella senza provider",
        );

        // Ora arriva una chiamata v1: la riga per provider nasce qui.
        $this->postJson(
            self::EXCHANGE_V1,
            ["provider_id" => (string) $esterna->id],
            ["Authorization" => "Bearer {$master}"],
        )->assertStatus(200);

        $this->assertSame(2, Session::where("user_id", $user->id)->count(), "la v1 non ha creato la sua riga");
        $this->assertSame(
            1,
            Session::where("user_id", $user->id)->where("provider_id", $esterna->id)->count(),
            "manca la riga del provider chiesto",
        );
    }

    /** `TMT32`: una `v2` non fa nascere nessuna riga per provider, nemmeno chiamandola due volte. */
    public function test_a_v2_app_never_gets_a_provider_row(): void
    {
        $user = User::factory()->create(["enabled" => 1]);
        $esterna = $this->providerWithAccess((int) config("idp.provider_id") + 1, $user);
        $master = (new TokenProviderService())->generateMasterToken($user, $esterna->id);

        (new SessionService())->openProviderSession($user, $esterna->id, self::CLIENT_IP, "phpunit", $master);

        $corpo = ["provider_id" => (string) $esterna->id];
        $this->postJson(self::EXCHANGE_V2, $corpo, ["x-master-token" => $master])->assertStatus(200);
        $this->postJson(self::EXCHANGE_V2, $corpo, ["x-master-token" => $master])->assertStatus(200);

        $this->assertSame(1, Session::where("user_id", $user->id)->count(), "la v2 ha creato righe che non le servono");
    }

    /**
     * `TMT32`, la prova che il modello non riapre `VDF14`: dopo una revoca **anche la `v1`** rifiuta,
     * perche' la riga senza provider — la prova che l'utente e' entrato — e' sparita con le altre.
     */
    public function test_after_a_revocation_even_the_v1_refuses(): void
    {
        $user = User::factory()->create(["enabled" => 1]);
        $esterna = $this->providerWithAccess((int) config("idp.provider_id") + 1, $user);
        $master = (new TokenProviderService())->generateMasterToken($user, $esterna->id);

        (new SessionService())->openProviderSession($user, $esterna->id, self::CLIENT_IP, "phpunit", $master);
        SessionService::destroyAllUserSessions($user->id);

        $this->postJson(
            self::EXCHANGE_V1,
            ["provider_id" => (string) $esterna->id],
            ["Authorization" => "Bearer {$master}"],
        )->assertStatus(403);

        $this->assertSame(0, Session::where("user_id", $user->id)->count(), "la v1 ha ricreato una sessione revocata");
    }
}
