<?php

namespace Tests\Unit\Auth;

use App\Auth\Idp\IdpTokenExtractor;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * L'estrazione del token, provata da sola (punto TCC07).
 *
 * Unit: non tocca il database ne' la crittografia, solo la richiesta. E' anche la fase che nella
 * pratica scatta piu' spesso — quella in cui il token non c'e' affatto.
 */
class IdpTokenExtractorTest extends TestCase
{
    private function requestWith(array $cookies = [], ?string $bearer = null): Request
    {
        $request = Request::create("/qualsiasi", "GET", [], $cookies);

        if ($bearer !== null) {
            $request->headers->set("Authorization", "Bearer " . $bearer);
        }

        return $request;
    }

    public function test_the_cookie_name_depends_on_the_configured_provider(): void
    {
        $this->assertSame("idp_token_" . config("idp.provider_id"), (new IdpTokenExtractor())->cookieName());
    }

    public function test_with_nothing_it_extracts_nothing(): void
    {
        $this->assertNull((new IdpTokenExtractor())->extract($this->requestWith()));
    }

    public function test_it_extracts_from_the_cookie(): void
    {
        $estrattore = new IdpTokenExtractor();

        $token = $estrattore->extract($this->requestWith([$estrattore->cookieName() => "dal-cookie"]));

        $this->assertSame("dal-cookie", $token);
    }

    public function test_it_extracts_from_the_bearer_header_when_the_cookie_is_missing(): void
    {
        $this->assertSame("dal-bearer", (new IdpTokenExtractor())->extract($this->requestWith([], "dal-bearer")));
    }

    public function test_the_cookie_takes_precedence_over_the_bearer(): void
    {
        $estrattore = new IdpTokenExtractor();

        $token = $estrattore->extract($this->requestWith([$estrattore->cookieName() => "dal-cookie"], "dal-bearer"));

        // La precedenza non e' un dettaglio: e' cio' che decide quale sessione conta quando una
        // richiesta porta entrambi, e cambiarla cambierebbe chi risulta autenticato.
        $this->assertSame("dal-cookie", $token);
    }

    public function test_the_log_context_says_which_cookie_it_was_looking_for(): void
    {
        $estrattore = new IdpTokenExtractor();

        $contesto = $estrattore->missingTokenContext($this->requestWith());

        // Senza questo, «token assente» non distingue «non l'ha mandato» da «lo cerco col nome
        // sbagliato» — che e' un errore di configurazione, non dell'utente.
        $this->assertSame($estrattore->cookieName(), $contesto["cookie_specifico_cercato"]);
    }
}
