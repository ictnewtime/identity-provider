<?php

namespace App\Queries\Audit;

use App\Models\User;
use Illuminate\Contracts\Database\Query\Builder;

class AuditSortOrder
{
    public const ALLOWED = ["created_at", "event", "auditable_type", "user.username", "ip_address"];

    private const DEFAULT_COLUMN = "created_at";
    private const DEFAULT_DIRECTION = "desc";

    public function apply(Builder $query, ?string $field, ?string $direction): Builder
    {
        if (!in_array($field, self::ALLOWED, true)) {
            return $query->orderBy(self::DEFAULT_COLUMN, self::DEFAULT_DIRECTION);
        }

        $direction = strtolower((string) $direction) === "desc" ? "desc" : "asc";

        if (!str_starts_with($field, "user.")) {
            return $query->orderBy("audits." . $field, $direction);
        }

        return $query
            ->leftJoin("users", function ($join) {
                $join->on("audits.user_id", "=", "users.id")->where("audits.user_type", "=", User::class);
            })
            ->select("audits.*")
            ->orderBy(str_replace("user.", "users.", $field), $direction);
    }
}
