<?php

namespace Tests\Feature\Auth;

use App\Models\Provider;
use App\Models\ProviderUserRole;
use App\Models\Role;
use App\Models\Session;
use App\Models\User;
use App\Services\SessionService;
use App\Services\TokenProviderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * L'exchange dall'esterno, attraversato per intero (punto TTR07).
 *
 * Perche' non basta quello che c'e' gia': `SessionRevocationTest` prova `SessionService` **da
 * solo**. Qui si passa dalla rotta vera — `POST api/v1/token/exchange` — quindi dal middleware
 * `VerifyMasterToken`, dalla validazione della richiesta e dal controller. Ogni pezzo puo' essere
 * corretto mentre il passaggio fra due di essi e' rotto: e' il difetto che i test per classe non
 * vedono.
 *
 * E' anche il flusso che i client usano davvero: l'estensione chiama esattamente questa rotta col
 * master token nell'header (tmp/idp-extension/.../IdpAuthMiddleware.php:191).
 *
 * NON copre il rinnovo trasparente DENTRO l'IdP: quello e' `TTR04`, non ancora fatto, e i suoi
 * casi vanno con lui — scriverli qui significherebbe lasciare test rossi che descrivono codice
 * che non esiste.
 */
class TokenExchangeTest extends TestCase
{
    use RefreshDatabase;

    private const URI = "/api/v1/token/exchange";

    private function provider(): Provider
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

    private function masterToken(User $user, Provider $provider): string
    {
        return (new TokenProviderService())->generateMasterToken($user, $provider->id);
    }

    private function scambia(string $masterToken, Provider $provider, array $extra = [])
    {
        return $this->withHeaders(["Authorization" => "Bearer " . $masterToken, "Accept" => "application/json"])->postJson(
            self::URI,
            array_merge(
                [
                    "provider_id" => (string) $provider->id,
                    "ip_address" => env("TEST_IP_ADDRESS"),
                    "user_agent" => env("TEST_USER_AGENT"),
                ],
                $extra,
            ),
        );
    }

    private function apriSessione(User $user, Provider $provider): ?string
    {
        return (new SessionService())->getValidProviderToken(
            $user,
            $provider->id,
            env("TEST_IP_ADDRESS"),
            env("TEST_USER_AGENT"),
            new TokenProviderService(),
        );
    }

    // --- il flusso che funziona ----------------------------------------------------------

    public function test_con_una_sessione_aperta_lo_scambio_restituisce_un_token(): void
    {
        $provider = $this->provider();
        $user = $this->utenteConAccesso($provider);
        $this->apriSessione($user, $provider);

        $this->scambia($this->masterToken($user, $provider), $provider)
            ->assertStatus(200)
            ->assertJsonStructure(["token"]);
    }

    // --- le tre uscite che proteggono ----------------------------------------------------

    public function test_senza_master_token_lo_scambio_rifiuta(): void
    {
        $provider = $this->provider();
        $user = $this->utenteConAccesso($provider);
        $this->apriSessione($user, $provider);

        $this->postJson(self::URI, ["provider_id" => (string) $provider->id])->assertStatus(401);
    }

    public function test_dopo_una_revoca_lo_scambio_rifiuta_pur_col_master_token_valido(): void
    {
        $provider = $this->provider();
        $user = $this->utenteConAccesso($provider);
        $this->apriSessione($user, $provider);

        $master = $this->masterToken($user, $provider);
        $this->scambia($master, $provider)->assertStatus(200);

        // Un amministratore slogga l'utente da /admin/sessions.
        SessionService::destroyAllUserSessions($user->id);

        // Il master token e' ancora valido per ore: prima di TTR08 questo rispondeva 200 e
        // ricreava la sessione — il logout durava una richiesta (difetto VDF14).
        $this->scambia($master, $provider)->assertStatus(403);
        $this->assertSame(0, Session::where("user_id", $user->id)->count(), "la sessione si e' ricreata");
    }

    public function test_da_un_altro_dispositivo_lo_scambio_rifiuta(): void
    {
        $provider = $this->provider();
        $user = $this->utenteConAccesso($provider);
        $this->apriSessione($user, $provider);

        // Stesso utente, stesso master token, IP diverso: e' il caso del token rubato.
        $this->scambia($this->masterToken($user, $provider), $provider, [
            "ip_address" => env("TEST_IP_ADDRESS_OTHER"),
        ])->assertStatus(403);
    }

    // --- l'attraversamento che i test per classe non vedono ------------------------------

    public function test_lo_scambio_ripetuto_non_moltiplica_le_sessioni(): void
    {
        $provider = $this->provider();
        $user = $this->utenteConAccesso($provider);
        $this->apriSessione($user, $provider);
        $master = $this->masterToken($user, $provider);

        foreach (range(1, 3) as $i) {
            $this->scambia($master, $provider)->assertStatus(200);
        }

        // Il rinnovo rinnova la CHIAVE: una riga sola, non una per scambio.
        $this->assertSame(1, Session::where("user_id", $user->id)->count());
    }

    public function test_lo_scambio_restituisce_lo_stesso_token_finche_e_valido(): void
    {
        $provider = $this->provider();
        $user = $this->utenteConAccesso($provider);
        $this->apriSessione($user, $provider);
        $master = $this->masterToken($user, $provider);

        $primo = $this->scambia($master, $provider)->json("token");
        $secondo = $this->scambia($master, $provider)->json("token");

        // Non si emette un token nuovo a ogni richiesta: sarebbe una firma in piu' per niente, e
        // renderebbe impossibile capire quale token e' quello buono.
        $this->assertSame($primo, $secondo);
    }
}
