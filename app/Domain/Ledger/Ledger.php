<?php

namespace App\Domain\Ledger;

use App\Domain\Ledger\Models\Account;
use App\Domain\Ledger\Models\JournalEntry;
use App\Domain\Ledger\Models\JournalLine;
use App\Support\RuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class Ledger
{
    /**
     * Post a balanced entry. Each line is
     * ['account' => Account, 'debit' => cents] or ['account' => Account, 'credit' => cents],
     * optionally with 'member_id' and 'memo'. Zero lines are dropped.
     *
     * @param  array<int, array{account: Account, debit?: int, credit?: int, member_id?: int|null, memo?: string|null}>  $lines
     */
    public function post(
        string $description,
        array $lines,
        ?Model $source = null,
        ?CarbonImmutable $date = null,
        ?string $reference = null,
    ): JournalEntry {
        $lines = array_values(array_filter($lines, fn ($l) => ($l['debit'] ?? 0) > 0 || ($l['credit'] ?? 0) > 0));

        if ($lines === []) {
            throw new RuleViolation('An entry needs at least one non-zero line.');
        }

        $debits = 0;
        $credits = 0;
        $groupId = null;

        foreach ($lines as $line) {
            $debit = (int) ($line['debit'] ?? 0);
            $credit = (int) ($line['credit'] ?? 0);

            if ($debit < 0 || $credit < 0 || ($debit > 0 && $credit > 0)) {
                throw new RuleViolation('Each line must be a positive debit or a positive credit.');
            }

            $groupId ??= $line['account']->group_id;

            if ($line['account']->group_id !== $groupId) {
                throw new RuleViolation('All lines must belong to the same group.');
            }

            if ($line['account']->isPerMember() && empty($line['member_id'])) {
                throw new RuleViolation("Account {$line['account']->code} needs a member on every line.");
            }

            $debits += $debit;
            $credits += $credit;
        }

        if ($debits !== $credits) {
            throw new RuleViolation("Entry does not balance: debits {$debits}, credits {$credits}.");
        }

        return DB::transaction(function () use ($description, $lines, $source, $date, $reference, $groupId) {
            $entry = JournalEntry::withoutGlobalScopes()->create([
                'group_id' => $groupId,
                'number' => $this->nextNumber($groupId),
                'entry_date' => ($date ?? CarbonImmutable::today())->toDateString(),
                'description' => mb_substr($description, 0, 255),
                'reference' => $reference,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'created_by' => auth()->id(),
            ]);

            foreach ($lines as $line) {
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $line['account']->id,
                    'member_id' => $line['member_id'] ?? null,
                    'debit' => (int) ($line['debit'] ?? 0),
                    'credit' => (int) ($line['credit'] ?? 0),
                    'memo' => isset($line['memo']) ? mb_substr($line['memo'], 0, 255) : null,
                ]);
            }

            return $entry->load('lines');
        });
    }

    public function reverse(JournalEntry $entry, string $reason): JournalEntry
    {
        if ($entry->reversal_of_id !== null) {
            throw new RuleViolation('A reversal cannot itself be reversed.');
        }

        if (JournalEntry::withoutGlobalScopes()->where('reversal_of_id', $entry->id)->exists()) {
            throw new RuleViolation("Entry #{$entry->number} has already been reversed.");
        }

        return DB::transaction(function () use ($entry, $reason) {
            $reversal = JournalEntry::withoutGlobalScopes()->create([
                'group_id' => $entry->group_id,
                'number' => $this->nextNumber($entry->group_id),
                'entry_date' => CarbonImmutable::today()->toDateString(),
                'description' => mb_substr("Reversal of #{$entry->number}: {$reason}", 0, 255),
                'reference' => $entry->reference,
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
                'reversal_of_id' => $entry->id,
                'created_by' => auth()->id(),
            ]);

            foreach ($entry->lines()->get() as $line) {
                JournalLine::create([
                    'journal_entry_id' => $reversal->id,
                    'account_id' => $line->account_id,
                    'member_id' => $line->member_id,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'memo' => $line->memo,
                ]);
            }

            return $reversal->load('lines');
        });
    }

    /**
     * Natural balance: debit-positive for assets, credit-positive for funds.
     */
    public function balance(Account $account, ?int $memberId = null, ?CarbonImmutable $until = null): int
    {
        $query = JournalLine::query()
            ->where('account_id', $account->id)
            ->when($memberId !== null, fn ($q) => $q->where('member_id', $memberId))
            ->when($until !== null, fn ($q) => $q->whereHas(
                'entry',
                fn ($e) => $e->withoutGlobalScopes()->whereDate('entry_date', '<=', $until->toDateString()),
            ));

        $debit = (int) (clone $query)->sum('debit');
        $credit = (int) $query->sum('credit');

        return $account->isAsset() ? $debit - $credit : $credit - $debit;
    }

    /**
     * @return array<int, int> member_id => balance for a per-member account
     */
    public function memberBalances(Account $account): array
    {
        return JournalLine::query()
            ->where('account_id', $account->id)
            ->whereNotNull('member_id')
            ->groupBy('member_id')
            ->selectRaw('member_id, SUM(credit) - SUM(debit) as balance')
            ->pluck('balance', 'member_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    private function nextNumber(int $groupId): int
    {
        return Sequence::next($groupId, 'journal_entries');
    }
}
