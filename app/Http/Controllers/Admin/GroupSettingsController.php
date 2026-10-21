<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Audit;
use App\Domain\Groups\Support\GroupSettings;
use App\Http\Controllers\Controller;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The group's rules. Every change is logged with the resolution that
 * agreed it, so the audit trail shows why a number changed.
 */
class GroupSettingsController extends Controller
{
    private const MONEY_KEYS = ['contribution_amount', 'mgr_share', 'welfare_share', 'late_fine_amount'];

    public function edit(): Response
    {
        $group = $this->group();
        $settings = $group->settings()->all();

        foreach (self::MONEY_KEYS as $key) {
            $settings[$key] = Money::toShillings((int) $settings[$key]);
        }

        foreach ($settings['welfare_benefits'] as $key => $value) {
            $settings['welfare_benefits'][$key] = Money::toShillings((int) $value);
        }

        return Inertia::render('rules/edit', [
            'group' => [
                'name' => $group->name,
                'registration_number' => $group->registration_number,
                'phone' => $group->phone ? Phone::display($group->phone) : null,
                'email' => $group->email,
                'location' => $group->location,
                'paybill' => $group->paybill,
                'paybill_account' => $group->paybill_account,
            ],
            'settings' => $settings,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'group.name' => ['required', 'string', 'max:120'],
            'group.registration_number' => ['nullable', 'string', 'max:60'],
            'group.phone' => ['nullable', 'string', 'max:20'],
            'group.email' => ['nullable', 'email', 'max:120'],
            'group.location' => ['nullable', 'string', 'max:120'],
            'group.paybill' => ['nullable', 'string', 'max:20'],
            'group.paybill_account' => ['nullable', 'string', 'max:40'],
            'settings.contribution_amount' => Money::rule(true, 0),
            'settings.mgr_share' => Money::rule(true, 0),
            'settings.welfare_share' => Money::rule(true, 0),
            'settings.contribution_due_day' => ['required', 'integer', 'min:1', 'max:28'],
            'settings.late_fine_amount' => Money::rule(true, 0),
            'settings.late_fine_grace_days' => ['required', 'integer', 'min:0', 'max:60'],
            'settings.reminder_days_before' => ['required', 'integer', 'min:0', 'max:14'],
            'settings.exit_notice_days' => ['required', 'integer', 'min:0', 'max:180'],
            'settings.exit_refund_days' => ['required', 'integer', 'min:0', 'max:180'],
            'settings.payout_approvals_required' => ['required', 'integer', 'min:2', 'max:3'],
            'settings.mgr_skip_members_in_arrears' => ['boolean'],
            'settings.show_contributor_names_by_default' => ['boolean'],
            'settings.payer_pays_mpesa_fees' => ['boolean'],
            'settings.sms_costs_from_group_fund' => ['boolean'],
            'settings.project_profit_basis' => ['required', Rule::in(['contribution', 'equal'])],
            'settings.share_out_month' => ['required', 'integer', 'min:1', 'max:12'],
            'settings.sms_sender_id' => ['required', 'string', 'max:11'],
            'settings.welfare_benefits' => ['required', 'array'],
            'settings.welfare_benefits.*' => Money::rule(true, 0),
            'resolution_reference' => ['required', 'string', 'max:60'],
        ], [
            'resolution_reference.required' => 'Enter the resolution or meeting that agreed this change.',
        ]);

        $group = $this->group();
        $new = $data['settings'];

        foreach (self::MONEY_KEYS as $key) {
            $new[$key] = $this->cents($new[$key]);
        }

        foreach ($new['welfare_benefits'] as $key => $value) {
            if (! array_key_exists($key, GroupSettings::DEFAULTS['welfare_benefits'])) {
                unset($new['welfare_benefits'][$key]);

                continue;
            }
            $new['welfare_benefits'][$key] = $this->cents($value);
        }

        if ($new['contribution_amount'] < $new['mgr_share'] + $new['welfare_share']) {
            return back()->withErrors(['settings.mgr_share' => 'The merry-go-round and welfare shares cannot add up to more than the contribution.']);
        }

        $before = $group->settings()->all();
        $new['resolution_reference'] = $data['resolution_reference'];
        $new['loans_enabled'] = false;

        $group->fill([
            ...$data['group'],
            'phone' => Phone::normalize($data['group']['phone'] ?? null) ?? ($data['group']['phone'] ?? null),
            'settings' => $new,
        ])->save();

        $changed = collect($new)->filter(fn ($v, $k) => ($before[$k] ?? null) != $v && $k !== 'resolution_reference')->keys()->all();

        Audit::log('settings.updated', 'Group rules updated under resolution '.$data['resolution_reference'], $group, [
            'changed' => $changed,
            'before' => array_intersect_key($before, array_flip($changed)),
            'after' => array_intersect_key($new, array_flip($changed)),
        ]);

        $this->toast('Group rules saved. New amounts apply to dues raised from now on.');

        return back();
    }
}
