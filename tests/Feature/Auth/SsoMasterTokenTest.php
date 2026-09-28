<?php

namespace Tests\Feature\Auth;

use App\Models\Provider;
use App\Models\Role;
use App\Models\User;
use App\Services\SessionService;
use App\Services\TokenProviderService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Il master token che l'IdP consegna alle applicazioni al redirect SSO.
 *
 * IL DIFETTO CHE QUESTI TEST TENGONO CHIUSO (loop del 23/09/2026, tmp/idp-loop-token-scaduto-2026-09-23.md):
 * l'IdP rimandava all'applicazione il master token trovato nel proprio cookie **senza guardarlo**.
 * Il cookie durava 20 giorni (secondi passati come minuti), il token 8 ore: dopo le 8 ore l'IdP
 * consegnava un token scaduto, il client ne ricavava un cookie gia' scaduto, il browser lo buttava
 * e si ripartiva dall'IdP — all'infinito. Con lo stesso meccanismo poteva consegnare il token **di un
 * altro utente**, o quello dell'altro ambiente (staging e produzione condividono nome e dominio).
 */
class SsoMasterTokenTest extends TestCase
{
    use RefreshDatabase;

    /** L'applicazione di destinazione: su un dominio diverso dall'IdP, e col token in URL. */
    private const TARGET_ID = 7;
    private const TARGET_URL = "https://app.altro-dominio.it";

    /** Password di prova, scritta in chiaro qui e cifrata sull'utente: il factory ne ha una sua. */
    private const PASSWORD = "password-di-prova";

    private Provider $idp;
    private Provider $target;

    protected function setUp(): void
    {
        parent::setUp();

        $this->idp = Provider::forceCreate([
            "id" => (int) config("idp.provider_id"),
            "domain" => "idp.esempio.it",
            "url" => "https://idp.esempio.it",
            "protocol" => "https",
            "secret_key" => Str::random(32),
            "logoutUrl" => "https://idp.esempio.it/logout",
            "name" => "IDP",
        ]);

        $this->target = Provider::forceCreate([
            "id" => self::TARGET_ID,
            "domain" => "altro-dominio.it",
            "url" => self::TARGET_URL,
            "protocol" => "https",
            "secret_key" => Str::random(32),
            "logoutUrl" => self::TARGET_URL . "/logout",
            "name" => "Altra app",
            "has_token_url" => 1,
        ]);
    }

    private function userWithAccess(): User
    {
        $user = User::factory()->create([
            "enabled" => 1,
            "password" => bcrypt(self::PASSWORD),
            "password_expires_at" => now()->addYear(),
        ]);
        $role = Role::firstOrCreate(["name" => "user", "provider_id" => $this->target->id]);
        DB::table("provider_user_roles")->insert([
            "user_id" => $user->id,
            "provider_id" => $this->target->id,
            "role_id" => $role->id,
        ]);

        return $user;
    }

    /**
     * Un master token con `exp` scelto, firmato con la chiave indicata (di default quella del servizio).
     *
     * A mano e non con `generateMasterToken()`: quello scrive `exp` con `time()`, che il viaggio
     * nel tempo di Laravel non sposta — vedi la stessa nota in SessionRevocationTest.
     */
    private function masterToken(User $user, int $expiresInSeconds, ?string $privateKey = null): string
    {
        return JWT::encode(
            [
                "iss" => $this->idp->url,
                "iat" => time() - 3600,
                "exp" => time() + $expiresInSeconds,
                "sub" => (string) $user->id,
                "payload" => ["user" => ["id" => $user->id, "username" => $user->username]],
            ],
            $privateKey ?? File::get(storage_path("app/keys/private.key")),
            "RS256",
            config("idp.jwt.master_key_id"),
        );
    }

    /** Il token che l'IdP ha messo nell'URL di ritorno, o null se non ce n'e'. */
    private function tokenInRedirect($response): ?string
    {
        parse_str((string) parse_url($response->headers->get("Location"), PHP_URL_QUERY), $query);

        return $query["token"] ?? null;
    }

    private function claims(string $token): object
    {
        return JWT::decode($token, new Key(File::get(storage_path("app/keys/public.key")), "RS256"));
    }

    private function ssoRedirectWith(User $user, string $cookieToken)
    {
        return $this->actingAs($user)
            ->withUnencryptedCookie(config("idp.jwt.master_token_name"), $cookieToken)
            ->get("/?provider_id=" . self::TARGET_ID);
    }

    // --- durata dei cookie ---------------------------------------------------------------------

    /** Il cookie del master token vive quanto il token, non 60 volte tanto. */
    public function test_master_cookie_lasts_as_long_as_the_token(): void
    {
        $cookie = (new TokenProviderService())->cookieCretion(
            "a.b.c",
            (string) $this->idp->id,
            config("idp.jwt.master_token_name"),
        );

        $ttl = (new TokenProviderService())->getMasterTokenExpiredAt();
        $this->assertEqualsWithDelta(
            time() + $ttl,
            $cookie->getExpiresTime(),
            60,
            "il cookie sopravvive al token che contiene: dopo la scadenza l'IdP lo consegnerebbe ancora",
        );
    }

    /**
     * `exp - iat` vale esattamente la durata configurata. Prima `iat` ed `exp` venivano da due
     * `time()` diversi, con una query in mezzo, e a volte differivano di un secondo in meno.
     *
     * LIMITE, detto chiaro: lo scatto del secondo durante la query non si puo' provocare da qui,
     * quindi questo test sul codice vecchio passava quasi sempre. Tiene fermo il comportamento,
     * non avrebbe trovato il difetto.
     */
    public function test_master_token_lifetime_is_exactly_the_configured_ttl(): void
    {
        $user = $this->userWithAccess();
        $service = new TokenProviderService();

        $claims = $this->claims($service->generateMasterToken($user, (string) $this->idp->id));

        $this->assertSame($service->getMasterTokenExpiredAt(), $claims->exp - $claims->iat);
    }

    /**
     * La riga di sessione del master token scade quando scade il token, non "adesso + 8 ore".
     * Il caso che conta e' il redirect SSO che riusa il token valido del cookie, emesso ore prima:
     * con "adesso + 8 ore" la riga gli sopravviveva di ore.
     */
    public function test_master_session_row_expires_with_the_token(): void
    {
        $user = $this->userWithAccess();
        $token = $this->masterToken($user, 1800); // emesso un'ora fa, scade fra mezz'ora

        $this->ssoRedirectWith($user, $token)->assertRedirect();

        $row = (new SessionService())->masterSessionFor($user->id);
        $this->assertNotNull($row, "il redirect SSO non ha scritto la riga del master token");
        $this->assertSame($this->claims($token)->exp, $row->expires_at->getTimestamp());
    }

    // --- redirect SSO: cosa consegna l'IdP ------------------------------------------------------

    /** Un master token valido, dell'utente loggato, passa com'e': nessuna rigenerazione inutile. */
    public function test_a_valid_master_token_of_the_same_user_is_forwarded_unchanged(): void
    {
        $user = $this->userWithAccess();
        $token = $this->masterToken($user, 3600);

        $response = $this->ssoRedirectWith($user, $token);

        $response->assertRedirect();
        $this->assertSame($token, $this->tokenInRedirect($response));
    }

    /** Il cuore del loop: un master token scaduto **non** si consegna, se ne emette uno nuovo. */
    public function test_an_expired_master_token_is_replaced_before_the_redirect(): void
    {
        $user = $this->userWithAccess();
        $expired = $this->masterToken($user, -3600);

        $response = $this->ssoRedirectWith($user, $expired);

        $delivered = $this->tokenInRedirect($response);
        $this->assertNotNull($delivered, "nessun token nell'URL di ritorno");
        $this->assertNotSame($expired, $delivered, "l'IdP ha consegnato il token scaduto: e' il loop");
        $this->assertGreaterThan(time(), $this->claims($delivered)->exp);

        // E il cookie dell'IdP si aggiorna, sennò al giro dopo si ricomincia dal token vecchio.
        $response->assertPlainCookie(config("idp.jwt.master_token_name"), $delivered);
    }

    /** Il token di un altro utente, rimasto nel cookie, non si consegna all'utente loggato. */
    public function test_another_users_master_token_is_not_forwarded(): void
    {
        $loggedIn = $this->userWithAccess();
        $previous = $this->userWithAccess();

        $response = $this->ssoRedirectWith($loggedIn, $this->masterToken($previous, 3600));

        $delivered = $this->tokenInRedirect($response);
        $this->assertNotNull($delivered);
        $this->assertSame(
            (string) $loggedIn->id,
            (string) $this->claims($delivered)->sub,
            "l'applicazione riceve l'identita' di un altro utente",
        );
    }

    /**
     * Un token firmato con un'altra chiave — il caso dell'altro ambiente: staging e produzione
     * scrivono lo stesso cookie sullo stesso dominio — non si consegna.
     */
    public function test_a_master_token_signed_with_another_key_is_replaced(): void
    {
        $user = $this->userWithAccess();
        $otherKey = openssl_pkey_new(["private_key_bits" => 2048, "private_key_type" => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($otherKey, $otherPem);

        $response = $this->ssoRedirectWith($user, $this->masterToken($user, 3600, $otherPem));

        $delivered = $this->tokenInRedirect($response);
        $this->assertNotNull($delivered);
        $this->assertSame((string) $user->id, (string) $this->claims($delivered)->sub);
    }

    /**
     * Se l'IdP non riesce a emettere un master token buono — chiave assente, chiavi che non si
     * corrispondono: una configurazione sbagliata — non rimanda l'utente all'applicazione con un
     * token che non funzionera' (sarebbe di nuovo un loop): lo ferma su una pagina che lo dice.
     */
    public function test_a_master_token_that_cannot_be_issued_stops_on_the_error_page(): void
    {
        $user = $this->userWithAccess();
        $otherKey = openssl_pkey_new(["private_key_bits" => 2048, "private_key_type" => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($otherKey, $otherPem);
        $unverifiable = $this->masterToken($user, 3600, $otherPem);

        // Il servizio emette un token che la chiave pubblica dell'IdP non verifica: e' cio' che
        // succede quando private.key e public.key non sono una coppia.
        $this->partialMock(TokenProviderService::class, function ($mock) use ($unverifiable) {
            $mock->shouldReceive("generateMasterToken")->andReturn($unverifiable);
        });

        $response = $this->ssoRedirectWith($user, $this->masterToken($user, -3600));

        $response->assertRedirect(
            route("sso.auth-error", ["provider_id" => self::TARGET_ID, "reason" => "master_token_issue"]),
        );
        $this->assertStringNotContainsString("token=", (string) $response->headers->get("Location"));
    }

    /** La pagina d'errore risponde anche senza login: chi ci arriva, spesso, una sessione non ce l'ha. */
    public function test_the_error_page_is_reachable_without_a_session(): void
    {
        $this->get(route("sso.auth-error", ["reason" => "token_expired", "provider_id" => "6"]))
            ->assertOk()
            ->assertInertia(
                fn($page) => $page
                    ->component("Client/AuthError")
                    ->where("reason", "token_expired")
                    ->where("providerId", "6"),
            );
    }

    /** Quello che arriva in query si mostra solo se ha la forma di un codice: il resto si scarta. */
    public function test_the_error_page_drops_parameters_with_an_unexpected_shape(): void
    {
        $this->get(route("sso.auth-error", ["reason" => "<script>x</script>", "provider_id" => "6 or 1=1"]))
            ->assertOk()
            ->assertInertia(fn($page) => $page->where("reason", null)->where("providerId", null));
    }

    // --- login ---------------------------------------------------------------------------------

    /**
     * Il login verso un'applicazione di un altro dominio **sostituisce** il cookie master dell'IdP.
     * Prima lo scriveva solo nello stesso dominio: il cookie vecchio — magari di un altro utente —
     * restava li' e il redirect SSO successivo lo consegnava.
     */
    public function test_a_cross_domain_login_replaces_the_idp_master_cookie(): void
    {
        $user = $this->userWithAccess();

        $response = $this->post("/v2/login", [
            "username" => $user->username,
            "password" => self::PASSWORD,
            "provider_id" => self::TARGET_ID,
        ]);

        $response->assertRedirect();
        $cookie = collect($response->headers->getCookies())->first(
            fn($c) => $c->getName() === config("idp.jwt.master_token_name") && $c->getValue() !== null,
        );
        $this->assertNotNull($cookie, "il login cross-domain non ha scritto il cookie master dell'IdP");
        $this->assertSame((string) $user->id, (string) $this->claims($cookie->getValue())->sub);
        $this->assertSame($cookie->getValue(), $this->tokenInRedirect($response));
    }
}
