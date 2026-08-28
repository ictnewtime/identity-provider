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
     * IL DIFETTO, fotografato: app token scaduto, master token valido per altre ore, sessione viva.
     * L'IdP **disconnette** invece di rinnovare.
     *
     * Con TMT08 questa asserzione diventera' 200, e questo test si riscrive.
     */
    public function test_today_the_idp_logs_out_even_with_a_valid_master_token(): void
    {
        $provider = $this->idpProvider();
        $user = User::factory()->create(["enabled" => 1]);

        $scaduto = $this->appToken($provider, $user, time() - 60);
        $this->sessionFor($user, $provider, $scaduto, 28800);

        $master = (new TokenProviderService())->generateMasterToken($user, $provider->id);

        $this->callWith($scaduto, $master)->assertStatus(401);
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

        $primo = $sessionService->getValidProviderToken($user, $provider->id, "1.2.3.4", "phpunit", $tokenService);
        sleep(1); // il JWT porta `iat`: due firme nello stesso secondo sarebbero identiche
        $secondo = $sessionService->getValidProviderToken($user, $provider->id, "1.2.3.4", "phpunit", $tokenService);

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
            "1.2.3.4",
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
        $this->postJson("/api/v2/token/exchange", ["provider_id" => "1"])->assertStatus(401);
    }

    /** `TMT04`: il master token si accetta in tutte e tre le forme, e senza header no. */
    public function test_the_master_token_is_read_from_both_headers(): void
    {
        $provider = $this->idpProvider();
        $user = $this->userWithAccess($provider);
        $master = (new TokenProviderService())->generateMasterToken($user, $provider->id);

        $corpo = ["provider_id" => (string) $provider->id];

        $this->postJson("/api/v2/token/exchange", $corpo, ["Authorization" => "Bearer {$master}"])->assertStatus(200);
        $this->postJson("/api/v2/token/exchange", $corpo, ["x-master-token" => $master])->assertStatus(200);
        $this->postJson("/api/v2/token/exchange", $corpo, ["x-master-token" => "Bearer {$master}"])->assertStatus(200);
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
}
