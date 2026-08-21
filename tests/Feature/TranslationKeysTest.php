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

    /** I sorgenti PHP, dove le traduzioni si chiedono con `__()`. */
    private const PHP_DIRECTORIES = ["app", "routes", "database"];
    private const PHP_EXTENSIONS = ["php"];
    private const PHP_PATTERN = '/__\(\s*["\']([a-zA-Z0-9_.\-]+)["\']/';

    /** Il frontend, dove si chiedono con `trans()` nel codice e `$t()` nei template. */
    private const FRONTEND_DIRECTORIES = ["resources/js"];
    private const FRONTEND_EXTENSIONS = ["vue", "js"];
    private const FRONTEND_PATTERN = '/(?:trans|\$t)\(\s*["\']([a-zA-Z0-9_.\-]+)["\']/';

    /** Sotto questi numeri, la scansione non sta guardando piu' niente. */
    private const MINIMUM_PHP_KEYS = 40;
    private const MINIMUM_FRONTEND_KEYS = 200;

    /** @return array<string, string> chiave => `file:riga` della prima occorrenza */
    private function keysIn(array $directories, array $extensions, string $pattern): array
    {
        $found = [];

        foreach ($directories as $directory) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($directory), \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($files as $file) {
                if (!in_array($file->getExtension(), $extensions, true)) {
                    continue;
                }

                foreach (file($file->getPathname(), FILE_IGNORE_NEW_LINES) as $number => $line) {
                    // Solo le chiavi **letterali**: una chiave costruita a runtime non si controlla
                    // da fuori. Oggi nel frontend non ce n'e' nessuna, misurato.
                    if (!preg_match_all($pattern, $line, $matches)) {
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

    private function phpKeys(): array
    {
        return $this->keysIn(self::PHP_DIRECTORIES, self::PHP_EXTENSIONS, self::PHP_PATTERN);
    }

    private function frontendKeys(): array
    {
        return $this->keysIn(self::FRONTEND_DIRECTORIES, self::FRONTEND_EXTENSIONS, self::FRONTEND_PATTERN);
    }

    /** @return array<string, string> chiave => dove e' usata e in quale lingua manca */
    private function missingAmong(array $keys): array
    {
        $missing = [];

        foreach ($keys as $key => $where) {
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
            self::MINIMUM_PHP_KEYS,
            count($this->phpKeys()),
            "la scansione dei sorgenti PHP non trova piu' le chiamate: e' rotta la ricerca, non il codice",
        );

        $this->assertGreaterThan(
            self::MINIMUM_FRONTEND_KEYS,
            count($this->frontendKeys()),
            "la scansione del frontend non trova piu' le chiamate: e' rotta la ricerca, non il codice",
        );
    }

    /**
     * Il frontend ha **cinque volte** le chiavi del PHP, ed e' dove `VDF23` si nascondeva: due
     * dialoghi mostravano all'utente la chiave invece del testo, e nessuno lo vedeva perche' il
     * controllo guardava l'altra meta'.
     *
     * Test **a parte** e non un'aggiunta a quello di sopra: le due scansioni cercano cose diverse, e
     * quando diventa rosso deve dirsi subito da quale lato sta il problema.
     */
    public function test_every_key_used_in_the_frontend_has_a_translation(): void
    {
        $missing = $this->missingAmong($this->frontendKeys());

        $this->assertSame(
            [],
            $missing,
            "chiavi usate nel frontend e senza traduzione:\n" .
                implode("\n", array_map(fn($key, $where) => "  {$key} -> {$where}", array_keys($missing), $missing)),
        );
    }

    /**
     * La regola generale dietro `VDF24`: **nessun valore di traduzione puo' essere falso**.
     *
     * `Translator::get()` restituisce `$line ?: $key`, e in PHP `"0"` e `""` sono falsi — quindi una
     * traduzione che vale zero o vuota si comporta **esattamente** come una che manca, e i due test di
     * sopra non la distinguono: la chiave c'e', il valore c'e', e `__()` restituisce la chiave.
     *
     * Il caso reale era `primevue.first_day_of_week` a `"0"` in inglese: il calendario cominciava di
     * lunedi' invece che di domenica, e nessuno poteva accorgersene.
     */
    public function test_no_translation_value_is_falsy(): void
    {
        foreach (self::LOCALES as $locale) {
            $file = base_path("lang/{$locale}.json");
            $translations = json_decode(file_get_contents($file), true);

            $falsy = array_keys(array_filter($translations, fn($value) => !$value));

            $this->assertSame(
                [],
                $falsy,
                "in lang/{$locale}.json questi valori sono falsi, e __() restituira' la chiave: " .
                    implode(", ", $falsy),
            );
        }
    }

    public function test_every_key_used_in_php_sources_has_a_translation(): void
    {
        $missing = $this->missingAmong($this->phpKeys());

        $this->assertSame(
            [],
            $missing,
            "chiavi usate nel codice e senza traduzione:\n" .
                implode("\n", array_map(fn($key, $where) => "  {$key} -> {$where}", array_keys($missing), $missing)),
        );
    }
}
