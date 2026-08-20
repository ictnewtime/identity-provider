<?php

namespace Tests\Feature\Auth;

use App\Models\Provider;
use App\Models\Session;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tymon\JWTAuth\Providers\JWT\Lcobucci;

/**
 * Il meccanismo INTERO, non i pezzi (punto TCC10).
 *
 * Perche' non basta un test per classe: ogni fase, provata da sola, puo' essere corretta mentre
 * il passaggio fra due di esse e' rotto. Questi test attraversano piu' fasi in un colpo solo e
 * coprono le condizioni che nella pratica capitano insieme — un token buono con una sessione
 * revocata, un utente disabilitato, la stessa richiesta con cookie e bearer.
 *
 * Differenza da AuthenticatedTest: quello copre i sei rami d'uscita **uno per uno** ed e' la rete
 * che il refactoring non doveva rompere. Questo prova le combinazioni.
 */
class IdpCompositionTest extends TestCase
{
    use RefreshDatabase;

    private const PROBE_URI = "/__probe/composizione";

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

    private function token(Provider $provider, array $payload): string
    {
        $jwt = new Lcobucci($provider->secret_key, config("jwt.algo", "HS256"), config("jwt.keys", []));

        return $jwt->encode($payload);
    }

    private function sessionFor(User $user, Provider $provider, string $token): void
    {
        Session::create([
            "id" => (string) Str::uuid(),
            "user_id" => $user->id,
            "provider_id" => $provider->id,
            "token" => $token,
            "ip_address" => env("TEST_IP_ADDRESS"),
            "user_agent" => env("TEST_USER_AGENT"),
        ]);
    }

    private function withBearer(string $token)
    {
        return $this->withHeaders(["Authorization" => "Bearer " . $token, "Accept" => "application/json"])->get(
            self::PROBE_URI,
        );
    }

    /**
     * Il flusso completo: estrazione → provider → decodifica → utente → sessione.
     * Se una qualunque di queste passa il testimone male, qui si vede.
     */
    public function test_the_whole_flow_lets_the_request_through(): void
    {
        $provider = $this->idpProvider();
        $user = User::factory()->create(["enabled" => 1]);
        $token = $this->token($provider, ["sub" => $user->id, "exp" => time() + 3600]);
        $this->sessionFor($user, $provider, $token);

        $this->withBearer($token)
            ->assertStatus(200)
            ->assertJson(["ok" => true]);
    }

    /**
     * Token buono, sessione revocata: e' il caso per cui la fase della sessione esiste. Se il
     * middleware si fermasse alla crittografia, un amministratore non potrebbe piu' cacciare
     * nessuno.
     */
    public function test_a_valid_token_with_a_revoked_session_does_not_pass(): void
    {
        $provider = $this->idpProvider();
        $user = User::factory()->create(["enabled" => 1]);
        $token = $this->token($provider, ["sub" => $user->id, "exp" => time() + 3600]);
        $this->sessionFor($user, $provider, $token);

        $this->withBearer($token)->assertStatus(200);

        Session::where("token", $token)->delete();

        $this->withBearer($token)->assertStatus(401);
    }

    /**
     * La sessione c'e' ma l'utente e' sparito: due fasi che si contraddicono. Deve vincere
     * l'assenza dell'utente, non la presenza della sessione.
     */
    public function test_a_live_session_with_a_vanished_user_does_not_pass(): void
    {
        $provider = $this->idpProvider();
        $user = User::factory()->create(["enabled" => 1]);
        $token = $this->token($provider, ["sub" => $user->id, "exp" => time() + 3600]);
        $this->sessionFor($user, $provider, $token);

        $user->forceDelete();

        $this->withBearer($token)->assertStatus(401);
    }

    /**
     * Un token firmato con un'altra chiave: la decodifica fallisce e non si arriva ne' all'utente
     * ne' alla sessione, anche se entrambi esistono.
     */
    public function test_a_token_signed_by_another_provider_does_not_pass(): void
    {
        $provider = $this->idpProvider();
        $user = User::factory()->create(["enabled" => 1]);

        $altro = new Lcobucci(Str::random(32), config("jwt.algo", "HS256"), config("jwt.keys", []));
        $token = $altro->encode(["sub" => $user->id, "exp" => time() + 3600]);
        $this->sessionFor($user, $provider, $token);

        $this->withBearer($token)->assertStatus(401);
    }

    /**
     * Cookie e bearer insieme, con valori diversi: passa quello del cookie. E' la precedenza che
     * `IdpTokenExtractor` dichiara, verificata qui end-to-end e non sul solo estrattore.
     */
    public function test_with_both_cookie_and_bearer_the_cookie_wins(): void
    {
        $provider = $this->idpProvider();
        $user = User::factory()->create(["enabled" => 1]);

        $buono = $this->token($provider, ["sub" => $user->id, "exp" => time() + 3600]);
        $this->sessionFor($user, $provider, $buono);

        // Nel bearer un token valido ma SENZA sessione: se vincesse lui, la richiesta sarebbe 401.
        $senzaSessione = $this->token($provider, ["sub" => $user->id, "exp" => time() + 7200]);

        $risposta = $this->withHeaders([
            "Authorization" => "Bearer " . $senzaSessione,
            "Accept" => "application/json",
        ])
            // `withUnencryptedCookie`: EncryptCookies esclude questo cookie a runtime leggendo i
            // provider dal database, ma il client di test cifra comunque a meno di dirglielo.
            ->withUnencryptedCookie("idp_token_" . config("idp.provider_id"), $buono)
            ->get(self::PROBE_URI);

        $risposta->assertStatus(200);
    }
}
