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
}
