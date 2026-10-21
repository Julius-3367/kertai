import { Head, router } from '@inertiajs/react';
import { Gavel, ListPlus, Timer } from 'lucide-react';
import { ActionButton, FormDialog } from '@/components/kertai/actions';
import {
    Field,
    MemberSelect,
    MoneyInput,
    Select,
} from '@/components/kertai/fields';
import {
    EmptyState,
    Page,
    PageHeader,
    Progress,
    Section,
    StatCard,
} from '@/components/kertai/page';
import { StatusBadge } from '@/components/kertai/status-badge';
import {
    Num,
    Table,
    TBody,
    TD,
    TH,
    THead,
    TR,
} from '@/components/kertai/table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useCan } from '@/hooks/use-can';
import { formatDate, formatKes, percent, titleCase } from '@/lib/format';
import type { Option } from '@/types/kertai';

type DueRow = {
    id: number;
    member: string;
    member_id: number;
    member_number: string;
    type: string;
    period: string | null;
    description: string;
    amount: number;
    paid: number;
    due_on: string;
    status: string;
    overdue: boolean;
    waived_reason: string | null;
};

export default function DuesIndex({
    dues,
    summary,
    filters,
    members,
    contribution,
}: {
    dues: DueRow[];
    summary: {
        expected: number;
        collected: number;
        members_paid: number;
        members_total: number;
        arrears_total: number;
    };
    filters: { period: string; type: string; status: string };
    members: Option[];
    contribution: { mgr: number; welfare: number; savings: number };
}) {
    const can = useCan();
    const apply = (next: Partial<typeof filters>) =>
        router.get(
            '/dues',
            { ...filters, ...next },
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title="Contributions" />
            <Page>
                <PageHeader
                    title="Contributions, levies and fines"
                    description={`Each contribution of ${formatKes(contribution.mgr + contribution.welfare + contribution.savings)} goes ${formatKes(contribution.mgr)} to the merry-go-round, ${formatKes(contribution.welfare)} to welfare and ${formatKes(contribution.savings)} to savings.`}
                    actions={
                        can('dues.manage') && (
                            <>
                                <FormDialog
                                    title="Raise monthly contributions"
                                    description="Adds this month's contribution for every active member who does not have one yet. Advance payments are applied automatically."
                                    action="/dues/generate"
                                    submitLabel="Raise"
                                    initial={{ period: filters.period }}
                                    trigger={
                                        <Button>
                                            <ListPlus /> Raise month
                                        </Button>
                                    }
                                >
                                    {(form) => (
                                        <Field
                                            label="Month"
                                            htmlFor="period"
                                            error={form.errors.period}
                                        >
                                            <Input
                                                id="period"
                                                type="month"
                                                value={form.data.period}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'period',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                    )}
                                </FormDialog>
                                <ActionButton
                                    url="/dues/fines/apply"
                                    variant="outline"
                                    confirm="Charge the late fine on every late contribution that has not been fined yet?"
                                >
                                    <Timer /> Apply late fines
                                </ActionButton>
                                <FormDialog
                                    title="Charge a fine"
                                    action="/dues/fines"
                                    submitLabel="Charge fine"
                                    initial={{
                                        member_id: '',
                                        amount: '',
                                        reason: '',
                                    }}
                                    trigger={
                                        <Button variant="outline">
                                            <Gavel /> Fine a member
                                        </Button>
                                    }
                                >
                                    {(form) => (
                                        <>
                                            <Field
                                                label="Member"
                                                htmlFor="fine-member"
                                                error={form.errors.member_id}
                                            >
                                                <MemberSelect
                                                    id="fine-member"
                                                    members={members}
                                                    value={form.data.member_id}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'member_id',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                            <Field
                                                label="Amount"
                                                htmlFor="fine-amount"
                                                error={form.errors.amount}
                                            >
                                                <MoneyInput
                                                    id="fine-amount"
                                                    value={form.data.amount}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'amount',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                            <Field
                                                label="Reason"
                                                htmlFor="fine-reason"
                                                error={form.errors.reason}
                                            >
                                                <Input
                                                    id="fine-reason"
                                                    placeholder="Absent from meeting without apology"
                                                    value={form.data.reason}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'reason',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                        </>
                                    )}
                                </FormDialog>
                            </>
                        )
                    }
                />

                <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                    <StatCard
                        label="Expected this month"
                        value={formatKes(summary.expected)}
                    />
                    <StatCard
                        label="Collected"
                        value={formatKes(summary.collected)}
                        hint={
                            <Progress
                                value={percent(
                                    summary.collected,
                                    summary.expected,
                                )}
                            />
                        }
                    />
                    <StatCard
                        label="Members fully paid"
                        value={`${summary.members_paid} of ${summary.members_total}`}
                    />
                    <StatCard
                        label="All arrears"
                        value={formatKes(summary.arrears_total)}
                        tone={summary.arrears_total > 0 ? 'warn' : 'good'}
                    />
                </div>

                <Section>
                    <div className="mb-4 flex flex-col gap-2 sm:flex-row">
                        <Input
                            type="month"
                            className="sm:max-w-44"
                            value={filters.period}
                            onChange={(e) => apply({ period: e.target.value })}
                        />
                        <Select
                            className="sm:max-w-44"
                            value={filters.type}
                            onChange={(e) => apply({ type: e.target.value })}
                            placeholder="All types"
                            options={[
                                {
                                    value: 'contribution',
                                    label: 'Contributions',
                                },
                                { value: 'levy', label: 'Welfare levies' },
                                { value: 'fine', label: 'Fines' },
                            ]}
                        />
                        <Select
                            className="sm:max-w-44"
                            value={filters.status}
                            onChange={(e) => apply({ status: e.target.value })}
                            placeholder="Any status"
                            options={[
                                { value: 'open', label: 'Open' },
                                { value: 'overdue', label: 'Overdue' },
                                { value: 'paid', label: 'Paid' },
                                { value: 'waived', label: 'Waived' },
                            ]}
                        />
                    </div>

                    {dues.length === 0 ? (
                        <EmptyState title="Nothing here">
                            {can('dues.manage')
                                ? 'Use "Raise month" to add this month\'s contributions.'
                                : null}
                        </EmptyState>
                    ) : (
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Member</TH>
                                    <TH>Item</TH>
                                    <TH>Due</TH>
                                    <TH className="text-right">Amount</TH>
                                    <TH className="text-right">Paid</TH>
                                    <TH className="text-right">Balance</TH>
                                    <TH>Status</TH>
                                    <TH />
                                </TR>
                            </THead>
                            <TBody>
                                {dues.map((d) => (
                                    <TR key={d.id}>
                                        <TD>
                                            <a
                                                href={`/members/${d.member_id}`}
                                                className="font-medium hover:underline"
                                            >
                                                {d.member}
                                            </a>
                                            <div className="text-xs text-muted-foreground">
                                                {d.member_number}
                                            </div>
                                        </TD>
                                        <TD>
                                            {d.description}
                                            <div className="text-xs text-muted-foreground">
                                                {titleCase(d.type)}
                                            </div>
                                        </TD>
                                        <TD>{formatDate(d.due_on)}</TD>
                                        <Num>{formatKes(d.amount)}</Num>
                                        <Num>{formatKes(d.paid)}</Num>
                                        <Num>
                                            {formatKes(
                                                d.status === 'open'
                                                    ? d.amount - d.paid
                                                    : 0,
                                            )}
                                        </Num>
                                        <TD>
                                            <StatusBadge
                                                status={
                                                    d.overdue
                                                        ? 'overdue'
                                                        : d.status
                                                }
                                            />
                                            {d.waived_reason && (
                                                <div className="text-xs text-muted-foreground">
                                                    {d.waived_reason}
                                                </div>
                                            )}
                                        </TD>
                                        <TD className="text-right">
                                            {d.status === 'open' &&
                                                ((d.type === 'fine' &&
                                                    can('fines.waive')) ||
                                                    can('settings.manage')) && (
                                                    <FormDialog
                                                        title={`Waive: ${d.description}`}
                                                        description={`${d.member} will no longer owe ${formatKes(d.amount - d.paid)}.`}
                                                        action={`/dues/${d.id}/waive`}
                                                        submitLabel="Waive"
                                                        initial={{ reason: '' }}
                                                        trigger={
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                            >
                                                                Waive
                                                            </Button>
                                                        }
                                                    >
                                                        {(form) => (
                                                            <Field
                                                                label="Reason (recorded in the audit log)"
                                                                htmlFor={`waive-${d.id}`}
                                                                error={
                                                                    form.errors
                                                                        .reason
                                                                }
                                                            >
                                                                <Input
                                                                    id={`waive-${d.id}`}
                                                                    value={
                                                                        form
                                                                            .data
                                                                            .reason
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        form.setData(
                                                                            'reason',
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                />
                                                            </Field>
                                                        )}
                                                    </FormDialog>
                                                )}
                                        </TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                    )}
                </Section>
            </Page>
        </>
    );
}

DuesIndex.layout = { breadcrumbs: [{ title: 'Contributions', href: '/dues' }] };
