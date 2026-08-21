<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * I dati di partenza non si possono creare: se ne occupano i seeder.
 *
 * Perche' esiste (punto TEW01): le stesse ragioni di `DestructiveDatabaseException` — un nome che
 * dice **cosa** e' andato storto al posto della classe di qualunque guasto a runtime.
 *
 * Estende `RuntimeException` (decisione `D1`) e ce n'e' **una per modulo** (decisione `D2`): i seeder
 * si fermano per due cause — la password dell'amministratore non e' nell'ambiente, oppure il database
 * contiene gia' i dati iniziali — e il dettaglio, col rimedio, sta nel messaggio.
 *
 * ATTENZIONE ai messaggi: nominano la **variabile** `SEED_ADMIN_PASSWORD`, non il suo valore. E'
 * la forma giusta e va conservata — un'eccezione finisce nei log, e un log non e' il posto di una
 * credenziale.
 */
class SeedingException extends RuntimeException {}
