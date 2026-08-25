<?php

namespace App\Support;

use App\Exceptions\DestructiveDatabaseException;

/**
 * Rifiuta le operazioni distruttive su un database che non sia quello di test.
 *
 * Perche' esiste: il 2026-08-12 una esecuzione della suite ha fatto `migrate:fresh` sul database
 * di sviluppo e lo ha svuotato (difetto VDF11). La difesa stava nei test, e proteggeva solo chi
 * passava di li': uno script, un comando, un seeder chiamato a mano non incontravano niente.
 *
 * Perche' NON sovrascrive `migrate:fresh`: quel comando, fuori da `local`, chiede gia' conferma
 * da console — Laravel protegge chi lo digita. Quello che manca e' una guardia per chi cancella
 * **da codice**, dove nessuno chiede niente. Da qui la forma: una funzione che si chiama, non un
 * comando che si sostituisce.
 *
 * Fallisce CHIUSA: senza un elenco di database consentiti non lascia passare. Su un'operazione che
 * distrugge dati, «non lo so» deve valere «no».
 */
class DestructiveDatabaseGuard
{
    /**
     * Il nome della variabile che elenca i database su cui si puo' cancellare.
     * I valori stanno in `phpunit.xml` e in `.env.test.*.example`, non qui.
     */
    public const ALLOWED_ENV = "TEST_ALLOWED_DATABASES";

    /**
     * @throws DestructiveDatabaseException se il database in uso non e' fra quelli consentiti
     */
    public static function ensureTestDatabase(?string $connection = null): void
    {
        $connection ??= config("database.default");
        $database = config("database.connections.{$connection}.database");

        $allowed = self::allowedDatabases();

        if (empty($allowed)) {
            throw new DestructiveDatabaseException(
                self::ALLOWED_ENV .
                    " non e' impostata: un'operazione distruttiva sul database non parte senza " .
                    "un elenco di database consentiti, perche' non c'e' modo di sapere se sta per " .
                    "cancellare quello sbagliato. Vedi phpunit.xml e .env.test.backend.example.",
            );
        }

        if (in_array($database, $allowed, true)) {
            return;
        }

        throw new DestructiveDatabaseException(
            sprintf(
                "Operazione distruttiva rifiutata: '%s' (connessione '%s') non e' un database di test.\n" .
                    "Consentiti: %s.\n" .
                    "Causa frequente: una config cache rende env() inerte e phpunit.xml non ha effetto.\n" .
                    "Rimedio: vedi docs/TEST.md.",
                $database,
                $connection,
                implode(", ", $allowed),
            ),
        );
    }

    /** @return string[] */
    private static function allowedDatabases(): array
    {
        return array_values(array_filter(array_map("trim", explode(",", (string) env(self::ALLOWED_ENV)))));
    }
}
