<?php

namespace App\Console\Commands;

use App\Domain\Access\Models\Position;
use App\Domain\Groups\Actions\InstallGroup;
use App\Domain\Groups\CurrentGroup;
use App\Domain\Members\Actions\GiveMemberLogin;
use App\Domain\Members\Actions\SaveMember;
use App\Support\Phone;
use Illuminate\Console\Command;

/**
 * First-time setup on a server: the group, its accounts and positions, and
 * one member who holds the chair and system administrator positions so
 * they can add everyone else.
 */
class InstallCommand extends Command
{
    protected $signature = 'kertai:install
        {--group= : Group name}
        {--registration= : Registration number}
        {--paybill=247247 : Paybill number}
        {--account=0720653394 : Paybill account number}
        {--name= : First official\'s full name}
        {--phone= : First official\'s phone}
        {--email= : First official\'s email (optional)}
        {--position=chairperson : Position for the first official}';

    protected $description = 'Create the group, chart of accounts, positions and the first official login';

    public function handle(InstallGroup $install, SaveMember $save, GiveMemberLogin $login, CurrentGroup $current): int
    {
        $name = $this->option('group') ?: $this->ask('Group name', 'KERTAI006 Self Help Group');

        $group = $install->handle($name, array_filter([
            'registration_number' => $this->option('registration'),
            'paybill' => $this->option('paybill'),
            'paybill_account' => $this->option('account'),
        ]));

        $current->set($group);
        $this->components->info("Group ready: {$group->name} (id {$group->id}).");

        if ($group->members()->exists()) {
            $this->components->info('Members already exist; skipping the first official.');

            return self::SUCCESS;
        }

        $fullName = $this->option('name') ?: $this->ask('First official\'s full name');
        $phone = $this->option('phone') ?: $this->ask('Their phone number');

        if (Phone::normalize($phone) === null) {
            $this->components->error('That is not a valid Kenyan mobile number.');

            return self::FAILURE;
        }

        $member = $save->handle($group, [
            'name' => $fullName,
            'phone' => $phone,
            'email' => $this->option('email') ?: null,
            'joined_on' => now()->toDateString(),
            'locale' => 'en',
        ]);

        $first = $this->option('position');

        foreach (array_unique([is_string($first) ? $first : 'chairperson', 'system-admin']) as $slug) {
            $position = Position::query()->where('slug', $slug)->first();

            if ($position) {
                $position->terms()->create([
                    'group_id' => $group->id,
                    'member_id' => $member->id,
                    'starts_on' => now()->toDateString(),
                    'resolution_reference' => 'Initial setup',
                ]);
            }
        }

        $password = $login->handle($member, notify: false);

        $this->components->twoColumnDetail('Sign in with', Phone::display($member->phone));
        $this->components->twoColumnDetail('One-time password', $password);
        $this->components->warn('Write the password down now; it is not shown again. It must be changed on first sign-in.');

        return self::SUCCESS;
    }
}
