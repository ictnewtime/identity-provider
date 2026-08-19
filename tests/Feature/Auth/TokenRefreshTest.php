<?php

namespace Tests\Feature\Auth;

use App\Models\Provider;
use App\Models\ProviderUserRole;
use App\Models\Role;
use App\Models\Session;
use App\Models\User;
use App\Services\SessionService;
use App\Services\TokenProviderService;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tymon\JWTAuth\Providers\JWT\Lcobucci;

/**
 * Il rinnovo trasparente dell'app token dentro l'IdP (punti TTR04/TTR05/TTR06, difetto VDF13).
 *
 * Questo file **e' stato riscritto**. Nella prima stesura (`TTR02`) fotografava il difetto: era
 * verde perche' l'IdP disconnetteva, e dichiarava in testa che sarebbe dovuto diventare rosso.
 * E' successo: le due asserzioni «401» sono ora «200», e quella che diceva «col master token o
 * senza il risultato e' lo stesso» dice l'opposto. La versione precedente sta nel git di questo
 * file, ed e' la prova che la correzione ha cambiato il comportamento.
 *
 * Le tre regole che il rinnovo NON allarga, una per test:
 * - la sessione revocata **non si ricrea** (VDF14);
 * - il vincolo IP + user agent resta;
 * - `expires_at` della sessione non si sposta: scade col master token, non col rinnovo.
 */
class TokenRefreshTest extends TestCase
{
    use RefreshDatabase;

    private const PROBE_URI = "/__probe/refresh";

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(["web", "authenticated"])->get(self::PROBE_URI, fn() => response()->json(["ok" => true]));
    }

    private function providerIdp(): Provider
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

    /** Il rinnovo passa da `hasAccessToProvider()`: senza ruolo non rinnoverebbe, e per il motivo sbagliato. */
    private function utenteConAccesso(Provider $provider): User
    {
        $user = User::factory()->create(["enabled" => 1]);
        $role = Role::create(["name" => "admin", "provider_id" => $provider->id]);

        ProviderUserRole::create([
            "provider_id" => $provider->id,
            "user_id" => $user->id,
            "role_id" => $role->id,
        ]);

        return $user;
    }

    private function appToken(Provider $provider, User $user, int $exp): string
    {
        $jwt = new Lcobucci($provider->secret_key, config("jwt.algo", "HS256"), config("jwt.keys", []));

        return $jwt->encode(["sub" => $user->id, "exp" => $exp]);
    }

    private function sessione(User $user, Provider $provider, string $token, ?int $secondi = null): Session
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

    private function masterToken(User $user, Provider $provider): string
    {
        return (new TokenProviderService())->generateMasterToken($user, $provider->id);
    }

    /** Un master token **scaduto**, firmato bene: e' la stessa chiave, cambia solo `exp`. */
    private function masterTokenScaduto(User $user, Provider $provider): string
    {
        return JWT::encode(
            ["iss" => $provider->url, "iat" => time() - 7200, "exp" => time() - 60, "sub" => (string) $user->id],
            File::get(storage_path("app/keys/private.key")),
            "RS256",
            config("idp.jwt.master_key_id"),
        );
    }

    /** Una navigazione: e' il caso in cui il rinnovo si fa (TTR06). */
    private function naviga(string $appToken, ?string $masterToken = null)
    {
        return $this->conCookie($appToken, $masterToken)->get(self::PROBE_URI);
    }

    /** Una chiamata API: `Accept: application/json` senza `X-Inertia`. Qui il rinnovo non si tenta. */
    private function chiamaInApi(string $appToken, ?string $masterToken = null)
    {
        return $this->conCookie($appToken, $masterToken)
            ->withHeaders(["Accept" => "application/json"])
            ->get(self::PROBE_URI);
    }

    /**
     * La richiesta deve **presentarsi** con l'IP e lo user agent della sessione: il rinnovo li
     * confronta, e una richiesta di prova senza questi due arriva da «un altro dispositivo».
     */
    private function conCookie(string $appToken, ?string $masterToken)
    {
        $richiesta = $this->withServerVariables(["REMOTE_ADDR" => env("TEST_IP_ADDRESS")])
            ->withHeaders(["User-Agent" => env("TEST_USER_AGENT")])
            ->withUnencryptedCookie("idp_token_" . config("idp.provider_id"), $appToken);

        if ($masterToken !== null) {
            $richiesta = $richiesta->withUnencryptedCookie(config("idp.jwt.master_token_name"), $masterToken);
        }

        return $richiesta;
    }

    // --- la correzione -------------------------------------------------------------------

    /**
     * IL DIFETTO, corretto: app token scaduto, master token valido per altre ore, sessione viva.
     * Nella stesura precedente questa riga era `assertStatus(401)`.
     */
    public function test_col_master_token_valido_la_navigazione_prosegue(): void
    {
        $provider = $this->providerIdp();
        $user = $this->utenteConAccesso($provider);

        $scaduto = $this->appToken($provider, $user, time() - 60);
        $this->sessione($user, $provider, $scaduto, 28800);

        $this->naviga($scaduto, $this->masterToken($user, $provider))->assertStatus(200);
    }

    /** Il rinnovo non basta che passi: deve **consegnare** il token nuovo, o al giro dopo si riparte da capo. */
    public function test_il_cookie_torna_col_token_nuovo(): void
    {
        $provider = $this->providerIdp();
        $user = $this->utenteConAccesso($provider);

        $scaduto = $this->appToken($provider, $user, time() - 60);
        $sessione = $this->sessione($user, $provider, $scaduto, 28800);

        $risposta = $this->naviga($scaduto, $this->masterToken($user, $provider));

        $cookie = $risposta->getCookie("idp_token_" . config("idp.provider_id"), false);
        $this->assertNotNull($cookie, "nessun cookie app token nella risposta");
        $this->assertNotSame($scaduto, $cookie->getValue(), "il cookie riporta il token scaduto");
        $this->assertSame(
            $cookie->getValue(),
            $sessione->fresh()->token,
            "il token nel cookie e quello in sessione sono diversi",
        );
    }

    /** Ora il master token **serve**: e' l'inversione esatta del test che diceva il contrario. */
    public function test_senza_master_token_la_navigazione_finisce_al_login(): void
    {
        $provider = $this->providerIdp();
        $user = $this->utenteConAccesso($provider);

        $scaduto = $this->appToken($provider, $user, time() - 60);
        $this->sessione($user, $provider, $scaduto, 28800);

        $this->naviga($scaduto)->assertRedirect(route("loginForm"))->assertSessionHasErrors(["login"]);
    }

    // --- TTR05: i messaggi si distinguono ------------------------------------------------

    /** Master token scaduto: la sessione e' finita davvero, e il messaggio lo dice. */
    public function test_col_master_token_scaduto_il_messaggio_e_quello_del_rinnovo_fallito(): void
    {
        $provider = $this->providerIdp();
        $user = $this->utenteConAccesso($provider);

        $scaduto = $this->appToken($provider, $user, time() - 60);
        $this->sessione($user, $provider, $scaduto, 28800);

        $this->naviga($scaduto, $this->masterTokenScaduto($user, $provider))->assertSessionHasErrors([
            "login" => __("auth.renew-failed"),
        ]);
    }

    /**
     * Rinnovo **rifiutato**, non fallito: il master token e' valido, e' la sessione a dire di no.
     * Sono due cose diverse e il messaggio e' diverso — e' tutto `TTR05`.
     */
    public function test_da_un_altro_dispositivo_il_messaggio_e_quello_del_rinnovo_rifiutato(): void
    {
        $provider = $this->providerIdp();
        $user = $this->utenteConAccesso($provider);

        $scaduto = $this->appToken($provider, $user, time() - 60);
        $sessione = $this->sessione($user, $provider, $scaduto, 28800);

        // La sessione appartiene a un altro IP: e' il caso del cookie rubato.
        $sessione->forceFill(["ip_address" => env("TEST_IP_ADDRESS_OTHER")])->save();

        $this->naviga($scaduto, $this->masterToken($user, $provider))
            ->assertRedirect(route("loginForm"))
            ->assertSessionHasErrors(["login" => __("auth.renew-refused")]);

        $this->assertSame($scaduto, $sessione->fresh()->token, "il token e' stato rinnovato da un altro dispositivo");
    }

    /** I due messaggi non devono solo esistere: devono essere **diversi**, o TTR05 non serve a niente. */
    public function test_i_due_messaggi_sono_distinti(): void
    {
        $this->assertNotSame(__("auth.renew-failed"), __("auth.renew-refused"));
        $this->assertNotSame("auth.renew-failed", __("auth.renew-failed"), "la chiave non ha traduzione");
        $this->assertNotSame("auth.renew-refused", __("auth.renew-refused"), "la chiave non ha traduzione");
    }

    // --- cio' che il rinnovo NON allarga --------------------------------------------------

    /** Il logout dell'amministratore vince sul rinnovo: la sessione revocata non torna (VDF14). */
    public function test_dopo_una_revoca_il_rinnovo_non_ricrea_la_sessione(): void
    {
        $provider = $this->providerIdp();
        $user = $this->utenteConAccesso($provider);

        $scaduto = $this->appToken($provider, $user, time() - 60);
        $this->sessione($user, $provider, $scaduto, 28800);

        SessionService::destroyAllUserSessions($user->id);

        $this->naviga($scaduto, $this->masterToken($user, $provider))->assertRedirect(route("loginForm"));
        $this->assertSame(0, Session::where("user_id", $user->id)->count(), "la sessione si e' ricreata");
    }

    /** Si rinnova la chiave, non il rapporto di fiducia: la finestra della sessione resta quella. */
    public function test_il_rinnovo_non_sposta_la_scadenza_della_sessione(): void
    {
        $provider = $this->providerIdp();
        $user = $this->utenteConAccesso($provider);

        $scaduto = $this->appToken($provider, $user, time() - 60);
        $sessione = $this->sessione($user, $provider, $scaduto, 28800);
        $scadenzaPrima = $sessione->expires_at;

        $this->naviga($scaduto, $this->masterToken($user, $provider))->assertStatus(200);

        $this->assertTrue($scadenzaPrima->equalTo($sessione->fresh()->expires_at), "expires_at si e' spostato");
    }

    // --- TTR06: solo la navigazione -------------------------------------------------------

    /**
     * Una chiamata API con lo stesso identico stato **non** rinnova: un cookie in risposta a un
     * `fetch` non viene raccolto come ci si aspetta, e il rinnovo sembrerebbe funzionare a
     * intermittenza. Chi chiama in API ha gia' la sua strada: il 401 e poi `token/exchange`.
     */
    public function test_in_api_il_rinnovo_non_si_tenta(): void
    {
        $provider = $this->providerIdp();
        $user = $this->utenteConAccesso($provider);

        $scaduto = $this->appToken($provider, $user, time() - 60);
        $sessione = $this->sessione($user, $provider, $scaduto, 28800);

        $this->chiamaInApi($scaduto, $this->masterToken($user, $provider))->assertStatus(401);

        $this->assertSame($scaduto, $sessione->fresh()->token, "il token e' stato rinnovato su una chiamata API");
    }

    // --- la controprova -------------------------------------------------------------------

    /** Il token valido passa senza rinnovare niente: il rinnovo scatta sulla SCADENZA, non sempre. */
    public function test_un_app_token_valido_passa_e_non_viene_rinnovato(): void
    {
        $provider = $this->providerIdp();
        $user = $this->utenteConAccesso($provider);

        $valido = $this->appToken($provider, $user, time() + 3600);
        $sessione = $this->sessione($user, $provider, $valido);

        $this->naviga($valido, $this->masterToken($user, $provider))->assertStatus(200);

        $this->assertSame($valido, $sessione->fresh()->token, "un token valido e' stato rinnovato senza motivo");
    }
}
