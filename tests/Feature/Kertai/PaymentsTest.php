<?php

use App\Domain\Dues\Actions\GenerateMonthlyDues;
use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Ledger\Ledger;
use App\Domain\Notifications\Models\OutboundMessage;
use App\Domain\Payments\Actions\ReconcilePendingPayments;
use App\Domain\Payments\Gateway\FakeGateway;
use App\Domain\Payments\Gateway\GatewayException;
use App\Domain\Payments\Gateway\SimulinkPayGateway;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\WebhookEvent;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

function ledgerBalance(string $code, ?int $memberId = null): int
{
    return app(Ledger::class)->balance(app(ChartOfAccounts::class)->get(test()->group(), $code), $memberId);
}

function signedWebhook(array $payload): array
{
    $raw = json_encode($payload);

    return [$raw, hash_hmac('sha256', $raw, FakeGateway::WEBHOOK_SECRET)];
}

it('records cash, allocates it and sends a receipt', function () {
    $treasurer = $this->official('treasurer');
    $member = $this->member();
    app(GenerateMonthlyDues::class)->handle($this->group(), now()->format('Y-m'));

    $this->actingAs($treasurer)->post('/payments', [
        'channel' => 'cash',
        'amount' => '1000',
        'purpose' => 'contribution',
        'member_id' => $member->id,
        'received_on' => now()->toDateString(),
    ])->assertSessionHasNoErrors();

    $payment = Payment::query()->where('member_id', $member->id)->firstOrFail();

    expect($payment->allocated_at)->not->toBeNull()
        ->and(ledgerBalance(ChartOfAccounts::CASH))->toBe(100000)
        ->and(OutboundMessage::query()->where('member_id', $member->id)->where('template_key', 'payment_received')->where('status', 'sent')->count())->toBe(2);
});

it('needs a receipt number for paybill lines and refuses duplicates', function () {
    $treasurer = $this->official('treasurer');
    $member = $this->member();
    $base = ['channel' => 'paybill', 'amount' => '500', 'purpose' => 'contribution', 'member_id' => $member->id, 'received_on' => now()->toDateString()];

    $this->actingAs($treasurer)->post('/payments', $base)->assertSessionHasErrors('reference');
    $this->actingAs($treasurer)->post('/payments', [...$base, 'reference' => 'RJK1ABCD12'])->assertSessionHasNoErrors();
    $this->actingAs($treasurer)->post('/payments', [...$base, 'reference' => 'RJK1ABCD12'])->assertSessionHasErrors('action');

    expect(Payment::query()->count())->toBe(1);
});

it('keeps members from recording payments', function () {
    $member = $this->member();

    $this->actingAs($member->user)->post('/payments', [])->assertForbidden();
    $this->actingAs($member->user)->get('/payments')->assertForbidden();
});

it('lets a member pay by M-Pesa prompt and settles it on check', function () {
    $member = $this->member(['phone' => '0712345678']);
    app(GenerateMonthlyDues::class)->handle($this->group(), now()->format('Y-m'));

    $this->actingAs($member->user)->post('/me/pay', ['amount' => 1000, 'phone' => '0712345678', 'purpose' => 'contribution'])
        ->assertSessionHasNoErrors();

    $payment = Payment::query()->firstOrFail();
    expect($payment->status)->toBe('processing')
        ->and($payment->channel)->toBe('simulinkpay')
        ->and($payment->external_id)->toStartWith('fake_');

    $this->actingAs($member->user)->post("/payments/{$payment->id}/check")->assertRedirect();

    expect($payment->refresh()->status)->toBe('succeeded')
        ->and($payment->receipt_number)->toStartWith('FK')
        ->and(ledgerBalance(ChartOfAccounts::MPESA))->toBe(100000)
        ->and($member->dues()->first()->status)->toBe('paid');
});

it('records a failed M-Pesa payment without touching the books', function () {
    $member = $this->member(['phone' => '0712340000']);

    $this->actingAs($member->user)->post('/me/pay', ['amount' => 500, 'phone' => '0712340000', 'purpose' => 'contribution']);
    $payment = Payment::query()->firstOrFail();
    app(ReconcilePendingPayments::class)->one($payment);

    expect($payment->refresh()->status)->toBe('failed')
        ->and($payment->failure_reason)->toContain('Insufficient')
        ->and($payment->journal_entry_id)->toBeNull()
        ->and(ledgerBalance(ChartOfAccounts::MPESA))->toBe(0);
});

it('accepts a signed SimulinkPay webhook once and rejects forged ones', function () {
    $member = $this->member(['phone' => '0712345678']);
    $this->actingAs($member->user)->post('/me/pay', ['amount' => 700, 'phone' => '0712345678', 'purpose' => 'donation']);
    $payment = Payment::query()->firstOrFail();
    auth()->logout();

    $payload = ['id' => $payment->external_id, 'type' => 'payment.succeeded', 'payment' => ['id' => $payment->external_id, 'status' => 'succeeded']];
    [$raw, $signature] = signedWebhook($payload);

    $this->call('POST', '/webhooks/simulinkpay', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIMULINKPAY_SIGNATURE' => 'bad'], $raw)
        ->assertStatus(401);
    expect($payment->refresh()->status)->toBe('processing');

    $this->call('POST', '/webhooks/simulinkpay', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIMULINKPAY_SIGNATURE' => $signature], $raw)
        ->assertOk();
    $this->call('POST', '/webhooks/simulinkpay', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIMULINKPAY_SIGNATURE' => $signature], $raw)
        ->assertOk();

    expect($payment->refresh()->status)->toBe('succeeded')
        ->and(ledgerBalance(ChartOfAccounts::GROUP_FUND))->toBe(70000)
        ->and(WebhookEvent::query()->where('signature_valid', true)->count())->toBe(2)
        ->and(WebhookEvent::query()->where('signature_valid', false)->count())->toBe(1);
});

it('ignores webhooks for payments it does not know', function () {
    $this->group();
    [$raw, $signature] = signedWebhook(['id' => 999, 'type' => 'payment.succeeded']);

    $this->call('POST', '/webhooks/simulinkpay', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIMULINKPAY_SIGNATURE' => $signature], $raw)
        ->assertOk()
        ->assertJson(['message' => 'Ignored: unknown payment.']);
});

it('reconciles pending prompts and expires the ones nobody answers', function () {
    $member = $this->member(['phone' => '0712345678']);
    $this->actingAs($member->user)->post('/me/pay', ['amount' => 100, 'phone' => '0712345678', 'purpose' => 'donation']);

    $lost = Payment::query()->create([
        'group_id' => $this->group()->id,
        'channel' => 'simulinkpay',
        'status' => 'processing',
        'purpose' => 'donation',
        'amount' => 10000,
        'external_id' => 'fake_unknown',
    ]);
    $lost->forceFill(['created_at' => now()->subHours(2)])->save();

    $this->travel(2)->minutes();
    $stats = app(ReconcilePendingPayments::class)->handle();

    expect($stats['checked'])->toBe(2)
        ->and(Payment::query()->where('status', 'succeeded')->count())->toBe(1)
        ->and($lost->refresh()->status)->toBe('expired');
});

it('imports a paybill statement, matches members and skips lines already in', function () {
    $treasurer = $this->official('treasurer');
    $alice = $this->member(['name' => 'Alice Chepkoech', 'phone' => '0799000001']);
    $bob = $this->member(['name' => 'Bob Kiprono', 'member_number' => 'M777']);
    $carol = $this->member(['name' => 'Carol Jepchumba Langat']);

    $csv = implode("\n", [
        'Equity Bank statement',
        'Transaction Date,Value Date,Narrative,Debit,Credit,Running Balance',
        '01/11/2026,01/11/2026,MPS 254799000001 RJK1ABCD12 ALICE C 0720653394,,"1,000.00",1000',
        '02/11/2026,02/11/2026,MPS RJK2ABCD34 M777 0720653394,,500.00,1500',
        '03/11/2026,03/11/2026,MPS RJK3ABCD56 CAROL JEPCHUMBA LANGAT 0720653394,,750.00,2250',
        '04/11/2026,04/11/2026,MPS RJK4ABCD78 SOMEONE ELSE 0720653394,,300.00,2550',
        '05/11/2026,05/11/2026,Bank charges,50.00,,2500',
    ]);

    $upload = fn () => UploadedFile::fake()->createWithContent('equity.csv', $csv);

    $this->actingAs($treasurer)->post('/payments/import', ['file' => $upload()])->assertRedirect();

    expect(Payment::query()->count())->toBe(4)
        ->and(Payment::query()->where('member_id', $alice->id)->value('amount'))->toBe(100000)
        ->and(Payment::query()->where('member_id', $bob->id)->value('amount'))->toBe(50000)
        ->and(Payment::query()->where('member_id', $carol->id)->value('amount'))->toBe(75000)
        ->and(Payment::query()->unallocated()->count())->toBe(1)
        ->and(Payment::query()->whereNull('member_id')->value('phone'))->toBeNull();

    $this->actingAs($treasurer)->post('/payments/import', ['file' => $upload()])->assertRedirect();
    expect(Payment::query()->count())->toBe(4);

    $stray = Payment::query()->unallocated()->firstOrFail();
    $this->actingAs($treasurer)->post("/payments/{$stray->id}/assign", ['member_id' => $bob->id, 'purpose' => 'contribution'])->assertSessionHasNoErrors();

    expect($stray->refresh()->allocated_at)->not->toBeNull()
        ->and(ledgerBalance(ChartOfAccounts::BANK))->toBe(255000);

    $this->actingAs($treasurer)->get('/payments/import')->assertOk()->assertInertia(fn ($p) => $p->has('imports', 2));
});

it('reads an M-Pesa paybill statement too', function () {
    $treasurer = $this->official('treasurer');
    $member = $this->member(['phone' => '0722111222']);

    $csv = implode("\n", [
        'Receipt No.,Completion Time,Details,Transaction Status,Paid In,Withdrawn,Balance,Other Party Info',
        'RKL1XYZ123,2026-11-02 10:15:00,Pay Bill from 254722111222 - JOHN DOE Acc. 0720653394,Completed,"2,000.00",,2000,254722111222 - JOHN DOE',
        'RKL1XYZ124,2026-11-02 11:00:00,Pay Bill from 254700000000,Failed,"9,000.00",,2000,',
    ]);

    $this->actingAs($treasurer)->post('/payments/import', ['file' => UploadedFile::fake()->createWithContent('mpesa.csv', $csv)]);

    expect(Payment::query()->count())->toBe(1)
        ->and(Payment::query()->first()->member_id)->toBe($member->id)
        ->and(Payment::query()->first()->external_id)->toBe('RKL1XYZ123');
});

it('reverses a contribution recorded against the wrong member', function () {
    $treasurer = $this->official('treasurer');
    $member = $this->member();
    app(GenerateMonthlyDues::class)->handle($this->group(), now()->format('Y-m'));

    $this->actingAs($treasurer)->post('/payments', ['channel' => 'cash', 'amount' => '1200', 'purpose' => 'contribution', 'member_id' => $member->id, 'received_on' => now()->toDateString()]);
    $payment = Payment::query()->where('member_id', $member->id)->firstOrFail();

    expect(ledgerBalance(ChartOfAccounts::PREPAYMENTS, $member->id))->toBe(20000);

    $this->actingAs($treasurer)->post("/payments/{$payment->id}/reverse", ['reason' => 'Wrong member'])->assertSessionHasNoErrors();

    expect($payment->refresh()->status)->toBe('cancelled')
        ->and(ledgerBalance(ChartOfAccounts::CASH))->toBe(0)
        ->and(ledgerBalance(ChartOfAccounts::PREPAYMENTS, $member->id))->toBe(0)
        ->and($member->dues()->first()->paid)->toBe(0)
        ->and($member->dues()->first()->status)->toBe('open');
});

it('talks to SimulinkPay the way its API expects', function () {
    Http::fake([
        'pay.test/api/v1/payments' => Http::response(['id' => 57, 'status' => 'processing', 'amount' => 1500, 'currency' => 'KES', 'payment_link_id' => null, 'reference' => 'KRT-1', 'receipt_number' => null, 'created_at' => now()->toIso8601String(), 'completed_at' => null], 202),
        'pay.test/api/v1/payments/57' => Http::response(['id' => 57, 'status' => 'succeeded', 'amount' => 1500, 'receipt_number' => 'TJK3XQ9ABC']),
        'pay.test/api/v1/payments/58' => Http::response(['error' => ['type' => 'not_found', 'message' => 'Payment not found.']], 404),
    ]);

    $gateway = new SimulinkPayGateway(['base_url' => 'https://pay.test', 'token' => '12|sk_live_abc', 'receiving_account_id' => '3', 'webhook_secret' => 'whsec_x']);
    $created = $gateway->requestStkPush(150000, '254712345678', 'KRT-1', 'key-1');
    $fetched = $gateway->fetch('57');

    Http::assertSent(fn (Request $r) => $r->url() === 'https://pay.test/api/v1/payments'
        && $r->hasHeader('Authorization', 'Bearer 12|sk_live_abc')
        && $r->hasHeader('Idempotency-Key', 'key-1')
        && $r['amount'] === 1500
        && $r['receiving_account_id'] === 3
        && $r['customer_phone'] === '254712345678');

    expect($created->externalId)->toBe('57')
        ->and($created->amount)->toBe(150000)
        ->and($fetched->status)->toBe('succeeded')
        ->and($fetched->receiptNumber)->toBe('TJK3XQ9ABC')
        ->and(fn () => $gateway->fetch('58'))->toThrow(GatewayException::class, 'Payment not found.')
        ->and(fn () => $gateway->requestStkPush(150050, '254712345678', 'x', 'k'))->toThrow(GatewayException::class)
        ->and($gateway->verifyWebhookSignature('{"a":1}', hash_hmac('sha256', '{"a":1}', 'whsec_x')))->toBeTrue()
        ->and($gateway->verifyWebhookSignature('{"a":2}', hash_hmac('sha256', '{"a":1}', 'whsec_x')))->toBeFalse();
});
