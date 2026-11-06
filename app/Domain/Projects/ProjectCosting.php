<?php

namespace App\Domain\Projects;

use App\Domain\Audit\Audit;
use App\Domain\Groups\Models\Group;
use App\Domain\Members\Models\Member;
use App\Domain\Payouts\Actions\RequestPayout;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectCost;
use App\Domain\Projects\Models\ProjectEntry;
use App\Domain\Projects\Models\ProjectParticipant;
use App\Domain\Projects\Models\ProjectSeason;
use App\Domain\Projects\Models\Sale;
use App\Support\Money;
use App\Support\RuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The cost register behind a project: every cost with its category,
 * quantity and price, and the season and land it belongs to.
 *
 * Money only leaves the project fund through a payout, so a cost paid from
 * the fund raises one and still needs two approvals. A cost a member paid
 * for counts as their contribution and goes into the project book at once.
 */
final class ProjectCosting
{
    public function __construct(private readonly RequestPayout $payouts) {}

    /**
     * @param  array<string, mixed>  $data  unit_cost already in cents
     */
    public function record(Group $group, Project $project, array $data): ProjectCost
    {
        $incurred = CarbonImmutable::parse((string) $data['incurred_on']);
        $season = isset($data['project_season_id'])
            ? $project->seasons()->findOrFail((int) $data['project_season_id'])
            : $project->seasonFor($incurred);
        $this->ensureOpen($season);

        $amount = $this->amount((string) $data['quantity'], (int) $data['unit_cost']);
        $paidBy = (string) $data['paid_by'];
        $member = $paidBy === 'member' ? Member::query()->findOrFail((int) $data['member_id']) : null;

        return DB::transaction(function () use ($group, $project, $data, $season, $amount, $paidBy, $member) {
            $cost = ProjectCost::create([
                'project_id' => $project->id,
                'project_season_id' => $season?->id,
                'project_plot_id' => $data['project_plot_id'] ?? null,
                'category' => $data['category'],
                'description' => $data['description'],
                'quantity' => $data['quantity'],
                'unit' => $data['unit'],
                'unit_cost' => $data['unit_cost'],
                'amount' => $amount,
                'incurred_on' => $data['incurred_on'],
                'supplier_name' => $data['supplier_name'] ?? null,
                'supplier_phone' => $data['supplier_phone'] ?? null,
                'receipt_ref' => $data['receipt_ref'] ?? null,
                'labour_type' => $data['labour_type'] ?? null,
                'workers' => $data['workers'] ?? null,
                'days' => $data['days'] ?? null,
                'paid_by' => $paidBy === 'fund' ? 'owed' : $paidBy,
                'member_id' => $member?->id,
                'recorded_by' => auth()->id(),
            ]);

            if ($paidBy === 'fund') {
                $this->raisePayout($group, $project, $cost);
            }

            if ($member) {
                $this->creditMember($project, $cost, $member);
            }

            Audit::log('project.cost_recorded', "{$project->name}: {$this->label($cost)} ".Money::format($amount), $cost, ['paid_by' => $paidBy]);

            return $cost;
        });
    }

    /**
     * Details can be corrected while the season is open. The amount can only
     * change on a cost still owed: once a payout exists or a member has been
     * credited, the amount is fixed.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Project $project, ProjectCost $cost, array $data): ProjectCost
    {
        $this->ensureOpen($cost->season);

        $season = isset($data['project_season_id']) ? $project->seasons()->findOrFail((int) $data['project_season_id']) : null;
        $this->ensureOpen($season);

        $amount = $this->amount((string) $data['quantity'], (int) $data['unit_cost']);

        if ($amount !== $cost->amount && $cost->paid_by !== 'owed') {
            throw new RuleViolation('The amount can only change while the cost is owed. Delete this cost and record it again, or cancel its payout first.');
        }

        $cost->update([
            'project_season_id' => $season?->id,
            'project_plot_id' => $data['project_plot_id'] ?? null,
            'category' => $data['category'],
            'description' => $data['description'],
            'quantity' => $data['quantity'],
            'unit' => $data['unit'],
            'unit_cost' => $data['unit_cost'],
            'amount' => $amount,
            'incurred_on' => $data['incurred_on'],
            'supplier_name' => $data['supplier_name'] ?? null,
            'supplier_phone' => $data['supplier_phone'] ?? null,
            'receipt_ref' => $data['receipt_ref'] ?? null,
            'labour_type' => $data['labour_type'] ?? null,
            'workers' => $data['workers'] ?? null,
            'days' => $data['days'] ?? null,
        ]);

        Audit::log('project.cost_updated', "{$project->name}: {$this->label($cost)} ".Money::format($amount), $cost);

        return $cost;
    }

    public function delete(Project $project, ProjectCost $cost): void
    {
        $this->ensureOpen($cost->season);

        if (in_array($cost->state(), ['paid', 'awaiting'], true)) {
            throw new RuleViolation($cost->payout
                ? "This cost has payout #{$cost->payout->number} ({$cost->payout->status}). Cancel or reject the payout before deleting the cost."
                : 'This cost was paid from the fund, so it stays in the register.');
        }

        DB::transaction(function () use ($project, $cost) {
            if ($cost->paid_by === 'member') {
                $this->uncreditMember($project, $cost);
            }

            Audit::log('project.cost_deleted', "{$project->name}: {$this->label($cost)} ".Money::format($cost->amount), $project);
            $cost->delete();
        });
    }

    /** Pay an owed cost, or try again after a payout was turned down. */
    public function requestPayout(Group $group, Project $project, ProjectCost $cost): void
    {
        if (! in_array($cost->state(), ['owed', 'rejected'], true)) {
            throw new RuleViolation('Only a cost that is owed, or whose payout was turned down, can be sent for payment.');
        }

        if (! $cost->supplier_name) {
            throw new RuleViolation('Add who is being paid before requesting the payout.');
        }

        DB::transaction(fn () => $this->raisePayout($group, $project, $cost));
    }

    /**
     * Totals for one season, or for the whole project when no season is given.
     * Costs are counted when incurred, including those still owed; costs whose
     * payout was turned down are left out.
     *
     * @return array<string, mixed>
     */
    public function summary(Project $project, ?ProjectSeason $season, float $acres): array
    {
        $costs = ProjectCost::query()
            ->with('payout:id,status')
            ->where('project_id', $project->id)
            ->when($season, fn ($q) => $q->where('project_season_id', $season?->id))
            ->get()
            ->filter(fn (ProjectCost $c) => $c->counts());

        $byCategory = $costs->groupBy('category')->map(fn ($group) => (int) $group->sum('amount'))->sortDesc();
        $byState = $costs->groupBy(fn (ProjectCost $c) => $c->state())->map(fn ($group) => (int) $group->sum('amount'));
        $total = (int) $costs->sum('amount');

        $sales = (int) Sale::query()
            ->where('project_id', $project->id)
            ->when($season, fn ($q) => $q->where('project_season_id', $season?->id))
            ->sum('total');

        $income = (int) ProjectEntry::query()
            ->where('project_id', $project->id)
            ->where('kind', 'income')
            ->when($season, fn ($q) => $q
                ->where('entry_on', '>=', $season->starts_on->toDateString())
                ->when($season->ends_on, fn ($q) => $q->where('entry_on', '<=', $season->ends_on?->toDateString())))
            ->sum('amount');

        $budget = $season
            ? $season->budgetLines()->pluck('amount', 'category')->map(fn ($v) => (int) $v)->all()
            : [];

        $revenue = $sales + $income;
        $yield = $season?->yield_quantity !== null ? (float) $season->yield_quantity : null;

        return [
            'costs' => $total,
            'by_category' => $byCategory->all(),
            'paid' => $byState['paid'] ?? 0,
            'awaiting' => $byState['awaiting'] ?? 0,
            'owed' => $byState['owed'] ?? 0,
            'in_kind' => $byState['in_kind'] ?? 0,
            'sales' => $sales,
            'income' => $income,
            'revenue' => $revenue,
            'profit' => $revenue - $total,
            'budget' => $budget,
            'budget_total' => array_sum($budget),
            'acres' => $acres,
            'cost_per_acre' => $acres > 0 ? $this->shillings($total / $acres) : null,
            'profit_per_acre' => $acres > 0 ? $this->shillings(($revenue - $total) / $acres) : null,
            'yield' => $yield,
            'yield_unit' => $season?->yield_unit,
            'cost_per_unit' => $yield ? $this->shillings($total / $yield) : null,
            'labour' => $byCategory['labour'] ?? 0,
        ];
    }

    private function raisePayout(Group $group, Project $project, ProjectCost $cost): void
    {
        $payout = $this->payouts->handle(
            $group,
            'project_expense',
            $cost->amount,
            $project->fundAccount(),
            "{$project->name}: {$cost->description}",
            null,
            $cost->supplier_name,
            $cost->supplier_phone,
            payable: $project,
        );

        $cost->forceFill(['paid_by' => 'fund', 'payout_id' => $payout->id])->save();
        $cost->setRelation('payout', $payout);
    }

    private function creditMember(Project $project, ProjectCost $cost, Member $member): void
    {
        $participant = ProjectParticipant::firstOrCreate(
            ['project_id' => $project->id, 'member_id' => $member->id],
            ['joined_on' => $cost->incurred_on->toDateString()],
        );
        $participant->increment('contributed', $cost->amount);

        foreach (['contribution' => "In kind from {$member->name}: {$cost->description}", 'expense' => "{$cost->description} (paid by {$member->name})"] as $kind => $description) {
            ProjectEntry::create([
                'project_id' => $project->id,
                'kind' => $kind,
                'amount' => $cost->amount,
                'entry_on' => $cost->incurred_on->toDateString(),
                'description' => mb_substr($description, 0, 255),
                'member_id' => $member->id,
                'project_cost_id' => $cost->id,
            ]);
        }
    }

    private function uncreditMember(Project $project, ProjectCost $cost): void
    {
        $sharedSince = ProjectEntry::query()
            ->where('project_id', $project->id)
            ->where('kind', 'distribution')
            ->where('created_at', '>=', $cost->created_at)
            ->exists();

        if ($sharedSince) {
            throw new RuleViolation('Profit has been shared since this was recorded, so it is part of that share-out and cannot be deleted.');
        }

        $participant = ProjectParticipant::query()->where('project_id', $project->id)->where('member_id', $cost->member_id)->first();

        if ($participant) {
            $participant->forceFill(['contributed' => max(0, $participant->contributed - $cost->amount)])->save();
        }

        ProjectEntry::query()->where('project_cost_id', $cost->id)->delete();
    }

    private function ensureOpen(?ProjectSeason $season): void
    {
        if ($season && ! $season->isOpen()) {
            throw new RuleViolation("{$season->name} is closed. Reopen it to change its costs.");
        }
    }

    private function amount(string $quantity, int $unitCost): int
    {
        $amount = (int) round((float) $quantity * $unitCost);

        if ($amount <= 0) {
            throw new RuleViolation('The amount works out to zero. Check the quantity and the cost per unit.');
        }

        if ($amount > 10_000_000_000) {
            throw new RuleViolation('That is more than KES 100,000,000. Check the quantity and the cost per unit.');
        }

        return $amount;
    }

    /** Averages are shown to the shilling; cents there are noise. */
    private function shillings(float $cents): int
    {
        return (int) round($cents / 100) * 100;
    }

    private function label(ProjectCost $cost): string
    {
        return (ProjectCost::CATEGORIES[$cost->category] ?? $cost->category)." – {$cost->description},";
    }
}
