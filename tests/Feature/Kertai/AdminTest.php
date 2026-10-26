<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Documents\Models\ComplianceItem;
use App\Domain\Documents\Models\Document;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Ledger\Ledger;
use App\Domain\Notifications\Email\EmailSender;
use App\Domain\Payouts\Models\Payout;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

function rulesPayload(array $settings = [], string $resolution = '2026/05'): array
{
    return [
        'group' => ['name' => 'KERTAI006 Self Help Group', 'paybill' => '247247', 'paybill_account' => '0720653394'],
        'settings' => [
            'contribution_amount' => '1500', 'mgr_share' => '500', 'welfare_share' => '300', 'contribution_due_day' => 10,
            'late_fine_amount' => '150', 'late_fine_grace_days' => 3, 'reminder_days_before' => 2, 'exit_notice_days' => 30,
            'exit_refund_days' => 30, 'payout_approvals_required' => 2, 'mgr_skip_members_in_arrears' => true,
            'show_contributor_names_by_default' => false, 'payer_pays_mpesa_fees' => true, 'sms_costs_from_group_fund' => true,
            'project_profit_basis' => 'contribution', 'share_out_month' => 12, 'sms_sender_id' => 'KERTAI006',
            'welfare_benefits' => ['member_death' => '50000', 'spouse_death' => '30000', 'child_death' => '20000', 'parent_death' => '20000', 'hospitalisation' => '10000'],
            ...$settings,
        ],
        'resolution_reference' => $resolution,
    ];
}

it('changes group rules only with a resolution, and logs who changed what', function () {
    $chair = $this->official('chairperson');

    $this->actingAs($chair)->put('/rules', rulesPayload(resolution: ''))->assertSessionHasErrors('resolution_reference');
    $this->actingAs($chair)->put('/rules', rulesPayload(['mgr_share' => '1400', 'welfare_share' => '300']))->assertSessionHasErrors('settings.mgr_share');
    $this->actingAs($chair)->put('/rules', rulesPayload())->assertSessionHasNoErrors();

    $settings = $this->group()->refresh()->settings();
    expect($settings->contributionBreakdown())->toBe(['mgr' => 50000, 'welfare' => 30000, 'savings' => 70000])
        ->and($settings->get('late_fine_amount'))->toBe(15000)
        ->and($settings->get('resolution_reference'))->toBe('2026/05')
        ->and(AuditLog::query()->where('action', 'settings.updated')->first()->properties['changed'])->toContain('contribution_amount');

    $this->actingAs($chair)->get('/rules')->assertOk()->assertInertia(fn ($p) => $p->component('rules/edit')->where('settings.contribution_amount', '1500.00'));
    $this->actingAs($this->official('treasurer'))->get('/rules')->assertForbidden();
});

it('saves the lending rules through the group rules', function () {
    $chair = $this->official('chairperson');

    $this->actingAs($chair)->put('/rules', rulesPayload([
        'loans_enabled' => true, 'loan_interest_on' => false, 'loan_interest_rate' => '5', 'loan_min_savings' => '2500',
        'loan_guarantors_required' => 3, 'loan_repayment_order' => 'interest_first',
    ]))->assertSessionHasNoErrors();

    $settings = $this->group()->refresh()->settings();
    expect($settings->loansEnabled())->toBeTrue()
        ->and($settings->loanInterestOn())->toBeFalse()
        ->and($settings->loanTerms()['interest_rate'])->toBe(5.0)
        ->and($settings->get('loan_min_savings'))->toBe(250000)
        ->and($settings->get('loan_guarantors_required'))->toBe(3);
});

it('opens the group books to members through the rules', function () {
    $chair = $this->official('chairperson');

    $this->actingAs($chair)->put('/rules', rulesPayload(['members_see_group_finances' => true]))->assertSessionHasNoErrors();

    expect($this->group()->refresh()->settings()->membersSeeGroupFinances())->toBeTrue();
});

it('stores provider keys encrypted and never sends secrets back', function () {
    $admin = $this->official('system-admin');

    $this->actingAs($admin)->put('/admin/integrations/sms', [
        'driver' => 'africastalking',
        'is_active' => true,
        'values' => ['username' => 'kertai', 'api_key' => 'atsk_secret_value', 'sender_id' => 'KERTAI006'],
    ])->assertSessionHasNoErrors();

    $raw = DB::table('integrations')->value('credentials');
    expect($raw)->not->toContain('atsk_secret_value');

    $this->actingAs($admin)->put('/admin/integrations/sms', [
        'driver' => 'africastalking',
        'is_active' => true,
        'values' => ['username' => 'kertai2', 'api_key' => '', 'sender_id' => 'KERTAI006'],
    ])->assertSessionHasNoErrors();

    $integration = Integration::query()->firstOrFail();
    expect($integration->credentials['api_key'])->toBe('atsk_secret_value')->and($integration->credentials['username'])->toBe('kertai2');

    $page = $this->actingAs($admin)->get('/admin/integrations');
    $page->assertOk()->assertInertia(fn ($p) => $p
        ->where('current.sms.driver', 'africastalking')
        ->where('current.sms.values.api_key', null)
        ->where('current.sms.values.api_key__set', true));
    expect($page->getContent())->not->toContain('atsk_secret_value');

    $this->actingAs($admin)->put('/admin/integrations/sms', ['driver' => 'log', 'is_active' => true, 'values' => []]);
    $this->actingAs($admin)->post('/admin/integrations/sms/test', ['to' => '0712345678'])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post('/admin/integrations/email/test', ['to' => 'test@example.com'])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post('/admin/integrations/payments/test', ['to' => '57'])->assertSessionHasErrors('action');

    $this->actingAs($this->official('treasurer'))->get('/admin/integrations')->assertForbidden();
});

it('sends email through a saved SMTP-style provider', function () {
    $admin = $this->official('system-admin');
    $this->actingAs($admin)->put('/admin/integrations/email', [
        'driver' => 'log',
        'is_active' => true,
        'values' => ['from_address' => 'kertai@example.com'],
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->post('/admin/integrations/email/test', ['to' => 'someone@example.com'])->assertSessionHasNoErrors();

    expect(config('mail.mailers.kertai.transport'))->toBe('log');
});

it('builds the Mailgun, Postmark and SES mailers from saved keys', function (string $driver, array $values, string $transport) {
    $this->group();
    Integration::query()->create(['kind' => 'email', 'driver' => $driver, 'credentials' => $values, 'is_active' => true]);

    $sender = app(EmailSender::class);
    $mailer = (new ReflectionMethod($sender, 'configureMailer'))->invoke($sender, $driver, $values);

    expect($mailer)->toBe('kertai')
        ->and((string) Mail::mailer('kertai')->getSymfonyTransport())->toContain($transport);
})->with([
    'mailgun' => ['mailgun', ['domain' => 'mg.example.com', 'secret' => 'key-123'], 'mailgun'],
    'postmark' => ['postmark', ['token' => 'pm-123'], 'postmark'],
    'ses' => ['ses', ['region' => 'eu-west-1', 'username' => 'AKIA', 'password' => 'x'], 'email-smtp.eu-west-1.amazonaws.com'],
]);

it('shows the audit log to the auditor only', function () {
    $this->actingAs($this->official('chairperson'))->put('/rules', rulesPayload());

    $this->actingAs($this->official('auditor'))->get('/admin/audit?q=rules')->assertOk()->assertInertia(fn ($p) => $p->has('logs.data', 1));
    $this->actingAs($this->official('treasurer'))->get('/admin/audit')->assertForbidden();
    expect(fn () => AuditLog::query()->first()->update(['description' => 'x']))->toThrow(LogicException::class);
});

it('exports all data without secrets and serves it only to the admin', function () {
    Storage::fake('local');
    $admin = $this->official('system-admin');
    $this->member(['name' => 'Exported Member']);

    $this->actingAs($admin)->post('/admin/backups')->assertSessionHasNoErrors();
    $file = collect(Storage::disk('local')->files('backups'))->first();
    expect($file)->toStartWith('backups/test-shg-');

    $zip = new ZipArchive;
    $zip->open(Storage::disk('local')->path($file));
    $members = $zip->getFromName('members.csv');
    $users = $zip->getFromName('users.csv');
    $zip->close();

    expect($members)->toContain('Exported Member')->and($users)->not->toContain('password');

    $this->actingAs($admin)->get('/admin/backups')->assertInertia(fn ($p) => $p->has('files', 1));
    $this->actingAs($admin)->get('/admin/backups/'.basename($file))->assertOk();
    $this->actingAs($admin)->get('/admin/backups/..%2F..%2F.env')->assertNotFound();
    $this->actingAs($this->official('treasurer'))->get('/admin/backups')->assertForbidden();
});

it('keeps documents and a compliance calendar that rolls forward', function () {
    Storage::fake('local');
    $secretary = $this->official('secretary');
    $member = $this->member();

    $this->actingAs($secretary)->post('/documents', [
        'title' => 'Constitution', 'category' => 'constitution', 'visibility' => 'members',
        'file' => UploadedFile::fake()->create('constitution.pdf', 120, 'application/pdf'),
    ])->assertSessionHasNoErrors();
    $this->actingAs($secretary)->post('/documents', [
        'title' => 'Bank letter', 'category' => 'financial', 'visibility' => 'officials',
        'file' => UploadedFile::fake()->create('letter.pdf', 20, 'application/pdf'),
    ])->assertSessionHasNoErrors();
    $this->actingAs($secretary)->post('/documents', [
        'title' => 'Virus', 'category' => 'other', 'visibility' => 'members',
        'file' => UploadedFile::fake()->create('run.exe', 20, 'application/octet-stream'),
    ])->assertSessionHasErrors('file');

    $constitution = Document::query()->where('title', 'Constitution')->firstOrFail();
    $letter = Document::query()->where('title', 'Bank letter')->firstOrFail();

    $this->actingAs($member->user)->get('/documents')->assertInertia(fn ($p) => $p->has('documents', 1));
    $this->actingAs($member->user)->get("/documents/{$constitution->id}/download")->assertOk();
    $this->actingAs($member->user)->get("/documents/{$letter->id}/download")->assertForbidden();

    $this->actingAs($secretary)->delete("/documents/{$letter->id}")->assertSessionHasNoErrors();
    Storage::disk('local')->assertMissing($letter->path);

    $initial = ComplianceItem::query()->count();
    $this->actingAs($secretary)->post('/compliance', ['title' => 'File annual returns', 'due_on' => '2026-12-31', 'recurrence' => 'yearly'])->assertSessionHasNoErrors();
    $item = ComplianceItem::query()->where('title', 'File annual returns')->firstOrFail();

    $this->actingAs($secretary)->post("/compliance/{$item->id}/done")->assertSessionHasNoErrors();
    expect($item->refresh()->status)->toBe('done')
        ->and(ComplianceItem::query()->where('title', 'File annual returns')->where('status', 'pending')->first()->due_on->toDateString())->toBe('2027-12-31')
        ->and(ComplianceItem::query()->count())->toBe($initial + 2);

    $this->actingAs($secretary)->put("/compliance/{$item->id}", ['title' => 'Annual returns', 'due_on' => '2026-12-31', 'recurrence' => 'none'])->assertSessionHasNoErrors();
    $this->actingAs($secretary)->delete("/compliance/{$item->id}")->assertSessionHasNoErrors();
    $this->actingAs($member->user)->post('/compliance', [])->assertForbidden();
});

it('plans and raises the December share-out', function () {
    $treasurer = $this->official('treasurer');
    $alice = $this->member();
    $bob = $this->member();
    $accounts = app(ChartOfAccounts::class);
    $ledger = app(Ledger::class);

    foreach ([[$alice, 300000], [$bob, 100000]] as [$m, $cents]) {
        $ledger->post('Savings', [
            ['account' => $accounts->get($this->group(), ChartOfAccounts::BANK), 'debit' => $cents, 'member_id' => $m->id],
            ['account' => $accounts->get($this->group(), ChartOfAccounts::SAVINGS), 'credit' => $cents, 'member_id' => $m->id],
        ]);
    }
    $ledger->post('Fines', [
        ['account' => $accounts->get($this->group(), ChartOfAccounts::BANK), 'debit' => 40000],
        ['account' => $accounts->get($this->group(), ChartOfAccounts::GROUP_FUND), 'credit' => 40000],
    ]);

    $this->actingAs($treasurer)->get('/share-out?percent=50')->assertOk()->assertInertia(fn ($p) => $p
        ->where('plan.distribute', 20000)
        ->where('plan.rows.1.from_group_fund', 15000)
        ->where('plan.rows.2.from_group_fund', 5000));

    $this->actingAs($treasurer)->post('/share-out', ['percent' => 50, 'label' => 'Share-out 2026'])->assertRedirect();
    expect(Payout::query()->where('type', 'share_out')->count())->toBe(4)
        ->and((int) Payout::query()->where('payee_member_id', $alice->id)->sum('amount'))->toBe(315000);

    $this->actingAs($treasurer)->post('/share-out', ['percent' => 0, 'label' => 'Again'])->assertSessionHasErrors('action');
});
