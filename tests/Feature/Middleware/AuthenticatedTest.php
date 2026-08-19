<?php

namespace Tests\Feature\Middleware;

use App\Models\Provider;
use App\Models\Session;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tymon\JWTAuth\Providers\JWT\Lcobucci;

/**
 * Rete di sicurezza per il refactoring di Authenticated::handle() (punto TCC01).
 *
 * Copre i SEI rami d'uscita piu' il ramo felice. Questi test devono restare INVARIATI
 * durante la scomposizione: sono l'unica prova che il comportamento non e' cambiato.
 *
 * Si registra una rotta di prova protetta dal solo middleware `authenticated`, perche' le
 * rotte reali portano anche `role:admin` e un fallimento non direbbe quale dei due ha parlato.
 */
class AuthenticatedTest extends TestCase
{
    use RefreshDatabase;

    private const PROBE_URI = "/__probe/authenticated";

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(["web", "authenticated"])->get(self::PROBE_URI, fn() => response()->json(["ok" => true]));
    }

    /**
     * Il provider dell'IdP, con l'id che la configurazione si aspetta.
     *
     * `forceCreate` e non `create`: `id` NON e' fra i `$fillable` di Provider, quindi `create()`
     * lo scarterebbe in silenzio e il provider prenderebbe il prossimo valore dell'auto-increment.
     * Su un database appena creato quel valore e' 1 e coincide per caso con
     * `config("idp.provider_id")`; su un database gia' usato no, e il middleware non trova piu' il
     * provider. E' il motivo per cui questi test passavano in un ambiente e fallivano nell'altro.
     */
    private function idpProvider(array $overrides = []): Provider
    {
        return Provider::forceCreate(
            array_merge(
                [
                    "id" => (int) config("idp.provider_id"),
                    "domain" => "localhost",
                    "url" => "http://localhost",
                    "protocol" => "http",
                    "secret_key" => Str::random(32),
                    "logoutUrl" => "http://localhost/logout",
                    "name" => "IDP",
                ],
                $overrides,
            ),
        );
    }

    /** Firma un payload con la stessa chiave e lo stesso algoritmo del middleware. */
    private function tokenFor(Provider $provider, array $payload): string
    {
        $jwt = new Lcobucci($provider->secret_key, config("jwt.algo", "HS256"), config("jwt.keys", []));

        return $jwt->encode($payload);
    }

    private function sessionFor(User $user, Provider $provider, string $token): Session
    {
        return Session::create([
            "id" => (string) Str::uuid(),
            "user_id" => $user->id,
            "provider_id" => $provider->id,
            "token" => $token,
            "ip_address" => env("TEST_IP_ADDRESS"),
            "user_agent" => env("TEST_USER_AGENT"),
        ]);
    }

    private function callWithToken(?string $token)
    {
        $headers = $token === null ? [] : ["Authorization" => "Bearer " . $token];

        return $this->withHeaders(array_merge($headers, ["Accept" => "application/json"]))->get(self::PROBE_URI);
    }

    // --- Ramo 1: nessun token ------------------------------------------------------------

    public function test_rifiuta_quando_non_ce_nessun_token(): void
    {
        $this->idpProvider();

        $this->callWithToken(null)->assertStatus(401);
    }

    // --- Ramo 2: provider mancante, o senza secret_key -----------------------------------

    public function test_rifiuta_quando_il_provider_idp_non_esiste(): void
    {
        // Nessun provider creato: la configurazione di sicurezza manca del tutto.
        $this->callWithToken("un.token.qualsiasi")->assertStatus(401);
    }

    public function test_rifiuta_quando_il_provider_non_ha_secret_key(): void
    {
        $this->idpProvider(["secret_key" => ""]);

        $this->callWithToken("un.token.qualsiasi")->assertStatus(401);
    }

    // --- Ramo 3: token scaduto -----------------------------------------------------------

    public function test_rifiuta_un_token_scaduto(): void
    {
        $provider = $this->idpProvider();
        $user = User::factory()->create(["enabled" => 1]);

        $token = $this->tokenFor($provider, ["sub" => $user->id, "exp" => time() - 3600]);

        $this->callWithToken($token)->assertStatus(401);
    }

    // --- Ramo 4: claim `sub` mancante ----------------------------------------------------

    public function test_rifiuta_un_token_senza_claim_sub(): void
    {
        $provider = $this->idpProvider();

        $token = $this->tokenFor($provider, ["exp" => time() + 3600]);

        $this->callWithToken($token)->assertStatus(401);
    }

    // --- Ramo 5: utente inesistente ------------------------------------------------------

    public function test_rifiuta_quando_lutente_del_token_non_esiste_piu(): void
    {
        $provider = $this->idpProvider();

        $token = $this->tokenFor($provider, ["sub" => 999999, "exp" => time() + 3600]);

        $this->callWithToken($token)->assertStatus(401);
    }

    // --- Ramo 6: sessione eliminata dal database -----------------------------------------

    public function test_rifiuta_un_token_valido_la_cui_sessione_e_stata_eliminata(): void
    {
        $provider = $this->idpProvider();
        $user = User::factory()->create(["enabled" => 1]);

        // Token crittograficamente valido, ma nessuna riga in `sessions`.
        $token = $this->tokenFor($provider, ["sub" => $user->id, "exp" => time() + 3600]);

        $this->callWithToken($token)->assertStatus(401);
    }

    // --- Ramo felice ---------------------------------------------------------------------

    public function test_lascia_passare_un_token_valido_con_sessione_viva(): void
    {
        $provider = $this->idpProvider();
        $user = User::factory()->create(["enabled" => 1]);

        $token = $this->tokenFor($provider, ["sub" => $user->id, "exp" => time() + 3600]);
        $this->sessionFor($user, $provider, $token);

        $this->callWithToken($token)->assertStatus(200)->assertJson(["ok" => true]);
    }
}
