<?php

namespace App\Queries\Audit;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use OwenIt\Auditing\Models\Audit;

class AuditListQuery
{
    public const PER_PAGE_DEFAULT = 25;
    public const PER_PAGE_MAX = 200;

    public function __construct(private readonly AuditSearchFilter $search, private readonly AuditSortOrder $sort) {}

    public function paginate(
        ?string $term,
        ?string $sortBy,
        ?string $sortDir,
        mixed $perPage = null,
    ): LengthAwarePaginator {
        // `with("user")` carica in eager la relazione polimorfa: senza, AuditResource farebbe una
        // query per riga — un N+1 dentro una API Resource.
        $query = Audit::with("user");

        $query = $this->search->apply($query, $term);
        $query = $this->sort->apply($query, $sortBy, $sortDir);

        return $query->paginate($this->perPage($perPage));
    }

    private function perPage(mixed $requested): int
    {
        $perPage = (int) ($requested ?? self::PER_PAGE_DEFAULT);

        return max(1, min($perPage, self::PER_PAGE_MAX));
    }
}
