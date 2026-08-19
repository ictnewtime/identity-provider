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
 * La sessione come rapporto di fiducia, e la sua revoca (punti TTR03, TTR08, TTR09).
 *
 * Tre proprieta', che vanno insieme e da sole non bastano:
 *   1. la sessione dura quanto il MASTER token, non quanto l'app token — altrimenti allo scadere
 *      del token non resta niente da cui rinnovare in sicurezza;
 *   2. se la sessione e' viva ma il token e' scaduto si rinnova la CHIAVE, non il rapporto;
 *   3. il rinnovo non puo' CREARE una sessione: se un amministratore l'ha revocata, ricrearla
 *      significherebbe che il logout non slogga (difetto VDF14).
 */
class SessionRevocationTest extends TestCase
{
    use RefreshDatabase;

    private function provider(string $nome = "IDP"): Provider
    {
        return Provider::forceCreate([
            "domain" => "localhost",
            "url" => "http://localhost",
            "protocol" => "http",
            "secret_key" => Str::random(32),
            "logoutUrl" => "http://localhost/logout",
            "name" => $nome,
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

    private function login(User $user, Provider $provider, string $ip = "10.0.0.1", string $ua = "phpunit"): ?string
    {
        return (new SessionService())->getValidProviderToken($user, $provider->id, $ip, $ua, new TokenProviderService());
    }

    private function rinnovo(User $user, Provider $provider, string $ip = "10.0.0.1", string $ua = "phpunit"): ?string
    {
        return (new SessionService())->getValidProviderToken(
            $user,
            $provider->id,
            $ip,
            $ua,
            new TokenProviderService(),
            canCreate: false,
        );
    }

    // --- TTR03: la sessione dura quanto il master token ----------------------------------

    public function test_la_sessione_dura_quanto_il_master_token_non_quanto_lapp_token(): void
    {
        $provider = $this->provider();
        $user = $this->utenteConAccesso($provider);

        $this->login($user, $provider);

        $sessione = Session::where("user_id", $user->id)->first();
        $durata = now()->diffInSeconds($sessione->expires_at);

        // 28800 (master) e non 1800 (app). Il margine assorbe il tempo di esecuzione.
        $this->assertGreaterThan(1800 + 60, $durata, "la sessione scade ancora con l'app token");
        $this->assertEqualsWithDelta(28800, $durata, 60);
    }

    public function test_se_la_sessione_e_viva_ma_il_token_e_scaduto_si_rinnova_la_chiave(): void
    {
        $provider = $this->provider();
        $user = $this->utenteConAccesso($provider);

        $primo = $this->login($user, $provider);
        $sessione = Session::where("user_id", $user->id)->first();

        // Si simula il passare dei 30 minuti: il token e' scaduto, la sessione no.
        $sessione->forceFill(["token" => $this->tokenScaduto($provider, $user)])->save();

        $secondo = $this->login($user, $provider);

        $this->assertNotNull($secondo);
        $this->assertNotSame($sessione->token, $secondo, "ha restituito il token scaduto invece di rinnovarlo");
        $this->assertSame(1, Session::where("user_id", $user->id)->count(), "ha creato una sessione in piu'");
    }

    private function tokenScaduto(Provider $provider, User $user): string
    {
        $jwt = new \Tymon\JWTAuth\Providers\JWT\Lcobucci(
            $provider->secret_key,
            config("jwt.algo", "HS256"),
            config("jwt.keys", []),
        );

        return $jwt->encode(["sub" => $user->id, "exp" => time() - 60]);
    }

    // --- TTR08: il rinnovo non crea ------------------------------------------------------

    public function test_il_rinnovo_rifiuta_se_la_sessione_e_stata_revocata(): void
    {
        $provider = $this->provider();
        $user = $this->utenteConAccesso($provider);

        $this->login($user, $provider);
        (new SessionService())->destroySession($user->id, $provider->id);

        // E' il cuore di VDF14: col master token ancora valido, il rinnovo NON deve ricreare.
        $this->assertNull($this->rinnovo($user, $provider), "il rinnovo ha ricreato una sessione revocata");
        $this->assertSame(0, Session::where("user_id", $user->id)->count());
    }

    public function test_il_rinnovo_rifiuta_da_un_altro_dispositivo(): void
    {
        $provider = $this->provider();
        $user = $this->utenteConAccesso($provider);

        $this->login($user, $provider, "10.0.0.1", "phpunit");

        // Stesso utente, stesso master token, IP diverso: il rinnovo non sposta la sessione.
        $this->assertNull($this->rinnovo($user, $provider, "192.168.1.1", "phpunit"));
    }

    public function test_il_login_invece_puo_creare_la_sessione(): void
    {
        $provider = $this->provider();
        $user = $this->utenteConAccesso($provider);

        // La distinzione fra i due chiamanti: il login e' il momento in cui la fiducia nasce.
        $this->assertNotNull($this->login($user, $provider));
    }

    public function test_i_messaggi_di_rifiuto_passano_dalle_traduzioni(): void
    {
        // Non e' pignoleria: un messaggio scritto a mano nel codice esce in una lingua sola, e
        // qui e' gia' successo che una chiave sbagliata facesse rispondere all'API la chiave
        // grezza invece del testo (`session.error.access_denied.userdisabled_...`).
        foreach (
            [
                "session.error.renew_refused.other_device" => ["userId" => 1],
                "session.error.renew_refused.no_session" => ["userId" => 1, "providerId" => 2],
            ]
            as $chiave => $parametri
        ) {
            foreach (["it", "en"] as $lingua) {
                $testo = __($chiave, $parametri, $lingua);

                $this->assertNotSame($chiave, $testo, "la chiave '{$chiave}' manca in {$lingua}");
                $this->assertStringNotContainsString(":userId", $testo, "parametro non sostituito");
            }
        }
    }

    public function test_nessuna_chiave_di_traduzione_usata_e_inesistente(): void
    {
        // Il refuso trovato il 2026-08-13: il controller chiamava
        // `session.error.access_denied.userdisabled_or_missing_roles` — senza underscore — e l'API
        // rispondeva con la chiave. `__()` non fallisce: restituisce cio' che non ha trovato.
        $sorgente = file_get_contents(app_path("Http/Controllers/Manage/SessionController.php"));
        preg_match_all('/__\("([a-z0-9_.]+)"/', $sorgente, $trovate);

        foreach (array_unique($trovate[1]) as $chiave) {
            $this->assertNotSame($chiave, __($chiave, [], "it"), "chiave inesistente nel codice: {$chiave}");
        }
    }

    // --- TTR09: il logout amministrativo vale su tutte -----------------------------------

    public function test_il_logout_amministrativo_slogga_da_tutti_i_provider(): void
    {
        $primo = $this->provider("IDP");
        $secondo = $this->provider("Anagrafe");

        $user = $this->utenteConAccesso($primo);
        $role = Role::create(["name" => "user", "provider_id" => $secondo->id]);
        ProviderUserRole::create(["provider_id" => $secondo->id, "user_id" => $user->id, "role_id" => $role->id]);

        $this->login($user, $primo);
        $this->login($user, $secondo);
        $this->assertSame(2, Session::where("user_id", $user->id)->count());

        SessionService::destroyAllUserSessions($user->id);

        $this->assertSame(0, Session::where("user_id", $user->id)->count(), "non le ha cancellate tutte");
        $this->assertNull($this->rinnovo($user, $primo), "la prima si e' ricreata");
        $this->assertNull($this->rinnovo($user, $secondo), "la seconda si e' ricreata");
    }
}
