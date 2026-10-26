<?php

use App\Domain\Access\Permission as P;
use App\Domain\Fundraisers\Models\Fundraiser;
use App\Domain\Groups\Models\Group;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\GroupSettingsController;
use App\Http\Controllers\Admin\IntegrationController;
use App\Http\Controllers\Admin\MessageController;
use App\Http\Controllers\Auth\FirstPasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Finance\DueController;
use App\Http\Controllers\Finance\LedgerController;
use App\Http\Controllers\Finance\PaymentController;
use App\Http\Controllers\Finance\PayoutController;
use App\Http\Controllers\Finance\ReportController;
use App\Http\Controllers\Finance\ShareOutController;
use App\Http\Controllers\Finance\StatementImportController;
use App\Http\Controllers\Members\MemberController;
use App\Http\Controllers\Members\MemberLifecycleController;
use App\Http\Controllers\Members\PositionController;
use App\Http\Controllers\Programmes\AssetController;
use App\Http\Controllers\Programmes\FundraiserController;
use App\Http\Controllers\Programmes\MeetingController;
use App\Http\Controllers\Programmes\MgrController;
use App\Http\Controllers\Programmes\ProjectController;
use App\Http\Controllers\Programmes\WelfareController;
use App\Http\Controllers\PublicFundraiserController;
use App\Http\Controllers\Webhooks\InboundSmsController;
use App\Http\Controllers\Webhooks\SimulinkPayWebhookController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public
Route::get('/', function () {
    $group = Group::query()->first();

    return Inertia::render('welcome', [
        'group' => $group ? ['name' => $group->name, 'location' => $group->location, 'paybill' => $group->paybill, 'paybill_account' => $group->paybill_account] : null,
        'fundraisers' => $group ? Fundraiser::withoutGlobalScopes()->where('group_id', $group->id)->where('status', 'active')->latest()->limit(3)->get()
            ->map(fn ($f) => ['slug' => $f->slug, 'title' => $f->title, 'summary' => $f->summary, 'target' => $f->target, 'raised' => $f->raised()]) : [],
    ]);
})->name('home');

Route::get('f/{slug}', [PublicFundraiserController::class, 'show'])->name('public.fundraiser');
Route::post('f/{slug}/pay', [PublicFundraiserController::class, 'pay'])->middleware('throttle:stk')->name('public.fundraiser.pay');
Route::get('f/{slug}/payments/{key}', [PublicFundraiserController::class, 'status'])->middleware('throttle:60,1')->name('public.fundraiser.status');

Route::post('webhooks/simulinkpay', SimulinkPayWebhookController::class)->middleware('throttle:120,1')->name('webhooks.simulinkpay');
Route::post('webhooks/sms/{token}', InboundSmsController::class)->middleware('throttle:120,1')->name('webhooks.sms');

Route::middleware('auth')->group(function () {
    Route::get('welcome/password', [FirstPasswordController::class, 'show'])->name('password.first');
    Route::put('welcome/password', [FirstPasswordController::class, 'update'])->name('password.first.update');
});

Route::middleware(['auth', 'member'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Every member
    Route::post('me/pay', [PaymentController::class, 'stk'])->middleware('throttle:stk')->name('me.pay');
    Route::put('me/preferences', [MemberController::class, 'preferences'])->name('me.preferences');
    Route::get('statements/{member}', [ReportController::class, 'statement'])->name('statements.show');
    Route::get('group-finances', [ReportController::class, 'groupBooks'])->name('group.finances');
    Route::post('payments/{payment}/check', [PaymentController::class, 'check'])->name('payments.check');
    Route::get('mgr', [MgrController::class, 'index'])->name('mgr.index');
    Route::get('mgr/{cycle}', [MgrController::class, 'show'])->name('mgr.show')->whereNumber('cycle');
    Route::post('mgr/{cycle}/swaps', [MgrController::class, 'requestSwap'])->name('mgr.swaps.store');
    Route::post('mgr/swaps/{swap}', [MgrController::class, 'respondSwap'])->name('mgr.swaps.respond');
    Route::get('fundraisers', [FundraiserController::class, 'index'])->name('fundraisers.index');
    Route::get('meetings', [MeetingController::class, 'index'])->name('meetings.index');
    Route::get('meetings/{meeting}', [MeetingController::class, 'show'])->name('meetings.show');
    Route::get('meetings/{meeting}/calendar.ics', [MeetingController::class, 'calendar'])->name('meetings.calendar');
    Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('assets', [AssetController::class, 'index'])->name('assets.index');

    // Member register
    Route::middleware('can:'.P::MembersManage->value)->group(function () {
        Route::get('members/create', [MemberController::class, 'create'])->name('members.create');
        Route::post('members', [MemberController::class, 'store'])->name('members.store');
        Route::get('members/{member}/edit', [MemberController::class, 'edit'])->name('members.edit');
        Route::put('members/{member}', [MemberController::class, 'update'])->name('members.update');
        Route::post('members/{member}/login', [MemberLifecycleController::class, 'issueLogin'])->name('members.login');
        Route::post('members/{member}/dependants', [MemberController::class, 'storeDependant'])->name('members.dependants.store');
        Route::delete('members/{member}/dependants/{dependant}', [MemberController::class, 'destroyDependant'])->name('members.dependants.destroy');
        Route::post('members/{member}/notice', [MemberLifecycleController::class, 'notice'])->name('members.notice');
        Route::post('members/{member}/notice/withdraw', [MemberLifecycleController::class, 'withdraw'])->name('members.notice.withdraw');
        Route::post('members/{member}/death', [MemberLifecycleController::class, 'death'])->name('members.death');
    });

    Route::get('members', [MemberController::class, 'index'])->middleware('can:'.P::MembersView->value)->name('members.index');
    Route::get('members/{member}', [MemberController::class, 'show'])->name('members.show')->whereNumber('member');
    Route::post('members/{member}/exit', [MemberLifecycleController::class, 'process'])->middleware('can:'.P::PayoutsRequest->value)->name('members.exit');

    Route::middleware('can:'.P::PositionsManage->value)->group(function () {
        Route::get('positions', [PositionController::class, 'index'])->name('positions.index');
        Route::post('positions', [PositionController::class, 'store'])->name('positions.store');
        Route::put('positions/{position}', [PositionController::class, 'update'])->name('positions.update');
        Route::delete('positions/{position}', [PositionController::class, 'destroy'])->name('positions.destroy');
        Route::post('positions/{position}/assign', [PositionController::class, 'assign'])->name('positions.assign');
        Route::put('terms/{term}/end', [PositionController::class, 'endTerm'])->name('terms.end');
    });

    // Money: read
    Route::middleware('can:'.P::FinanceView->value)->group(function () {
        Route::get('dues', [DueController::class, 'index'])->name('dues.index');
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('payouts', [PayoutController::class, 'index'])->name('payouts.index');
        Route::get('payouts/{payout}', [PayoutController::class, 'show'])->name('payouts.show')->whereNumber('payout');
        Route::get('ledger', [LedgerController::class, 'index'])->name('ledger.index');
        Route::get('ledger/accounts/{account}', [LedgerController::class, 'account'])->name('ledger.account');
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/annual', [ReportController::class, 'annual'])->name('reports.annual');
        Route::get('reports/arrears.csv', [ReportController::class, 'arrearsCsv'])->name('reports.arrears');
        Route::get('fundraisers/{fundraiser}/export', [FundraiserController::class, 'export'])->name('fundraisers.export');
        Route::get('share-out', [ShareOutController::class, 'index'])->name('share-out.index');
    });

    // Money: write
    Route::middleware('can:'.P::DuesManage->value)->group(function () {
        Route::post('dues/generate', [DueController::class, 'generate'])->name('dues.generate');
        Route::post('dues/fines/apply', [DueController::class, 'applyFines'])->name('dues.fines.apply');
        Route::post('dues/fines', [DueController::class, 'storeFine'])->name('dues.fines.store');
    });
    Route::post('dues/{due}/waive', [DueController::class, 'waive'])->name('dues.waive');

    Route::middleware('can:'.P::PaymentsRecord->value)->group(function () {
        Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::post('payments/stk', [PaymentController::class, 'stk'])->middleware('throttle:stk')->name('payments.stk');
        Route::post('payments/reconcile', [PaymentController::class, 'reconcile'])->name('payments.reconcile');
        Route::post('payments/{payment}/assign', [PaymentController::class, 'assign'])->name('payments.assign');
        Route::get('payments/import', [StatementImportController::class, 'index'])->name('payments.import');
        Route::post('payments/import', [StatementImportController::class, 'store'])->name('payments.import.store');
        Route::post('meetings/{meeting}/collect', [MeetingController::class, 'collect'])->name('meetings.collect');
    });

    Route::middleware('can:'.P::LedgerManage->value)->group(function () {
        Route::post('ledger/transfers', [LedgerController::class, 'transfer'])->name('ledger.transfer');
        Route::post('ledger/opening', [LedgerController::class, 'opening'])->name('ledger.opening');
        Route::post('ledger/entries/{entry}/reverse', [LedgerController::class, 'reverse'])->name('ledger.reverse');
        Route::post('payments/{payment}/reverse', [LedgerController::class, 'reversePayment'])->name('payments.reverse');
    });

    Route::post('payouts', [PayoutController::class, 'store'])->middleware('can:'.P::PayoutsRequest->value)->name('payouts.store');
    Route::post('payouts/approve-many', [PayoutController::class, 'bulkApprove'])->middleware('can:'.P::PayoutsApprove->value)->name('payouts.bulk-approve');
    Route::post('payouts/{payout}/decide', [PayoutController::class, 'decide'])->middleware('can:'.P::PayoutsApprove->value)->name('payouts.decide');
    Route::post('payouts/{payout}/pay', [PayoutController::class, 'pay'])->middleware('can:'.P::PayoutsPay->value)->name('payouts.pay');
    Route::post('payouts/{payout}/cancel', [PayoutController::class, 'cancel'])->name('payouts.cancel');
    Route::post('share-out', [ShareOutController::class, 'store'])->middleware('can:'.P::PayoutsRequest->value)->name('share-out.store');

    // Merry-go-round
    Route::post('mgr', [MgrController::class, 'store'])->name('mgr.store');
    Route::post('mgr/{cycle}/ballot', [MgrController::class, 'ballot'])->name('mgr.ballot');
    Route::post('mgr/{cycle}/release', [MgrController::class, 'release'])->name('mgr.release');

    // Fundraisers
    Route::middleware('can:'.P::FundraisersManage->value)->group(function () {
        Route::get('fundraisers/create', [FundraiserController::class, 'create'])->name('fundraisers.create');
        Route::post('fundraisers', [FundraiserController::class, 'store'])->name('fundraisers.store');
        Route::get('fundraisers/{fundraiser}/edit', [FundraiserController::class, 'edit'])->name('fundraisers.edit');
        Route::put('fundraisers/{fundraiser}', [FundraiserController::class, 'update'])->name('fundraisers.update');
        Route::post('fundraisers/{fundraiser}/status', [FundraiserController::class, 'status'])->name('fundraisers.status');
        Route::post('fundraisers/{fundraiser}/pledges', [FundraiserController::class, 'pledge'])->name('fundraisers.pledges.store');
        Route::delete('fundraisers/{fundraiser}/pledges/{pledge}', [FundraiserController::class, 'destroyPledge'])->name('fundraisers.pledges.destroy');
    });
    Route::get('fundraisers/{fundraiser}', [FundraiserController::class, 'show'])->name('fundraisers.show')->whereNumber('fundraiser');

    // Welfare
    Route::middleware('can:'.P::WelfareManage->value)->group(function () {
        Route::get('welfare', [WelfareController::class, 'index'])->name('welfare.index');
        Route::post('welfare', [WelfareController::class, 'store'])->name('welfare.store');
        Route::get('welfare/{case}', [WelfareController::class, 'show'])->name('welfare.show');
        Route::post('welfare/{case}/decide', [WelfareController::class, 'decide'])->name('welfare.decide');
        Route::post('welfare/{case}/levy', [WelfareController::class, 'levy'])->name('welfare.levy');
        Route::post('welfare/{case}/payout', [WelfareController::class, 'payout'])->name('welfare.payout');
    });

    // Projects
    Route::middleware('can:'.P::ProjectsView->value)->group(function () {
        Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    });
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::put('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::post('projects/{project}/participants', [ProjectController::class, 'addParticipant'])->name('projects.participants.store');
    Route::delete('projects/{project}/participants/{participant}', [ProjectController::class, 'removeParticipant'])->name('projects.participants.destroy');
    Route::post('projects/{project}/money', [ProjectController::class, 'money'])->name('projects.money');
    Route::post('projects/{project}/expenses', [ProjectController::class, 'expense'])->name('projects.expenses');
    Route::post('projects/{project}/items', [ProjectController::class, 'storeItem'])->name('projects.items.store');
    Route::put('projects/{project}/items/{item}', [ProjectController::class, 'updateItem'])->name('projects.items.update');
    Route::post('projects/{project}/items/{item}/stock', [ProjectController::class, 'stock'])->name('projects.items.stock');
    Route::post('projects/{project}/sales', [ProjectController::class, 'sell'])->name('projects.sales.store');
    Route::post('projects/{project}/sales/{sale}/payments', [ProjectController::class, 'salePayment'])->name('projects.sales.pay');
    Route::post('projects/{project}/distribute', [ProjectController::class, 'distribute'])->name('projects.distribute');

    // Assets and hire
    Route::post('assets', [AssetController::class, 'store'])->name('assets.store');
    Route::put('assets/{asset}', [AssetController::class, 'update'])->name('assets.update');
    Route::post('hires', [AssetController::class, 'book'])->name('hires.store');
    Route::post('hires/{hire}/status', [AssetController::class, 'status'])->name('hires.status');
    Route::post('hires/{hire}/payments', [AssetController::class, 'pay'])->name('hires.pay');

    // Meetings
    Route::post('meetings', [MeetingController::class, 'store'])->name('meetings.store');
    Route::put('meetings/{meeting}', [MeetingController::class, 'update'])->name('meetings.update');
    Route::post('meetings/{meeting}/attendance', [MeetingController::class, 'attendance'])->name('meetings.attendance');
    Route::post('meetings/{meeting}/notify', [MeetingController::class, 'notify'])->name('meetings.notify');
    Route::post('meetings/{meeting}/resolutions', [MeetingController::class, 'resolution'])->name('meetings.resolutions.store');

    // Documents and compliance
    Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    Route::post('compliance', [DocumentController::class, 'storeCompliance'])->name('compliance.store');
    Route::put('compliance/{item}', [DocumentController::class, 'updateCompliance'])->name('compliance.update');
    Route::post('compliance/{item}/done', [DocumentController::class, 'completeCompliance'])->name('compliance.done');
    Route::delete('compliance/{item}', [DocumentController::class, 'destroyCompliance'])->name('compliance.destroy');

    // Messages
    Route::middleware('can:'.P::MessagesSend->value)->group(function () {
        Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
        Route::post('messages', [MessageController::class, 'send'])->name('messages.send');
        Route::post('messages/retry-failed', [MessageController::class, 'retryFailed'])->name('messages.retry-failed');
        Route::post('messages/{message}/retry', [MessageController::class, 'retry'])->name('messages.retry');
        Route::get('messages/templates', [MessageController::class, 'templates'])->name('messages.templates');
        Route::put('messages/templates', [MessageController::class, 'updateTemplate'])->name('messages.templates.update');
    });

    // Administration
    Route::middleware('can:'.P::SettingsManage->value)->group(function () {
        Route::get('rules', [GroupSettingsController::class, 'edit'])->name('rules.edit');
        Route::put('rules', [GroupSettingsController::class, 'update'])->name('rules.update');
    });

    Route::middleware('can:'.P::IntegrationsManage->value)->group(function () {
        Route::get('admin/integrations', [IntegrationController::class, 'index'])->name('integrations.index');
        Route::put('admin/integrations/{kind}', [IntegrationController::class, 'update'])->name('integrations.update');
        Route::post('admin/integrations/{kind}/test', [IntegrationController::class, 'test'])->name('integrations.test');
    });

    Route::get('admin/audit', [AuditController::class, 'index'])->middleware('can:'.P::AuditView->value)->name('audit.index');

    Route::middleware('can:'.P::BackupsManage->value)->group(function () {
        Route::get('admin/backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('admin/backups', [BackupController::class, 'store'])->name('backups.store');
        Route::get('admin/backups/{name}', [BackupController::class, 'download'])->name('backups.download');
    });
});

require __DIR__.'/settings.php';
