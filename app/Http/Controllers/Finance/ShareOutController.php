<?php

namespace App\Http\Controllers\Finance;

use App\Domain\Audit\Audit;
use App\Domain\Payouts\Actions\PlanShareOut;
use App\Domain\Payouts\Models\Payout;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShareOutController extends Controller
{
    public function index(Request $request, PlanShareOut $plan): Response
    {
        $percent = max(0, min(100, $request->integer('percent')));

        return Inertia::render('share-out/index', [
            'percent' => $percent,
            'plan' => $plan->preview($this->group(), $percent),
            'batches' => Payout::query()->where('type', 'share_out')->whereNotNull('batch')
                ->selectRaw('batch, COUNT(*) as payouts, SUM(amount) as total, MIN(created_at) as created_at')
                ->groupBy('batch')->orderByDesc('created_at')->get()
                ->map(fn ($b) => [
                    'batch' => $b->batch,
                    'payouts' => (int) $b->payouts,
                    'total' => (int) $b->total,
                    'pending' => Payout::query()->where('batch', $b->batch)->where('status', 'pending')->count(),
                    'approved' => Payout::query()->where('batch', $b->batch)->where('status', 'approved')->count(),
                    'paid' => Payout::query()->where('batch', $b->batch)->where('status', 'paid')->count(),
                ]),
        ]);
    }

    public function store(Request $request, PlanShareOut $plan): RedirectResponse
    {
        $data = $request->validate([
            'percent' => ['required', 'integer', 'min:0', 'max:100'],
            'label' => ['required', 'string', 'max:60'],
        ]);

        $result = $plan->raise($this->group(), $data['percent'], $data['label']);
        Audit::log('shareout.raised', "{$data['label']}: {$result['count']} payouts raised (batch {$result['batch']})", null, $data);
        $this->toast("{$result['count']} share-out payouts raised. Approve them from Payouts (you can tick several at once).");

        return to_route('payouts.index', ['status' => 'pending']);
    }
}
