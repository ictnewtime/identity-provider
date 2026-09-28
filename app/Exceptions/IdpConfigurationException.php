<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * La configurazione dell'IdP manca di qualcosa senza cui non puo' funzionare.
 *
 * Perche' esiste: oggi la usa `APP_ENV`, che entra nel nome dei cookie. Un valore sbagliato non
 * darebbe un errore, darebbe cookie con un nome che le applicazioni non cercano — accessi che
 * falliscono senza spiegazione. Meglio non partire e dire quale variabile sistemare.
 */
class IdpConfigurationException extends RuntimeException {}
