<?php

namespace Tests\Feature\Auth;

use App\Auth\Idp\IdpProviderResolver;
use App\Auth\Idp\IdpSessionValidator;
use App\Models\Provider;
use App\Models\Session;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Le due fasi del middleware che toccano il database, provate da sole (punto TCC07).
 *
 * In `Feature` e non in `Unit` per la regola del progetto: leggono dal database. L'estrazione del
 * token, che non lo tocca, sta invece in `tests/Unit/Auth/`.
 */
class IdpPhasesTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_without_a_configured_provider_it_returns_null(): void
    {
        $resolver = new IdpProviderResolver();

        // E' il caso che il middleware sbagliava: il provider puo' non esserci, e chi chiama
        // deve deciderlo invece di leggerlo comunque (difetto VDF10).
        $this->assertNull($resolver->resolve());
        $this->assertFalse($resolver->isUsable(null));
    }

    public function test_a_provider_without_a_secret_key_is_not_usable(): void
    {
        // Senza chiave non si verifica niente: e' come se non ci fosse, ed e' lo stesso ramo.
        $this->assertFalse((new IdpProviderResolver())->isUsable($this->idpProvider(["secret_key" => ""])));
    }

    public function test_a_provider_with_a_key_is_usable(): void
    {
        $this->idpProvider();
        $resolver = new IdpProviderResolver();

        $this->assertTrue($resolver->isUsable($resolver->resolve()));
    }

    public function test_a_token_without_a_row_in_sessions_is_not_alive(): void
    {
        // E' la fase con la conseguenza piu' grave: senza, un amministratore non puo' piu'
        // cacciare nessuno — il token continuerebbe a verificare.
        $this->assertFalse((new IdpSessionValidator())->isAlive("un-token-qualsiasi"));
    }

    public function test_a_token_with_its_session_is_alive(): void
    {
        $provider = $this->idpProvider();
        $user = User::factory()->create(["enabled" => 1]);

        Session::create([
            "id" => (string) Str::uuid(),
            "user_id" => $user->id,
            "provider_id" => $provider->id,
            "token" => "token-vivo",
            "ip_address" => env("TEST_IP_ADDRESS"),
            "user_agent" => env("TEST_USER_AGENT"),
        ]);

        $this->assertTrue((new IdpSessionValidator())->isAlive("token-vivo"));
    }
}
