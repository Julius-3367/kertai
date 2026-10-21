import { Head } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import { FormDialog } from '@/components/kertai/actions';
import { Field, Select, Textarea } from '@/components/kertai/fields';
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
    };
    can: { decide: boolean; pay: boolean; cancel: boolean };
    approvalsRequired: number;
    methods: Record<string, string>;
};

export default function PayoutShow({
    payout,
    can,
    approvalsRequired,
    methods,
}: Props) {
    return (
        <>
            <Head title={`Payout #${payout.number}`} />
            <Page className="max-w-4xl">
                <PageHeader
                    title={`Payout #${payout.number}: ${formatKes(payout.amount)}`}
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            {payout.type_label} to {payout.payee_name}
                            <StatusBadge status={payout.status} />
                        </span>
                    }
                    actions={
                        <>
                            {can.decide && (
                                <>
                                    <FormDialog
                                        title="Approve payout"
                                        description={`You confirm ${formatKes(payout.amount)} to ${payout.payee_name} for "${payout.description}" is correct.`}
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
                                    description="Only after the money has actually been sent. This moves the ledger."
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
