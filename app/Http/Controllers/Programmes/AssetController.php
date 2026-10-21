<?php

namespace App\Http\Controllers\Programmes;

use App\Domain\Access\Permission;
use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Models\Hire;
use App\Domain\Audit\Audit;
use App\Domain\Members\Models\Member;
use App\Domain\Payments\Actions\RecordPayment;
use App\Http\Controllers\Controller;
use App\Support\Money;
use App\Support\Phone;
use App\Support\RuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tents, chairs and other things the group owns and hires out.
 */
class AssetController extends Controller
{
    public function index(): Response
    {
        $today = CarbonImmutable::today();

        return Inertia::render('assets/index', [
            'assets' => Asset::query()->orderBy('name')->get()->map(fn (Asset $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'category' => $a->category,
                'quantity' => $a->quantity,
                'available_today' => $a->availableBetween($today, $today),
                'hire_rate' => $a->hire_rate,
                'hire_rate_input' => $a->hire_rate !== null ? Money::toShillings($a->hire_rate) : '',
                'purchase_cost' => $a->purchase_cost,
                'purchase_cost_input' => $a->purchase_cost !== null ? Money::toShillings($a->purchase_cost) : '',
                'acquired_on' => $a->acquired_on?->toDateString(),
                'condition' => $a->condition,
                'notes' => $a->notes,
            ]),
            'hires' => Hire::query()->with('asset:id,name')->latest('starts_on')->limit(100)->get()->map(fn (Hire $h) => [
                'id' => $h->id,
                'asset' => $h->asset->name,
                'hirer_name' => $h->hirer_name,
                'hirer_phone' => $h->hirer_phone ? Phone::display($h->hirer_phone) : null,
                'quantity' => $h->quantity,
                'starts_on' => $h->starts_on->toDateString(),
                'ends_on' => $h->ends_on->toDateString(),
                'days' => $h->days(),
                'rate' => $h->rate,
                'total' => $h->total,
                'paid' => $h->paid,
                'balance' => $h->balance(),
                'status' => $h->status,
                'notes' => $h->notes,
            ]),
            'categories' => Asset::CATEGORIES,
            'members' => $this->memberOptions(),
            'canManage' => request()->user()->hasPermission(Permission::AssetsManage->value),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize(Permission::AssetsManage->value);

        $asset = Asset::create($this->validated($request));
        Audit::log('asset.created', "Added asset {$asset->quantity} x {$asset->name}", $asset);
        $this->toast("{$asset->name} added.");

        return back();
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        Gate::authorize(Permission::AssetsManage->value);

        $asset->update($this->validated($request));
        Audit::log('asset.updated', "Updated asset {$asset->name}", $asset);
        $this->toast("{$asset->name} saved.");

        return back();
    }

    public function book(Request $request): RedirectResponse
    {
        Gate::authorize(Permission::AssetsManage->value);

        $data = $request->validate([
            'asset_id' => ['required', 'integer', Rule::exists('assets', 'id')->where('group_id', $this->group()->id)],
            'member_id' => ['nullable', 'integer', Rule::exists('members', 'id')->where('group_id', $this->group()->id)],
            'hirer_name' => ['nullable', 'required_without:member_id', 'string', 'max:120'],
            'hirer_phone' => ['nullable', 'string', 'max:20'],
            'quantity' => ['required', 'integer', 'min:1'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'rate' => Money::rule(false, 0),
            'notes' => ['nullable', 'string', 'max:200'],
        ]);

        $asset = Asset::findOrFail($data['asset_id']);
        $member = isset($data['member_id']) ? Member::find($data['member_id']) : null;
        $from = CarbonImmutable::parse($data['starts_on']);
        $to = CarbonImmutable::parse($data['ends_on']);
        $rate = isset($data['rate']) && $data['rate'] !== '' ? $this->cents($data['rate']) : $asset->hire_rate;

        if ($rate === null || $rate <= 0) {
            throw new RuleViolation("No hire rate is set for {$asset->name} yet. Enter the agreed rate for this booking.");
        }

        $available = $asset->availableBetween($from, $to);
        if ($data['quantity'] > $available) {
            throw new RuleViolation("Only {$available} {$asset->name} are free for those dates.");
        }

        $days = (int) $from->diffInDays($to) + 1;

        $hire = Hire::create([
            'asset_id' => $asset->id,
            'member_id' => $member?->id,
            'hirer_name' => $data['hirer_name'] ?? $member->name,
            'hirer_phone' => Phone::normalize($data['hirer_phone'] ?? null) ?? ($member ? Phone::normalize($member->phone) : null),
            'quantity' => $data['quantity'],
            'starts_on' => $from->toDateString(),
            'ends_on' => $to->toDateString(),
            'rate' => $rate,
            'total' => $rate * $data['quantity'] * $days,
            'notes' => $data['notes'] ?? null,
        ]);

        Audit::log('hire.booked', "Booked {$hire->quantity} {$asset->name} for {$hire->hirer_name}", $hire);
        $this->toast('Booked. Total '.Money::format($hire->total)." for {$days} day(s).");

        return back();
    }

    public function status(Request $request, Hire $hire): RedirectResponse
    {
        Gate::authorize(Permission::AssetsManage->value);

        $data = $request->validate(['status' => ['required', Rule::in(['out', 'returned', 'cancelled'])]]);

        $allowed = [
            'booked' => ['out', 'cancelled'],
            'out' => ['returned'],
        ];

        if (! in_array($data['status'], $allowed[$hire->status] ?? [], true)) {
            throw new RuleViolation("A {$hire->status} hire cannot be marked {$data['status']}.");
        }

        if ($data['status'] === 'cancelled' && $hire->paid > 0) {
            throw new RuleViolation('This hire has payments. Refund them through a payout before cancelling.');
        }

        $hire->update(['status' => $data['status']]);
        Audit::log("hire.{$data['status']}", "Hire #{$hire->id} for {$hire->hirer_name} marked {$data['status']}", $hire);
        $this->toast('Hire updated.');

        return back();
    }

    public function pay(Request $request, Hire $hire, RecordPayment $record): RedirectResponse
    {
        Gate::authorize(Permission::AssetsManage->value);

        $data = $request->validate([
            'amount' => Money::rule(),
            'channel' => ['required', Rule::in(['cash', 'paybill', 'manual'])],
            'reference' => ['nullable', 'required_if:channel,paybill', 'string', 'max:100'],
        ]);

        $record->handle(
            $this->group(),
            $data['channel'],
            $this->cents($data['amount']),
            'hire',
            $hire->member,
            $hire,
            $data['reference'] ?? null,
            payerName: $hire->hirer_name,
            phone: $hire->hirer_phone,
        );

        $this->toast('Hire payment recorded.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'category' => ['required', Rule::in(array_keys(Asset::CATEGORIES))],
            'quantity' => ['required', 'integer', 'min:1'],
            'hire_rate' => Money::rule(false, 0),
            'purchase_cost' => Money::rule(false, 0),
            'acquired_on' => ['nullable', 'date'],
            'condition' => ['required', Rule::in(['new', 'good', 'fair', 'needs_repair'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $data['hire_rate'] = isset($data['hire_rate']) && $data['hire_rate'] !== '' ? $this->cents($data['hire_rate']) : null;
        $data['purchase_cost'] = isset($data['purchase_cost']) && $data['purchase_cost'] !== '' ? $this->cents($data['purchase_cost']) : null;

        return $data;
    }
}
