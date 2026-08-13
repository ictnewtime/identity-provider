<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * I messaggi 404 di ProviderUserRoleController (punto TPU01, risposta a D2).
 *
 * Perche' esistono: il developer ha chiesto di verificare se qualcuno confronta quelle stringhe,
 * e la risposta e' **no** — ne' il frontend, ne' Cypress, ne' un test. Quindi tradurle non e' una
 * regressione. Ma «nessuno le confronta» vale finche' nessuno le prova: questi test le fissano,
 * cosi' il giorno che `TPU03` le fa passare dalle traduzioni **il diff lo mostra** invece di
 * cambiarle in silenzio.
 *
 * ATTENZIONE a chi implementera' TPU03: questi test dovranno cambiare, e va bene — sono la
 * fotografia del comportamento attuale, non il comportamento desiderato. Il valore atteso
 * diventera' `__("provider_user_roles.not_found")`, chiave che **esiste gia'** in lang/it.json e
 * lang/en.json, e che in inglese vale esattamente il literale scritto oggi nel controller.
 */
class ProviderUserRoleNotFoundTest extends TestCase
{
    use RefreshDatabase;

    private const MESSAGGIO_ATTUALE = "Provider user role not found";
    private const ID_INESISTENTE = 999999;

    public function test_find_su_un_id_inesistente_risponde_404_col_messaggio(): void
    {
        $this->withoutMiddleware()
            ->getJson("/admin/v1/provider-user-roles/" . self::ID_INESISTENTE)
            ->assertStatus(404)
            ->assertJson(["message" => self::MESSAGGIO_ATTUALE]);
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
            ->putJson("/admin/v1/provider-user-roles/" . self::ID_INESISTENTE, [
                "provider_id" => $provider->id,
                "user_id" => $user->id,
                "role_id" => $role->id,
            ])
            ->assertStatus(404)
            ->assertJson(["message" => self::MESSAGGIO_ATTUALE]);
    }

    public function test_delete_su_un_id_inesistente_risponde_404_col_messaggio(): void
    {
        $this->withoutMiddleware()
            ->deleteJson("/admin/v1/provider-user-roles/" . self::ID_INESISTENTE)
            ->assertStatus(404)
            ->assertJson(["message" => self::MESSAGGIO_ATTUALE]);
    }

    /**
     * La chiave di traduzione c'e' gia', in entrambe le lingue, e in inglese coincide col literale.
     * Chi scrivera' `TPU03` non deve inventarne una: deve smettere di ignorare questa.
     */
    public function test_la_chiave_di_traduzione_esiste_gia_e_coincide(): void
    {
        $this->assertSame(self::MESSAGGIO_ATTUALE, __("provider_user_roles.not_found", [], "en"));
        $this->assertNotSame(
            "provider_user_roles.not_found",
            __("provider_user_roles.not_found", [], "it"),
            "la chiave manca in italiano",
        );
    }
}
