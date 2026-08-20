<?php

namespace Tests\Feature\Auth;

use App\Models\Provider;
use App\Models\Session;
use App\Models\User;
use App\Services\TokenProviderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tymon\JWTAuth\Providers\JWT\Lcobucci;

/**
 * Il rinnovo dell'app token nell'IdP (punto TTR02, difetto VDF13).
 *
 * ATTENZIONE, questo file nasce al contrario: **fotografa il difetto**, non la correzione.
 * Oggi e' verde perche' descrive il comportamento attuale — app token scaduto, master token
 * ancora valido, e l'IdP disconnette lo stesso. Con `TTR04` dovra' diventare **rosso**, e allora
 * si riscrive sul comportamento nuovo.
 *
 * Perche' scriverlo prima: il difetto e' in un percorso di autenticazione, e senza una prova
 * eseguibile «l'IdP si slogga dopo 30 minuti» resta un racconto. Cosi' e' un comando.
 *
 * Nota sull'asimmetria che il test rende evidente: i client esterni **rinnovano gia' da soli** —
 * l'estensione chiama `token/exchange` col master token (F11 dell'analisi). L'IdP, che il master
 * token ce l'ha nel cookie, e' l'unico a non farlo.
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

        return $richiesta->withUnencryptedCookie("idp_token_" . config("idp.provider_id"), $appToken)->get(
            self::PROBE_URI,
        );
    }

    /**
     * IL DIFETTO, fotografato: app token scaduto, master token valido per altre ore, sessione viva.
     * L'IdP **disconnette** invece di rinnovare.
     *
     * Con TTR04 questa asserzione diventera' 200, e questo test si riscrive.
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
}
