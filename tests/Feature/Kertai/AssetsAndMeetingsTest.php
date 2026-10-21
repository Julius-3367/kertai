<?php

use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Models\Hire;
use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Ledger\Ledger;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Meetings\Models\MeetingAttendance;
use App\Domain\Notifications\Models\OutboundMessage;
use App\Domain\Payments\Models\Payment;

it('books tents without double-booking and puts hire money in the assets fund', function () {
    $treasurer = $this->official('treasurer');

    $this->actingAs($treasurer)->post('/assets', ['name' => 'Tents', 'category' => 'tents', 'quantity' => 2, 'condition' => 'good'])->assertSessionHasNoErrors();
    $tents = Asset::query()->firstOrFail();
    expect($tents->hire_rate)->toBeNull();

    $booking = ['asset_id' => $tents->id, 'hirer_name' => 'Wedding committee', 'hirer_phone' => '0700222333', 'quantity' => 2, 'starts_on' => '2026-12-01', 'ends_on' => '2026-12-02'];

    $this->actingAs($treasurer)->post('/hires', $booking)->assertSessionHasErrors('action');
    $this->actingAs($treasurer)->post('/hires', [...$booking, 'rate' => '1500'])->assertSessionHasNoErrors();
    $this->actingAs($treasurer)->post('/hires', [...$booking, 'quantity' => 1, 'starts_on' => '2026-12-02', 'ends_on' => '2026-12-03', 'rate' => '1500'])->assertSessionHasErrors('action');

    $hire = Hire::query()->firstOrFail();
    expect($hire->total)->toBe(2 * 2 * 150000);

    $this->actingAs($treasurer)->put("/assets/{$tents->id}", ['name' => 'Tents (10x20)', 'category' => 'tents', 'quantity' => 3, 'hire_rate' => '2000', 'condition' => 'good'])->assertSessionHasNoErrors();
    $this->actingAs($treasurer)->post('/hires', [...$booking, 'quantity' => 1, 'starts_on' => '2026-12-02', 'ends_on' => '2026-12-02'])->assertSessionHasNoErrors();
    expect(Hire::query()->latest('id')->first()->rate)->toBe(200000);

    $this->actingAs($treasurer)->post("/hires/{$hire->id}/payments", ['amount' => '6000', 'channel' => 'cash'])->assertSessionHasNoErrors();
    expect($hire->refresh()->paid)->toBe(600000)
        ->and(app(Ledger::class)->balance(app(ChartOfAccounts::class)->get($this->group(), ChartOfAccounts::ASSETS)))->toBe(600000);

    $this->actingAs($treasurer)->post("/hires/{$hire->id}/status", ['status' => 'returned'])->assertSessionHasErrors('action');
    $this->actingAs($treasurer)->post("/hires/{$hire->id}/status", ['status' => 'cancelled'])->assertSessionHasErrors('action');
    $this->actingAs($treasurer)->post("/hires/{$hire->id}/status", ['status' => 'out'])->assertSessionHasNoErrors();
    $this->actingAs($treasurer)->post("/hires/{$hire->id}/status", ['status' => 'returned'])->assertSessionHasNoErrors();
    expect($hire->refresh()->status)->toBe('returned');

    $member = $this->member();
    $this->actingAs($member->user)->get('/assets')->assertOk()->assertInertia(fn ($p) => $p->where('canManage', false)->has('assets', 1));
    $this->actingAs($member->user)->post('/assets', [])->assertForbidden();
});

it('schedules a meeting, sends the notice, takes the roll and records minutes and resolutions', function () {
    $secretary = $this->official('secretary');
    $members = [$this->member(), $this->member()];

    $this->actingAs($secretary)->post('/meetings', [
        'title' => 'Monthly meeting',
        'kind' => 'ordinary',
        'scheduled_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
        'venue' => 'Kertai primary',
        'agenda' => '1. Prayer',
    ])->assertRedirect();
    $meeting = Meeting::query()->firstOrFail();

    $this->actingAs($secretary)->post("/meetings/{$meeting->id}/notify")->assertSessionHasNoErrors();
    expect(OutboundMessage::query()->where('template_key', 'meeting_notice')->count())->toBe(6);

    $this->actingAs($secretary)->post("/meetings/{$meeting->id}/attendance", ['attendance' => [
        $members[0]->id => 'present',
        $members[1]->id => 'apology',
        $secretary->member->id => 'present',
    ]])->assertSessionHasNoErrors();
    expect(MeetingAttendance::query()->count())->toBe(3);

    $this->actingAs($secretary)->post("/meetings/{$meeting->id}/attendance", ['attendance' => [$members[1]->id => '']]);
    expect(MeetingAttendance::query()->count())->toBe(2);

    $this->actingAs($secretary)->put("/meetings/{$meeting->id}", [
        'title' => 'Monthly meeting',
        'kind' => 'ordinary',
        'scheduled_at' => $meeting->scheduled_at->format('Y-m-d\TH:i'),
        'minutes' => 'Members agreed to raise the contribution.',
        'status' => 'held',
    ])->assertSessionHasNoErrors();
    expect($meeting->refresh()->status)->toBe('held');

    $this->actingAs($secretary)->post("/meetings/{$meeting->id}/resolutions", ['number' => '2026/01', 'title' => 'Contribution raised', 'adopted_on' => now()->toDateString()])->assertSessionHasNoErrors();
    $this->actingAs($secretary)->post("/meetings/{$meeting->id}/resolutions", ['number' => '2026/01', 'title' => 'Duplicate', 'adopted_on' => now()->toDateString()])->assertSessionHasErrors('number');

    $this->actingAs($members[0]->user)->get("/meetings/{$meeting->id}")->assertOk()->assertInertia(fn ($p) => $p->where('canManage', false)->has('resolutions', 1)->where('collections', []));
    $this->actingAs($members[0]->user)->post("/meetings/{$meeting->id}/notify")->assertForbidden();
});

it('records cash collected at a meeting as contributions', function () {
    $treasurer = $this->official('treasurer');
    $members = [$this->member(), $this->member()];
    $meeting = Meeting::query()->create(['group_id' => $this->group()->id, 'title' => 'Meeting', 'kind' => 'ordinary', 'scheduled_at' => now()->subHour()]);

    $this->actingAs($treasurer)->post("/meetings/{$meeting->id}/collect", ['rows' => [
        ['member_id' => $members[0]->id, 'amount' => '1000'],
        ['member_id' => $members[1]->id, 'amount' => '500'],
    ]])->assertSessionHasNoErrors();

    expect((int) Payment::query()->where('meeting_id', $meeting->id)->sum('amount'))->toBe(150000)
        ->and(app(Ledger::class)->balance(app(ChartOfAccounts::class)->get($this->group(), ChartOfAccounts::CASH)))->toBe(150000);

    $this->actingAs($treasurer)->get("/meetings/{$meeting->id}")->assertInertia(fn ($p) => $p->has('collections', 2));
});
