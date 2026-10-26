import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { FormDialog, Tabs } from '@/components/kertai/actions';
import {
    Field,
    MemberSelect,
    MoneyInput,
    Select,
    Textarea,
} from '@/components/kertai/fields';
import { InterestFields, LoansTable } from '@/components/kertai/loans';
import type { LoanRow } from '@/components/kertai/loans';
import {
    EmptyState,
    Page,
    PageHeader,
    Section,
    StatCard,
} from '@/components/kertai/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatKes } from '@/lib/format';
import type { Option } from '@/types/kertai';

type Props = {
    loans: LoanRow[];
    summary: {
        principal: number;
        interest: number;
        active: number;
        overdue: number;
        overdue_amount: number;
        lendable: number;
        interest_charged: number;
        interest_collected: number;
    };
    rules: {
        loans_enabled: boolean;
        interest_on: boolean;
        interest_rate: number;
        interest_method: string;
        interest_period: string;
        repayment_order: string;
    };
    members: Option[];
    countedIn: Option[];
    can: { manage: boolean };
};

type Tab = 'running' | 'applications' | 'closed';

export default function LoansIndex({
    loans,
    summary,
    rules,
    members,
    countedIn,
    can,
}: Props) {
    const [tab, setTab] = useState<Tab>('running');
    const groups: Record<Tab, LoanRow[]> = {
        running: loans.filter((l) => l.status === 'active'),
        applications: loans.filter((l) =>
            ['pending', 'submitted'].includes(l.status),
        ),
        closed: loans.filter(
            (l) => !['active', 'pending', 'submitted'].includes(l.status),
        ),
    };

    return (
        <>
            <Head title="Loans" />
            <Page>
                <PageHeader
                    title="Loans"
                    description={
                        <>
                            {rules.interest_on
                                ? `Interest is on: new loans ${rules.interest_rate}% ${rules.interest_period === 'once' ? 'once' : 'a month'}.`
                                : 'Interest is off for the whole group.'}{' '}
                            {rules.loans_enabled
                                ? 'Members can apply for new loans.'
                                : 'New lending is off.'}{' '}
                            <Link href="/rules" className="underline">
                                Group rules
                            </Link>
                        </>
                    }
                    actions={
                        can.manage && (
                            <BringForwardDialog
                                members={members}
                                countedIn={countedIn}
                                rules={rules}
                            />
                        )
                    }
                />

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <StatCard
                        label="Owed to the group"
                        value={formatKes(summary.principal + summary.interest)}
                        hint={`${summary.active} running · ${formatKes(summary.interest)} of it interest`}
                    />
                    <StatCard
                        label="Past due date"
                        value={formatKes(summary.overdue_amount)}
                        tone={summary.overdue > 0 ? 'bad' : 'default'}
                        hint={`${summary.overdue} ${summary.overdue === 1 ? 'loan' : 'loans'}`}
                    />
                    <StatCard
                        label="Available to lend"
                        value={formatKes(summary.lendable)}
                        hint="Savings and group fund, less loans out, up to the cash held"
                    />
                    <StatCard
                        label="Interest this year"
                        value={formatKes(summary.interest_collected)}
                        tone="good"
                        hint={`Collected, of ${formatKes(summary.interest_charged)} charged`}
                    />
                </div>

                <Tabs
                    value={tab}
                    onChange={setTab}
                    tabs={[
                        {
                            value: 'running',
                            label: 'Running',
                            count: groups.running.length,
                        },
                        {
                            value: 'applications',
                            label: 'Applications',
                            count: groups.applications.length,
                        },
                        { value: 'closed', label: 'Closed' },
                    ]}
                />

                <Section>
                    {groups[tab].length === 0 ? (
                        <EmptyState
                            title={
                                tab === 'running'
                                    ? 'No loans running'
                                    : tab === 'applications'
                                      ? 'No applications waiting'
                                      : 'No closed loans yet'
                            }
                        >
                            {tab === 'running' &&
                                can.manage &&
                                'Record loans members took before the group used Kertai with "Record old loan".'}
                        </EmptyState>
                    ) : (
                        <LoansTable
                            loans={groups[tab]}
                            groupInterestOn={rules.interest_on}
                        />
                    )}
                </Section>
            </Page>
        </>
    );
}

function BringForwardDialog({
    members,
    countedIn,
    rules,
}: {
    members: Option[];
    countedIn: Option[];
    rules: Props['rules'];
}) {
    return (
        <FormDialog
            wide
            title="Record a loan from before Kertai"
            description="Enter what the member still owes today. Repayments from now on are recorded against it."
            action="/loans"
            submitLabel="Record loan"
            initial={{
                member_id: '',
                principal: '',
                interest: '',
                issued_on: '',
                due_on: '',
                instalment: '',
                interest_rate: String(rules.interest_rate),
                interest_method: rules.interest_method,
                interest_period: rules.interest_period,
                interest_paused: false,
                counted_in: countedIn[0] ? String(countedIn[0].id) : '',
                purpose: '',
                resolution_reference: '',
                notes: '',
            }}
            trigger={
                <Button>
                    <Plus /> Record old loan
                </Button>
            }
        >
            {(form) => (
                <>
                    <Field
                        label="Member"
                        htmlFor="bf-member"
                        error={form.errors.member_id}
                    >
                        <MemberSelect
                            id="bf-member"
                            members={members}
                            value={form.data.member_id}
                            onChange={(e) =>
                                form.setData('member_id', e.target.value)
                            }
                        />
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Principal still owed"
                            htmlFor="bf-principal"
                            error={form.errors.principal}
                            hint="The part of the amount lent not yet repaid."
                        >
                            <MoneyInput
                                id="bf-principal"
                                value={form.data.principal}
                                onChange={(e) =>
                                    form.setData('principal', e.target.value)
                                }
                            />
                        </Field>
                        <Field
                            label="Interest still owed"
                            htmlFor="bf-interest"
                            error={form.errors.interest}
                            hint="Interest charged before today and not paid. 0 if none."
                        >
                            <MoneyInput
                                id="bf-interest"
                                value={form.data.interest}
                                onChange={(e) =>
                                    form.setData('interest', e.target.value)
                                }
                            />
                        </Field>
                        <Field
                            label="Date the loan was given"
                            htmlFor="bf-issued"
                            error={form.errors.issued_on}
                            hint="Interest falls due on this day each month."
                        >
                            <Input
                                id="bf-issued"
                                type="date"
                                value={form.data.issued_on}
                                onChange={(e) =>
                                    form.setData('issued_on', e.target.value)
                                }
                            />
                        </Field>
                        <Field
                            label="Date it should be cleared (optional)"
                            htmlFor="bf-due"
                            error={form.errors.due_on}
                        >
                            <Input
                                id="bf-due"
                                type="date"
                                value={form.data.due_on}
                                onChange={(e) =>
                                    form.setData('due_on', e.target.value)
                                }
                            />
                        </Field>
                        <Field
                            label="Agreed monthly repayment (optional)"
                            htmlFor="bf-instalment"
                            error={form.errors.instalment}
                            hint="Used to show when the member falls behind."
                        >
                            <MoneyInput
                                id="bf-instalment"
                                value={form.data.instalment}
                                onChange={(e) =>
                                    form.setData('instalment', e.target.value)
                                }
                            />
                        </Field>
                        <Field
                            label="In the books today"
                            htmlFor="bf-counted"
                            error={form.errors.counted_in}
                            hint="If an opening bank balance already included this money, choose the bank."
                        >
                            <Select
                                id="bf-counted"
                                value={form.data.counted_in}
                                onChange={(e) =>
                                    form.setData('counted_in', e.target.value)
                                }
                                options={countedIn.map((o) => ({
                                    value: o.id,
                                    label: o.label,
                                }))}
                            />
                        </Field>
                    </div>
                    <InterestFields
                        data={form.data}
                        errors={form.errors}
                        set={(key, value) => form.setData(key, value as never)}
                        idPrefix="bf"
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label="What the loan was for (optional)"
                            htmlFor="bf-purpose"
                            error={form.errors.purpose}
                        >
                            <Input
                                id="bf-purpose"
                                value={form.data.purpose}
                                onChange={(e) =>
                                    form.setData('purpose', e.target.value)
                                }
                            />
                        </Field>
                        <Field
                            label="Minute or resolution (optional)"
                            htmlFor="bf-resolution"
                            error={form.errors.resolution_reference}
                        >
                            <Input
                                id="bf-resolution"
                                value={form.data.resolution_reference}
                                onChange={(e) =>
                                    form.setData(
                                        'resolution_reference',
                                        e.target.value,
                                    )
                                }
                            />
                        </Field>
                    </div>
                    <Field
                        label="Notes (officials only)"
                        htmlFor="bf-notes"
                        error={form.errors.notes}
                    >
                        <Textarea
                            id="bf-notes"
                            value={form.data.notes}
                            onChange={(e) =>
                                form.setData('notes', e.target.value)
                            }
                            className="min-h-16"
                        />
                    </Field>
                </>
            )}
        </FormDialog>
    );
}

LoansIndex.layout = { breadcrumbs: [{ title: 'Loans', href: '/loans' }] };
