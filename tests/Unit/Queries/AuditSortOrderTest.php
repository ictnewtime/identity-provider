<?php

namespace Tests\Unit\Queries;

use App\Queries\Audit\AuditSortOrder;
use OwenIt\Auditing\Models\Audit;
use Tests\TestCase;

/**
 * L'ordinamento, provato da solo (punto TCC06).
 *
 * Qui vive la correzione del difetto VDF02, e questi test la fissano nell'SQL invece che nei dati:
 * un test sui dati direbbe «le righe ci sono», questi dicono **perche'** ci sono.
 */
class AuditSortOrderTest extends TestCase
{
    /**
     * L'SQL **senza le virgolette dell'identificatore**: sqlite scrive `"created_at"`, MySQL
     * `` `created_at` ``. Asserire sulla forma di un motore fa fallire i test sull'altro — ed e'
     * successo: questi test passavano su sqlite e fallivano su MariaDB (punto TCC06).
     */
    private function sql(?string $campo, ?string $direzione = null): string
    {
        $sql = (new AuditSortOrder())->apply(Audit::query(), $campo, $direzione)->toSql();

        return str_replace(['"', "`"], "", $sql);
    }

    public function test_without_a_field_it_sorts_by_date_descending(): void
    {
        $this->assertStringContainsString('order by created_at desc', $this->sql(null));
    }

    public function test_a_field_that_is_not_allowed_falls_back_to_the_default_order(): void
    {
        // `id` non e' fra le colonne ammesse: il campo arriva dal client, e cio' che non e'
        // nell'elenco non deve finire in un `order by`.
        $sql = $this->sql("id", "asc");

        $this->assertStringContainsString('order by created_at desc', $sql);
        $this->assertStringNotContainsString("id asc", $sql);
    }

    public function test_it_sorts_on_an_allowed_column_qualifying_it(): void
    {
        $sql = $this->sql("event", "asc");

        // Qualificata con la tabella: con la join attiva, `event` da solo sarebbe ambiguo.
        $this->assertStringContainsString('order by audits.event asc', $sql);
    }

    public function test_an_invalid_direction_becomes_ascending(): void
    {
        $this->assertStringContainsString("asc", $this->sql("event", "qualsiasi-cosa"));
    }

    public function test_sorting_by_username_uses_a_left_join_on_the_type(): void
    {
        $sql = $this->sql("user.username", "asc");

        // Le due meta' della correzione di VDF02, e servono INSIEME:
        //  - `left join`, perche' `audits.user_id` e' nullable e una join interna farebbe sparire
        //    dalla lista gli audit di sistema;
        //  - la condizione su `user_type`, perche' la relazione e' polimorfa e unire sul solo
        //    `user_id` attaccherebbe l'audit di un client all'utente con lo stesso id.
        $this->assertStringContainsString("left join", strtolower($sql));
        $this->assertStringContainsString("user_type", $sql);
        $this->assertStringContainsString('order by users.username asc', $sql);
    }
}
