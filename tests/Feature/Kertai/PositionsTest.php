<?php

use App\Domain\Access\Models\Position;
use App\Domain\Access\Models\PositionTerm;

it('creates a vice chair by copying the chair and lets the chair adjust it', function () {
    $chair = $this->official('chairperson');
    $source = Position::query()->where('slug', 'chairperson')->firstOrFail();

    $this->actingAs($chair)->post('/positions', ['name' => 'Vice chairperson', 'copy_from' => $source->id, 'max_holders' => 1])->assertSessionHasNoErrors();
    $vice = Position::query()->where('slug', 'vice-chairperson')->firstOrFail();

    expect($vice->permissions)->toBe($source->permissions)->and($vice->is_system)->toBeFalse();

    $this->actingAs($chair)->put("/positions/{$vice->id}", [
        'name' => 'Vice chairperson',
        'permissions' => ['members.view', 'meetings.manage'],
    ])->assertSessionHasNoErrors();

    expect($vice->refresh()->permissions)->toBe(['members.view', 'meetings.manage']);

    $this->actingAs($chair)->post('/positions', ['name' => 'Vice chairperson', 'copy_from' => $source->id])->assertSessionHasErrors('action');
});

it('never gives the system administrator money permissions', function () {
    $admin = $this->official('system-admin');
    $position = Position::query()->where('slug', 'system-admin')->firstOrFail();

    $this->actingAs($admin)->put("/positions/{$position->id}", [
        'name' => 'System administrator',
        'permissions' => [...$position->permissions, 'payouts.approve'],
    ])->assertSessionHasErrors('action');

    $this->actingAs($admin)->get('/payments')->assertForbidden();
    $this->actingAs($admin)->get('/ledger')->assertForbidden();
    $this->actingAs($admin)->get('/admin/integrations')->assertOk();
    $this->actingAs($admin)->get('/members')->assertOk()->assertInertia(fn ($p) => $p->where('showMoney', false));
});

it('limits holders, allows acting appointments that lapse on their own', function () {
    $chair = $this->official('chairperson');
    $deputy = $this->member();
    $position = Position::query()->where('slug', 'chairperson')->firstOrFail();

    $this->actingAs($chair)->post("/positions/{$position->id}/assign", ['member_id' => $deputy->id, 'starts_on' => now()->toDateString()])
        ->assertSessionHasErrors('action');

    $this->actingAs($chair)->post("/positions/{$position->id}/assign", ['member_id' => $deputy->id, 'starts_on' => now()->toDateString(), 'is_acting' => true])
        ->assertSessionHasErrors('action');

    $this->actingAs($chair)->post("/positions/{$position->id}/assign", [
        'member_id' => $deputy->id,
        'starts_on' => now()->toDateString(),
        'ends_on' => now()->addDays(10)->toDateString(),
        'is_acting' => true,
        'resolution_reference' => '2026/07',
    ])->assertSessionHasNoErrors();

    expect($deputy->refresh()->hasPermission('payouts.approve'))->toBeTrue();

    $this->travel(11)->days();
    expect($deputy->refresh()->hasPermission('payouts.approve'))->toBeFalse();
});

it('ends a term and refuses to delete positions with history', function () {
    $chair = $this->official('chairperson');
    $treasurer = $this->official('treasurer');
    $term = PositionTerm::query()->where('member_id', $treasurer->member->id)->firstOrFail();

    $this->actingAs($chair)->put("/terms/{$term->id}/end", ['ends_on' => now()->subDay()->toDateString(), 'ended_reason' => 'Resigned'])->assertSessionHasNoErrors();
    expect($treasurer->refresh()->member->hasPermission('payouts.pay'))->toBeFalse();

    $system = Position::query()->where('slug', 'treasurer')->firstOrFail();
    $this->actingAs($chair)->delete("/positions/{$system->id}")->assertSessionHasErrors('action');

    $this->actingAs($chair)->post('/positions', ['name' => 'Organising secretary', 'copy_from' => $system->id]);
    $fresh = Position::query()->where('slug', 'organising-secretary')->firstOrFail();
    $this->actingAs($chair)->delete("/positions/{$fresh->id}")->assertSessionHasNoErrors();
    expect(Position::query()->where('slug', 'organising-secretary')->exists())->toBeFalse();
});

it('stops the only position manager ending their own term', function () {
    $chair = $this->official('chairperson');
    $term = PositionTerm::query()->where('member_id', $chair->member->id)->firstOrFail();

    $this->actingAs($chair)->put("/terms/{$term->id}/end", ['ends_on' => now()->toDateString()])->assertSessionHasErrors('action');
});

it('shows the positions page only to those who manage positions', function () {
    $this->actingAs($this->official('chairperson'))->get('/positions')->assertOk()->assertInertia(fn ($p) => $p->has('positions', 7)->has('permissions'));
    $this->actingAs($this->official('treasurer'))->get('/positions')->assertForbidden();
});
