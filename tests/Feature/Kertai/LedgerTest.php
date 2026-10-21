<?php

use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Ledger\Ledger;
use App\Domain\Ledger\Models\JournalEntry;
use App\Support\RuleViolation;

function accounts(): ChartOfAccounts
{
    return app(ChartOfAccounts::class);
}

it('posts a balanced entry and reports natural balances', function () {
    $group = $this->group();
    $ledger = app(Ledger::class);

    $ledger->post('Opening', [
        ['account' => accounts()->get($group, ChartOfAccounts::BANK), 'debit' => 50000],
        ['account' => accounts()->get($group, ChartOfAccounts::GROUP_FUND), 'credit' => 50000],
    ]);

    expect($ledger->balance(accounts()->get($group, ChartOfAccounts::BANK)))->toBe(50000)
        ->and($ledger->balance(accounts()->get($group, ChartOfAccounts::GROUP_FUND)))->toBe(50000);
});

it('refuses entries that do not balance', function () {
    $group = $this->group();

    app(Ledger::class)->post('Bad', [
        ['account' => accounts()->get($group, ChartOfAccounts::BANK), 'debit' => 100],
        ['account' => accounts()->get($group, ChartOfAccounts::GROUP_FUND), 'credit' => 90],
    ]);
})->throws(RuleViolation::class, 'does not balance');

it('needs a member on every savings line', function () {
    $group = $this->group();

    app(Ledger::class)->post('Bad', [
        ['account' => accounts()->get($group, ChartOfAccounts::BANK), 'debit' => 100],
        ['account' => accounts()->get($group, ChartOfAccounts::SAVINGS), 'credit' => 100],
    ]);
})->throws(RuleViolation::class, 'needs a member');

it('never lets a posted entry change', function () {
    $group = $this->group();
    $entry = app(Ledger::class)->post('Opening', [
        ['account' => accounts()->get($group, ChartOfAccounts::CASH), 'debit' => 100],
        ['account' => accounts()->get($group, ChartOfAccounts::GROUP_FUND), 'credit' => 100],
    ]);

    expect(fn () => $entry->update(['description' => 'changed']))->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class)
        ->and(fn () => $entry->lines->first()->update(['debit' => 1]))->toThrow(LogicException::class);
});

it('corrects with a reversal, once', function () {
    $group = $this->group();
    $ledger = app(Ledger::class);
    $bank = accounts()->get($group, ChartOfAccounts::BANK);

    $entry = $ledger->post('Oops', [
        ['account' => $bank, 'debit' => 700],
        ['account' => accounts()->get($group, ChartOfAccounts::GROUP_FUND), 'credit' => 700],
    ]);

    $reversal = $ledger->reverse($entry, 'typo');

    expect($reversal->reversal_of_id)->toBe($entry->id)
        ->and($ledger->balance($bank))->toBe(0)
        ->and(fn () => $ledger->reverse($entry, 'again'))->toThrow(RuleViolation::class);
});

it('numbers entries one after another per group', function () {
    $group = $this->group();
    $ledger = app(Ledger::class);
    $lines = fn () => [
        ['account' => accounts()->get($group, ChartOfAccounts::CASH), 'debit' => 1],
        ['account' => accounts()->get($group, ChartOfAccounts::GROUP_FUND), 'credit' => 1],
    ];

    $a = $ledger->post('A', $lines());
    $b = $ledger->post('B', $lines());

    expect($b->number)->toBe($a->number + 1);
});

it('lets the treasurer bank meeting cash and post opening balances', function () {
    $treasurer = $this->official('treasurer');
    $member = $this->member();
    $group = $this->group();
    $cash = accounts()->get($group, ChartOfAccounts::CASH);
    $bank = accounts()->get($group, ChartOfAccounts::BANK);
    $savings = accounts()->get($group, ChartOfAccounts::SAVINGS);

    $this->actingAs($treasurer)->post('/ledger/opening', [
        'asset_account_id' => $cash->id,
        'fund_account_id' => $savings->id,
        'amount' => '3000',
        'date' => now()->toDateString(),
    ])->assertSessionHasErrors('action');

    $this->actingAs($treasurer)->post('/ledger/opening', [
        'asset_account_id' => $cash->id,
        'fund_account_id' => $savings->id,
        'member_id' => $member->id,
        'amount' => '3000',
        'date' => now()->toDateString(),
    ])->assertSessionHasNoErrors();

    $this->actingAs($treasurer)->post('/ledger/transfers', [
        'from_account_id' => $cash->id,
        'to_account_id' => $bank->id,
        'amount' => '5000',
        'description' => 'Too much',
        'date' => now()->toDateString(),
    ])->assertSessionHasErrors('action');

    $this->actingAs($treasurer)->post('/ledger/transfers', [
        'from_account_id' => $cash->id,
        'to_account_id' => $bank->id,
        'amount' => '2000',
        'description' => 'Cash banked',
        'date' => now()->toDateString(),
    ])->assertSessionHasNoErrors();

    $ledger = app(Ledger::class);
    expect($ledger->balance($cash))->toBe(100000)
        ->and($ledger->balance($bank))->toBe(200000)
        ->and($ledger->balance($savings, $member->id))->toBe(300000);

    $transfer = JournalEntry::query()->latest('id')->first();
    $this->actingAs($treasurer)->post("/ledger/entries/{$transfer->id}/reverse", ['reason' => 'Wrong day'])->assertSessionHasNoErrors();
    expect($ledger->balance($cash))->toBe(300000);

    $this->actingAs($treasurer)->get('/ledger')->assertOk();
    $this->actingAs($treasurer)->get("/ledger/accounts/{$savings->id}")->assertOk()->assertInertia(fn ($p) => $p->has('memberBalances', 1));
});

it('keeps the ledger page from members without finance access', function () {
    $this->actingAs($this->member()->user)->get('/ledger')->assertForbidden();
    $this->actingAs($this->official('secretary'))->post('/ledger/transfers', [])->assertForbidden();
});
