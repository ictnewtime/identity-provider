<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Ogni chiave passata a `__()` deve avere una traduzione, in tutte le lingue.
 *
 * PERCHE' ESISTE: `__()` non fallisce. Se la chiave non c'e', restituisce **la chiave stessa**, e
 * quella finisce sotto gli occhi dell'utente. Non e' teoria — e' successo due volte in una settimana:
 *
 *   - il 2026-08-13 con `session.error.access_denied.userdisabled_or_missing_roles`: un underscore
 *     mancante, e l'API rispondeva con la chiave;
 *   - il 2026-08-19 con `auth.token-expired`, che non esisteva in nessuna lingua e arrivava **sul form
 *     di login**. Ed era gia' stata corretta il 14: un revert l'ha riportata indietro, e nessuno se ne
 *     e' accorto tranne questo controllo.
 *
 * NIENTE LISTA DI ECCEZIONI. La prima stesura ne aveva una — le chiavi note come mancanti, che il test
 * non faceva fallire — e il developer ha fatto notare la cosa giusta: una lista da aggiornare a mano
 * chiede a una persona di ricordarsi, e le persone non si ricordano. Qui la regola e' secca: chiave
 * senza traduzione, rosso. Chi ha bisogno di un'eccezione traduce la chiave, che costa meno di
 * scrivere la riga di un'eccezione.
 *
 * COSA NON COPRE, e va saputo: le chiavi **letterali** dei sorgenti PHP. `__($variabile)` non e'
 * verificabile da fuori, e il frontend — 329 chiavi fra `trans()` e `$t()` — ha il suo difetto aperto
 * (`VDF23`).
 */
class TranslationKeysTest extends TestCase
{
    /** Le lingue che il prodotto dichiara di parlare. */
    private const LOCALES = ["it", "en"];

    private const SOURCE_DIRECTORIES = ["app", "routes", "database"];

    /** Sotto questo numero di chiavi trovate, il controllo non sta guardando piu' niente. */
    private const MINIMUM_KEYS_FOUND = 40;

    /** @return array<string, string> chiave => `file:riga` della prima occorrenza */
    private function usedKeys(): array
    {
        $found = [];

        foreach (self::SOURCE_DIRECTORIES as $directory) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($directory), \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($files as $file) {
                if ($file->getExtension() !== "php") {
                    continue;
                }

                foreach (file($file->getPathname(), FILE_IGNORE_NEW_LINES) as $number => $line) {
                    // Solo le chiavi **letterali**: `__($variabile)` non si puo' controllare qui.
                    if (!preg_match_all('/__\(\s*["\']([a-zA-Z0-9_.\-]+)["\']/', $line, $matches)) {
                        continue;
                    }

                    foreach ($matches[1] as $key) {
                        $found[$key] ??= str_replace(base_path() . "/", "", $file->getPathname()) .
                            ":" .
                            ($number + 1);
                    }
                }
            }
        }

        return $found;
    }

    /** @return array<string, string> chiave => dove e' usata e in quale lingua manca */
    private function missingKeys(): array
    {
        $missing = [];

        foreach ($this->usedKeys() as $key => $where) {
            foreach (self::LOCALES as $locale) {
                if (__($key, [], $locale) === $key) {
                    $missing[$key] = "{$where} (manca in '{$locale}')";
                }
            }
        }

        return $missing;
    }

    /**
     * Il controllo di sopra passerebbe anche se l'espressione regolare smettesse di trovare le
     * chiamate: passerebbe **guardando niente**. Questo test tiene ferma la differenza.
     */
    public function test_the_check_still_finds_the_translation_calls(): void
    {
        $this->assertGreaterThan(
            self::MINIMUM_KEYS_FOUND,
            count($this->usedKeys()),
            "il controllo non trova piu' le chiamate a __(): la ricerca e' rotta, non il codice",
        );
    }

    public function test_every_key_used_in_php_sources_has_a_translation(): void
    {
        $missing = $this->missingKeys();

        $this->assertSame(
            [],
            $missing,
            "chiavi usate nel codice e senza traduzione:\n" .
                implode("\n", array_map(fn($key, $where) => "  {$key} -> {$where}", array_keys($missing), $missing)),
        );
    }
}
