<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Models\Session;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * L'elenco delle sessioni visto dall'amministratore (punti TMT37 e TMT38).
 *
 * Due cose diverse, che qui si tengono separate perche' confonderle e' facile:
 * - il **record** e' la riga nel database;
 * - la **riga** e' cio' che l'amministratore vede in tabella.
 * Il record per provider continua a esistere; e' la riga che sparisce quando c'e' quella comune.
 *
 * Le rotte reali portano `authenticated` + `role:admin`: qui si passa oltre, perche' quei due sono
 * coperti altrove (AuthenticatedTest) e mescolarli renderebbe illeggibile un rosso.
 */
class SessionListTest extends TestCase
{
    use RefreshDatabase;

    private const URI = "/admin/v1/sessions";

    /** Oltre il tetto di 100 imposto a `per_page`, per vedere che il tetto tenga. */
    private const PER_PAGE_ABOVE_THE_CEILING = 999999;

    /** Non c'e' una factory per i provider: si crea a mano, come fa gia' SessionRevocationTest. */
    private function provider(string $name): Provider
    {
        return Provider::forceCreate([
            "domain" => "{$name}.it",
            "url" => "https://{$name}.it",
            "protocol" => "https",
            "secret_key" => Str::random(32),
            "logoutUrl" => "https://{$name}.it/logout",
            "name" => $name,
        ]);
    }

    /** Un record di sessione. `$providerId` a null e' la riga comune, quella che vale per tutte le app. */
    private function sessionRecord(User $user, ?int $providerId): Session
    {
        return Session::create([
            "id" => (string) Str::uuid(),
            "user_id" => $user->id,
            "provider_id" => $providerId,
            "token" => "token-" . Str::random(8),
            "refresh_token" => "master-" . Str::random(8),
            "ip_address" => env("TEST_IP_ADDRESS"),
            "user_agent" => env("TEST_USER_AGENT"),
            "expires_at" => now()->addHour(),
            "last_activity" => now(),
        ]);
    }

    private function callIndex(array $query = [])
    {
        return $this->withoutMiddleware()->getJson(self::URI . "?" . http_build_query($query));
    }

    public function test_the_common_row_hides_the_per_provider_ones(): void
    {
        $utente = User::factory()->create(["enabled" => 1]);
        $provider = $this->provider("device");

        $this->sessionRecord($utente, null);
        $this->sessionRecord($utente, $provider->id);

        // Due record nel database, una sola riga in tabella: l'altra direbbe la stessa cosa.
        $this->assertSame(2, Session::count());
        $this->callIndex()->assertStatus(200)->assertJsonCount(1, "data")->assertJsonPath("data.0.provider_id", null);
    }

    public function test_without_the_common_row_the_per_provider_ones_stay_visible(): void
    {
        $utente = User::factory()->create(["enabled" => 1]);

        $this->sessionRecord($utente, $this->provider("device")->id);
        $this->sessionRecord($utente, $this->provider("altra")->id);

        $this->callIndex()->assertStatus(200)->assertJsonCount(2, "data");
    }

    public function test_the_common_row_of_one_user_does_not_hide_another_users_rows(): void
    {
        $conRigaComune = User::factory()->create(["enabled" => 1]);
        $senzaRigaComune = User::factory()->create(["enabled" => 1]);
        $provider = $this->provider("device");

        $this->sessionRecord($conRigaComune, null);
        $this->sessionRecord($conRigaComune, $provider->id);
        $this->sessionRecord($senzaRigaComune, $provider->id);

        // Una riga per il primo utente (la comune) e una per il secondo: il filtro guarda l'utente.
        $this->callIndex()->assertStatus(200)->assertJsonCount(2, "data");
    }

    public function test_sorting_by_provider_keeps_the_common_row(): void
    {
        $utente = User::factory()->create(["enabled" => 1]);
        $this->sessionRecord($utente, null);

        // Era il difetto: con la join interna, ordinando per provider la riga comune spariva.
        foreach (["asc", "desc"] as $verso) {
            $this->callIndex(["sort_by" => "provider.name", "sort_dir" => $verso])
                ->assertStatus(200)
                ->assertJsonCount(1, "data")
                ->assertJsonPath("data.0.provider_label", "*");
        }
    }

    public function test_the_listing_never_carries_the_tokens(): void
    {
        $utente = User::factory()->create(["enabled" => 1]);
        $this->sessionRecord($utente, $this->provider("device")->id);

        // Anche ordinando: erano i due rami con `sessions.*` a portarli fuori.
        foreach ([[], ["sort_by" => "provider.name"], ["sort_by" => "user.username"]] as $query) {
            $risposta = $this->callIndex($query)->assertStatus(200);
            $riga = $risposta->json("data.0");

            $this->assertArrayNotHasKey("token", $riga);
            $this->assertArrayNotHasKey("refresh_token", $riga);
        }
    }

    public function test_per_page_is_capped(): void
    {
        $utente = User::factory()->create(["enabled" => 1]);
        $this->sessionRecord($utente, null);

        $this->callIndex(["per_page" => self::PER_PAGE_ABOVE_THE_CEILING])
            ->assertStatus(200)
            ->assertJsonPath("per_page", 100);
    }
}
