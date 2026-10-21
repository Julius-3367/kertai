<?php

namespace App\Domain\Projects;

use App\Domain\Audit\Audit;
use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Ledger\Ledger;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectEntry;
use App\Domain\Projects\Models\ProjectItem;
use App\Domain\Projects\Models\ProjectParticipant;
use App\Domain\Projects\Models\Sale;
use App\Domain\Projects\Models\StockMovement;
use App\Support\Money;
use App\Support\Phone;
use App\Support\RuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ProjectBook
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly ChartOfAccounts $accounts,
    ) {}

    public function adjustStock(ProjectItem $item, string $kind, int $quantity, ?int $unitCost, ?string $note): StockMovement
    {
        if ($quantity === 0) {
            throw new RuleViolation('Enter a quantity.');
        }

        $signed = match ($kind) {
            'in' => abs($quantity),
            'out' => -abs($quantity),
            default => $quantity,
        };

        if ($item->quantity + $signed < 0) {
            throw new RuleViolation("Only {$item->quantity} {$item->unit} of {$item->name} in stock.");
        }

        return DB::transaction(function () use ($item, $kind, $signed, $unitCost, $note) {
            $item->increment('quantity', $signed);

            return StockMovement::create([
                'project_item_id' => $item->id,
                'kind' => $kind,
                'quantity' => $signed,
                'unit_cost' => $unitCost,
                'note' => $note,
                'recorded_by' => auth()->id(),
            ]);
        });
    }

    /**
     * @param  array{buyer_name: string, buyer_phone?: string|null, member_id?: int|null, quantity: int, unit_price: int, sold_on: string}  $data
     */
    public function sell(ProjectItem $item, array $data): Sale
    {
        if ($data['quantity'] > $item->quantity) {
            throw new RuleViolation("Only {$item->quantity} {$item->unit} of {$item->name} in stock.");
        }

        return DB::transaction(function () use ($item, $data) {
            $sale = Sale::create([
                'project_id' => $item->project_id,
                'project_item_id' => $item->id,
                'member_id' => $data['member_id'] ?? null,
                'quantity' => $data['quantity'],
                'unit_price' => $data['unit_price'],
                'total' => $data['quantity'] * $data['unit_price'],
                'buyer_name' => $data['buyer_name'],
                'buyer_phone' => Phone::normalize($data['buyer_phone'] ?? null),
                'sold_on' => $data['sold_on'],
                'recorded_by' => auth()->id(),
            ]);

            $item->decrement('quantity', $data['quantity']);

            StockMovement::create([
                'project_item_id' => $item->id,
                'kind' => 'sale',
                'quantity' => -$data['quantity'],
                'note' => "Sold to {$data['buyer_name']}",
                'sale_id' => $sale->id,
                'recorded_by' => auth()->id(),
            ]);

            return $sale;
        });
    }

    /**
     * How profit splits between participants, by contribution or equally,
     * whichever the project uses.
     *
     * @return array{available: int, shares: list<array{member_id: int, name: string, contributed: int, amount: int}>}
     */
    public function shareOutPreview(Project $project, int $amount): array
    {
        $participants = $project->participants()->with('member')->get()
            ->filter(fn (ProjectParticipant $p) => $p->member->isActive() || $p->member->status === 'deceased')
            ->values();
        $available = $this->ledger->balance($project->fundAccount());
        $totalContributed = (int) $participants->sum('contributed');
        $byContribution = $project->share_basis === 'contribution' && $totalContributed > 0;
        $count = $participants->count();

        if ($count === 0 || $amount <= 0) {
            return ['available' => $available, 'shares' => []];
        }

        $shares = [];
        $allocated = 0;

        foreach ($participants as $p) {
            $share = $byContribution
                ? intdiv($amount * $p->contributed, $totalContributed)
                : intdiv($amount, $count);

            $shares[] = ['member_id' => $p->member_id, 'name' => $p->member->name, 'contributed' => $p->contributed, 'amount' => $share];
            $allocated += $share;
        }

        // Rounding leftovers (a few cents) go to the largest holder.
        if ($allocated < $amount) {
            usort($shares, fn ($a, $b) => $b['amount'] <=> $a['amount']);
            $shares[0]['amount'] += $amount - $allocated;
        }

        return ['available' => $available, 'shares' => array_values(array_filter($shares, fn ($s) => $s['amount'] > 0))];
    }

    /**
     * Credit each participant's savings with their share of the profit.
     */
    public function distribute(Project $project, int $amount): int
    {
        $preview = $this->shareOutPreview($project, $amount);

        if ($amount > $preview['available']) {
            throw new RuleViolation('The project fund holds only '.Money::format($preview['available']).'.');
        }

        $pnl = $project->profitAndLoss();
        $undistributed = max(0, $pnl['profit'] - $pnl['distributed']);

        if ($amount > $undistributed) {
            throw new RuleViolation('Only '.Money::format($undistributed).' of profit has not been shared yet.');
        }

        if ($preview['shares'] === []) {
            throw new RuleViolation('The project has no participants to share with.');
        }

        return DB::transaction(function () use ($project, $amount, $preview) {
            $savings = $this->accounts->get($project->group_id, ChartOfAccounts::SAVINGS);
            $lines = [['account' => $project->fundAccount(), 'debit' => $amount, 'memo' => 'Profit share']];

            foreach ($preview['shares'] as $share) {
                $lines[] = ['account' => $savings, 'credit' => $share['amount'], 'member_id' => $share['member_id'], 'memo' => "{$project->name} profit share"];
            }

            $this->ledger->post("Profit share from {$project->name}", $lines, $project, CarbonImmutable::today());

            ProjectEntry::create([
                'project_id' => $project->id,
                'kind' => 'distribution',
                'amount' => $amount,
                'entry_on' => now()->toDateString(),
                'description' => 'Profit shared to '.count($preview['shares']).' members\' savings',
            ]);

            Audit::log('project.distributed', 'Shared '.Money::format($amount)." profit from {$project->name}", $project);

            return count($preview['shares']);
        });
    }
}
