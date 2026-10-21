import { Head, Link } from '@inertiajs/react';
import { FormDialog, ActionButton } from '@/components/kertai/actions';
import { Field, MoneyInput } from '@/components/kertai/fields';
import {
    DefinitionList,
    Page,
    PageHeader,
    Progress,
    Section,
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
import {
    formatDate,
    formatKes,
    percent,
    titleCase,
    toShillings,
} from '@/lib/format';

type Props = {
    case: {
        id: number;
        member: string;
        member_id: number;
        beneficiary_name: string;
        relationship: string;
        event: string;
        event_on: string;
        description: string | null;
        benefit_amount: number;
        levy_per_member: number | null;
        status: string;
        payout_id: number | null;
    };
    levies: {
        id: number;
        member: string;
        amount: number;
        paid: number;
        status: string;
    }[];
    levyTotals: { expected: number; collected: number };
    fund: number;
    canManage: boolean;
};

export default function WelfareShow({
    case: c,
    levies,
    levyTotals,
    fund,
    canManage,
}: Props) {
    const can = useCan();
    const nextWeek = new Date(Date.now() + 7 * 864e5)
        .toISOString()
        .slice(0, 10);

    return (
        <>
            <Head title={`Welfare: ${c.beneficiary_name}`} />
            <Page className="max-w-5xl">
                <PageHeader
                    title={`${titleCase(c.event)}: ${c.beneficiary_name}`}
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            {c.relationship === 'self'
                                ? 'Member'
                                : `${titleCase(c.relationship)} of ${c.member}`}{' '}
                            · {formatDate(c.event_on)}
                            <StatusBadge status={c.status} />
                        </span>
                    }
                    actions={
                        canManage && (
                            <>
                                {c.status === 'open' && (
                                    <>
                                        <FormDialog
                                            title="Approve the case"
                                            action={`/welfare/${c.id}/decide`}
                                            submitLabel="Approve"
                                            initial={{
                                                decision: 'approved',
                                                benefit_amount: toShillings(
                                                    c.benefit_amount,
                                                ),
                                            }}
                                            trigger={<Button>Approve</Button>}
                                        >
                                            {(form) => (
                                                <Field
                                                    label="Benefit to pay"
                                                    htmlFor="wa-benefit"
                                                    error={
                                                        form.errors
                                                            .benefit_amount
                                                    }
                                                >
                                                    <MoneyInput
                                                        id="wa-benefit"
                                                        value={
                                                            form.data
                                                                .benefit_amount
                                                        }
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'benefit_amount',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </Field>
                                            )}
                                        </FormDialog>
                                        <ActionButton
                                            url={`/welfare/${c.id}/decide`}
                                            data={{ decision: 'rejected' }}
                                            variant="outline"
                                            confirm="Reject this case?"
                                        >
                                            Reject
                                        </ActionButton>
                                    </>
                                )}
                                {['open', 'approved'].includes(c.status) &&
                                    c.levy_per_member === null && (
                                        <FormDialog
                                            title="Raise a welfare levy"
                                            description="Every other active member is charged this amount and notified by SMS."
                                            action={`/welfare/${c.id}/levy`}
                                            submitLabel="Raise levy"
                                            initial={{
                                                amount: '',
                                                due_on: nextWeek,
                                            }}
                                            trigger={
                                                <Button variant="outline">
                                                    Raise levy
                                                </Button>
                                            }
                                        >
                                            {(form) => (
                                                <>
                                                    <Field
                                                        label="Levy per member"
                                                        htmlFor="lv-amount"
                                                        error={
                                                            form.errors.amount
                                                        }
                                                    >
                                                        <MoneyInput
                                                            id="lv-amount"
                                                            value={
                                                                form.data.amount
                                                            }
                                                            onChange={(e) =>
                                                                form.setData(
                                                                    'amount',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    </Field>
                                                    <Field
                                                        label="Due by"
                                                        htmlFor="lv-due"
                                                        error={
                                                            form.errors.due_on
                                                        }
                                                    >
                                                        <Input
                                                            id="lv-due"
                                                            type="date"
                                                            value={
                                                                form.data.due_on
                                                            }
                                                            onChange={(e) =>
                                                                form.setData(
                                                                    'due_on',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    </Field>
                                                </>
                                            )}
                                        </FormDialog>
                                    )}
                                {c.status === 'approved' &&
                                    can('payouts.request') && (
                                        <ActionButton
                                            url={`/welfare/${c.id}/payout`}
                                            confirm={`Request ${formatKes(c.benefit_amount)} from the welfare fund?`}
                                        >
                                            Request payout
                                        </ActionButton>
                                    )}
                            </>
                        )
                    }
                />

                <Section title="Case">
                    <DefinitionList
                        items={[
                            {
                                label: 'Member',
                                value: (
                                    <Link
                                        href={`/members/${c.member_id}`}
                                        className="underline"
                                    >
                                        {c.member}
                                    </Link>
                                ),
                            },
                            {
                                label: 'Benefit',
                                value: formatKes(c.benefit_amount),
                            },
                            {
                                label: 'Welfare fund now',
                                value: formatKes(fund),
                            },
                            {
                                label: 'Payout',
                                value: c.payout_id ? (
                                    <Link
                                        href={`/payouts/${c.payout_id}`}
                                        className="underline"
                                    >
                                        View payout
                                    </Link>
                                ) : (
                                    'Not requested'
                                ),
                            },
                        ]}
                    />
                    {c.description && (
                        <p className="mt-4 text-sm whitespace-pre-line">
                            {c.description}
                        </p>
                    )}
                </Section>

                {c.levy_per_member !== null && (
                    <Section
                        title="Levy collection"
                        description={`${formatKes(c.levy_per_member)} per member`}
                    >
                        <div className="mb-4 space-y-1">
                            <Progress
                                value={percent(
                                    levyTotals.collected,
                                    levyTotals.expected,
                                )}
                            />
                            <div className="text-sm text-muted-foreground">
                                {formatKes(levyTotals.collected)} of{' '}
                                {formatKes(levyTotals.expected)} collected
                            </div>
                        </div>
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Member</TH>
                                    <TH>Status</TH>
                                    <TH className="text-right">Paid</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {levies.map((l) => (
                                    <TR key={l.id}>
                                        <TD>{l.member}</TD>
                                        <TD>
                                            <StatusBadge status={l.status} />
                                        </TD>
                                        <Num>
                                            {formatKes(l.paid)} /{' '}
                                            {formatKes(l.amount)}
                                        </Num>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                    </Section>
                )}
            </Page>
        </>
    );
}

WelfareShow.layout = { breadcrumbs: [{ title: 'Welfare', href: '/welfare' }] };
