<?php

namespace App\Support;

use App\Exceptions\IdpConfigurationException;
use Illuminate\Support\Facades\Cookie;

/**
 * I nomi dei cookie dell'IdP, in un posto solo.
 *
 * Il nome porta l'ambiente (`nt-idp-mt-staging`, `nt-idp-at-6-production`): staging e produzione
 * scrivono sullo stesso dominio `.newtimegroup.it`, e con lo stesso nome si sovrascrivevano a vicenda.
 * Il formato sta in `config/idp.php`; qui si compone. L'ambiente e' l'`APP_ENV` dell'IdP; i pacchetti
 * client (`idp-extension-v2`, `idp-extension-node-v2`) costruiscono gli stessi nomi da `IDP_APP_ENV`,
 * che deve valere quanto l'`APP_ENV` dell'IdP a cui si collegano.
 */
class IdpCookies
{
    /**
     * Ferma l'avvio se `APP_ENV` manca o non e' uno dei valori ammessi.
     *
     * @throws IdpConfigurationException
     */
    public static function assertConfigured(): void
    {
        $env = config("idp.app_env");
        $allowed = config("idp.app_envs");

        if (!in_array($env, $allowed, true)) {
            throw new IdpConfigurationException(
                "APP_ENV non valida (" .
                    var_export($env, true) .
                    "): deve essere una fra " .
                    implode(", ", $allowed) .
                    ". E' obbligatoria: entra nel nome dei cookie dell'IdP.",
            );
        }
    }

    public static function masterTokenName(): string
    {
        return config("idp.jwt.master_token_name");
    }

    public static function appTokenName(int|string $providerId): string
    {
        return sprintf(config("idp.jwt.app_token_name_pattern"), $providerId);
    }

    /**
     * Cancella dal browser i cookie coi nomi di prima del 2026-09, sul dominio indicato e senza
     * dominio (localhost). Solo cancellazione: leggerli riaprirebbe il conflitto fra ambienti.
     */
    public static function forgetLegacy(int|string $providerId, ?string $domain = null): void
    {
        $names = [
            config("idp.legacy_cookies.master_token_name"),
            sprintf(config("idp.legacy_cookies.app_token_name_pattern"), $providerId),
        ];

        foreach ($names as $name) {
            Cookie::queue(Cookie::forget($name, "/"));
            if ($domain) {
                Cookie::queue(Cookie::forget($name, "/", $domain));
            }
        }
    }
}
