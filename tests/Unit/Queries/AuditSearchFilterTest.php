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

    public function test_un_termine_vuoto_lascia_la_query_intatta(): void
    {
        foreach ([null, "", "   "] as $vuoto) {
            $this->assertSame(Audit::query()->toSql(), $this->sql($vuoto), "un termine vuoto non deve filtrare");
        }
    }

    public function test_cerca_su_indirizzo_evento_e_tipo_di_entita(): void
    {
        $sql = $this->sql("mario");

        foreach (["ip_address", "event", "auditable_type"] as $colonna) {
            $this->assertStringContainsString($colonna, $sql, "la ricerca non copre {$colonna}");
        }
    }

    public function test_cerca_anche_nellattore_polimorfo(): void
    {
        $sql = $this->sql("mario");

        // Le due tabelle degli attori: utenti e client Passport. Cercare su una sola perderebbe
        // meta' degli attori, e nessun dato di prova lo direbbe se contenesse solo utenti.
        $this->assertStringContainsString("users", $sql);
        $this->assertStringContainsString("oauth_clients", $sql);
    }

    public function test_il_termine_viene_legato_e_non_concatenato(): void
    {
        $query = (new AuditSearchFilter())->apply(Audit::query(), "mario' or 1=1 --");

        // Il termine arriva dal client: deve stare nei binding, non nell'SQL.
        $this->assertStringNotContainsString("or 1=1", $query->toSql());
        $this->assertContains("%mario' or 1=1 --%", $query->getBindings());
    }
}
