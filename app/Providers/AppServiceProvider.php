<?php

namespace App\Providers;

use App\Support\IdpCookies;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // APP_ENV e' obbligatoria e fra i valori ammessi: entra nel nome dei cookie, e con un valore
        // sbagliato i cookie avrebbero un nome che nessuna app cerca.
        IdpCookies::assertConfigured();

        URL::forceRootUrl(config("app.url"));

        // Fuori da locale e test, forza tutti i link generati da Laravel ad usare HTTPS. I test E2E
        // girano su http://localhost dentro il loro container: li' HTTPS non c'e'.
        if (!in_array(config("app.env"), ["local", "testing"], true)) {
            URL::forceScheme("https");
        }

        Passport::hashClientSecrets();
    }
}
