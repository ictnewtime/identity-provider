<?php

namespace Tests;

use App\Support\DestructiveDatabaseGuard;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Rifiuta di eseguire un test se il database in uso non e' uno di quelli consentiti.
     *
     * La logica sta in `DestructiveDatabaseGuard` e non qui: dentro `tests/` proteggerebbe solo
     * chi passa dai test, mentre il difetto che ha fatto danno (VDF11) puo' ripresentarsi da uno
     * script o da un comando. Qui resta la sola chiamata.
     *
     * Il controllo sta in `setUpTraits()` e NON in `setUp()`, e la differenza e' tutto:
     * `RefreshDatabase` agisce dentro `setUpTraits()`, quindi un controllo dopo `parent::setUp()`
     * parlerebbe **a database gia' ricreato**. Provato: con la versione precedente il
     * `create table "migrations"` veniva tentato prima del messaggio d'errore.
     */
    protected function setUpTraits()
    {
        DestructiveDatabaseGuard::ensureTestDatabase();

        return parent::setUpTraits();
    }
}
