<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Un'operazione distruttiva sul database e' stata rifiutata da `DestructiveDatabaseGuard`.
 *
 * Perche' esiste (punto TEW01): prima qui c'era `RuntimeException`, che e' la classe di **qualunque**
 * guasto a runtime. Chi voleva intercettare «la guardia ha detto no» doveva prendere quella, e
 * prendeva insieme ogni altro errore capitato nello stesso blocco — compreso un errore di
 * programmazione, che invece va lasciato passare.
 *
 * Estende `RuntimeException` di proposito (decisione `D1`): e' un guasto d'ambiente scoperto
 * all'esecuzione, che e' esattamente cio' che quella classe significa in PHP. Da qui discende anche
 * che chi cattura `RuntimeException` continua a funzionare come prima.
 *
 * **Una per modulo, non una per causa** (decisione `D2`): la guardia rifiuta per due ragioni —
 * l'elenco dei database consentiti non c'e', o il database in uso non e' fra quelli — e il dettaglio
 * sta nel messaggio, che porta con se' il rimedio. Se un giorno servisse distinguerle in un `catch`,
 * si aggiunge una sottoclasse e nessun `catch` esistente cambia.
 */
class DestructiveDatabaseException extends RuntimeException {}
