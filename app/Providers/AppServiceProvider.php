<?php

namespace App\Providers;

use App\Domain\Access\Permission;
use App\Domain\Assets\Models\Hire;
use App\Domain\Dues\Models\Due;
use App\Domain\Fundraisers\Models\Fundraiser;
use App\Domain\Groups\CurrentGroup;
use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Loans\Models\Loan;
use App\Domain\Members\Models\Member;
use App\Domain\Mgr\Models\MgrCycle;
use App\Domain\Mgr\Models\MgrSlot;
use App\Domain\Payments\Gateway\GatewayManager;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payouts\Models\Payout;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\Sale;
use App\Domain\Welfare\Models\WelfareCase;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentGroup::class);
        $this->app->scoped(ChartOfAccounts::class);
        $this->app->singleton(GatewayManager::class);
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->configurePermissions();
        $this->configureRateLimits();

        Relation::morphMap([
            'member' => Member::class,
            'payment' => Payment::class,
            'payout' => Payout::class,
            'due' => Due::class,
            'mgr_cycle' => MgrCycle::class,
            'mgr_slot' => MgrSlot::class,
            'welfare_case' => WelfareCase::class,
            'project' => Project::class,
            'sale' => Sale::class,
            'hire' => Hire::class,
            'fundraiser' => Fundraiser::class,
            'loan' => Loan::class,
            'user' => User::class,
        ]);
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(10)
                ->letters()
                ->numbers()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Position permissions are checked with the normal Gate, so routes can
     * use ->can('payouts.approve') and React gets the same list.
     */
    protected function configurePermissions(): void
    {
        Gate::before(function (User $user, string $ability) {
            if (Permission::tryFrom($ability) === null) {
                return null;
            }

            return $user->hasPermission($ability);
        });
    }

    protected function configureRateLimits(): void
    {
        RateLimiter::for('stk', fn (Request $request) => [
            Limit::perMinute(5)->by('stk-ip:'.$request->ip()),
            Limit::perHour(10)->by('stk-phone:'.preg_replace('/\D/', '', (string) $request->input('phone'))),
        ]);
    }
}
