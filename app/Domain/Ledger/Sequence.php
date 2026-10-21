<?php

namespace App\Domain\Ledger;

use Illuminate\Support\Facades\DB;

/**
 * Gap-free per-group numbering for journal entries and payouts. Locks the
 * group row (Postgres refuses FOR UPDATE on an aggregate), so callers must
 * already be inside a transaction.
 */
final class Sequence
{
    public static function next(int $groupId, string $table): int
    {
        DB::table('groups')->where('id', $groupId)->lockForUpdate()->first();

        return ((int) DB::table($table)->where('group_id', $groupId)->max('number')) + 1;
    }
}
