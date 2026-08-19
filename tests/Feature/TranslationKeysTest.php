<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Ogni chiave passata a `__()` deve avere una traduzione (punto TSH06).
 *
 * PERCHE' ESISTE: `__()` non fallisce. Se la chiave non c'e', restituisce **la chiave stessa**, e
 * quella finisce sotto gli occhi dell'utente. E' successo due volte in una settimana:
 *
 * - il 2026-08-13 con `session.error.access_denied.userdisabled_or_missing_roles` — un underscore
 *   mancante, e l'API rispondeva con la chiave;
 * - il 2026-08-19 con `auth.token-expired`, che non esisteva in nessuna lingua e arrivava **sul form
 *   di login**.
 *
 * Questo controllo nasce dentro `SessionRevocationTest` (cancellato il 2026-08-19) e guardava **un
 * solo** controller. Qui guarda tutti i sorgenti che chiamano `__()`, e in **due lingue**: una chiave
 * tradotta in italiano e non in inglese e' lo stesso difetto per chi usa l'inglese.
 */
class TranslationKeysTest extends TestCase
{
    /** Le lingue che il prodotto dichiara di parlare. */
    private const LINGUE = ["it", "en"];

    private const CARTELLE = ["app", "routes", "database"];

    /**
     * Le chiavi che oggi **non** hanno traduzione, e che questo test non fa fallire.
     *
     * Non e' un permesso: e' un debito, con la data in cui e' stato misurato. Serve a distinguere
     * cio' che era gia' rotto da cio' che si rompe adesso — senza questa lista il test nascerebbe
     * rosso e nessuno lo terrebbe. Le prime due sono state **rimosse dal revert del 2026-08-19**
     * (erano state aggiunte da `TTR05`); le altre tre erano rotte da prima e nessuno le aveva viste.
     *
     * Il secondo test qui sotto impedisce che questa lista invecchi: appena una di queste chiavi
     * viene tradotta, diventa rosso e chiede di togliere la riga.
     */
    /**
     * Vuota, e va tenuta vuota.
     *
     * Ha contenuto cinque chiavi il 2026-08-19, per mezza giornata: tre erano davvero senza
     * traduzione e sono state tradotte (`TSH07`), due erano nominate in due voci morte di
     * `ProviderRequest::messages()` e sono state **cancellate col codice che le nominava** — non
     * tradotte, perche' nessuno le avrebbe mai lette.
     *
     * Se un giorno tornasse a riempirsi, ogni riga porti la data e il perche': il terzo test qui
     * sotto obbliga a togliere le righe appena la chiave viene tradotta, ma non puo' obbligare
     * nessuno a spiegarle.
     */
    private const DEBITO = [];


    /** @return array<string, string> chiave => file:riga dove e' usata */
    private function chiaviUsate(): array
    {
        $trovate = [];

        foreach (self::CARTELLE as $cartella) {
            $iteratore = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($cartella), \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iteratore as $file) {
                if ($file->getExtension() !== "php") {
                    continue;
                }

                $righe = file($file->getPathname(), FILE_IGNORE_NEW_LINES);

                foreach ($righe as $numero => $riga) {
                    // Solo le chiavi **letterali**: `__($variabile)` non si puo' controllare qui.
                    if (preg_match_all('/__\(\s*["\']([a-zA-Z0-9_.\-]+)["\']/', $riga, $trovateRiga)) {
                        foreach ($trovateRiga[1] as $chiave) {
                            $trovate[$chiave] ??= str_replace(base_path() . "/", "", $file->getPathname()) .
                                ":" .
                                ($numero + 1);
                        }
                    }
                }
            }
        }

        return $trovate;
    }

    private function mancanti(): array
    {
        $mancanti = [];

        foreach ($this->chiaviUsate() as $chiave => $dove) {
            foreach (self::LINGUE as $lingua) {
                if (__($chiave, [], $lingua) === $chiave) {
                    $mancanti[$chiave] = "{$dove} (manca in '{$lingua}')";
                }
            }
        }

        return $mancanti;
    }

    public function test_il_controllo_ha_qualcosa_da_controllare(): void
    {
        // Se una regex smette di trovare le chiamate, i due test qui sotto passano per il motivo
        // sbagliato: non perche' tutto e' tradotto, ma perche' non guardano niente.
        $this->assertGreaterThan(40, count($this->chiaviUsate()), "il controllo non trova piu' le chiamate a __()");
    }

    public function test_nessuna_chiave_nuova_resta_senza_traduzione(): void
    {
        $nuove = array_diff_key($this->mancanti(), array_flip(self::DEBITO));

        $this->assertSame(
            [],
            $nuove,
            "chiavi usate nel codice e senza traduzione:\n" .
                implode("\n", array_map(fn($k, $v) => "  {$k} -> {$v}", array_keys($nuove), $nuove)),
        );
    }

    public function test_il_debito_non_invecchia(): void
    {
        $mancanti = $this->mancanti();
        $risolte = array_values(array_diff(self::DEBITO, array_keys($mancanti)));

        $this->assertSame(
            [],
            $risolte,
            "queste chiavi ora hanno una traduzione: togli la riga da DEBITO, o la lista mente: " .
                implode(", ", $risolte),
        );
    }
}
