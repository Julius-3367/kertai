<?php

use App\Domain\Documents\Models\ComplianceItem;
use App\Domain\Dues\Models\Due;
use App\Domain\Groups\Models\Group;
use App\Domain\Members\Models\Member;
use App\Domain\Notifications\Models\OutboundMessage;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

it('installs a group with its first official', function () {
    $this->artisan('kertai:install', [
        '--group' => 'KERTAI006 Self Help Group',
        '--name' => 'Joseph Kiprotich',
        '--phone' => '0712000001',
        '--registration' => 'SHG/123',
    ])->expectsOutputToContain('One-time password')->assertSuccessful();

    $group = Group::query()->firstOrFail();
    $member = Member::withoutGlobalScopes()->firstOrFail();
    $user = User::query()->firstOrFail();

    expect($group->paybill)->toBe('247247')
        ->and($group->paybill_account)->toBe('0720653394')
        ->and($group->registration_number)->toBe('SHG/123')
        ->and($member->user_id)->toBe($user->id)
        ->and($user->must_change_password)->toBeTrue()
        ->and($member->permissions())->toContain('payouts.approve')
        ->and($member->permissions())->toContain('integrations.manage');

    $this->artisan('kertai:install', ['--group' => 'KERTAI006 Self Help Group'])->assertSuccessful();
    expect(Group::query()->count())->toBe(1)->and(Member::withoutGlobalScopes()->count())->toBe(1);
});

it('refuses a bad phone number on install', function () {
    $this->artisan('kertai:install', ['--group' => 'G', '--name' => 'X', '--phone' => '123'])->assertFailed();
});

it('runs the daily round: dues on schedule, reminders, fines and compliance nudges', function () {
    $group = $this->group(['late_fine_grace_days' => 0]);
    $treasurer = $this->official('treasurer');
    $member = $this->member();
    ComplianceItem::query()->update(['due_on' => '2026-11-10']);

    // Reminder day: two days before the 5th.
    $this->artisan('kertai:daily', ['--date' => '2026-11-03'])->assertSuccessful();

    expect(Due::query()->where('period', '2026-11')->count())->toBe(2)
        ->and(OutboundMessage::query()->where('template_key', 'contribution_reminder')->count())->toBe(4)
        ->and(OutboundMessage::query()->where('template_key', 'compliance_due')->count())->toBeGreaterThan(0);

    // A Monday after the due day: fines and an arrears nudge.
    $this->artisan('kertai:daily', ['--date' => '2026-11-09'])->assertSuccessful();

    expect(Due::query()->where('type', 'fine')->count())->toBe(2)
        ->and(OutboundMessage::query()->where('template_key', 'arrears_reminder')->count())->toBe(4)
        ->and(Due::query()->where('period', '2026-11')->where('type', 'contribution')->count())->toBe(2);
});

it('reconciles and backs up from the scheduler', function () {
    Storage::fake('local');
    $this->group();

    $this->artisan('kertai:reconcile')->expectsOutputToContain('Checked 0')->assertSuccessful();
    $this->artisan('kertai:backup')->assertSuccessful();

    expect(Storage::disk('local')->files('backups'))->toHaveCount(1);
});
