<?php

namespace App\Http\Middleware;

use Closure;
use Laravel\Passport\Http\Middleware\CheckClientCredentials;

class ProviderClientCredentials extends CheckClientCredentials
{
    public function handle($request, Closure $next, ...$scopes)
    {
        // Passiamo la richiesta (ora arricchita) al validatore nativo di Passport.
        // Se il token è falso/scaduto, Passport bloccherà la chiamata qui.
        return parent::handle($request, $next, ...$scopes);
    }
}
