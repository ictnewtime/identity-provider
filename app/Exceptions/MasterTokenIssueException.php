<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * L'IdP non riesce a emettere un master token utilizzabile.
 *
 * Perche' esiste: e' un guasto di **configurazione** (chiave privata assente, `private.key` e
 * `public.key` che non sono una coppia, provider dell'IdP mancante), non un problema dell'utente.
 * Va distinto dagli altri errori perche' la risposta giusta e' diversa: non si rimanda l'utente
 * all'applicazione con un token che non funzionera' — sarebbe un loop di redirect — ma lo si ferma
 * su una pagina che chiede all'amministratore di verificare.
 *
 * Il messaggio dice cosa non va, mai il contenuto delle chiavi: finisce nei log.
 */
class MasterTokenIssueException extends RuntimeException {}
