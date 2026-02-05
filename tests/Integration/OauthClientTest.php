<?php

namespace Tests\Integration;

use Tests\TestCase;
use App\Models\OauthClient;
use Tests\Utility\UserUtility;
use App\Repositories\ClientRoleRepository;

class OauthClientTest extends TestCase
{
    /**
     * Helper per ottenere gli headers con il token
     */
    private function getAuthHeaders(): array
    {
        $user = UserUtility::getAdmin();
        
        $response = $this->postJson('v2/login', [
            'username' => $user->email,
            'password' => 'secret'
        ]);
        $token = $response->json('token');
        
        return ['token' => $token];
    }

    /**
     * Get oauth_clients
     */
    public function testAllOauthUsers()
    {
        $n_oauth_clients = OauthClient::count();
        
        $headers = $this->getAuthHeaders();

        $response = $this->getJson('/admin/oauth-clients-all', $headers);
        
        $response->assertStatus(200);
        
        $total = $response->json('meta.total');
        
        $this->assertEquals($n_oauth_clients, $total);
    }

    /**
     * Get oauth_clients filtered by params
     */
    public function testAllOauthUsersWithParameters()
    {
        $headers = $this->getAuthHeaders();

        $response = $this->getJson('/admin/oauth-clients-all?q=admin manager', $headers);
        
        $response->assertStatus(200);
    }

    /**
     * Update oauth_clients roles
     */
    public function testUpdateRoles()
    {
        $headers = $this->getAuthHeaders();
        
        $clientRoleRepository = new ClientRoleRepository;
        $roles = $clientRoleRepository->all();

        $oauthClient = OauthClient::first() ?? OauthClient::factory()->create(); 

        $body = [
            "clientId" => $oauthClient->id,
            "roles" =>  $roles
        ];
        
        $response = $this->putJson('/admin/update-roles', $body, $headers);
        
        $response->assertStatus(204);
    }

    public function testUpdateRolesIfOauthClientDontExist()
    {
        $headers = $this->getAuthHeaders();
        
        $clientRoleRepository = new ClientRoleRepository;
        $roles = $clientRoleRepository->all();

        $body = [
            "clientId" => 99999,
            "roles" =>  $roles
        ];
        
        $response = $this->putJson('/admin/update-roles', $body, $headers);
        
        // ATTENZIONE: In Laravel moderno, se il modello non esiste (findOrFail), 
        // spesso ritorna 404, non 500. Se il tuo codice vecchio lanciava eccezioni non gestite, era 500.
        // Verifica se devi cambiare questo assert in assertStatus(404)
        $response->assertStatus(500); 
    }

    public function testUpdateRolesIfClientDontExist()
    {
        $headers = $this->getAuthHeaders();
        
        $clientRoleRepository = new ClientRoleRepository;
        $roles = $clientRoleRepository->all();

        // Test fallimento (ID inesistente)
        $body = [
            "clientId" => 15, // Assicurati che 15 non esista nel DB di test
            "roles" =>  $roles
        ];
        
        $response = $this->putJson('/admin/update-roles', $body, $headers);
        $response->assertStatus(500); // Vedi nota sopra su 404 vs 500

        $oauthClient = OauthClient::factory()->create();
        
        $bodySuccess = [
            "clientId" => $oauthClient->id,
            "roles" =>  $roles
        ];

        $responseSuccess = $this->putJson('/admin/update-roles', $bodySuccess, $headers);
        $responseSuccess->assertStatus(204);
    }
}