<?php

namespace App\Http\Middleware;

use App\Domain\Groups\CurrentGroup;
use App\Domain\Loans\Models\Loan;
use App\Domain\Loans\Models\LoanGuarantor;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $member = $user?->member;
        $group = app(CurrentGroup::class)->get();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'email_verified_at' => $user->email_verified_at,
                    'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ] : null,
                'member' => $member ? [
                    'id' => $member->id,
                    'name' => $member->name,
                    'member_number' => $member->member_number,
                    'status' => $member->status,
                    'positions' => $member->positionNames(),
                    'has_loans' => Loan::query()->where('member_id', $member->id)->exists()
                        || LoanGuarantor::query()->where('member_id', $member->id)->exists(),
                ] : null,
                'permissions' => $user?->permissions() ?? [],
            ],
            'group' => $group ? [
                'name' => $group->name,
                'paybill' => $group->paybill,
                'paybill_account' => $group->paybill_account,
                'open_books' => $group->settings()->membersSeeGroupFinances(),
                'loans_enabled' => $group->settings()->loansEnabled(),
            ] : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
