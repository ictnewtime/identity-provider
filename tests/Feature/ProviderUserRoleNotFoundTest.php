<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * I messaggi 404 di ProviderUserRoleController (punti TPU01 e TPU03).
 *
 * STORIA DI QUESTO FILE, perche' e' il motivo per cui e' stato scritto due volte:
 *
 * Nato con `TPU01` per **fotografare** i messaggi scritti a mano, dopo aver verificato che nessuno
 * li confrontava — ne' il frontend, ne' Cypress, ne' un test. Servivano a rendere visibile il
 * cambiamento invece di lasciarlo avvenire in silenzio.
 *
 * `TPU03` ha fatto passare quei messaggi dalle traduzioni, e questi tre test **sono diventati
 * rossi**: era il loro scopo. Ora asseriscono il comportamento nuovo, e l'ultimo prova cio' che la
 * traduzione guadagna — la stessa rotta risponde in due lingue diverse, cosa che con il literale
 * era impossibile.
 */
class ProviderUserRoleNotFoundTest extends TestCase
{
    use RefreshDatabase;

    private const CHIAVE = "provider_user_roles.not_found";
    private const ID_INESISTENTE = 999999;
    private const ROUTE_ADMIN_V1_PUR = "/admin/v1/provider-user-roles/";

    public function test_find_su_un_id_inesistente_risponde_404_col_messaggio(): void
    {
        $this->withoutMiddleware()
            ->getJson(self::ROUTE_ADMIN_V1_PUR . self::ID_INESISTENTE)
            ->assertStatus(404)
            ->assertJson(["message" => __(self::CHIAVE)]);
    }

    /**
     * Il corpo dev'essere VALIDO, altrimenti non si arriva mai al 404.
     *
     * `ProviderUserRoleRequest` richiede che `user_id`, `role_id` e `provider_id` esistano
     * davvero (`exists:`), e la validazione gira prima del metodo: con riferimenti inventati la
     * risposta e' 422 e il ramo «non trovato» resta irraggiungibile. Scoperto scrivendo il test —
     * la prima versione asseriva 404 e riceveva 422.
     */
    public function test_update_su_un_id_inesistente_risponde_404_col_messaggio(): void
    {
        $provider = Provider::forceCreate([
            "domain" => "localhost",
            "url" => "http://localhost",
            "protocol" => "http",
            "secret_key" => Str::random(32),
            "logoutUrl" => "http://localhost/logout",
            "name" => "IDP",
        ]);
        $role = Role::create(["name" => "admin", "provider_id" => $provider->id]);
        $user = User::factory()->create(["enabled" => 1]);

        $this->withoutMiddleware()
            ->putJson(self::ROUTE_ADMIN_V1_PUR . self::ID_INESISTENTE, [
                "provider_id" => $provider->id,
                "user_id" => $user->id,
                "role_id" => $role->id,
            ])
            ->assertStatus(404)
            ->assertJson(["message" => __(self::CHIAVE)]);
    }

    public function test_delete_su_un_id_inesistente_risponde_404_col_messaggio(): void
    {
        $this->withoutMiddleware()
            ->deleteJson(self::ROUTE_ADMIN_V1_PUR . self::ID_INESISTENTE)
            ->assertStatus(404)
            ->assertJson(["message" => __(self::CHIAVE)]);
    }

    /**
     * La chiave di traduzione c'e' gia', in entrambe le lingue, e in inglese coincide col literale.
     * Chi scrivera' `TPU03` non deve inventarne una: deve smettere di ignorare questa.
     */
    /**
     * Il guadagno vero di TPU03: la stessa rotta risponde nella lingua della richiesta.
     * Col literale scritto a mano era impossibile, e le chiavi esistevano gia' — inutilizzate.
     */
    public function test_il_messaggio_segue_la_lingua_della_richiesta(): void
    {
        app()->setLocale("en");
        $inglese = $this->withoutMiddleware()
            ->getJson(self::ROUTE_ADMIN_V1_PUR . self::ID_INESISTENTE)
            ->json("message");

        app()->setLocale("it");
        $italiano = $this->withoutMiddleware()
            ->getJson(self::ROUTE_ADMIN_V1_PUR . self::ID_INESISTENTE)
            ->json("message");

        $this->assertNotSame($inglese, $italiano, "il messaggio non cambia con la lingua: non passa dalle traduzioni");
        $this->assertSame(__(self::CHIAVE, [], "it"), $italiano);
        $this->assertSame("Provider user role not found", $inglese, "in inglese resta il testo di prima");
    }

    public function test_nessun_messaggio_not_found_resta_scritto_a_mano(): void
    {
        // La verifica che l'HTTP non da': dodici literali su cinque file, ora zero.
        $trovati = shell_exec('grep -rn \'message" => "[A-Za-z ]*not found"\' ' . app_path("Http/Controllers"));

        $this->assertEmpty($trovati, "restano messaggi 404 scritti a mano:\n" . (string) $trovati);
    }
}
