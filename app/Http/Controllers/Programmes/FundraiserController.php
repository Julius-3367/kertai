<?php

namespace App\Http\Controllers\Programmes;

use App\Domain\Access\Permission;
use App\Domain\Audit\Audit;
use App\Domain\Fundraisers\Models\Fundraiser;
use App\Domain\Fundraisers\Models\FundraiserPledge;
use App\Domain\Ledger\Ledger;
use App\Domain\Members\Models\Member;
use App\Domain\Payments\Models\Payment;
use App\Http\Controllers\Controller;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FundraiserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('fundraisers/index', [
            'fundraisers' => Fundraiser::query()->latest('starts_on')->get()->map(fn (Fundraiser $f) => [
                'id' => $f->id,
                'title' => $f->title,
                'slug' => $f->slug,
                'status' => $f->status,
                'target' => $f->target,
                'raised' => $f->raised(),
                'starts_on' => $f->starts_on->toDateString(),
                'ends_on' => $f->ends_on?->toDateString(),
                'public_url' => route('public.fundraiser', $f->slug),
            ]),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize(Permission::FundraisersManage->value);

        return Inertia::render('fundraisers/form', ['fundraiser' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize(Permission::FundraisersManage->value);

        $data = $this->validated($request);
        $fundraiser = Fundraiser::create([
            ...$data,
            'target' => $this->cents($data['target']),
            'slug' => $this->uniqueSlug($data['title']),
            'created_by' => $request->user()->id,
        ]);
        $fundraiser->fundAccount();

        Audit::log('fundraiser.created', "Created fundraiser {$fundraiser->title}", $fundraiser);
        $this->toast('Fundraiser created. Publish it when you are ready to share the link.');

        return to_route('fundraisers.show', $fundraiser);
    }

    public function show(Request $request, Fundraiser $fundraiser, Ledger $ledger): Response
    {
        $canManage = $request->user()->hasPermission(Permission::FundraisersManage->value)
            || $request->user()->hasPermission(Permission::FinanceView->value);

        $payments = $fundraiser->payments()->with('member:id,name')->latest()->get();
        $succeeded = $payments->where('status', 'succeeded');

        return Inertia::render('fundraisers/show', [
            'fundraiser' => [
                ...$this->present($fundraiser),
                'raised' => (int) $succeeded->sum('amount'),
                'balance' => $ledger->balance($fundraiser->fundAccount()),
                'account_id' => $fundraiser->account_id,
                'public_url' => route('public.fundraiser', $fundraiser->slug),
            ],
            'stats' => [
                'contributions' => $succeeded->count(),
                'by_channel' => $succeeded->groupBy('channel')->map(fn ($g) => (int) $g->sum('amount')),
                'members' => $succeeded->whereNotNull('member_id')->sum('amount'),
                'non_members' => $succeeded->whereNull('member_id')->sum('amount'),
                'pledged' => (int) $fundraiser->pledges()->sum('amount'),
            ],
            'payments' => $canManage ? $payments->map(fn (Payment $p) => [
                'id' => $p->id,
                'name' => $p->member?->name ?? $p->payer_name ?? 'Anonymous',
                'phone' => $p->phone ? Phone::display($p->phone) : null,
                'amount' => $p->amount,
                'channel' => $p->channel,
                'status' => $p->status,
                'receipt' => $p->receipt_number,
                'show_name' => $p->show_name,
                'date' => ($p->received_at ?? $p->created_at)->toIso8601String(),
            ]) : [],
            'pledges' => $fundraiser->pledges->map(function (FundraiserPledge $pl) use ($succeeded) {
                $paid = $pl->phone ? (int) $succeeded->where('phone', $pl->phone)->sum('amount') : 0;

                return [
                    'id' => $pl->id,
                    'name' => $pl->name,
                    'phone' => $pl->phone ? Phone::display($pl->phone) : null,
                    'amount' => $pl->amount,
                    'paid' => $paid,
                    'note' => $pl->note,
                ];
            }),
            'canManage' => $request->user()->hasPermission(Permission::FundraisersManage->value),
            'members' => $this->memberOptions(),
        ]);
    }

    public function edit(Fundraiser $fundraiser): Response
    {
        Gate::authorize(Permission::FundraisersManage->value);

        return Inertia::render('fundraisers/form', ['fundraiser' => $this->present($fundraiser)]);
    }

    public function update(Request $request, Fundraiser $fundraiser): RedirectResponse
    {
        Gate::authorize(Permission::FundraisersManage->value);

        $data = $this->validated($request);
        $fundraiser->update([...$data, 'target' => $this->cents($data['target'])]);
        Audit::log('fundraiser.updated', "Updated fundraiser {$fundraiser->title}", $fundraiser);
        $this->toast('Fundraiser saved.');

        return to_route('fundraisers.show', $fundraiser);
    }

    public function status(Request $request, Fundraiser $fundraiser): RedirectResponse
    {
        Gate::authorize(Permission::FundraisersManage->value);

        $data = $request->validate(['status' => ['required', Rule::in(['draft', 'active', 'closed'])]]);
        $fundraiser->update(['status' => $data['status']]);
        Audit::log('fundraiser.status', "Fundraiser {$fundraiser->title} is now {$data['status']}", $fundraiser);

        $this->toast(match ($data['status']) {
            'active' => 'Published. Share the public link.',
            'closed' => 'Closed. The public page no longer accepts payments.',
            default => 'Moved back to draft.',
        });

        return back();
    }

    public function pledge(Request $request, Fundraiser $fundraiser): RedirectResponse
    {
        Gate::authorize(Permission::FundraisersManage->value);

        $data = $request->validate([
            'member_id' => ['nullable', 'integer', Rule::exists('members', 'id')->where('group_id', $this->group()->id)],
            'name' => ['nullable', 'required_without:member_id', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'amount' => Money::rule(),
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $member = isset($data['member_id']) ? Member::find($data['member_id']) : null;

        $fundraiser->pledges()->create([
            'member_id' => $member?->id,
            'name' => $data['name'] ?? $member->name,
            'phone' => Phone::normalize($data['phone'] ?? null) ?? ($member ? Phone::normalize($member->phone) : null),
            'amount' => $this->cents($data['amount']),
            'note' => $data['note'] ?? null,
        ]);

        $this->toast('Pledge recorded.');

        return back();
    }

    public function destroyPledge(Fundraiser $fundraiser, FundraiserPledge $pledge): RedirectResponse
    {
        Gate::authorize(Permission::FundraisersManage->value);
        abort_unless($pledge->fundraiser_id === $fundraiser->id, 404);

        $pledge->delete();
        $this->toast('Pledge removed.');

        return back();
    }

    public function export(Fundraiser $fundraiser): StreamedResponse
    {
        abort_unless(request()->user()->hasPermission(Permission::FundraisersManage->value)
            || request()->user()->hasPermission(Permission::FinanceView->value), 403);

        $payments = $fundraiser->payments()->with('member')->where('status', 'succeeded')->oldest()->get();

        return response()->streamDownload(function () use ($payments) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Name', 'Phone', 'Member', 'Channel', 'Receipt', 'Amount (KES)', 'Name shown publicly']);

            foreach ($payments as $p) {
                fputcsv($out, [
                    ($p->received_at ?? $p->created_at)->format('Y-m-d H:i'),
                    $p->member?->name ?? $p->payer_name,
                    $p->phone ? Phone::display($p->phone) : '',
                    $p->member?->member_number ?? '',
                    $p->channel,
                    $p->receipt_number,
                    Money::toShillings($p->amount),
                    $p->show_name ? 'yes' : 'no',
                ]);
            }

            fclose($out);
        }, Str::slug($fundraiser->title).'-contributions.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'summary' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'beneficiary' => ['nullable', 'string', 'max:120'],
            'target' => Money::rule(),
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'show_contributors' => ['boolean'],
        ]);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'fundraiser';
        $slug = $base;
        $i = 2;

        while (Fundraiser::withoutGlobalScopes()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Fundraiser $f): array
    {
        return [
            'id' => $f->id,
            'title' => $f->title,
            'slug' => $f->slug,
            'summary' => $f->summary,
            'description' => $f->description,
            'beneficiary' => $f->beneficiary,
            'target' => $f->target,
            'target_input' => Money::toShillings($f->target),
            'starts_on' => $f->starts_on->toDateString(),
            'ends_on' => $f->ends_on?->toDateString(),
            'status' => $f->status,
            'show_contributors' => $f->show_contributors,
        ];
    }
}
