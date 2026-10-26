import { Head, Link } from '@inertiajs/react';
import { Check, Smartphone, X } from 'lucide-react';
import { ActionButton, FormDialog } from '@/components/kertai/actions';
import {
    Field,
    MoneyInput,
    Select,
    Textarea,
} from '@/components/kertai/fields';
import {
    InterestFields,
    interestText,
    LoanStatus,
    RuleList,
} from '@/components/kertai/loans';
import type { LoanRow } from '@/components/kertai/loans';
import {
    DefinitionList,
    Page,
    PageHeader,
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
import { formatDate, formatKes, today } from '@/lib/format';
import { cn } from '@/lib/utils';

type Props = {
    loan: LoanRow & {
        purpose: string | null;
        term_months: number | null;
        instalment: number | null;
        interest_note: string | null;
        next_interest_on: string | null;
        closed_on: string | null;
        resolution_reference: string | null;
        notes: string | null;
        payout: { id: number; number: number; status: string } | null;
        charging_interest: boolean;
    };
    history: {
        id: number;
        date: string;
        type: string;
        label: string;
        description: string;
        principal: number;
        interest: number;
        balance: number;
    }[];
    guarantors: {
        id: number;
        member_id: number;
        name: string;
        amount: number;
        status: string;
        recovered: number | null;
    }[];
    rules: { key: string; ok: boolean; label: string }[];
    recoverFrom: { id: number; label: string; savings: number }[];
    groupInterestOn: boolean;
    myGuarantee: { id: number; amount: number; status: string } | null;
    can: {
        submit: boolean;
        reject: boolean;
        withdraw: boolean;
        adjust: boolean;
        record: boolean;
        pay: boolean;
    };
    phone: string;
};

/** A signed amount as it moves the balance: +1,000 or −500. */
function Change({ cents }: { cents: number }) {
    if (cents === 0) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <span
            className={cn(
                cents < 0 && 'text-emerald-700 dark:text-emerald-400',
            )}
        >
            {cents > 0 ? '+' : '−'}
            {formatKes(Math.abs(cents))}
        </span>
    );
}

export default function LoanShow({
    loan,
    history,
    guarantors,
    rules,
    recoverFrom,
    groupInterestOn,
    myGuarantee,
    can,
    phone,
}: Props) {
    const active = loan.status === 'active';

    return (
        <>
            <Head title={`Loan ${loan.reference}`} />
            <Page className="max-w-4xl">
                <PageHeader
                    title={`Loan ${loan.reference}`}
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            {loan.member.name}
                            <LoanStatus loan={loan} />
                            {loan.source === 'brought_forward' && (
                                <StatusBadge status="brought_forward" />
                            )}
                        </span>
                    }
                    actions={
                        <>
                            {can.pay && <PayDialog loan={loan} phone={phone} />}
                            {can.record && <RecordDialog loan={loan} />}
                        </>
                    }
                />

                {myGuarantee?.status === 'pending' &&
                    loan.status === 'pending' && (
                        <Section
                            title="You have been asked to guarantee this loan"
                            className="border-amber-300 dark:border-amber-800"
                        >
                            <p className="text-sm">
                                {loan.member.name} asks you to stand behind{' '}
                                <strong>{formatKes(myGuarantee.amount)}</strong>{' '}
                                of a {formatKes(loan.principal)} loan. If they
                                do not repay, up to that amount can be taken
                                from your savings. You cannot leave the group
                                while the loan is running.
                            </p>
                            <div className="mt-3 flex flex-wrap gap-2">
                                <ActionButton
                                    url={`/loans/${loan.id}/guarantee`}
                                    data={{ accept: true }}
                                    confirm={`Guarantee ${formatKes(myGuarantee.amount)} of ${loan.member.name}'s loan?`}
                                >
                                    <Check /> Accept
                                </ActionButton>
                                <ActionButton
                                    url={`/loans/${loan.id}/guarantee`}
                                    data={{ accept: false }}
                                    variant="outline"
                                >
                                    <X /> Decline
                                </ActionButton>
                            </div>
                        </Section>
                    )}

                {loan.status !== 'pending' && loan.status !== 'submitted' && (
                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <StatCard
                            label="Owed now"
                            value={formatKes(loan.owed.total)}
                            tone={
                                loan.overdue
                                    ? 'bad'
                                    : loan.owed.total > 0
                                      ? 'warn'
                                      : 'good'
                            }
                        />
                        <StatCard
                            label="Principal"
                            value={formatKes(loan.owed.principal)}
                            hint={`of ${formatKes(loan.principal)} ${loan.source === 'brought_forward' ? 'brought forward' : 'lent'}`}
                        />
                        <StatCard
                            label="Interest"
                            value={formatKes(loan.owed.interest)}
                            hint={
                                active
                                    ? interestText(
                                          loan.interest,
                                          groupInterestOn,
                                      )
                                    : undefined
                            }
                        />
                        <StatCard
                            label="Due"
                            value={loan.due_on ? formatDate(loan.due_on) : '—'}
                            tone={loan.overdue ? 'bad' : 'default'}
                            hint={
                                loan.overdue
                                    ? 'Past the date agreed'
                                    : loan.behind > 0
                                      ? `${formatKes(loan.behind)} behind on repayments`
                                      : loan.closed_on
                                        ? `Closed ${formatDate(loan.closed_on)}`
                                        : undefined
                            }
                        />
                    </div>
                )}

                {(loan.status === 'pending' || loan.status === 'submitted') && (
                    <ApplicationSection
                        loan={loan}
                        rules={rules}
                        guarantors={guarantors}
                        can={can}
                    />
                )}

                <Section
                    title="Terms"
                    actions={
                        can.adjust && (
                            <>
                                {!loan.interest.paused &&
                                    loan.interest.rate > 0 && (
                                        <InterestDialog loan={loan} stop />
                                    )}
                                <InterestDialog loan={loan} />
                            </>
                        )
                    }
                >
                    <DefinitionList
                        items={[
                            {
                                label:
                                    loan.source === 'brought_forward'
                                        ? 'Principal brought forward'
                                        : 'Amount lent',
                                value: formatKes(loan.principal),
                            },
                            {
                                label: 'Given on',
                                value: loan.issued_on
                                    ? formatDate(loan.issued_on)
                                    : 'Not paid out yet',
                            },
                            {
                                label: 'Monthly repayment',
                                value: loan.instalment
                                    ? `${formatKes(loan.instalment)}${loan.term_months ? ` for ${loan.term_months} months` : ''}`
                                    : null,
                            },
                            {
                                label: 'Interest',
                                value: (
                                    <>
                                        {interestText(
                                            loan.interest,
                                            groupInterestOn,
                                        )}
                                        {loan.interest_note && (
                                            <span className="block text-xs font-normal text-muted-foreground">
                                                {loan.interest_note}
                                            </span>
                                        )}
                                    </>
                                ),
                            },
                            {
                                label: 'Next interest date',
                                value:
                                    loan.charging_interest &&
                                    loan.next_interest_on
                                        ? formatDate(loan.next_interest_on)
                                        : null,
                            },
                            { label: 'What it is for', value: loan.purpose },
                            {
                                label: 'Minute or resolution',
                                value: loan.resolution_reference,
                            },
                            {
                                label: 'Payout',
                                value: loan.payout ? (
                                    <Link
                                        href={`/payouts/${loan.payout.id}`}
                                        className="underline"
                                    >
                                        #{loan.payout.number} (
                                        {loan.payout.status})
                                    </Link>
                                ) : null,
                            },
                            ...(loan.notes
                                ? [{ label: 'Notes', value: loan.notes }]
                                : []),
                        ]}
                    />
                </Section>

                {guarantors.length > 0 &&
                    loan.status !== 'pending' &&
                    loan.status !== 'submitted' && (
                        <Section title="Guarantors">
                            <GuarantorList guarantors={guarantors} />
                        </Section>
                    )}

                <Section title="History">
                    {history.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Nothing has moved on this loan yet.
                        </p>
                    ) : (
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Date</TH>
                                    <TH>What</TH>
                                    <TH className="text-right">Principal</TH>
                                    <TH className="text-right">Interest</TH>
                                    <TH className="text-right">Balance</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {history.map((h) => (
                                    <TR key={h.id}>
                                        <TD className="whitespace-nowrap">
                                            {formatDate(h.date)}
                                        </TD>
                                        <TD>
                                            <div>
                                                <span className="font-medium">
                                                    {h.label}
                                                </span>
                                                {h.description !== h.label && (
                                                    <span className="block text-xs text-muted-foreground">
                                                        {h.description}
                                                    </span>
                                                )}
                                            </div>
                                        </TD>
                                        <Num>
                                            <Change cents={h.principal} />
                                        </Num>
                                        <Num>
                                            <Change cents={h.interest} />
                                        </Num>
                                        <Num className="font-medium">
                                            {formatKes(h.balance)}
                                        </Num>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                    )}
                </Section>

                {can.adjust && (
                    <Section
                        title="If the loan is not being repaid"
                        description="Each of these is logged with your reason."
                    >
                        <div className="flex flex-wrap gap-2">
                            {recoverFrom.length > 0 && (
                                <RecoverDialog
                                    loan={loan}
                                    recoverFrom={recoverFrom}
                                />
                            )}
                            <WaiveDialog loan={loan} />
                        </div>
                    </Section>
                )}
            </Page>
        </>
    );
}

function GuarantorList({ guarantors }: { guarantors: Props['guarantors'] }) {
    return (
        <ul className="divide-y">
            {guarantors.map((g) => (
                <li
                    key={g.id}
                    className="flex items-center justify-between gap-3 py-2 text-sm"
                >
                    <div>
                        <span className="font-medium">{g.name}</span>
                        <span className="block text-xs text-muted-foreground">
                            Guarantees {formatKes(g.amount)}
                            {g.recovered
                                ? ` · ${formatKes(g.recovered)} taken from savings`
                                : ''}
                        </span>
                    </div>
                    <StatusBadge status={g.status} />
                </li>
            ))}
        </ul>
    );
}

function ApplicationSection({
    loan,
    rules,
    guarantors,
    can,
}: {
    loan: Props['loan'];
    rules: Props['rules'];
    guarantors: Props['guarantors'];
    can: Props['can'];
}) {
    return (
        <Section
            title="Application"
            description={
                loan.status === 'submitted'
                    ? 'Sent for approval. It is paid out once two officials approve the payout and the treasurer pays it.'
                    : `${formatKes(loan.principal)} over ${loan.term_months} months for ${loan.purpose ?? 'no reason given'}.`
            }
            actions={
                <>
                    {can.submit && (
                        <ActionButton
                            url={`/loans/${loan.id}/submit`}
                            confirm="Send this loan for the two payout approvals?"
                        >
                            Send for approval
                        </ActionButton>
                    )}
                    {can.reject && (
                        <FormDialog
                            title="Turn down this application"
                            action={`/loans/${loan.id}/reject`}
                            submitLabel="Turn down"
                            destructive
                            initial={{ reason: '' }}
                            trigger={
                                <Button variant="outline">Turn down</Button>
                            }
                        >
                            {(form) => (
                                <Field
                                    label="Reason"
                                    htmlFor="rj-reason"
                                    error={form.errors.reason}
                                >
                                    <Input
                                        id="rj-reason"
                                        value={form.data.reason}
                                        onChange={(e) =>
                                            form.setData(
                                                'reason',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            )}
                        </FormDialog>
                    )}
                    {can.withdraw && (
                        <ActionButton
                            url={`/loans/${loan.id}/withdraw`}
                            variant="ghost"
                            confirm="Withdraw your application?"
                        >
                            Withdraw
                        </ActionButton>
                    )}
                </>
            }
        >
            <div className="grid gap-5 sm:grid-cols-2">
                {rules.length > 0 && (
                    <div>
                        <h3 className="mb-2 text-sm font-medium text-muted-foreground">
                            Lending rules
                        </h3>
                        <RuleList rules={rules} />
                    </div>
                )}
                <div>
                    <h3 className="mb-2 text-sm font-medium text-muted-foreground">
                        Guarantors
                    </h3>
                    {guarantors.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            None needed.
                        </p>
                    ) : (
                        <GuarantorList guarantors={guarantors} />
                    )}
                </div>
            </div>
        </Section>
    );
}

function PayDialog({ loan, phone }: { loan: Props['loan']; phone: string }) {
    return (
        <FormDialog
            title="Pay with M-Pesa"
            description="An M-Pesa prompt is sent to the phone. Enter the PIN to complete."
            action="/me/pay"
            submitLabel="Send prompt"
            initial={{
                amount: String(Math.ceil(loan.owed.total / 100)),
                phone,
                purpose: 'loan',
                target_id: String(loan.id),
                member_id: String(loan.member.id),
            }}
            trigger={
                <Button>
                    <Smartphone /> Pay with M-Pesa
                </Button>
            }
        >
            {(form) => (
                <>
                    <Field
                        label="Amount (KES, whole shillings)"
                        htmlFor="lp-amount"
                        error={form.errors.amount}
                        hint={`Owed: ${formatKes(loan.owed.total)}`}
                    >
                        <Input
                            id="lp-amount"
                            inputMode="numeric"
                            value={form.data.amount}
                            onChange={(e) =>
                                form.setData('amount', e.target.value)
                            }
                        />
                    </Field>
                    <Field
                        label="M-Pesa number"
                        htmlFor="lp-phone"
                        error={form.errors.phone}
                    >
                        <Input
                            id="lp-phone"
                            inputMode="tel"
                            value={form.data.phone}
                            onChange={(e) =>
                                form.setData('phone', e.target.value)
                            }
                        />
                    </Field>
                </>
            )}
        </FormDialog>
    );
}

function RecordDialog({ loan }: { loan: Props['loan'] }) {
    return (
        <FormDialog
            title="Record a repayment"
            description={`Money ${loan.member.name} paid towards ${loan.reference}.`}
            action="/payments"
            submitLabel="Record"
            initial={{
                channel: 'cash',
                asset: 'bank',
                amount: '',
                reference: '',
                received_on: today(),
                purpose: 'loan',
                target_id: String(loan.id),
                member_id: String(loan.member.id),
            }}
            trigger={<Button variant="outline">Record repayment</Button>}
        >
            {(form) => (
                <>
                    <Field
                        label="Paid by"
                        htmlFor="lr-channel"
                        error={form.errors.channel}
                    >
                        <Select
                            id="lr-channel"
                            value={form.data.channel}
                            onChange={(e) =>
                                form.setData('channel', e.target.value)
                            }
                            options={[
                                { value: 'cash', label: 'Cash' },
                                {
                                    value: 'paybill',
                                    label: 'Paybill (has an M-Pesa code)',
                                },
                                { value: 'manual', label: 'Bank deposit' },
                            ]}
                        />
                    </Field>
                    <Field
                        label="Amount"
                        htmlFor="lr-amount"
                        error={form.errors.amount}
                        hint={`Owed: ${formatKes(loan.owed.total)}. Anything more is kept as a prepayment.`}
                    >
                        <MoneyInput
                            id="lr-amount"
                            value={form.data.amount}
                            onChange={(e) =>
                                form.setData('amount', e.target.value)
                            }
                        />
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Date received"
                            htmlFor="lr-date"
                            error={form.errors.received_on}
                        >
                            <Input
                                id="lr-date"
                                type="date"
                                value={form.data.received_on}
                                onChange={(e) =>
                                    form.setData('received_on', e.target.value)
                                }
                            />
                        </Field>
                        <Field
                            label={
                                form.data.channel === 'paybill'
                                    ? 'M-Pesa code'
                                    : 'Reference (optional)'
                            }
                            htmlFor="lr-ref"
                            error={form.errors.reference}
                        >
                            <Input
                                id="lr-ref"
                                value={form.data.reference}
                                onChange={(e) =>
                                    form.setData('reference', e.target.value)
                                }
                            />
                        </Field>
                    </div>
                </>
            )}
        </FormDialog>
    );
}

function InterestDialog({
    loan,
    stop = false,
}: {
    loan: Props['loan'];
    stop?: boolean;
}) {
    return (
        <FormDialog
            title={stop ? 'Stop interest on this loan' : 'Change interest'}
            description={
                stop
                    ? 'No more interest is charged; the member repays the rest. Interest already owed stays unless you waive it.'
                    : 'Applies from the next interest date. Months while interest is stopped are never charged later.'
            }
            action={`/loans/${loan.id}/interest`}
            submitLabel={stop ? 'Stop interest' : 'Save'}
            wide={!stop}
            initial={{
                interest_rate: String(loan.interest.rate),
                interest_method: loan.interest.method,
                interest_period: loan.interest.period,
                interest_paused: stop ? true : loan.interest.paused,
                reason: '',
            }}
            trigger={
                <Button size="sm" variant={stop ? 'outline' : 'ghost'}>
                    {stop ? 'Stop interest' : 'Change interest'}
                </Button>
            }
        >
            {(form) => (
                <>
                    {!stop && (
                        <InterestFields
                            data={form.data}
                            errors={form.errors}
                            set={(key, value) =>
                                form.setData(key, value as never)
                            }
                            idPrefix="li"
                        />
                    )}
                    <Field
                        label="Reason or minute"
                        htmlFor="li-reason"
                        error={form.errors.reason}
                        hint="For example: AGM 2026/05, principal only from now."
                    >
                        <Input
                            id="li-reason"
                            value={form.data.reason}
                            onChange={(e) =>
                                form.setData('reason', e.target.value)
                            }
                        />
                    </Field>
                </>
            )}
        </FormDialog>
    );
}

function WaiveDialog({ loan }: { loan: Props['loan'] }) {
    return (
        <FormDialog
            title="Waive or write off"
            description="Forgiven money comes out of the group fund."
            action={`/loans/${loan.id}/waive`}
            submitLabel="Waive"
            destructive
            initial={{ part: 'interest', amount: '', reason: '' }}
            trigger={<Button variant="outline">Waive or write off</Button>}
        >
            {(form) => (
                <>
                    <Field
                        label="What to forgive"
                        htmlFor="lw-part"
                        error={form.errors.part}
                    >
                        <Select
                            id="lw-part"
                            value={form.data.part}
                            onChange={(e) =>
                                form.setData('part', e.target.value)
                            }
                            options={[
                                {
                                    value: 'interest',
                                    label: `Interest (${formatKes(loan.owed.interest)} owed)`,
                                },
                                {
                                    value: 'principal',
                                    label: `Principal (${formatKes(loan.owed.principal)} owed)`,
                                },
                                {
                                    value: 'all',
                                    label: `Everything: write the loan off (${formatKes(loan.owed.total)})`,
                                },
                            ]}
                        />
                    </Field>
                    {form.data.part !== 'all' && (
                        <Field
                            label="Amount (leave empty for all of it)"
                            htmlFor="lw-amount"
                            error={form.errors.amount}
                        >
                            <MoneyInput
                                id="lw-amount"
                                value={form.data.amount}
                                onChange={(e) =>
                                    form.setData('amount', e.target.value)
                                }
                            />
                        </Field>
                    )}
                    <Field
                        label="Reason or minute"
                        htmlFor="lw-reason"
                        error={form.errors.reason}
                    >
                        <Textarea
                            id="lw-reason"
                            value={form.data.reason}
                            onChange={(e) =>
                                form.setData('reason', e.target.value)
                            }
                            className="min-h-16"
                        />
                    </Field>
                </>
            )}
        </FormDialog>
    );
}

function RecoverDialog({
    loan,
    recoverFrom,
}: {
    loan: Props['loan'];
    recoverFrom: Props['recoverFrom'];
}) {
    return (
        <FormDialog
            title="Recover from savings"
            description="Take what is owed from the borrower's savings, or from a guarantor's up to what they guaranteed."
            action={`/loans/${loan.id}/recover`}
            submitLabel="Recover"
            destructive
            initial={{
                member_id: String(recoverFrom[0].id),
                amount: '',
                reason: '',
            }}
            trigger={<Button variant="outline">Recover from savings</Button>}
        >
            {(form) => {
                const from = recoverFrom.find(
                    (m) => String(m.id) === form.data.member_id,
                );

                return (
                    <>
                        <Field
                            label="From"
                            htmlFor="lv-member"
                            error={form.errors.member_id}
                            hint={
                                from
                                    ? `Savings: ${formatKes(from.savings)}`
                                    : undefined
                            }
                        >
                            <Select
                                id="lv-member"
                                value={form.data.member_id}
                                onChange={(e) =>
                                    form.setData('member_id', e.target.value)
                                }
                                options={recoverFrom.map((m) => ({
                                    value: m.id,
                                    label: m.label,
                                }))}
                            />
                        </Field>
                        <Field
                            label="Amount"
                            htmlFor="lv-amount"
                            error={form.errors.amount}
                            hint={`Owed on the loan: ${formatKes(loan.owed.total)}`}
                        >
                            <MoneyInput
                                id="lv-amount"
                                value={form.data.amount}
                                onChange={(e) =>
                                    form.setData('amount', e.target.value)
                                }
                            />
                        </Field>
                        <Field
                            label="Reason or minute"
                            htmlFor="lv-reason"
                            error={form.errors.reason}
                        >
                            <Input
                                id="lv-reason"
                                value={form.data.reason}
                                onChange={(e) =>
                                    form.setData('reason', e.target.value)
                                }
                            />
                        </Field>
                    </>
                );
            }}
        </FormDialog>
    );
}

// Members land here from My loans; officials have Loans in the menu.
LoanShow.layout = { breadcrumbs: [{ title: 'Loans', href: '/me/loans' }] };
