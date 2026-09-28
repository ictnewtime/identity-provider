<?php

// L'ambiente dell'IdP e' il suo APP_ENV: l'IdP e' se stesso, non gli serve un'altra variabile (le
// applicazioni client invece dichiarano con IDP_APP_ENV a quale IdP si collegano, e il valore deve
// coincidere con questo). Entra nel nome dei cookie: staging e produzione condividono il dominio
// `.newtimegroup.it`, e con lo stesso nome un login su staging sovrascriveva il cookie di produzione.
//
// Letto SENZA il ripiego "production" di config/app.php: se APP_ENV manca l'IdP non parte, invece di
// usare in silenzio i nomi di produzione. Lo verifica `App\Support\IdpCookies::assertConfigured()`.
$appEnv = env("APP_ENV");

return [
    "provider_id" => "1",
    "app_env" => $appEnv,
    // `testing` e' l'APP_ENV dei test (backend ed E2E): li' i cookie si chiamano nt-idp-mt-testing.
    // I pacchetti client ammettono solo i primi tre.
    "app_envs" => ["local", "staging", "production", "testing"],
    "jwt" => [
        "master_key_id" => "idp-master-key",
        // nt-idp-mt-<ambiente> — "new time", "identity provider", "master token"
        "master_token_name" => "nt-idp-mt-{$appEnv}",
        // nt-idp-at-<provider>-<ambiente> — "app token": uno per applicazione. Si compone con
        // `IdpCookies::appTokenName()`, mai a mano.
        "app_token_name_pattern" => "nt-idp-at-%s-{$appEnv}",
    ],
    // I nomi di prima del 2026-09. Non si leggono piu' (riaprirebbero il conflitto fra ambienti):
    // si cancellano soltanto, al login e al logout, perche' non restino nei browser.
    "legacy_cookies" => [
        "master_token_name" => "idp-master-token",
        "app_token_name_pattern" => "idp_token_%s",
    ],
];
