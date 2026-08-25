<?php

namespace Tests\Unit\Queries;

use App\Queries\Audit\AuditSearchFilter;
use OwenIt\Auditing\Models\Audit;
use Tests\TestCase;

/**
 * Il filtro di ricerca, provato da solo (punto TCC06).
 *
 * Unit e non Feature: **non tocca il database**. Si costruisce la query e si guarda l'SQL che
 * produce, senza eseguirla — che e' anche l'unico modo di distinguere «cerca su quattro campi» da
 * «cerca su tre e sembra funzionare» quando i dati di prova non coprono il quarto.
 */
class AuditSearchFilterTest extends TestCase
{
    private function sql(?string $termine): string
    {
        return (new AuditSearchFilter())->apply(Audit::query(), $termine)->toSql();
    }

    public function test_an_empty_term_leaves_the_query_untouched(): void
    {
        foreach ([null, "", "   "] as $vuoto) {
            $this->assertSame(Audit::query()->toSql(), $this->sql($vuoto), "un termine vuoto non deve filtrare");
        }
    }

    public function test_it_searches_address_event_and_entity_type(): void
    {
        $sql = $this->sql("mario");

        foreach (["ip_address", "event", "auditable_type"] as $colonna) {
            $this->assertStringContainsString($colonna, $sql, "la ricerca non copre {$colonna}");
        }
    }

    public function test_it_also_searches_the_polymorphic_actor(): void
    {
        $sql = $this->sql("mario");

        // Le due tabelle degli attori: utenti e client Passport. Cercare su una sola perderebbe
        // meta' degli attori, e nessun dato di prova lo direbbe se contenesse solo utenti.
        $this->assertStringContainsString("users", $sql);
        $this->assertStringContainsString("oauth_clients", $sql);
    }

    public function test_the_term_is_bound_and_not_concatenated(): void
    {
        $query = (new AuditSearchFilter())->apply(Audit::query(), "mario' or 1=1 --");

        // Il termine arriva dal client: deve stare nei binding, non nell'SQL.
        $this->assertStringNotContainsString("or 1=1", $query->toSql());
        $this->assertContains("%mario' or 1=1 --%", $query->getBindings());
    }
}
