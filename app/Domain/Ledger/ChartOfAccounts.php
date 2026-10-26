<?php

namespace App\Domain\Ledger;

use App\Domain\Groups\Models\Group;
use App\Domain\Ledger\Models\Account;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final class ChartOfAccounts
{
    public const BANK = '1000';

    public const MPESA = '1010';

    public const CASH = '1020';

    public const LOANS = '1100';

    public const SAVINGS = '2000';

    public const PREPAYMENTS = '2010';

    public const MGR = '2100';

    public const WELFARE = '2200';

    public const ASSETS = '2300';

    public const GROUP_FUND = '3000';

    /** @var array<int|string, array{name: string, kind: string, purpose: string}> */
    public const BASE = [
        self::BANK => ['name' => 'Equity Bank (paybill 247247)', 'kind' => 'asset', 'purpose' => 'bank'],
        self::MPESA => ['name' => 'SimulinkPay M-Pesa collections', 'kind' => 'asset', 'purpose' => 'mpesa'],
        self::CASH => ['name' => 'Cash on hand', 'kind' => 'asset', 'purpose' => 'cash'],
        // Money lent out is still the group's: an asset owed back by members.
        self::LOANS => ['name' => 'Loans to members', 'kind' => 'asset', 'purpose' => 'loans'],
        self::SAVINGS => ['name' => 'Member savings', 'kind' => 'fund', 'purpose' => 'savings'],
        self::PREPAYMENTS => ['name' => 'Member prepayments', 'kind' => 'fund', 'purpose' => 'prepayments'],
        self::MGR => ['name' => 'Merry-go-round pot', 'kind' => 'fund', 'purpose' => 'mgr'],
        self::WELFARE => ['name' => 'Welfare fund', 'kind' => 'fund', 'purpose' => 'welfare'],
        self::ASSETS => ['name' => 'Assets and hire fund', 'kind' => 'fund', 'purpose' => 'assets'],
        self::GROUP_FUND => ['name' => 'Group fund', 'kind' => 'fund', 'purpose' => 'group'],
    ];

    /** @var array<string, Account> */
    private array $cache = [];

    public function install(Group $group): void
    {
        foreach (self::BASE as $code => $attributes) {
            Account::withoutGlobalScopes()->firstOrCreate(
                ['group_id' => $group->id, 'code' => $code],
                $attributes,
            );
        }
    }

    public function get(Group|int $group, string $code): Account
    {
        $groupId = $group instanceof Group ? $group->id : $group;
        $key = "{$groupId}:{$code}";

        return $this->cache[$key] ??= Account::withoutGlobalScopes()
            ->where('group_id', $groupId)
            ->where('code', $code)
            ->first() ?? throw new RuntimeException("Account {$code} is missing for group {$groupId}. Run php artisan kertai:install.");
    }

    /**
     * The asset account a payment channel lands in, or a payout method
     * leaves from.
     */
    public function forChannel(Group|int $group, string $channel): Account
    {
        return $this->get($group, match ($channel) {
            'simulinkpay', 'mpesa' => self::MPESA,
            'cash' => self::CASH,
            default => self::BANK,
        });
    }

    /**
     * Create the dedicated fund account for a project or fundraiser.
     */
    public function openFor(Model $owner, string $purpose, string $name): Account
    {
        $prefix = $purpose === 'project' ? 'PRJ' : 'FR';

        return Account::withoutGlobalScopes()->firstOrCreate(
            ['group_id' => $owner->getAttribute('group_id'), 'code' => "{$prefix}-{$owner->getKey()}"],
            [
                'name' => $name,
                'kind' => 'fund',
                'purpose' => $purpose,
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
            ],
        );
    }

    public function flush(): void
    {
        $this->cache = [];
    }
}
