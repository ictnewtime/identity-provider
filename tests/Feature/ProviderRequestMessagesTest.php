<?php

namespace Tests\Feature;

use App\Http\Requests\ProviderRequest;
use Tests\TestCase;

/**
 * I messaggi di validazione dei provider arrivano davvero all'utente (punto TSH08, difetto VDF21).
 *
 * Il difetto che questo test tiene fermo non era un testo sbagliato: era una **mappa** sbagliata.
 * `messages()` vuole chiavi `campo.regola`; la versione precedente ci metteva chiavi di **traduzione**,
 * e allora nessuno di quei messaggi veniva reso — al suo posto usciva il predefinito di Laravel, in
 * inglese anche con locale italiano. Non se ne accorgeva nessuno perche' un messaggio c'era comunque.
 */
class ProviderRequestMessagesTest extends TestCase
{
    private function errori(array $dati, string $lingua): array
    {
        app()->setLocale($lingua);

        $request = new ProviderRequest();
        $regole = (new \ReflectionMethod($request, "rules"))->invoke($request);

        $validatore = validator($dati, $regole, $request->messages());
        $validatore->fails();

        return $validatore->errors()->all();
    }

    public function test_il_dominio_mancante_ha_il_messaggio_di_lang_in_italiano(): void
    {
        $errori = $this->errori([], "it");

        $this->assertContains(__("admin.roles.form.error.domain.mandatory", [], "it"), $errori);
        $this->assertNotContains("The domain field is required.", $errori, "esce ancora il predefinito di Laravel");
    }

    public function test_lurl_di_logout_non_valido_ha_il_messaggio_di_lang(): void
    {
        $errori = $this->errori(["domain" => "esempio.it", "logoutUrl" => "non-un-url"], "it");

        $this->assertContains(__("admin.roles.form.error.logout_url.invalid", [], "it"), $errori);
    }

    /** Il messaggio segue la lingua: e' la ragione per cui sta in `lang` e non nel codice. */
    public function test_i_messaggi_seguono_la_lingua(): void
    {
        $italiano = $this->errori([], "it");
        $inglese = $this->errori([], "en");

        $this->assertNotSame($italiano, $inglese, "il messaggio non cambia con la lingua");
        $this->assertContains(__("admin.roles.form.error.domain.mandatory", [], "en"), $inglese);
    }

    /**
     * La guardia contro il difetto **nella sua forma originale**: una chiave di `messages()` che non
     * sia `campo.regola` non aggancia niente, e il test qui sopra non lo vedrebbe se un domani
     * qualcuno aggiungesse una voce nuova sbagliata accanto a quelle giuste.
     */
    public function test_ogni_chiave_di_messages_nomina_una_regola_esistente(): void
    {
        $request = new ProviderRequest();
        $campi = array_keys((new \ReflectionMethod($request, "rules"))->invoke($request));

        foreach (array_keys($request->messages()) as $chiave) {
            $campo = explode(".", $chiave)[0];
            $this->assertContains($campo, $campi, "la chiave '{$chiave}' non nomina nessun campo validato");
        }
    }
}
