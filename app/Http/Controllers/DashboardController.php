<?php

namespace App\Http\Controllers;

use App\Domain\Access\Permission;
use App\Domain\Dues\Models\Due;
use App\Domain\Fundraisers\Models\Fundraiser;
use App\Domain\Ledger\Ledger;
use App\Domain\Ledger\Models\Account;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Members\MemberFinance;
use App\Domain\Members\Models\Member;
use App\Domain\Mgr\Models\MgrSlot;
use App\Domain\Notifications\Models\OutboundMessage;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payouts\Models\Payout;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, MemberFinance $finance, Ledger $ledger): Response
    {
        $user = $request->user();
        $member = $user->member;

        $nextTurn = MgrSlot::query()
            ->with('cycle')
            ->where('member_id', $member->id)
            ->whereIn('status', ['upcoming', 'requested'])
            ->orderBy('scheduled_on')
            ->first();

        $props = [
            'me' => [
                ...$finance->summary($member),
                'next_turn' => $nextTurn ? [
                    'cycle' => $nextTurn->cycle->name,
                    'position' => $nextTurn->position,
                    'date' => $nextTurn->scheduled_on->toDateString(),
                    'amount' => $nextTurn->cycle->payout_amount,
                ] : null,
                'phone' => $member->phone,
                'recent_payments' => Payment::query()
                    ->where('member_id', $member->id)
                    ->latest()
                    ->limit(5)
                    ->get()
                    ->map(fn (Payment $p) => [
                        'id' => $p->id,
                        'amount' => $p->amount,
                        'status' => $p->status,
                        'channel' => $p->channel,
                        'purpose' => $p->purpose,
                        'date' => ($p->received_at ?? $p->created_at)->toDateString(),
                        'receipt' => $p->receipt_number,
                    ]),
            ],
            'meetings' => Meeting::query()
                ->where('status', 'scheduled')
                ->where('scheduled_at', '>=', CarbonImmutable::today())
                ->orderBy('scheduled_at')
                ->limit(3)
                ->get()
                ->map(fn (Meeting $m) => ['id' => $m->id, 'title' => $m->title, 'scheduled_at' => $m->scheduled_at->toIso8601String(), 'venue' => $m->venue, 'online_link' => $m->online_link]),
            'fundraisers' => Fundraiser::query()
                ->where('status', 'active')
                ->latest()
                ->limit(3)
                ->get()
                ->map(fn (Fundraiser $f) => ['id' => $f->id, 'title' => $f->title, 'slug' => $f->slug, 'target' => $f->target, 'raised' => $f->raised()]),
            'finance' => null,
            'admin' => null,
        ];

        if ($user->hasPermission(Permission::FinanceView->value)) {
            $monthStart = CarbonImmutable::today()->startOfMonth();

            $props['finance'] = [
                'accounts' => Account::query()->where('is_active', true)->orderBy('code')->get()
                    ->map(fn (Account $a) => ['code' => $a->code, 'name' => $a->name, 'kind' => $a->kind, 'balance' => $ledger->balance($a)]),
                'collected_this_month' => (int) Payment::query()->succeeded()->where('received_at', '>=', $monthStart)->sum('amount'),
                'arrears' => (int) Due::query()->overdue()->get()->sum(fn (Due $d) => $d->outstanding()),
                'members' => Member::query()->active()->count(),
                'unmatched' => Payment::query()->unallocated()->count(),
                'pending_stk' => Payment::query()->where('channel', 'simulinkpay')->whereIn('status', ['pending', 'processing'])->count(),
                'awaiting_my_approval' => $user->hasPermission(Permission::PayoutsApprove->value)
                    ? Payout::query()->awaitingDecisionBy($user)->count()
                    : 0,
                'awaiting_payment' => Payout::query()->where('status', 'approved')->count(),
            ];
        }

        if ($user->hasPermission(Permission::IntegrationsManage->value) || $user->hasPermission(Permission::MessagesSend->value)) {
            $props['admin'] = [
                'failed_messages' => OutboundMessage::query()->where('status', 'failed')->count(),
                'queued_messages' => OutboundMessage::query()->where('status', 'queued')->count(),
                'members_without_login' => Member::query()->active()->whereNull('user_id')->count(),
            ];
        }

        return Inertia::render('dashboard', $props);
    }
}
