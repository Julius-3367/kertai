<?php

namespace Database\Seeders;

use App\Domain\Access\Models\Position;
use App\Domain\Assets\Models\Asset;
use App\Domain\Dues\Actions\GenerateMonthlyDues;
use App\Domain\Fundraisers\Models\Fundraiser;
use App\Domain\Groups\Actions\InstallGroup;
use App\Domain\Groups\CurrentGroup;
use App\Domain\Groups\Models\Group;
use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Ledger\Ledger;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Members\Actions\SaveMember;
use App\Domain\Members\Models\Member;
use App\Domain\Mgr\MerryGoRound;
use App\Domain\Mgr\Models\MgrCycle;
use App\Domain\Payments\Actions\RecordPayment;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectItem;
use App\Domain\Projects\ProjectBook;
use App\Models\User;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Made-up members and activity so every screen has something on it during
 * development and demos. Never run this on the live server: use
 * `php artisan kertai:install` there and enter the real register.
 *
 * Every demo login uses the password "password".
 */
class DemoSeeder extends Seeder
{
    private const MEMBERS = [
        ['Joseph Kiprotich', 'chairperson'],
        ['Mary Chepkemoi', 'secretary'],
        ['Daniel Kipkorir', 'treasurer'],
        ['Grace Chebet', 'coordinator'],
        ['Wilson Langat', 'project-coordinator'],
        ['Esther Cherono', 'auditor'],
        ['Paul Kipngetich', 'system-admin'],
        ['Ruth Jepkoech', null],
        ['Samuel Rotich', null],
        ['Nancy Chelangat', null],
        ['David Koech', null],
        ['Lilian Chepngeno', null],
        ['Peter Kirui', null],
        ['Agnes Cheruiyot', null],
        ['Richard Mutai', null],
        ['Sarah Chemutai', null],
    ];

    public function run(): void
    {
        $group = app(InstallGroup::class)->handle('KERTAI006 Self Help Group', [
            'slug' => 'kertai006',
            'registration_number' => 'DEMO/SHG/0006',
            'location' => 'Kericho',
            'paybill' => '247247',
            'paybill_account' => '0720653394',
        ]);

        app(CurrentGroup::class)->set($group);

        if ($group->members()->exists()) {
            $this->command?->info('Demo data already present.');

            return;
        }

        $members = $this->members($group);
        $this->money($group, $members);
        $this->programmes($group, $members);

        $this->command?->info('Demo group ready. Sign in as 0712000001 (chair), 0712000003 (treasurer), 0712000008 (member); password: password');
    }

    /**
     * @return list<Member>
     */
    private function members(Group $group): array
    {
        $save = app(SaveMember::class);
        $members = [];

        foreach (self::MEMBERS as $i => [$name, $position]) {
            $phone = '07120000'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);

            $member = $save->handle($group, [
                'name' => $name,
                'phone' => $phone,
                'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
                'joined_on' => '2025-01-15',
                'gender' => str_ends_with(explode(' ', $name)[1], 'i') || in_array(explode(' ', $name)[0], ['Mary', 'Grace', 'Esther', 'Ruth', 'Nancy', 'Lilian', 'Agnes', 'Sarah'], true) ? 'female' : 'male',
                'nominee_name' => 'Nominee of '.explode(' ', $name)[0],
                'nominee_relationship' => 'spouse',
                'locale' => $i % 3 === 0 ? 'sw' : 'en',
            ]);

            $user = User::create([
                'group_id' => $group->id,
                'name' => $name,
                'phone' => Phone::normalize($phone),
                'email' => $member->email,
                'password' => 'password',
                'email_verified_at' => now(),
            ]);
            $member->forceFill(['user_id' => $user->id])->save();
            $member->setRelation('user', $user);

            if ($position) {
                Position::query()->where('slug', $position)->firstOrFail()->terms()->create([
                    'group_id' => $group->id,
                    'member_id' => $member->id,
                    'starts_on' => '2025-01-15',
                    'resolution_reference' => '2025/01',
                ]);
            }

            $member->dependants()->create(['name' => 'Spouse of '.explode(' ', $name)[0], 'relationship' => 'spouse']);
            $members[] = $member;
        }

        return $members;
    }

    /**
     * @param  list<Member>  $members
     */
    private function money(Group $group, array $members): void
    {
        $ledger = app(Ledger::class);
        $accounts = app(ChartOfAccounts::class);

        // Balances brought forward from the paper books.
        foreach ($members as $member) {
            $ledger->post("Opening balance for {$member->name} (Member savings)", [
                ['account' => $accounts->get($group, ChartOfAccounts::BANK), 'debit' => 500000, 'member_id' => $member->id, 'memo' => 'Opening balance'],
                ['account' => $accounts->get($group, ChartOfAccounts::SAVINGS), 'credit' => 500000, 'member_id' => $member->id, 'memo' => 'Opening balance'],
            ], date: CarbonImmutable::parse('2026-07-31'), reference: 'OPENING');
        }

        $ledger->post('Opening balance (Welfare fund)', [
            ['account' => $accounts->get($group, ChartOfAccounts::BANK), 'debit' => 4000000],
            ['account' => $accounts->get($group, ChartOfAccounts::WELFARE), 'credit' => 4000000],
        ], date: CarbonImmutable::parse('2026-07-31'), reference: 'OPENING');

        $dues = app(GenerateMonthlyDues::class);
        $record = app(RecordPayment::class);
        $today = CarbonImmutable::today();

        foreach ([$today->subMonths(2), $today->subMonth(), $today] as $month) {
            $dues->handle($group, $month->format('Y-m'));
        }

        foreach ($members as $i => $member) {
            // Most members are up to date; a few are behind.
            $months = match (true) {
                $i % 7 === 3 => 1,
                $i % 5 === 4 => 2,
                default => 3,
            };

            for ($m = 0; $m < $months; $m++) {
                $record->handle(
                    $group,
                    $m % 2 === 0 ? 'paybill' : 'cash',
                    100000,
                    'contribution',
                    $member,
                    reference: $m % 2 === 0 ? sprintf('DEMO%02d%02dX', $i, $m) : null,
                    receivedOn: $today->subMonths(2 - $m)->startOfMonth()->addDays(3),
                );
            }
        }
    }

    /**
     * @param  list<Member>  $members
     */
    private function programmes(Group $group, array $members): void
    {
        $mgr = app(MerryGoRound::class);
        $chair = $members[0]->user;

        $cycle = MgrCycle::create(['name' => 'Merry-go-round 2026', 'starts_on' => CarbonImmutable::today()->subMonths(2)->startOfMonth()->addDays(9)]);
        $mgr->ballot($cycle, array_map(fn ($m) => $m->id, $members), $chair, 'demo-seed');

        $fundraiser = Fundraiser::create([
            'title' => 'Empowerment day 2026',
            'slug' => 'empowerment-2026',
            'summary' => 'Help KERTAI006 members start new income projects.',
            'description' => "On 20 November 2026 we will hold our empowerment day.\nEvery shilling goes to seed capital for members' projects.",
            'beneficiary' => 'KERTAI006 members',
            'target' => 30000000,
            'starts_on' => CarbonImmutable::today()->subDays(5),
            'ends_on' => '2026-11-20',
            'status' => 'active',
            'created_by' => $chair->id,
        ]);
        $fundraiser->fundAccount();

        $record = app(RecordPayment::class);
        foreach ([[$members[0], 500000], [$members[2], 300000], [null, 200000], [$members[9], 150000]] as $k => [$member, $amount]) {
            $payment = $record->handle($group, 'cash', $amount, 'fundraiser', $member, $fundraiser, null, payerName: $member?->name ?? 'Well-wisher from Kericho', phone: $member ? null : '0722000001');
            $payment->forceFill(['show_name' => $k !== 2])->save();
        }

        $coffee = Project::create([
            'name' => 'Coffee seedlings nursery',
            'category' => 'crops',
            'description' => 'SL28 and Ruiru 11 seedlings raised in the group nursery and sold to farmers.',
            'starts_on' => '2026-03-01',
            'share_basis' => 'contribution',
            'coordinator_member_id' => $members[4]->id,
        ]);

        foreach (array_slice($members, 0, 10) as $member) {
            $record->handle($group, 'cash', 200000, 'project', $member, $coffee, null, CarbonImmutable::parse('2026-03-05'));
        }

        $item = ProjectItem::create(['project_id' => $coffee->id, 'name' => 'Coffee seedling (SL28)', 'unit' => 'seedlings', 'unit_price' => 3000]);
        $book = app(ProjectBook::class);
        $book->adjustStock($item, 'in', 5000, 1000, 'Germinated batch 1');
        $sale = $book->sell($item, ['buyer_name' => 'Kipsigis farmers co-op', 'buyer_phone' => '0733000001', 'quantity' => 800, 'unit_price' => 3000, 'sold_on' => CarbonImmutable::today()->subDays(10)->toDateString()]);
        $record->handle($group, 'cash', $sale->total, 'sale', null, $sale, null, payerName: $sale->buyer_name);

        Project::create([
            'name' => 'Sugarcane block',
            'category' => 'crops',
            'description' => 'Two acres leased for sugarcane, supplied to the factory.',
            'starts_on' => '2026-06-01',
            'status' => 'planning',
            'share_basis' => 'contribution',
        ])->fundAccount();

        Asset::create(['name' => 'Tents (10x20 ft)', 'category' => 'tents', 'quantity' => 2, 'condition' => 'good', 'notes' => 'Hire rate to be agreed.']);
        Asset::create(['name' => 'Plastic chairs', 'category' => 'chairs', 'quantity' => 100, 'condition' => 'good', 'notes' => 'Hire rate to be agreed.']);

        Meeting::create([
            'title' => 'Monthly meeting',
            'kind' => 'ordinary',
            'scheduled_at' => CarbonImmutable::today()->addDays(6)->setTime(14, 0),
            'venue' => 'Kertai primary school',
            'agenda' => "1. Opening prayer\n2. Contributions and arrears\n3. Empowerment day plans\n4. AOB",
            'created_by' => $chair->id,
        ]);
    }
}
