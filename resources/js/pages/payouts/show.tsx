import { Head } from '@inertiajs/react';
import { Check, Plus, Trash2, X } from 'lucide-react';
import { ActionButton, FormDialog } from '@/components/kertai/actions';
import {
    Field,
    MoneyInput,
    Select,
    Textarea,
} from '@/components/kertai/fields';
import {
    DefinitionList,
    Page,
    PageHeader,
    Section,
} from '@/components/kertai/page';
import { StatusBadge } from '@/components/kertai/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDateTime, formatKes } from '@/lib/format';

type Props = {
    payout: {
        id: number;
        number: number;
        type_label: string;
        status: string;
        amount: number;
        net: number;
        fund: string;
        payee_name: string;
        payee_phone: string | null;
        description: string;
        approvals_count: number;
        created_at: string;
        paid_at: string | null;
        requested_by: string | null;
        paid_by: string | null;
        paid_method: string | null;
        paid_reference: string | null;
        approvals: {
            id: number;
            name: string;
            position: string;
            decision: string;
            comment: string | null;
            at: string;
        }[];
        adjustments: {
            id: number;
            kind: string;
            label: string;
            amount: number;
            effect: number;
            reason: string;
            by: string | null;
        }[];
    };
    loans: { id: number; label: string; owed: number }[];
    adjustmentKinds: Record<string, string>;
    can: { decide: boolean; pay: boolean; cancel: boolean; adjust: boolean };
    approvalsRequired: number;
    methods: Record<string, string>;
};

export default function PayoutShow({
    payout,
    loans,
    adjustmentKinds,
    can,
    approvalsRequired,
    methods,
}: Props) {
    const adjusted = payout.adjustments.length > 0;

    return (
        <>
            <Head title={`Payout #${payout.number}`} />
            <Page className="max-w-4xl">
                <PageHeader
                    title={`Payout #${payout.number}: ${formatKes(payout.amount)}`}
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            {payout.type_label} to {payout.payee_name}
                            {adjusted &&
                                ` · ${formatKes(payout.net)} to send after deductions`}
                            <StatusBadge status={payout.status} />
                        </span>
                    }
                    actions={
                        <>
                            {can.decide && (
                                <>
                                    <FormDialog
                                        title="Approve payout"
                                        description={`You confirm ${formatKes(payout.net)} to ${payout.payee_name} for "${payout.description}" is correct${adjusted ? ', including the deductions listed below' : ''}.`}
                                        action={`/payouts/${payout.id}/decide`}
                                        submitLabel="Approve"
                                        initial={{
                                            decision: 'approved',
                                            comment: '',
                                        }}
                                        trigger={
                                            <Button>
                                                <Check /> Approve
                                            </Button>
                                        }
                                    >
                                        {(form) => (
                                            <Field
                                                label="Comment (optional)"
                                                htmlFor="ap-comment"
                                            >
                                                <Input
                                                    id="ap-comment"
                                                    value={form.data.comment}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'comment',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                        )}
                                    </FormDialog>
                                    <FormDialog
                                        title="Reject payout"
                                        action={`/payouts/${payout.id}/decide`}
                                        submitLabel="Reject"
                                        destructive
                                        initial={{
                                            decision: 'rejected',
                                            comment: '',
                                        }}
                                        trigger={
                                            <Button variant="outline">
                                                <X /> Reject
                                            </Button>
                                        }
                                    >
                                        {(form) => (
                                            <Field
                                                label="Why?"
                                                htmlFor="rj-comment"
                                                error={form.errors.comment}
                                            >
                                                <Textarea
                                                    id="rj-comment"
                                                    value={form.data.comment}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'comment',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                        )}
                                    </FormDialog>
                                </>
                            )}
                            {can.pay && (
                                <FormDialog
                                    title="Mark as paid"
                                    description={`Send ${formatKes(payout.net)} to ${payout.payee_name} first. Mark it paid only after the money has actually gone; this moves the ledger.`}
                                    action={`/payouts/${payout.id}/pay`}
                                    submitLabel="Mark paid"
                                    initial={{ method: 'mpesa', reference: '' }}
                                    trigger={<Button>Mark as paid</Button>}
                                >
                                    {(form) => (
                                        <>
                                            <Field
                                                label="Paid by"
                                                htmlFor="pay-method"
                                                error={form.errors.method}
                                            >
                                                <Select
                                                    id="pay-method"
                                                    value={form.data.method}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'method',
                                                            e.target.value,
                                                        )
                                                    }
                                                    options={Object.entries(
                                                        methods,
                                                    ).map(([value, label]) => ({
                                                        value,
                                                        label,
                                                    }))}
                                                />
                                            </Field>
                                            <Field
                                                label="Transaction reference"
                                                htmlFor="pay-ref"
                                                error={form.errors.reference}
                                                hint="M-Pesa code, bank reference or cash voucher number."
                                            >
                                                <Input
                                                    id="pay-ref"
                                                    value={form.data.reference}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'reference',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                        </>
                                    )}
                                </FormDialog>
                            )}
                            {can.cancel && (
                                <FormDialog
                                    title="Cancel payout"
                                    action={`/payouts/${payout.id}/cancel`}
                                    submitLabel="Cancel payout"
                                    destructive
                                    initial={{ reason: '' }}
                                    trigger={
                                        <Button variant="ghost">Cancel</Button>
                                    }
                                >
                                    {(form) => (
                                        <Field
                                            label="Reason"
                                            htmlFor="cx-reason"
                                            error={form.errors.reason}
                                        >
                                            <Input
                                                id="cx-reason"
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
                        </>
                    }
                />

                <Section title="Details">
                    <DefinitionList
                        items={[
                            { label: 'What for', value: payout.description },
                            { label: 'From fund', value: payout.fund },
                            { label: 'Payee phone', value: payout.payee_phone },
                            {
                                label: 'Requested',
                                value: `${formatDateTime(payout.created_at)}${payout.requested_by ? ` by ${payout.requested_by}` : ''}`,
                            },
                            {
                                label: 'Paid',
                                value: payout.paid_at
                                    ? `${formatDateTime(payout.paid_at)} by ${payout.paid_by ?? '—'} (${payout.paid_method}, ${payout.paid_reference})`
                                    : null,
                            },
                        ]}
                    />
                </Section>

                {(adjusted || can.adjust) && (
                    <Section
                        title="Deductions and refunds"
                        description="Money held back from this payout (an old loan, the group's share) or added to it (something the member paid for the group). Any change sends the payout back for two approvals."
                        actions={
                            can.adjust && (
                                <FormDialog
                                    title="Deduct or add"
                                    action={`/payouts/${payout.id}/adjustments`}
                                    submitLabel="Save"
                                    initial={{
                                        kind: loans.length ? 'loan' : 'group',
                                        loan_id: loans[0]
                                            ? String(loans[0].id)
                                            : '',
                                        amount: '',
                                        reason: '',
                                    }}
                                    trigger={
                                        <Button size="sm" variant="outline">
                                            <Plus /> Deduct or add
                                        </Button>
                                    }
                                >
                                    {(form) => (
                                        <>
                                            <Field
                                                label="What is it?"
                                                htmlFor="adj-kind"
                                                error={form.errors.kind}
                                            >
                                                <Select
                                                    id="adj-kind"
                                                    value={form.data.kind}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'kind',
                                                            e.target.value,
                                                        )
                                                    }
                                                    options={Object.entries(
                                                        adjustmentKinds,
                                                    )
                                                        .filter(
                                                            ([k]) =>
                                                                k !== 'loan' ||
                                                                loans.length >
                                                                    0,
                                                        )
                                                        .map(
                                                            ([
                                                                value,
                                                                label,
                                                            ]) => ({
                                                                value,
                                                                label,
                                                            }),
                                                        )}
                                                />
                                            </Field>
                                            {form.data.kind === 'loan' && (
                                                <Field
                                                    label="Loan"
                                                    htmlFor="adj-loan"
                                                    error={form.errors.loan_id}
                                                >
                                                    <Select
                                                        id="adj-loan"
                                                        value={
                                                            form.data.loan_id
                                                        }
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'loan_id',
                                                                e.target.value,
                                                            )
                                                        }
                                                        options={loans.map(
                                                            (l) => ({
                                                                value: l.id,
                                                                label: l.label,
                                                            }),
                                                        )}
                                                    />
                                                </Field>
                                            )}
                                            <Field
                                                label="Amount"
                                                htmlFor="adj-amount"
                                                error={form.errors.amount}
                                            >
                                                <MoneyInput
                                                    id="adj-amount"
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
                                                htmlFor="adj-reason"
                                                error={form.errors.reason}
                                                hint={
                                                    form.data.kind ===
                                                    'reimburse'
                                                        ? 'What they paid for, e.g. "Chairs for the meeting, receipt 1123"'
                                                        : 'e.g. "Old loan from 2025" or "Group share agreed on 3 Nov"'
                                                }
                                            >
                                                <Input
                                                    id="adj-reason"
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
                            )
                        }
                    >
                        <div className="grid gap-1 text-sm tabular-nums">
                            <div className="flex justify-between gap-4 py-1">
                                <span>{payout.type_label}</span>
                                <span>{formatKes(payout.amount)}</span>
                            </div>
                            {payout.adjustments.map((a) => (
                                <div
                                    key={a.id}
                                    className="flex items-start justify-between gap-4 border-t py-1.5"
                                >
                                    <div>
                                        <div>{a.label}</div>
                                        <div className="text-xs text-muted-foreground">
                                            {a.reason}
                                            {a.by ? ` · by ${a.by}` : ''}
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <span
                                            className={
                                                a.effect < 0
                                                    ? 'text-red-700 dark:text-red-400'
                                                    : 'text-emerald-700 dark:text-emerald-400'
                                            }
                                        >
                                            {a.effect < 0 ? '− ' : '+ '}
                                            {formatKes(a.amount)}
                                        </span>
                                        {can.adjust && (
                                            <ActionButton
                                                size="sm"
                                                variant="ghost"
                                                aria-label="Remove"
                                                method="delete"
                                                url={`/payouts/${payout.id}/adjustments/${a.id}`}
                                                confirm={`Remove "${a.label}" of ${formatKes(a.amount)}?`}
                                            >
                                                <Trash2 />
                                            </ActionButton>
                                        )}
                                    </div>
                                </div>
                            ))}
                            <div className="flex justify-between gap-4 border-t pt-2 text-base font-semibold">
                                <span>To send to {payout.payee_name}</span>
                                <span>{formatKes(payout.net)}</span>
                            </div>
                        </div>
                    </Section>
                )}

                <Section
                    title="Approvals"
                    description={`${payout.approvals_count} of ${approvalsRequired} needed.`}
                >
                    {payout.approvals.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No decisions yet. The chair, secretary and treasurer
                            have been notified.
                        </p>
                    ) : (
                        <ul className="space-y-3">
                            {payout.approvals.map((a) => (
                                <li
                                    key={a.id}
                                    className="flex items-start justify-between gap-4 text-sm"
                                >
                                    <div>
                                        <span className="font-medium">
                                            {a.name}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {' '}
                                            · {a.position}
                                        </span>
                                        {a.comment && (
                                            <p className="text-muted-foreground">
                                                "{a.comment}"
                                            </p>
                                        )}
                                    </div>
                                    <div className="text-right">
                                        <StatusBadge status={a.decision} />
                                        <div className="text-xs text-muted-foreground">
                                            {formatDateTime(a.at)}
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Section>
            </Page>
        </>
    );
}

PayoutShow.layout = { breadcrumbs: [{ title: 'Payouts', href: '/payouts' }] };
