<?php

use App\Domain\Groups\CurrentGroup;
use App\Domain\Groups\Models\Group;
use App\Models\User;
use Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->seed(DemoSeeder::class);
    app(CurrentGroup::class)->set(Group::query()->firstOrFail());
});

it('shows a page without access inside the app instead of a bare error', function () {
    $member = User::query()->where('phone', '254712000008')->firstOrFail();

    $this->actingAs($member)->get('/admin/audit')
        ->assertForbidden()
        ->assertInertia(fn ($p) => $p->component('error')->where('status', 403)->where('message', null)->has('auth.user'));
});

it('shows a friendly not-found page', function () {
    $member = User::query()->where('phone', '254712000008')->firstOrFail();

    $this->actingAs($member)->get('/members/999999')
        ->assertNotFound()
        ->assertInertia(fn ($p) => $p->component('error')->where('status', 404));
});

it('keeps json errors as json', function () {
    $member = User::query()->where('phone', '254712000008')->firstOrFail();

    $this->actingAs($member)->getJson('/admin/audit')
        ->assertForbidden()
        ->assertJsonStructure(['message']);
});
