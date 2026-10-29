<?php

namespace App\Http\Controllers\Members;

use App\Domain\Access\Models\Position;
use App\Domain\Access\Models\PositionTerm;
use App\Domain\Access\Permission;
use App\Domain\Audit\Audit;
use App\Domain\Members\Models\Member;
use App\Http\Controllers\Controller;
use App\Support\RuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Positions are permission bundles held for a term. New ones (vice chair,
 * vice secretary, organising secretary) are made by copying an existing one.
 */
class PositionController extends Controller
{
    public function index(): Response
    {
        $positions = Position::query()->orderBy('sort')->orderBy('name')->get();

        return Inertia::render('positions/index', [
            'positions' => $positions->map(fn (Position $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'description' => $p->description,
                'permissions' => $p->permissions,
                'is_official' => $p->is_official,
                'is_system' => $p->is_system,
                'max_holders' => $p->max_holders,
                'holders' => $p->terms()->current()->with('member')->get()->map(fn (PositionTerm $t) => [
                    'term_id' => $t->id,
                    'member_id' => $t->member_id,
                    'name' => $t->member->name,
                    'starts_on' => $t->starts_on->toDateString(),
                    'ends_on' => $t->ends_on?->toDateString(),
                    'is_acting' => $t->is_acting,
                ]),
            ]),
            'permissions' => collect(Permission::cases())->map(fn (Permission $p) => [
                'value' => $p->value,
                'label' => $p->label(),
                'money' => $p->touchesMoney(),
            ]),
            'members' => $this->memberOptions(),
            'history' => PositionTerm::query()->with('position', 'member')->latest('starts_on')->limit(40)->get()->map(fn (PositionTerm $t) => [
                'id' => $t->id,
                'position' => $t->position->name,
                'member' => $t->member->name,
                'starts_on' => $t->starts_on->toDateString(),
                'ends_on' => $t->ends_on?->toDateString(),
                'is_acting' => $t->is_acting,
                'resolution_reference' => $t->resolution_reference,
                'ended_reason' => $t->ended_reason,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'copy_from' => ['required', 'integer', Rule::exists('positions', 'id')->where('group_id', $this->group()->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'max_holders' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $source = Position::findOrFail((int) $data['copy_from']);
        $slug = Str::slug($data['name']);

        if (Position::query()->where('slug', $slug)->exists()) {
            throw new RuleViolation("A position called {$data['name']} already exists.");
        }

        $position = Position::create([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? "Copy of {$source->name}",
            'permissions' => $source->permissions,
            'is_official' => $source->is_official,
            'max_holders' => $data['max_holders'] ?? $source->max_holders,
            'sort' => $source->sort + 1,
        ]);

        Audit::log('position.created', "Created position {$position->name} from {$source->name}", $position);
        $this->toast("{$position->name} created with the same access as {$source->name}. Adjust it below if needed.");

        return back();
    }

    public function update(Request $request, Position $position): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:255'],
            'max_holders' => ['nullable', 'integer', 'min:1', 'max:50'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(Permission::values())],
        ]);

        $permissions = array_values($data['permissions'] ?? []);
        $manage = Permission::PositionsManage->value;

        // Any position can be given any access, but someone currently in
        // office must still be able to manage positions afterwards.
        if (in_array($manage, $position->permissions ?? [], true) && ! in_array($manage, $permissions, true)) {
            $others = PositionTerm::query()->current()->with('position')->where('position_id', '!=', $position->id)->get()
                ->contains(fn (PositionTerm $t) => in_array($manage, $t->position->permissions ?? [], true));

            if (! $others) {
                throw new RuleViolation('No one else in office can manage positions. Give that access to another position first.');
            }
        }

        $before = $position->permissions;
        $position->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'max_holders' => $data['max_holders'] ?? null,
            'permissions' => $permissions,
        ]);

        $added = array_values(array_diff($position->permissions, $before));
        Audit::log('position.updated', "Updated position {$position->name}", $position, [
            'added' => $added,
            'removed' => array_values(array_diff($before, $position->permissions)),
        ]);

        $addedMoney = array_filter($added, fn ($p) => Permission::from($p)->touchesMoney());
        $this->toast($position->slug === 'system-admin' && $addedMoney !== []
            ? "{$position->name} saved. It now has access to money as well as the payment keys. Payouts still need two approvals, and no one can approve their own."
            : "{$position->name} saved.");

        return back();
    }

    public function destroy(Position $position): RedirectResponse
    {
        if ($position->is_system) {
            throw new RuleViolation('Built-in positions cannot be deleted.');
        }

        if ($position->terms()->exists()) {
            throw new RuleViolation('This position has been held before, so it is kept for the record.');
        }

        $position->delete();
        $this->toast('Position deleted.');

        return back();
    }

    public function assign(Request $request, Position $position): RedirectResponse
    {
        $data = $request->validate([
            'member_id' => ['required', 'integer', Rule::exists('members', 'id')->where('group_id', $this->group()->id)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'is_acting' => ['boolean'],
            'resolution_reference' => ['nullable', 'string', 'max:60'],
        ]);

        if (($data['is_acting'] ?? false) && empty($data['ends_on'])) {
            throw new RuleViolation('An acting appointment needs an end date.');
        }

        $member = Member::findOrFail((int) $data['member_id']);

        if (! $member->isActive()) {
            throw new RuleViolation("{$member->name} is not an active member.");
        }

        $start = CarbonImmutable::parse($data['starts_on']);
        $holders = $position->terms()->current($start)->where('member_id', '!=', $member->id)->count();

        if ($position->max_holders !== null && $holders >= $position->max_holders && ! ($data['is_acting'] ?? false)) {
            throw new RuleViolation("{$position->name} already has {$holders} holder(s). End the current term first, or appoint in an acting capacity.");
        }

        if ($position->terms()->current($start)->where('member_id', $member->id)->exists()) {
            throw new RuleViolation("{$member->name} already holds {$position->name}.");
        }

        $term = $position->terms()->create([
            'group_id' => $this->group()->id,
            'member_id' => $member->id,
            'starts_on' => $start->toDateString(),
            'ends_on' => $data['ends_on'] ?? null,
            'is_acting' => $data['is_acting'] ?? false,
            'resolution_reference' => $data['resolution_reference'] ?? null,
        ]);

        Audit::log('position.assigned', "{$member->name} appointed ".($term->is_acting ? 'acting ' : '')."{$position->name} from {$start->format('j M Y')}", $position, ['member_id' => $member->id]);
        $this->toast("{$member->name} is now ".($term->is_acting ? 'acting ' : '')."{$position->name}.");

        return back();
    }

    public function endTerm(Request $request, PositionTerm $term): RedirectResponse
    {
        $data = $request->validate([
            'ends_on' => ['required', 'date', 'after_or_equal:'.$term->starts_on->toDateString()],
            'ended_reason' => ['nullable', 'string', 'max:120'],
        ]);

        $managesPositions = fn (PositionTerm $t) => in_array(Permission::PositionsManage->value, $t->position->permissions ?? [], true);

        if ($term->member_id === $request->user()->member?->id && $managesPositions($term)) {
            $others = PositionTerm::query()->current()->with('position')->where('member_id', '!=', $term->member_id)->get()
                ->filter($managesPositions)
                ->isNotEmpty();

            if (! $others) {
                throw new RuleViolation('You are the only person who can manage positions. Appoint someone else first.');
            }
        }

        $term->update(['ends_on' => $data['ends_on'], 'ended_reason' => $data['ended_reason'] ?? 'Term ended']);
        Audit::log('position.ended', "{$term->member->name}'s term as {$term->position->name} ends {$data['ends_on']}", $term->position);
        $this->toast('Term ended.');

        return back();
    }
}
