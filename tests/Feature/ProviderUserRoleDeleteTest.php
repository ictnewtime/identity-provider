<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Models\ProviderUserRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * La cancellazione risponde 204 SENZA corpo (punto TPU04, difetto VDF05).
 *
 * 204 significa «nessun contenuto»: un corpo spedito con questo stato viene scartato da molti
 * client, quindi il messaggio che il controller componeva non arrivava a nessuno — e chi leggeva
 * il codice credeva di sì.
 *
 * Le rotte reali portano `authenticated` + `role:admin`: qui si passa oltre, perche' quei due
 * sono coperti da AuthenticatedTest e mescolarli renderebbe illeggibile un rosso.
 */
class ProviderUserRoleDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function anAssociation(): ProviderUserRole
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

        return ProviderUserRole::create([
            "provider_id" => $provider->id,
            "user_id" => $user->id,
            "role_id" => $role->id,
        ]);
    }

    public function test_the_deletion_responds_204_with_no_body(): void
    {
        $anAssociation = $this->anAssociation();

        $risposta = $this->withoutMiddleware()->deleteJson("/admin/v1/provider-user-roles/{$anAssociation->id}");

        $risposta->assertStatus(204);

        // NON si asserisce sul corpo: Laravel lo svuota da se' quando spedisce un 204, quindi
        // `getContent()` e' vuoto anche col difetto in piedi — provato. Cio' che distingue le due
        // forme e' l'intestazione: `response()->json([...], 204)` dichiara `application/json` per
        // un corpo che poi non c'e'; `noContent()` non dichiara niente.
        $this->assertNull(
            $risposta->headers->get("Content-Type"),
            "un 204 non deve dichiarare un tipo di contenuto: significa che qualcuno ha composto un corpo",
        );
    }

    public function test_the_deletion_actually_happens(): void
    {
        $anAssociation = $this->anAssociation();

        $this->withoutMiddleware()->deleteJson("/admin/v1/provider-user-roles/{$anAssociation->id}");

        // Il 204 senza corpo non deve diventare una scusa per non fare niente: si verifica l'effetto.
        $this->assertSoftDeleted("provider_user_roles", ["id" => $anAssociation->id]);
    }

    public function test_deleting_a_missing_association_responds_404_with_the_message(): void
    {
        $risposta = $this->withoutMiddleware()->deleteJson("/admin/v1/provider-user-roles/999999");

        $risposta->assertStatus(404);
        $this->assertNotSame("", $risposta->getContent(), "il 404 invece un corpo ce l'ha, ed e' giusto");
    }

    public function test_no_204_response_of_the_controller_composes_a_body(): void
    {
        // La verifica che l'HTTP non puo' dare: `response()->json([...], 204)` e `noContent()`
        // arrivano identici al client, perche' Laravel svuota il corpo dei 204 quando spedisce.
        // Quello che cambia e' il codice — e un codice che compone un messaggio mai recapitato
        // fa credere a chi lo legge che quel messaggio esista.
        $sorgente = file_get_contents(app_path("Http/Controllers/Manage/ProviderUserRoleController.php"));

        $this->assertDoesNotMatchRegularExpression(
            "/return response\(\)->json\(.*, *204\)/",
            $sorgente,
            "un 204 non deve comporre un corpo: usare response()->noContent()",
        );
    }
}
