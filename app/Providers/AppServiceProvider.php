<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
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
        // Se non siamo in locale, forza tutti i link generati da Laravel ad usare HTTPS
        if (config("app.env") !== "local") {
            URL::forceScheme("https");
        }

        Passport::hashClientSecrets();
    }
}
