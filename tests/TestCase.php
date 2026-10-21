<?php

namespace Tests;

use App\Domain\Access\Models\Position;
use App\Domain\Groups\Actions\InstallGroup;
use App\Domain\Groups\CurrentGroup;
use App\Domain\Groups\Models\Group;
use App\Domain\Members\Models\Member;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected ?Group $kertaiGroup = null;

    private int $phoneSeq = 0;

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * A group with its accounts and positions, set as current.
     *
     * @param  array<string, mixed>  $settings
     */
    protected function group(array $settings = []): Group
    {
        if ($this->kertaiGroup === null) {
            $this->kertaiGroup = app(InstallGroup::class)->handle('Test Self Help Group', [
                'slug' => 'test-shg',
                'paybill' => '247247',
                'paybill_account' => '0720653394',
            ]);
        }

        if ($settings !== []) {
            $this->kertaiGroup->update(['settings' => array_replace($this->kertaiGroup->settings ?? [], $settings)]);
        }

        app(CurrentGroup::class)->set($this->kertaiGroup);

        return $this->kertaiGroup;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function member(array $attributes = [], ?string $position = null, bool $login = true): Member
    {
        $group = $this->group();
        $this->phoneSeq++;
        $phone = $attributes['phone'] ?? '0711'.str_pad((string) $this->phoneSeq, 6, '0', STR_PAD_LEFT);

        $member = new Member;
        $member->forceFill([
            'group_id' => $group->id,
            'member_number' => $attributes['member_number'] ?? 'M'.str_pad((string) $this->phoneSeq, 3, '0', STR_PAD_LEFT),
            'name' => $attributes['name'] ?? "Member {$this->phoneSeq} Test",
            'phone' => Phone::normalize($phone),
            'email' => array_key_exists('email', $attributes) ? $attributes['email'] : "member{$this->phoneSeq}@example.test",
            'joined_on' => $attributes['joined_on'] ?? '2025-01-01',
            'status' => $attributes['status'] ?? 'active',
            ...array_diff_key($attributes, array_flip(['phone', 'member_number', 'name', 'email', 'joined_on', 'status'])),
        ])->save();

        if ($login) {
            $user = User::factory()->create([
                'group_id' => $group->id,
                'name' => $member->name,
                'email' => $member->email,
                'phone' => $member->phone,
            ]);
            $member->forceFill(['user_id' => $user->id])->save();
        }

        if ($position !== null) {
            Position::query()->where('slug', $position)->firstOrFail()->terms()->create([
                'group_id' => $group->id,
                'member_id' => $member->id,
                'starts_on' => '2025-01-01',
            ]);
        }

        return $member->refresh();
    }

    protected function official(string $position, array $attributes = []): User
    {
        return $this->member($attributes, $position)->user;
    }
}
