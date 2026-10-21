<?php

namespace App\Http\Controllers\Members;

use App\Domain\Members\Actions\GiveMemberLogin;
use App\Domain\Members\Actions\MemberExit;
use App\Domain\Members\Models\Member;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Logins, exit notices, exits and deaths.
 */
class MemberLifecycleController extends Controller
{
    public function issueLogin(Member $member, GiveMemberLogin $login): RedirectResponse
    {
        $login->handle($member);
        $this->toast("Login details sent to {$member->name} by SMS".($member->email ? ' and email' : '').'.');

        return back();
    }

    public function notice(Request $request, Member $member, MemberExit $exit): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'notice_on' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $exit->giveNotice($member, $data['reason'], CarbonImmutable::parse($data['notice_on']));
        $this->toast("Exit notice recorded. Refund can be processed from {$exit->noticeEndsOn($member)->format('j M Y')}.");

        return back();
    }

    public function withdraw(Member $member, MemberExit $exit): RedirectResponse
    {
        $exit->withdrawNotice($member);
        $this->toast("{$member->name} is active again.");

        return back();
    }

    public function process(Request $request, Member $member, MemberExit $exit): RedirectResponse
    {
        $payout = $exit->process($member, $request->boolean('ignore_notice'));

        if ($payout === null) {
            $this->toast("{$member->name} has left. There were no savings to refund.");

            return back();
        }

        $this->toast("Exit refund raised as payout #{$payout->number}. It needs two approvals.");

        return to_route('payouts.show', $payout);
    }

    public function death(Request $request, Member $member, MemberExit $exit): RedirectResponse
    {
        $data = $request->validate(['died_on' => ['required', 'date', 'before_or_equal:today']]);

        $case = $exit->recordDeath($member, CarbonImmutable::parse($data['died_on']));
        $this->toast('Recorded. A welfare case has been opened; process the exit to refund savings to the nominee.');

        return to_route('welfare.show', $case);
    }
}
