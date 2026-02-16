<?php

namespace App\Http\Middleware;

use Illuminate\Container\Attributes\Log;
use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * I nomi dei cookie che non devono essere crittografati.
     */
    protected $except = [
        // ESEMPI DI UTILIZZO:
        "idp_token_*", // Es: idp_token_1, idp_token_abc
        // "session_v?", // Es: session_v1, session_v (ma non session_v12)
        // "auth_user_+", // Es: auth_user_1 (ma non auth_user_)
    ];

    /**
     * Determina se il cookie deve essere escluso dalla crittografia.
     */

    public function handle($request, \Closure $next)
    {
        return parent::handle($request, $next);
    }

    public function isDisabled($name)
    {
        return true;
        if (parent::isDisabled($name)) {
            return true;
        }
        foreach ($this->except as $pattern) {
            if (str_ends_with($pattern, "*")) {
                $prefix = rtrim($pattern, "*");
                if (str_starts_with($name, $prefix)) {
                    return true;
                }
            }

            // Se vuoi mantenere la tua logica regex completa per ? e +:
            if ($this->patternMatch($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Converte i simboli custom in Regex e verifica il match.
     */
    protected function patternMatch($pattern, $value)
    {
        if ($pattern === $value) {
            return true;
        }

        $regex = preg_quote($pattern, "#");
        $regex = str_replace(["\*", "\+", "\?"], [".*", ".+", ".?"], $regex);

        return (bool) preg_match("#^" . $regex . '$#u', $value);
    }
}
