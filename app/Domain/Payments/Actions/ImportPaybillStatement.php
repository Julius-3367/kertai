<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Audit\Audit;
use App\Domain\Groups\Models\Group;
use App\Domain\Members\Models\Member;
use App\Domain\Payments\Import\StatementParser;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\StatementImport;
use App\Support\Phone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Imports paybill 247247 credits from a bank or M-Pesa statement. Each
 * credit becomes a contribution; lines we can tie to a member are allocated
 * straight away, the rest wait in the unmatched queue.
 */
final class ImportPaybillStatement
{
    public function __construct(
        private readonly StatementParser $parser,
        private readonly AllocatePayment $allocate,
    ) {}

    public function handle(Group $group, string $path, string $filename): StatementImport
    {
        // The paybill account (0720653394) is itself a phone-shaped number
        // that appears in every narrative; it is never the payer.
        $account = Phone::normalize($group->paybill_account);
        $rows = array_map(function (array $row) use ($account) {
            $row['phone'] = collect($row['phones'])->first(fn ($p) => $p !== $account);

            return $row;
        }, $this->parser->parse($path));

        $members = Member::withoutGlobalScopes()->where('group_id', $group->id)->get();
        $stats = ['rows' => count($rows), 'created' => 0, 'matched' => 0, 'duplicates' => 0];
        $created = [];

        DB::transaction(function () use ($group, $rows, $members, &$stats, &$created) {
            foreach ($rows as $row) {
                $exists = Payment::withoutGlobalScopes()
                    ->where('group_id', $group->id)
                    ->where('channel', 'paybill')
                    ->where('external_id', $row['reference'])
                    ->exists();

                if ($exists) {
                    $stats['duplicates']++;

                    continue;
                }

                $member = $this->match($row, $members);

                $created[] = Payment::withoutGlobalScopes()->create([
                    'group_id' => $group->id,
                    'member_id' => $member?->id,
                    'channel' => 'paybill',
                    'status' => 'succeeded',
                    'purpose' => 'contribution',
                    'amount' => $row['amount'],
                    'phone' => $row['phone'],
                    'payer_name' => $row['name'] ?? $member?->name,
                    'reference' => $row['account'],
                    'external_id' => $row['reference'],
                    'receipt_number' => $row['reference'],
                    'received_at' => $row['date']->toDateTimeString(),
                    'recorded_by' => auth()->id(),
                    'meta' => ['narrative' => mb_substr($row['narrative'], 0, 500)],
                ]);

                $stats['created']++;
                $stats['matched'] += $member ? 1 : 0;
            }
        });

        foreach ($created as $payment) {
            $this->allocate->handle($payment);
        }

        $import = StatementImport::withoutGlobalScopes()->create([
            'group_id' => $group->id,
            'filename' => mb_substr($filename, 0, 255),
            ...$stats,
            'imported_by' => auth()->id(),
        ]);

        Audit::log('statement.imported', "Imported {$stats['created']} paybill payments from {$filename} ({$stats['matched']} matched, {$stats['duplicates']} already recorded)", $import, $stats, $group->id);

        return $import;
    }

    /**
     * Phone first, then a member number written in the account field or
     * narrative, then a name that matches exactly one member.
     *
     * @param  array{phone: string|null, name: string|null, account: string|null, narrative: string}  $row
     * @param  Collection<int, Member>  $members
     */
    public function match(array $row, Collection $members): ?Member
    {
        if ($row['phone']) {
            $byPhone = $members->first(fn (Member $m) => Phone::normalize($m->phone) === $row['phone']);

            if ($byPhone) {
                return $byPhone;
            }
        }

        $haystack = strtoupper(' '.($row['account'] ?? '').' '.$row['narrative'].' '.($row['name'] ?? '').' ');

        $byNumber = $members->filter(fn (Member $m) => preg_match('/[^A-Z0-9]'.preg_quote(strtoupper($m->member_number), '/').'[^A-Z0-9]/', $haystack) === 1);

        if ($byNumber->count() === 1) {
            return $byNumber->first();
        }

        $byName = $members->filter(function (Member $m) use ($haystack) {
            $parts = array_filter(explode(' ', strtoupper($m->name)), fn ($p) => strlen($p) > 2);

            return count($parts) >= 2 && collect($parts)->every(fn ($p) => str_contains($haystack, " {$p} "));
        });

        return $byName->count() === 1 ? $byName->first() : null;
    }
}
