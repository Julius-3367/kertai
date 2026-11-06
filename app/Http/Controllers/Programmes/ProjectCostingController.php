<?php

namespace App\Http\Controllers\Programmes;

use App\Domain\Audit\Audit;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectBudgetLine;
use App\Domain\Projects\Models\ProjectCost;
use App\Domain\Projects\Models\ProjectPlot;
use App\Domain\Projects\Models\ProjectSeason;
use App\Domain\Projects\ProjectCosting;
use App\Http\Controllers\Controller;
use App\Support\Money;
use App\Support\Phone;
use App\Support\RuleViolation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Land, seasons, budgets and the cost register of a project. Everyone in the
 * group can see them; holders of "Manage projects" and the project's own
 * coordinator keep them.
 */
class ProjectCostingController extends Controller
{
    public function storePlot(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeCosts($request, $project);

        $plot = $project->plots()->create($this->plotData($request));
        Audit::log('project.plot_added', "{$project->name}: added {$plot->name}, {$plot->size} {$plot->size_unit}", $project);
        $this->toast("{$plot->name} added.");

        return back();
    }

    public function updatePlot(Request $request, Project $project, ProjectPlot $plot): RedirectResponse
    {
        $this->authorizeCosts($request, $project);
        abort_unless($plot->project_id === $project->id, 404);

        $plot->update($this->plotData($request, true));
        Audit::log('project.plot_updated', "{$project->name}: updated {$plot->name}", $project);
        $this->toast('Plot saved.');

        return back();
    }

    public function destroyPlot(Request $request, Project $project, ProjectPlot $plot): RedirectResponse
    {
        $this->authorizeCosts($request, $project);
        abort_unless($plot->project_id === $project->id, 404);

        if (ProjectCost::query()->where('project_plot_id', $plot->id)->exists()) {
            throw new RuleViolation('Costs are recorded against this plot. Mark it as given back instead of deleting it.');
        }

        $plot->delete();
        Audit::log('project.plot_deleted', "{$project->name}: deleted {$plot->name}", $project);
        $this->toast('Plot deleted.');

        return back();
    }

    public function storeSeason(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeCosts($request, $project);

        $season = $project->seasons()->create($this->seasonData($request));
        Audit::log('project.season_opened', "{$project->name}: opened {$season->name}", $project);
        $this->toast("{$season->name} opened. Add a budget, then record costs as they happen.");

        return back();
    }

    public function updateSeason(Request $request, Project $project, ProjectSeason $season): RedirectResponse
    {
        $this->authorizeCosts($request, $project);
        abort_unless($season->project_id === $project->id, 404);

        $season->update($this->seasonData($request));
        Audit::log('project.season_updated', "{$project->name}: updated {$season->name}", $project);
        $this->toast('Season saved.');

        return back();
    }

    public function seasonStatus(Request $request, Project $project, ProjectSeason $season): RedirectResponse
    {
        $this->authorizeCosts($request, $project);
        abort_unless($season->project_id === $project->id, 404);

        $data = $request->validate(['status' => ['required', Rule::in(['open', 'closed'])]]);
        $season->update(['status' => $data['status']]);

        $closed = $data['status'] === 'closed';
        Audit::log($closed ? 'project.season_closed' : 'project.season_reopened', "{$project->name}: ".($closed ? 'closed' : 'reopened')." {$season->name}", $project);
        $this->toast($closed ? "{$season->name} closed. Its costs are now locked." : "{$season->name} reopened.");

        return back();
    }

    public function budget(Request $request, Project $project, ProjectSeason $season): RedirectResponse
    {
        $this->authorizeCosts($request, $project);
        abort_unless($season->project_id === $project->id, 404);

        $data = $request->validate([
            'budget' => ['present', 'array'],
            'budget.*' => Money::rule(false, 0),
        ]);

        $unknown = array_diff(array_keys($data['budget']), array_keys(ProjectCost::CATEGORIES));
        abort_if($unknown !== [], 422);

        foreach ($data['budget'] as $category => $value) {
            $amount = $this->cents($value);

            if ($amount > 0) {
                ProjectBudgetLine::updateOrCreate(['project_season_id' => $season->id, 'category' => $category], ['amount' => $amount]);
            } else {
                $season->budgetLines()->where('category', $category)->delete();
            }
        }

        Audit::log('project.budget_saved', "{$project->name}: budget for {$season->name} set to ".Money::format((int) $season->budgetLines()->sum('amount')), $project);
        $this->toast('Budget saved.');

        return back();
    }

    public function storeCost(Request $request, Project $project, ProjectCosting $costing): RedirectResponse
    {
        $this->authorizeCosts($request, $project);

        $data = $this->costData($request, $project, true);
        $cost = $costing->record($this->group(), $project, $data);

        $this->toast(match ($data['paid_by']) {
            'fund' => 'Cost saved. Payout #'.$cost->payout?->number.' is waiting for two approvals.',
            'member' => 'Cost saved and '.Money::format($cost->amount)." added to {$cost->member?->name}'s contribution.",
            default => 'Cost saved as owed. Request the payout when you are ready to pay.',
        });

        return back();
    }

    public function updateCost(Request $request, Project $project, ProjectCost $cost, ProjectCosting $costing): RedirectResponse
    {
        $this->authorizeCosts($request, $project);
        abort_unless($cost->project_id === $project->id, 404);

        $costing->update($project, $cost, $this->costData($request, $project, false));
        $this->toast('Cost saved.');

        return back();
    }

    public function destroyCost(Request $request, Project $project, ProjectCost $cost, ProjectCosting $costing): RedirectResponse
    {
        $this->authorizeCosts($request, $project);
        abort_unless($cost->project_id === $project->id, 404);

        $costing->delete($project, $cost);
        $this->toast('Cost deleted.');

        return back();
    }

    public function requestPayout(Request $request, Project $project, ProjectCost $cost, ProjectCosting $costing): RedirectResponse
    {
        $this->authorizeCosts($request, $project);
        abort_unless($cost->project_id === $project->id, 404);

        $costing->requestPayout($this->group(), $project, $cost);
        $this->toast('Payout #'.$cost->payout?->number.' requested. Two officials must approve it.');

        return back();
    }

    public function csv(Request $request, Project $project): StreamedResponse
    {
        $seasonId = $request->integer('season') ?: null;
        $costs = $project->costs()
            ->with(['season:id,name', 'plot:id,name', 'member:id,name', 'payout:id,number,status'])
            ->when($seasonId, fn ($q) => $q->where('project_season_id', $seasonId))
            ->get();

        $name = str($project->name)->slug()->append('-costs-'.now()->format('Y-m-d').'.csv')->toString();

        return response()->streamDownload(function () use ($costs) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['Date', 'Season', 'Category', 'Description', 'Quantity', 'Unit', 'Unit cost (KES)', 'Amount (KES)', 'Plot', 'Supplier / paid to', 'Receipt', 'Paid by', 'Status']);
            foreach ($costs as $c) {
                fputcsv($out, [
                    $c->incurred_on->toDateString(),
                    $c->season->name ?? '',
                    ProjectCost::CATEGORIES[$c->category] ?? $c->category,
                    $c->description,
                    $c->quantity,
                    $c->unit,
                    Money::toShillings($c->unit_cost),
                    Money::toShillings($c->amount),
                    $c->plot->name ?? '',
                    $c->supplier_name ?? '',
                    $c->receipt_ref ?? '',
                    $c->paid_by === 'member' ? 'Member: '.($c->member->name ?? '') : ($c->payout ? "Fund, payout #{$c->payout->number}" : ucfirst($c->paid_by)),
                    str_replace('_', ' ', $c->state()),
                ]);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    private function authorizeCosts(Request $request, Project $project): void
    {
        abort_unless($project->costsManagedBy($request->user()), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function plotData(Request $request, bool $updating = false): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'location' => ['nullable', 'string', 'max:120'],
            'size' => ['required', 'numeric', 'min:0.01', 'max:100000', 'decimal:0,2'],
            'size_unit' => ['required', Rule::in(['acres', 'hectares'])],
            'tenure' => ['required', Rule::in(array_keys(ProjectPlot::TENURES))],
            'member_id' => ['nullable', 'required_if:tenure,member', 'integer', Rule::exists('members', 'id')->where('group_id', $this->group()->id)],
            'owner_name' => ['nullable', 'string', 'max:120'],
            'owner_phone' => ['nullable', 'string', 'max:20'],
            'lease_amount' => [...Money::rule(false, 0), 'required_if:tenure,leased'],
            'lease_per' => ['nullable', 'required_if:tenure,leased', Rule::in(array_keys(ProjectPlot::LEASE_PER))],
            'lease_starts_on' => ['nullable', 'date'],
            'lease_ends_on' => ['nullable', 'date', 'after_or_equal:lease_starts_on'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => [$updating ? 'required' : 'nullable', Rule::in(['active', 'released'])],
        ], [
            'member_id.required_if' => 'Choose the member lending the land.',
            'lease_amount.required_if' => 'Enter what the lease costs.',
            'lease_per.required_if' => 'Choose how often the lease is paid.',
        ]);

        $leased = $data['tenure'] === 'leased';

        return [
            ...$data,
            'member_id' => $data['tenure'] === 'member' ? $data['member_id'] : null,
            'owner_phone' => Phone::normalize($data['owner_phone'] ?? null),
            'lease_amount' => $leased ? $this->cents($data['lease_amount']) : null,
            'lease_per' => $leased ? $data['lease_per'] : null,
            'status' => $data['status'] ?? 'active',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function seasonData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'yield_quantity' => ['nullable', 'numeric', 'min:0', 'max:100000000', 'decimal:0,2'],
            'yield_unit' => ['nullable', 'required_with:yield_quantity', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'yield_unit.required_with' => 'Say what the harvest is counted in, for example bags or kg.',
        ]);
    }

    /**
     * Labour is entered as workers × days × daily rate; everything else as
     * quantity × unit price.
     *
     * @return array<string, mixed>
     */
    private function costData(Request $request, Project $project, bool $creating): array
    {
        $labour = $request->input('category') === 'labour';

        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(ProjectCost::CATEGORIES))],
            'description' => ['required', 'string', 'max:200'],
            'project_season_id' => ['nullable', 'integer', Rule::exists('project_seasons', 'id')->where('project_id', $project->id)],
            'project_plot_id' => ['nullable', 'integer', Rule::exists('project_plots', 'id')->where('project_id', $project->id)],
            'labour_type' => [$labour ? 'required' : 'nullable', Rule::in(array_keys(ProjectCost::LABOUR_TYPES))],
            'workers' => [$labour ? 'required' : 'nullable', 'integer', 'min:1', 'max:10000'],
            'days' => [$labour ? 'required' : 'nullable', 'numeric', 'min:0.5', 'max:1000', 'decimal:0,2'],
            'quantity' => [$labour ? 'nullable' : 'required', 'numeric', 'min:0.01', 'max:10000000', 'decimal:0,2'],
            'unit' => [$labour ? 'nullable' : 'required', 'string', 'max:20'],
            'unit_cost' => Money::rule(),
            'incurred_on' => ['required', 'date', 'before_or_equal:today'],
            'supplier_name' => ['nullable', 'required_if:paid_by,fund', 'string', 'max:120'],
            'supplier_phone' => ['nullable', 'string', 'max:20'],
            'receipt_ref' => ['nullable', 'string', 'max:60'],
            ...($creating ? [
                'paid_by' => ['required', Rule::in(['fund', 'owed', 'member'])],
                'member_id' => ['nullable', 'required_if:paid_by,member', 'integer', Rule::exists('members', 'id')->where('group_id', $this->group()->id)],
            ] : []),
        ], [
            'supplier_name.required_if' => 'Enter who is being paid, so the treasurer knows whom to pay.',
            'member_id.required_if' => 'Choose the member who paid.',
        ]);

        if ($labour) {
            $data['quantity'] = (string) round((int) $data['workers'] * (float) $data['days'], 2);
            $data['unit'] = 'man-days';
        } else {
            $data['labour_type'] = $data['workers'] = $data['days'] = null;
        }

        $data['unit_cost'] = $this->cents($data['unit_cost']);
        $data['supplier_phone'] = Phone::normalize($data['supplier_phone'] ?? null);

        return $data;
    }
}
