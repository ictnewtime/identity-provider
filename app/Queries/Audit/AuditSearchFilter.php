<?php

namespace App\Queries\Audit;

use App\Models\User;
use Illuminate\Contracts\Database\Query\Builder;
use Laravel\Passport\Client as PassportClient;

class AuditSearchFilter
{
    /** Le colonne di `audits` su cui la ricerca lavora direttamente. */
    private const COLUMNS = ["ip_address", "event", "auditable_type"];

    public function apply(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = "%" . $term . "%";

        return $query->where(function (Builder $scoped) use ($like) {
            foreach (self::COLUMNS as $i => $column) {
                $i === 0 ? $scoped->where($column, "like", $like) : $scoped->orWhere($column, "like", $like);
            }

            $scoped->orWhereHasMorph("user", [User::class, PassportClient::class], function ($actor, $type) use (
                $like,
            ) {
                $actor->where($type === PassportClient::class ? "name" : "username", "like", $like);
            });
        });
    }
}
