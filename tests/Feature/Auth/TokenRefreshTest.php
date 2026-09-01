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
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tymon\JWTAuth\Providers\JWT\Lcobucci;

class TokenRefreshTest extends TestCase
{
    use RefreshDatabase;

    private const PROBE_URI = "/__probe/refresh";

    /** La rotta dello scambio: qui si prova solo la `v2`. */
    private const EXCHANGE_V2 = "/api/v2/token/exchange";

    /**
     * L'indirizzo di chi apre la sessione, non locale apposta (vedi `SessionRevocationTest`, dove
     * c'e' anche il perche' della rete `203.0.113.0/24`: e' quella riservata alla documentazione
     * dalla RFC 5737, e non appartiene a nessuno).
     */
    private const CLIENT_IP = "203.0.113.4";

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(["web", "authenticated"])->get(self::PROBE_URI, fn() => response()->json(["ok" => true]));
    }

    private function idpProvider(): Provider
    {
        return Provider::forceCreate([
            "id" => (int) config("idp.provider_id"),
            "domain" => "localhost",
            "url" => "http://localhost",
            "protocol" => "http",
            "secret_key" => Str::random(32),
            "logoutUrl" => "http://localhost/logout",
            "name" => "IDP",
        ]);
    }

    private function appToken(Provider $provider, User $user, int $exp): string
    {
        $jwt = new Lcobucci($provider->secret_key, config("jwt.algo", "HS256"), config("jwt.keys", []));

        return $jwt->encode(["sub" => $user->id, "exp" => $exp]);
    }

    private function sessionFor(User $user, Provider $provider, string $token, ?int $secondi = null): Session
    {
        return Session::create([
            "id" => (string) Str::uuid(),
            "user_id" => $user->id,
            "provider_id" => $provider->id,
            "token" => $token,
            "ip_address" => env("TEST_IP_ADDRESS"),
            "user_agent" => env("TEST_USER_AGENT"),
            "expires_at" => now()->addSeconds($secondi ?? 1800),
        ]);
    }

    /** Una NAVIGAZIONE: niente `Accept: application/json`, quindi il rinnovo si tenta (TMT10). */
    private function browseWith(string $appToken, ?string $masterToken = null)
    {
        $richiesta = $this->withUnencryptedCookie("idp_token_" . config("idp.provider_id"), $appToken);

        if ($masterToken !== null) {
            $richiesta = $richiesta->withUnencryptedCookie(config("idp.jwt.master_token_name"), $masterToken);
        }

        return $richiesta->get(self::PROBE_URI);
    }

    /** Una chiamata API: chiede JSON e non e' Inertia. */
    private function callWith(string $appToken, ?string $masterToken = null)
    {
        $richiesta = $this->withHeaders(["Accept" => "application/json"]);

        if ($masterToken !== null) {
            $richiesta = $richiesta->withUnencryptedCookie(config("idp.jwt.master_token_name"), $masterToken);
        }

        return $richiesta
            ->withUnencryptedCookie("idp_token_" . config("idp.provider_id"), $appToken)
            ->get(self::PROBE_URI);
    }

    /**
     * **Riscritto il 2026-08-31 con `TMT30`**, ed e' la terza volta: prima fotografava «l'IdP non
     * rinnova», poi «una chiamata API non si rinnova», e nessuna delle due era piu' vera.
     *
     * Ora dice quello che vale oggi: **a decidere e' la sessione, non il tipo di richiesta**. Con la
     * riga viva e l'utente autorizzato, anche una chiamata API viene rinnovata — perche' le XHR
     * dell'interfaccia amministrativa sono chiamate API a tutti gli effetti, e sloggarle significava
     * sloggare chi stava guardando la pagina.
     */
    public function test_an_api_call_is_renewed_when_the_session_is_alive(): void
    {
        $provider = $this->idpProvider();
        $user = $this->userWithAccess($provider);

        $scaduto = $this->appToken($provider, $user, time() - 60);
        $this->sessionFor($user, $provider, $scaduto, 28800);

        $master = (new TokenProviderService())->generateMasterToken($user, $provider->id);

        $this->callWith($scaduto, $master)->assertStatus(200);
    }

    /**
     * `TMT30`, l'altra meta': una chiamata API che **non** si puo' rinnovare riceve 401 **e i cookie
     * restano dov'erano**.
     *
     * E' il difetto che il developer ha visto il 2026-08-31: `forceLogoutAndRedirect()` accoda
     * `Cookie::forget`, quindi una XHR rifiutata portava via il cookie del browser e la navigazione
     * successiva ripartiva dal login.
     */
    public function test_a_refused_api_call_does_not_clear_the_cookies(): void
    {
        $provider = $this->idpProvider();
        $user = $this->userWithAccess($provider);

        $scaduto = $this->appToken($provider, $user, time() - 60);
        // Nessuna riga di sessione: il rinnovo non si puo' fare.
        $master = (new TokenProviderService())->generateMasterToken($user, $provider->id);

        $risposta = $this->callWith($scaduto, $master)->assertStatus(401);

        $risposta->assertCookieMissing("idp_token_" . config("idp.provider_id"));
    }

    /** Il master token c'e' e non serve a niente: senza di lui il risultato e' identico. */
    public function test_with_or_without_the_master_token_the_result_is_the_same(): void
    {
        $provider = $this->idpProvider();
        $user = User::factory()->create(["enabled" => 1]);

        $scaduto = $this->appToken($provider, $user, time() - 60);
        $this->sessionFor($user, $provider, $scaduto, 28800);

        $conMaster = $this->callWith($scaduto, (new TokenProviderService())->generateMasterToken($user, $provider->id));
        $senzaMaster = $this->callWith($scaduto);

        $this->assertSame(
            $senzaMaster->getStatusCode(),
            $conMaster->getStatusCode(),
            "il master token cambia il risultato: il rinnovo esiste gia' e questo test va riscritto",
        );
    }

    /** Il token valido passa: e' la controprova che il 401 di sopra dipende dalla SCADENZA. */
    public function test_a_valid_app_token_passes(): void
    {
        $provider = $this->idpProvider();
        $user = User::factory()->create(["enabled" => 1]);

        $valido = $this->appToken($provider, $user, time() + 3600);
        $this->sessionFor($user, $provider, $valido);

        $this->callWith($valido)->assertStatus(200);
    }

    // --- cio' che TMT01…TMT05 hanno cambiato, e che non deve tornare indietro -----------------

    /** `TMT01`: l'exchange stacca sempre un token nuovo, non riusa quello salvato. */
    public function test_the_exchange_always_mints_a_new_app_token(): void
    {
        $provider = $this->idpProvider();
        $user = $this->userWithAccess($provider);

        $tokenService = new TokenProviderService();
        $sessionService = new SessionService();

        $primo = $sessionService->getValidProviderToken($user, $provider->id, self::CLIENT_IP, "phpunit", $tokenService);
        sleep(1); // il JWT porta `iat`: due firme nello stesso secondo sarebbero identiche
        $secondo = $sessionService->getValidProviderToken($user, $provider->id, self::CLIENT_IP, "phpunit", $tokenService);

        $this->assertNotSame($primo, $secondo, "l'exchange ha riusato il token salvato: TMT01 e' tornato indietro");
    }

    /** `TMT02`: la riga porta il master token e dura quanto lui, non quanto l'app token. */
    public function test_the_session_row_carries_the_master_token_and_lasts_as_long(): void
    {
        $provider = $this->idpProvider();
        $user = $this->userWithAccess($provider);
        $master = "un.master.token";

        (new SessionService())->getValidProviderToken(
            $user,
            $provider->id,
            self::CLIENT_IP,
            "phpunit",
            new TokenProviderService(),
            $master,
        );

        $riga = Session::where("user_id", $user->id)->first();

        $this->assertSame($master, $riga->refresh_token, "il master token non e' finito in refresh_token");
        $this->assertNotSame($master, $riga->token, "in `token` deve restare l'app token: due ricerche lo cercano li'");
        $this->assertGreaterThanOrEqual(
            7,
            now()->diffInHours($riga->expires_at),
            "la riga non dura quanto il master token",
        );
    }

    /** `TMT05`: la rotta v2 esiste, e la protegge lo stesso middleware della v1. */
    public function test_the_v2_exchange_route_exists_and_is_protected(): void
    {
        $this->postJson(self::EXCHANGE_V2, ["provider_id" => "1"])->assertStatus(401);
    }

    /** `TMT04`: il master token si accetta in tutte e tre le forme, e senza header no. */
    public function test_the_master_token_is_read_from_both_headers(): void
    {
        $provider = $this->idpProvider();
        $user = $this->userWithAccess($provider);
        $master = (new TokenProviderService())->generateMasterToken($user, $provider->id);

        // Da TMT27 l'exchange **rinnova e non crea**: la sessione la apre il login (TMT28). Senza
        // questa riga la risposta sarebbe 403, ed e' il comportamento voluto — la revoca deve valere.
        (new SessionService())->openProviderSession($user, $provider->id, self::CLIENT_IP, "phpunit", $master);

        $corpo = ["provider_id" => (string) $provider->id];

        $this->postJson(self::EXCHANGE_V2, $corpo, ["Authorization" => "Bearer {$master}"])->assertStatus(200);
        $this->postJson(self::EXCHANGE_V2, $corpo, ["x-master-token" => $master])->assertStatus(200);
        $this->postJson(self::EXCHANGE_V2, $corpo, ["x-master-token" => "Bearer {$master}"])->assertStatus(200);
    }

    /** Un utente con accesso al provider: senza ruolo, `getValidProviderToken()` rifiuta ed e' giusto. */
    private function userWithAccess(Provider $provider): User
    {
        $user = User::factory()->create(["enabled" => 1]);
        $ruolo = Role::create(["name" => "admin", "provider_id" => $provider->id]);

        DB::table("provider_user_roles")->insert([
            "user_id" => $user->id,
            "provider_id" => $provider->id,
            "role_id" => $ruolo->id,
        ]);

        return $user;
    }

    // --- TMT08, TMT09, TMT10: il rinnovo dentro l'IdP -----------------------------------------

    /** `TMT08`: navigando, con l'app token scaduto e il master valido, l'IdP **rinnova**. */
    public function test_browsing_with_an_expired_app_token_renews_it(): void
    {
        $provider = $this->idpProvider();
        $user = $this->userWithAccess($provider);

        $scaduto = $this->appToken($provider, $user, time() - 60);
        $this->sessionFor($user, $provider, $scaduto, 28800);

        $master = (new TokenProviderService())->generateMasterToken($user, $provider->id);

        $this->browseWith($scaduto, $master)->assertStatus(200);

        $riga = Session::where("user_id", $user->id)->first();
        $this->assertNotSame($scaduto, $riga->token, "la sessione porta ancora il token scaduto: non ha rinnovato");
    }

    /** `TMT09`, primo esito: senza master token la sessione e' finita davvero. */
    public function test_browsing_without_a_master_token_ends_the_session(): void
    {
        $provider = $this->idpProvider();
        $user = $this->userWithAccess($provider);

        $scaduto = $this->appToken($provider, $user, time() - 60);
        $this->sessionFor($user, $provider, $scaduto, 28800);

        $this->browseWith($scaduto)->assertRedirect();
    }

    /**
     * `TMT08`, la parte che protegge `VDF14`: **il rinnovo non ricrea una sessione revocata.**
     * L'amministratore cancella la riga, e la richiesta successiva non deve rimetterla.
     */
    public function test_a_revoked_session_is_not_recreated_by_the_renewal(): void
    {
        $provider = $this->idpProvider();
        $user = $this->userWithAccess($provider);

        $scaduto = $this->appToken($provider, $user, time() - 60);
        $master = (new TokenProviderService())->generateMasterToken($user, $provider->id);

        // Nessuna riga: e' il caso della sessione revocata dall'amministratore.
        $this->browseWith($scaduto, $master)->assertRedirect();

        $this->assertSame(0, Session::where("user_id", $user->id)->count(), "il rinnovo ha ricreato la sessione revocata");
    }
}
