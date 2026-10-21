<?php

use App\Domain\Fundraisers\Models\Fundraiser;
use App\Domain\Groups\CurrentGroup;
use App\Domain\Groups\Models\Group;
use App\Domain\Ledger\Models\Account;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Members\Models\Member;
use App\Domain\Mgr\Models\MgrCycle;
use App\Domain\Payouts\Models\Payout;
use App\Domain\Projects\Models\Project;
use App\Domain\Welfare\Models\WelfareCase;
use App\Models\User;
use Database\Seeders\DemoSeeder;

/**
 * Loads the demo group and opens every screen as the person who would use
 * it. Each page must render its React component (Inertia checks the file
 * exists) with a 200.
 */
it('renders every page for the people who use it, and hides money from those who should not see it', function () {
    $this->seed(DemoSeeder::class);
    app(CurrentGroup::class)->set(Group::query()->firstOrFail());

    $user = fn (string $phone) => User::query()->where('phone', $phone)->firstOrFail();
    $chair = $user('254712000001');
    $secretary = $user('254712000002');
    $treasurer = $user('254712000003');
    $coordinator = $user('254712000004');
    $projects = $user('254712000005');
    $auditor = $user('254712000006');
    $admin = $user('254712000007');
    $member = $user('254712000008');

    $someone = Member::query()->where('member_number', 'M010')->firstOrFail();
    $fundraiser = Fundraiser::query()->firstOrFail();
    $project = Project::query()->firstOrFail();
    $cycle = MgrCycle::query()->firstOrFail();
    $meeting = Meeting::query()->firstOrFail();
    $account = Account::query()->where('code', '2000')->firstOrFail();

    // Data the demo does not create: a payout and a welfare case.
    $this->actingAs($treasurer)->post("/mgr/{$cycle->id}/release");
    $payout = Payout::query()->firstOrFail();
    $this->actingAs($secretary)->post('/welfare', ['member_id' => $someone->id, 'event' => 'hospitalisation', 'event_on' => now()->toDateString()]);
    $case = WelfareCase::query()->firstOrFail();

    $pages = [
        [$member, '/dashboard', 'dashboard'],
        [$treasurer, '/dashboard', 'dashboard'],
        [$secretary, '/members', 'members/index'],
        [$secretary, '/members/create', 'members/form'],
        [$secretary, "/members/{$someone->id}", 'members/show'],
        [$secretary, "/members/{$someone->id}/edit", 'members/form'],
        [$member, '/members/'.$member->member->id, 'members/show'],
        [$chair, '/positions', 'positions/index'],
        [$treasurer, '/dues', 'dues/index'],
        [$treasurer, '/payments', 'payments/index'],
        [$treasurer, '/payments?status=unmatched', 'payments/index'],
        [$treasurer, '/payments/import', 'payments/import'],
        [$treasurer, '/payouts', 'payouts/index'],
        [$chair, "/payouts/{$payout->id}", 'payouts/show'],
        [$treasurer, '/ledger', 'ledger/index'],
        [$auditor, "/ledger/accounts/{$account->id}", 'ledger/account'],
        [$treasurer, '/reports', 'reports/index'],
        [$auditor, '/reports/annual', 'reports/annual'],
        [$member, '/statements/'.$member->member->id, 'reports/statement'],
        [$treasurer, "/statements/{$someone->id}", 'reports/statement'],
        [$treasurer, '/share-out', 'share-out/index'],
        [$member, '/mgr', 'mgr/index'],
        [$member, "/mgr/{$cycle->id}", 'mgr/show'],
        [$member, '/fundraisers', 'fundraisers/index'],
        [$coordinator, '/fundraisers/create', 'fundraisers/form'],
        [$coordinator, "/fundraisers/{$fundraiser->id}", 'fundraisers/show'],
        [$coordinator, "/fundraisers/{$fundraiser->id}/edit", 'fundraisers/form'],
        [$secretary, '/welfare', 'welfare/index'],
        [$secretary, "/welfare/{$case->id}", 'welfare/show'],
        [$projects, '/projects', 'projects/index'],
        [$projects, "/projects/{$project->id}", 'projects/show'],
        [$member, '/assets', 'assets/index'],
        [$member, '/meetings', 'meetings/index'],
        [$member, "/meetings/{$meeting->id}", 'meetings/show'],
        [$member, '/documents', 'documents/index'],
        [$secretary, '/messages', 'messages/index'],
        [$secretary, '/messages/templates', 'messages/templates'],
        [$chair, '/rules', 'rules/edit'],
        [$admin, '/admin/integrations', 'admin/integrations'],
        [$auditor, '/admin/audit', 'admin/audit'],
        [$admin, '/admin/backups', 'admin/backups'],
        [$member, '/settings/profile', 'settings/profile'],
        [$member, '/settings/appearance', 'settings/appearance'],
    ];

    foreach ($pages as [$who, $url, $component]) {
        $this->actingAs($who)->get($url)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component($component));
    }

    // Downloads
    $this->actingAs($member)->get('/statements/'.$member->member->id.'?format=pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->actingAs($member)->get('/statements/'.$member->member->id.'?format=csv')->assertOk();
    $this->actingAs($treasurer)->get('/reports/arrears.csv')->assertOk();
    $this->actingAs($treasurer)->get("/fundraisers/{$fundraiser->id}/export")->assertOk();

    // Public
    auth()->logout();
    $this->get('/')->assertOk()->assertInertia(fn ($p) => $p->component('welcome')->has('fundraisers', 1));
    $this->get("/f/{$fundraiser->slug}")->assertOk()->assertInertia(fn ($p) => $p->component('public/fundraiser'));
    $this->get('/login')->assertOk();

    // An ordinary member sees their own things only.
    foreach (['/members', '/dues', '/payments', '/payouts', '/ledger', '/reports', '/share-out', '/positions', '/welfare', '/projects', '/messages', '/rules', '/admin/integrations', '/admin/audit', '/admin/backups', "/statements/{$someone->id}", "/members/{$someone->id}"] as $url) {
        $this->actingAs($member)->get($url)->assertForbidden();
    }

    // The system administrator never sees money.
    foreach (['/dues', '/payments', '/payouts', '/ledger', '/reports', '/share-out', "/statements/{$someone->id}"] as $url) {
        $this->actingAs($admin)->get($url)->assertForbidden();
    }

    $this->actingAs($admin)->get("/members/{$someone->id}")->assertOk()->assertInertia(fn ($p) => $p->where('finance', null));
});
