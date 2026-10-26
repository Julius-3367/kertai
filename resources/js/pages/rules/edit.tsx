import { Head, useForm } from '@inertiajs/react';
import { actionError, fieldError } from '@/components/kertai/actions';
import {
    ActionError,
    CheckField,
    Field,
    MoneyInput,
    Select,
} from '@/components/kertai/fields';
import { Page, PageHeader, Section } from '@/components/kertai/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { titleCase } from '@/lib/format';

type Settings = {
    contribution_amount: string;
    mgr_share: string;
    welfare_share: string;
    contribution_due_day: number;
    dues_start_on: string | null;
    late_fine_amount: string;
    late_fine_grace_days: number;
    reminder_days_before: number;
    exit_notice_days: number;
    exit_refund_days: number;
    payout_approvals_required: number;
    mgr_skip_members_in_arrears: boolean;
    show_contributor_names_by_default: boolean;
    payer_pays_mpesa_fees: boolean;
    sms_costs_from_group_fund: boolean;
    project_profit_basis: string;
    share_out_month: number;
    sms_sender_id: string;
    welfare_benefits: Record<string, string>;
    resolution_reference: string | null;
};

type Group = {
    name: string;
    registration_number: string | null;
    phone: string | null;
    email: string | null;
    location: string | null;
    paybill: string | null;
    paybill_account: string | null;
};

const MONTHS = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

export default function RulesEdit({
    group,
    settings,
}: {
    group: Group;
    settings: Settings;
}) {
    const form = useForm({
        group: {
            name: group.name,
            registration_number: group.registration_number ?? '',
            phone: group.phone ?? '',
            email: group.email ?? '',
            location: group.location ?? '',
            paybill: group.paybill ?? '',
            paybill_account: group.paybill_account ?? '',
        },
        settings: { ...settings, resolution_reference: undefined },
        resolution_reference: '',
    });

    const s = form.data.settings;
    const setS = <K extends keyof Settings>(key: K, value: Settings[K]) =>
        form.setData('settings', { ...form.data.settings, [key]: value });
    const setG = (key: keyof Group, value: string) =>
        form.setData('group', { ...form.data.group, [key]: value });
    const err = (key: string) => fieldError(form.errors, key);
    const savings =
        Number(s.contribution_amount || 0) -
        Number(s.mgr_share || 0) -
        Number(s.welfare_share || 0);

    return (
        <>
            <Head title="Group rules" />
            <Page className="max-w-4xl">
                <PageHeader
                    title="Group rules"
                    description={
                        settings.resolution_reference
                            ? `Last changed under resolution ${settings.resolution_reference}.`
                            : 'Standard settings. Change them only after the group agrees.'
                    }
                />
                <form
                    className="grid gap-6"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put('/rules', { preserveScroll: true });
                    }}
                >
                    <ActionError message={actionError(form.errors)} />

                    <Section title="The group">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                label="Name"
                                htmlFor="g-name"
                                error={err('group.name')}
                            >
                                <Input
                                    id="g-name"
                                    value={form.data.group.name}
                                    onChange={(e) =>
                                        setG('name', e.target.value)
                                    }
                                />
                            </Field>
                            <Field label="Registration number" htmlFor="g-reg">
                                <Input
                                    id="g-reg"
                                    value={form.data.group.registration_number}
                                    onChange={(e) =>
                                        setG(
                                            'registration_number',
                                            e.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field label="Location" htmlFor="g-loc">
                                <Input
                                    id="g-loc"
                                    value={form.data.group.location}
                                    onChange={(e) =>
                                        setG('location', e.target.value)
                                    }
                                />
                            </Field>
                            <Field label="Phone" htmlFor="g-phone">
                                <Input
                                    id="g-phone"
                                    value={form.data.group.phone}
                                    onChange={(e) =>
                                        setG('phone', e.target.value)
                                    }
                                />
                            </Field>
                            <Field
                                label="Email"
                                htmlFor="g-email"
                                error={err('group.email')}
                            >
                                <Input
                                    id="g-email"
                                    value={form.data.group.email}
                                    onChange={(e) =>
                                        setG('email', e.target.value)
                                    }
                                />
                            </Field>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label="Paybill" htmlFor="g-paybill">
                                    <Input
                                        id="g-paybill"
                                        value={form.data.group.paybill}
                                        onChange={(e) =>
                                            setG('paybill', e.target.value)
                                        }
                                    />
                                </Field>
                                <Field label="Account" htmlFor="g-account">
                                    <Input
                                        id="g-account"
                                        value={form.data.group.paybill_account}
                                        onChange={(e) =>
                                            setG(
                                                'paybill_account',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </div>
                        </div>
                    </Section>

                    <Section
                        title="Monthly contribution"
                        description={`Split: merry-go-round, then welfare, then savings (${savings >= 0 ? `KES ${savings.toLocaleString()}` : 'too little left'}).`}
                    >
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Field
                                label="Contribution"
                                htmlFor="s-amount"
                                error={err('settings.contribution_amount')}
                            >
                                <MoneyInput
                                    id="s-amount"
                                    value={s.contribution_amount}
                                    onChange={(e) =>
                                        setS(
                                            'contribution_amount',
                                            e.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                label="To the merry-go-round"
                                htmlFor="s-mgr"
                                error={err('settings.mgr_share')}
                            >
                                <MoneyInput
                                    id="s-mgr"
                                    value={s.mgr_share}
                                    onChange={(e) =>
                                        setS('mgr_share', e.target.value)
                                    }
                                />
                            </Field>
                            <Field
                                label="To welfare"
                                htmlFor="s-welfare"
                                error={err('settings.welfare_share')}
                            >
                                <MoneyInput
                                    id="s-welfare"
                                    value={s.welfare_share}
                                    onChange={(e) =>
                                        setS('welfare_share', e.target.value)
                                    }
                                />
                            </Field>
                            <Field
                                label="Due on day of month"
                                htmlFor="s-dueday"
                                error={err('settings.contribution_due_day')}
                            >
                                <Input
                                    id="s-dueday"
                                    type="number"
                                    min={1}
                                    max={28}
                                    value={s.contribution_due_day}
                                    onChange={(e) =>
                                        setS(
                                            'contribution_due_day',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                label="Contributions start on"
                                htmlFor="s-start"
                                error={err('settings.dues_start_on')}
                            >
                                <Input
                                    id="s-start"
                                    type="date"
                                    value={s.dues_start_on ?? ''}
                                    onChange={(e) =>
                                        setS(
                                            'dues_start_on',
                                            e.target.value || null,
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                label="Remind this many days before"
                                htmlFor="s-remind"
                            >
                                <Input
                                    id="s-remind"
                                    type="number"
                                    min={0}
                                    max={14}
                                    value={s.reminder_days_before}
                                    onChange={(e) =>
                                        setS(
                                            'reminder_days_before',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </Field>
                        </div>
                    </Section>

                    <Section title="Fines">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                label="Flat fine for paying late"
                                htmlFor="s-fine"
                                error={err('settings.late_fine_amount')}
                            >
                                <MoneyInput
                                    id="s-fine"
                                    value={s.late_fine_amount}
                                    onChange={(e) =>
                                        setS('late_fine_amount', e.target.value)
                                    }
                                />
                            </Field>
                            <Field
                                label="Days of grace after the due day"
                                htmlFor="s-grace"
                            >
                                <Input
                                    id="s-grace"
                                    type="number"
                                    min={0}
                                    value={s.late_fine_grace_days}
                                    onChange={(e) =>
                                        setS(
                                            'late_fine_grace_days',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </Field>
                        </div>
                    </Section>

                    <Section title="Welfare benefits">
                        <div className="grid gap-4 sm:grid-cols-3">
                            {Object.entries(s.welfare_benefits).map(
                                ([key, value]) => (
                                    <Field
                                        key={key}
                                        label={titleCase(key)}
                                        htmlFor={`wb-${key}`}
                                        error={err(
                                            `settings.welfare_benefits.${key}`,
                                        )}
                                    >
                                        <MoneyInput
                                            id={`wb-${key}`}
                                            value={value}
                                            onChange={(e) =>
                                                setS('welfare_benefits', {
                                                    ...s.welfare_benefits,
                                                    [key]: e.target.value,
                                                })
                                            }
                                        />
                                    </Field>
                                ),
                            )}
                        </div>
                    </Section>

                    <Section title="Payouts, exits and the year end">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                label="Approvals needed per payout"
                                htmlFor="s-appr"
                            >
                                <Select
                                    id="s-appr"
                                    value={String(s.payout_approvals_required)}
                                    onChange={(e) =>
                                        setS(
                                            'payout_approvals_required',
                                            Number(e.target.value),
                                        )
                                    }
                                    options={[
                                        { value: '2', label: 'Two officials' },
                                        {
                                            value: '3',
                                            label: 'Three officials',
                                        },
                                    ]}
                                />
                            </Field>
                            <Field
                                label="Exit notice (days)"
                                htmlFor="s-notice"
                            >
                                <Input
                                    id="s-notice"
                                    type="number"
                                    min={0}
                                    value={s.exit_notice_days}
                                    onChange={(e) =>
                                        setS(
                                            'exit_notice_days',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                label="Refund within (days)"
                                htmlFor="s-refund"
                            >
                                <Input
                                    id="s-refund"
                                    type="number"
                                    min={0}
                                    value={s.exit_refund_days}
                                    onChange={(e) =>
                                        setS(
                                            'exit_refund_days',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </Field>
                            <Field label="Share-out month" htmlFor="s-shareout">
                                <Select
                                    id="s-shareout"
                                    value={String(s.share_out_month)}
                                    onChange={(e) =>
                                        setS(
                                            'share_out_month',
                                            Number(e.target.value),
                                        )
                                    }
                                    options={MONTHS.map((m, i) => ({
                                        value: String(i + 1),
                                        label: m,
                                    }))}
                                />
                            </Field>
                            <Field
                                label="Project profit shared"
                                htmlFor="s-basis"
                            >
                                <Select
                                    id="s-basis"
                                    value={s.project_profit_basis}
                                    onChange={(e) =>
                                        setS(
                                            'project_profit_basis',
                                            e.target.value,
                                        )
                                    }
                                    options={[
                                        {
                                            value: 'contribution',
                                            label: 'In proportion to contribution',
                                        },
                                        { value: 'equal', label: 'Equally' },
                                    ]}
                                />
                            </Field>
                            <Field
                                label="SMS sender name"
                                htmlFor="s-sender"
                                error={err('settings.sms_sender_id')}
                                hint="Up to 11 characters, as registered with the SMS provider."
                            >
                                <Input
                                    id="s-sender"
                                    maxLength={11}
                                    value={s.sms_sender_id}
                                    onChange={(e) =>
                                        setS('sms_sender_id', e.target.value)
                                    }
                                />
                            </Field>
                        </div>
                        <div className="mt-4 grid gap-3">
                            <CheckField
                                id="s-skip"
                                label="Move members in arrears to the back of the merry-go-round"
                                checked={s.mgr_skip_members_in_arrears}
                                onChange={(v) =>
                                    setS('mgr_skip_members_in_arrears', v)
                                }
                            />
                            <CheckField
                                id="s-names"
                                label="Show contributor names on fundraisers by default"
                                checked={s.show_contributor_names_by_default}
                                onChange={(v) =>
                                    setS('show_contributor_names_by_default', v)
                                }
                            />
                            <CheckField
                                id="s-fees"
                                label="The payer covers M-Pesa charges"
                                checked={s.payer_pays_mpesa_fees}
                                onChange={(v) =>
                                    setS('payer_pays_mpesa_fees', v)
                                }
                            />
                            <CheckField
                                id="s-sms"
                                label="SMS costs are paid from the group fund"
                                checked={s.sms_costs_from_group_fund}
                                onChange={(v) =>
                                    setS('sms_costs_from_group_fund', v)
                                }
                            />
                        </div>
                        <p className="mt-3 text-xs text-muted-foreground">
                            Loans are switched off. They can be added later if
                            the group decides to lend.
                        </p>
                    </Section>

                    <Section title="Authority for this change">
                        <Field
                            label="Resolution or minute number"
                            htmlFor="res-ref"
                            error={err('resolution_reference')}
                            hint="Recorded in the audit log with every change."
                        >
                            <Input
                                id="res-ref"
                                placeholder="2026/04"
                                value={form.data.resolution_reference}
                                onChange={(e) =>
                                    form.setData(
                                        'resolution_reference',
                                        e.target.value,
                                    )
                                }
                            />
                        </Field>
                    </Section>

                    <div>
                        <Button disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Save rules
                        </Button>
                    </div>
                </form>
            </Page>
        </>
    );
}

RulesEdit.layout = { breadcrumbs: [{ title: 'Group rules', href: '/rules' }] };
