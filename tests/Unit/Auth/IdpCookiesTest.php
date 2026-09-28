<?php

namespace Tests\Unit\Auth;

use App\Exceptions\IdpConfigurationException;
use App\Providers\AppServiceProvider;
use App\Support\IdpCookies;
use Tests\TestCase;

/**
 * I nomi dei cookie portano l'ambiente, e senza ambiente l'IdP non parte.
 *
 * IL DIFETTO CHE QUESTI TEST TENGONO CHIUSO: staging e produzione scrivevano `idp-master-token` sullo
 * stesso dominio `.newtimegroup.it`. Nel browser era **un cookie solo**: un login su staging
 * sovrascriveva quello di produzione, e le applicazioni ricevevano un token dell'ambiente sbagliato.
 */
class IdpCookiesTest extends TestCase
{
    /** L'ambiente e' l'APP_ENV dell'IdP — `testing`, da phpunit.xml: i nomi lo riportano. */
    public function test_names_carry_the_environment(): void
    {
        $this->assertSame("nt-idp-mt-testing", IdpCookies::masterTokenName());
        $this->assertSame("nt-idp-at-6-testing", IdpCookies::appTokenName(6));
    }

    /** Ogni ambiente ammesso passa il controllo. */
    public function test_every_allowed_environment_is_accepted(): void
    {
        foreach (["local", "staging", "production", "testing"] as $env) {
            config(["idp.app_env" => $env]);
            IdpCookies::assertConfigured();
        }

        $this->addToAssertionCount(4);
    }

    /** Assente: niente ripiego su "production", eccezione che nomina la variabile. */
    public function test_a_missing_environment_is_refused(): void
    {
        config(["idp.app_env" => null]);

        $this->expectException(IdpConfigurationException::class);
        $this->expectExceptionMessage("APP_ENV");
        IdpCookies::assertConfigured();
    }

    /** Un valore fuori elenco e' rifiutato come uno assente. */
    public function test_an_unknown_environment_is_refused(): void
    {
        config(["idp.app_env" => "dev"]);

        $this->expectException(IdpConfigurationException::class);
        IdpCookies::assertConfigured();
    }

    /** Il controllo e' davvero nell'avvio: il provider dell'applicazione non termina il boot. */
    public function test_the_application_does_not_boot_without_it(): void
    {
        config(["idp.app_env" => ""]);

        $this->expectException(IdpConfigurationException::class);
        (new AppServiceProvider($this->app))->boot();
    }
}
