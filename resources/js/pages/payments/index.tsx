import { Head, Link, router } from '@inertiajs/react';
import { FileUp, HandCoins, RefreshCw, Smartphone } from 'lucide-react';
import { useState } from 'react';
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
    Section,
} from '@/components/kertai/page';
import { Pagination } from '@/components/kertai/pagination';
import { PurposeFields } from '@/components/kertai/purpose-fields';
import type { Targets } from '@/components/kertai/purpose-fields';
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
import { formatDateTime, formatKes, titleCase, today } from '@/lib/format';
import type { Option, Paginated } from '@/types/kertai';

type PaymentRow = {
    id: number;
    member: { id: number; name: string; number: string } | null;
    payer_name: string | null;
    phone: string | null;
    channel: string;
    status: string;
    purpose: string;
    amount: number;
    receipt: string | null;
    reference: string | null;
    failure_reason: string | null;
    allocated: boolean;
    narrative: string | null;
    date: string;
};

export default function PaymentsIndex({
    payments,
    filters,
    members,
    targets,
    counts,
}: {
    payments: Paginated<PaymentRow>;
    filters: {
        channel?: string;
        status?: string;
        purpose?: string;
        q?: string;
    };
    members: Option[];
    targets: Targets;
    counts: { unmatched: number; pending: number };
}) {
    const can = useCan();
    const [q, setQ] = useState(filters.q ?? '');
    const apply = (next: Record<string, string>) =>
        router.get(
            '/payments',
            { ...filters, q, ...next },
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title="Payments" />
            <Page>
                <PageHeader
                    title="Payments received"
                    description="M-Pesa through SimulinkPay, paybill 247247, cash and bank."
                    actions={
                        can('payments.record') && (
                            <>
                                <FormDialog
                                    title="Record a payment"
                                    description="Cash at a meeting, a bank deposit, or a paybill payment typed in by hand."
                                    action="/payments"
                                    submitLabel="Record"
                                    wide
                                    initial={{
                                        channel: 'cash',
                                        asset: 'bank',
                                        amount: '',
                                        purpose: 'contribution',
                                        target_id: '',
                                        member_id: '',
                                        payer_name: '',
                                        phone: '',
                                        reference: '',
                                        received_on: today(),
                                    }}
                                    trigger={
                                        <Button>
                                            <HandCoins /> Record payment
                                        </Button>
                                    }
                                >
                                    {(form) => (
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <Field
                                                label="How it came in"
                                                htmlFor="rp-channel"
                                                error={form.errors.channel}
                                            >
                                                <Select
                                                    id="rp-channel"
                                                    value={form.data.channel}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'channel',
                                                            e.target.value,
                                                        )
                                                    }
                                                    options={[
                                                        {
                                                            value: 'cash',
                                                            label: 'Cash',
                                                        },
                                                        {
                                                            value: 'paybill',
                                                            label: 'Paybill 247247',
                                                        },
                                                        {
                                                            value: 'manual',
                                                            label: 'Bank deposit / other',
                                                        },
                                                    ]}
                                                />
                                            </Field>
                                            {form.data.channel === 'manual' && (
                                                <Field
                                                    label="Landed in"
                                                    htmlFor="rp-asset"
                                                >
                                                    <Select
                                                        id="rp-asset"
                                                        value={form.data.asset}
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'asset',
                                                                e.target.value,
                                                            )
                                                        }
                                                        options={[
                                                            {
                                                                value: 'bank',
                                                                label: 'Equity Bank',
                                                            },
                                                            {
                                                                value: 'mpesa',
                                                                label: 'SimulinkPay / M-Pesa',
                                                            },
                                                            {
                                                                value: 'cash',
                                                                label: 'Cash box',
                                                            },
                                                        ]}
                                                    />
                                                </Field>
                                            )}
                                            <Field
                                                label="Amount"
                                                htmlFor="rp-amount"
                                                error={form.errors.amount}
                                            >
                                                <MoneyInput
                                                    id="rp-amount"
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
                                                label="Date received"
                                                htmlFor="rp-date"
                                                error={form.errors.received_on}
                                            >
                                                <Input
                                                    id="rp-date"
                                                    type="date"
                                                    value={
                                                        form.data.received_on
                                                    }
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'received_on',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                            <PurposeFields
                                                idPrefix="rp"
                                                purpose={form.data.purpose}
                                                targetId={form.data.target_id}
                                                onPurpose={(v) =>
                                                    form.setData('purpose', v)
                                                }
                                                onTarget={(v) =>
                                                    form.setData('target_id', v)
                                                }
                                                targets={targets}
                                                errors={form.errors}
                                            />
                                            <Field
                                                label="Member"
                                                htmlFor="rp-member"
                                                error={form.errors.member_id}
                                                hint="Leave empty for a non-member."
                                            >
                                                <MemberSelect
                                                    id="rp-member"
                                                    members={members}
                                                    placeholder="Not a member"
                                                    value={form.data.member_id}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'member_id',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                            {!form.data.member_id && (
                                                <Field
                                                    label="Payer name"
                                                    htmlFor="rp-name"
                                                    error={
                                                        form.errors.payer_name
                                                    }
                                                >
                                                    <Input
                                                        id="rp-name"
                                                        value={
                                                            form.data.payer_name
                                                        }
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'payer_name',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </Field>
                                            )}
                                            <Field
                                                label={
                                                    form.data.channel ===
                                                    'paybill'
                                                        ? 'M-Pesa receipt (required)'
                                                        : 'Reference'
                                                }
                                                htmlFor="rp-ref"
                                                error={form.errors.reference}
                                            >
                                                <Input
                                                    id="rp-ref"
                                                    value={form.data.reference}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'reference',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                        </div>
                                    )}
                                </FormDialog>
                                <FormDialog
                                    title="Send an M-Pesa prompt"
                                    description="The member gets a PIN prompt on their phone. The payment shows once they complete it."
                                    action="/payments/stk"
                                    submitLabel="Send prompt"
                                    initial={{
                                        member_id: '',
                                        phone: '',
                                        amount: '',
                                        purpose: 'contribution',
                                        target_id: '',
                                    }}
                                    trigger={
                                        <Button variant="outline">
                                            <Smartphone /> M-Pesa prompt
                                        </Button>
                                    }
                                >
                                    {(form) => (
                                        <>
                                            <Field
                                                label="Member"
                                                htmlFor="stk-member"
                                                error={form.errors.member_id}
                                            >
                                                <MemberSelect
                                                    id="stk-member"
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
                                                label="Phone to prompt"
                                                htmlFor="stk-phone"
                                                error={form.errors.phone}
                                            >
                                                <Input
                                                    id="stk-phone"
                                                    inputMode="tel"
                                                    placeholder="0712 345 678"
                                                    value={form.data.phone}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'phone',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                            <Field
                                                label="Amount (whole shillings)"
                                                htmlFor="stk-amount"
                                                error={form.errors.amount}
                                            >
                                                <Input
                                                    id="stk-amount"
                                                    inputMode="numeric"
                                                    value={form.data.amount}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'amount',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                            <PurposeFields
                                                idPrefix="stk"
                                                purpose={form.data.purpose}
                                                targetId={form.data.target_id}
                                                onPurpose={(v) =>
                                                    form.setData('purpose', v)
                                                }
                                                onTarget={(v) =>
                                                    form.setData('target_id', v)
                                                }
                                                targets={targets}
                                                errors={form.errors}
                                            />
                                        </>
                                    )}
                                </FormDialog>
                                <Button asChild variant="outline">
                                    <Link href="/payments/import">
                                        <FileUp /> Import statement
                                    </Link>
                                </Button>
                                <ActionButton
                                    url="/payments/reconcile"
                                    variant="outline"
                                >
                                    <RefreshCw /> Check pending
                                </ActionButton>
                            </>
                        )
                    }
                />

                <Section>
                    <div className="mb-4 flex flex-col gap-2 lg:flex-row">
                        <form
                            className="flex gap-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                apply({});
                            }}
                        >
                            <Input
                                placeholder="Receipt, name or phone"
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                className="lg:w-56"
                            />
                            <Button type="submit" variant="outline">
                                Search
                            </Button>
                        </form>
                        <Select
                            className="lg:w-44"
                            value={filters.status ?? ''}
                            onChange={(e) => apply({ status: e.target.value })}
                            placeholder="Any status"
                            options={[
                                {
                                    value: 'unmatched',
                                    label: `Unmatched (${counts.unmatched})`,
                                },
                                { value: 'succeeded', label: 'Received' },
                                {
                                    value: 'processing',
                                    label: `Waiting for PIN (${counts.pending})`,
                                },
                                { value: 'failed', label: 'Failed' },
                                { value: 'expired', label: 'Expired' },
                                { value: 'cancelled', label: 'Reversed' },
                            ]}
                        />
                        <Select
                            className="lg:w-44"
                            value={filters.channel ?? ''}
                            onChange={(e) => apply({ channel: e.target.value })}
                            placeholder="Any channel"
                            options={[
                                {
                                    value: 'simulinkpay',
                                    label: 'M-Pesa (SimulinkPay)',
                                },
                                { value: 'paybill', label: 'Paybill' },
                                { value: 'cash', label: 'Cash' },
                                { value: 'manual', label: 'Bank / other' },
                            ]}
                        />
                        <Select
                            className="lg:w-44"
                            value={filters.purpose ?? ''}
                            onChange={(e) => apply({ purpose: e.target.value })}
                            placeholder="Any purpose"
                            options={[
                                'contribution',
                                'fundraiser',
                                'project',
                                'sale',
                                'hire',
                                'donation',
                            ].map((p) => ({ value: p, label: titleCase(p) }))}
                        />
                    </div>

                    {payments.data.length === 0 ? (
                        <EmptyState title="No payments match" />
                    ) : (
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Date</TH>
                                    <TH>From</TH>
                                    <TH>For</TH>
                                    <TH>Channel</TH>
                                    <TH>Receipt</TH>
                                    <TH>Status</TH>
                                    <TH className="text-right">Amount</TH>
                                    <TH />
                                </TR>
                            </THead>
                            <TBody>
                                {payments.data.map((p) => (
                                    <TR key={p.id}>
                                        <TD className="whitespace-nowrap">
                                            {formatDateTime(p.date)}
                                        </TD>
                                        <TD>
                                            {p.member ? (
                                                <Link
                                                    href={`/members/${p.member.id}`}
                                                    className="font-medium hover:underline"
                                                >
                                                    {p.member.name}
                                                </Link>
                                            ) : (
                                                <span>
                                                    {p.payer_name ?? 'Unknown'}
                                                </span>
                                            )}
                                            <div className="text-xs text-muted-foreground">
                                                {p.phone}
                                            </div>
                                            {p.narrative && (
                                                <div
                                                    className="max-w-xs truncate text-xs text-muted-foreground"
                                                    title={p.narrative}
                                                >
                                                    {p.narrative}
                                                </div>
                                            )}
                                        </TD>
                                        <TD>{titleCase(p.purpose)}</TD>
                                        <TD>{titleCase(p.channel)}</TD>
                                        <TD className="font-mono text-xs">
                                            {p.receipt ?? '—'}
                                        </TD>
                                        <TD>
                                            {p.status === 'succeeded' &&
                                            !p.allocated ? (
                                                <StatusBadge
                                                    status="pending"
                                                    label="Unmatched"
                                                />
                                            ) : (
                                                <StatusBadge
                                                    status={p.status}
                                                />
                                            )}
                                            {p.failure_reason && (
                                                <div className="text-xs text-muted-foreground">
                                                    {p.failure_reason}
                                                </div>
                                            )}
                                        </TD>
                                        <Num>{formatKes(p.amount)}</Num>
                                        <TD className="text-right whitespace-nowrap">
                                            <RowActions
                                                payment={p}
                                                members={members}
                                                targets={targets}
                                            />
                                        </TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                    )}
                    <Pagination page={payments} />
                </Section>
            </Page>
        </>
    );
}

function RowActions({
    payment,
    members,
    targets,
}: {
    payment: PaymentRow;
    members: Option[];
    targets: Targets;
}) {
    const can = useCan();

    return (
        <div className="flex justify-end gap-1">
            {['pending', 'processing'].includes(payment.status) && (
                <ActionButton
                    url={`/payments/${payment.id}/check`}
                    size="sm"
                    variant="ghost"
                >
                    Check
                </ActionButton>
            )}
            {payment.status === 'succeeded' &&
                !payment.allocated &&
                can('payments.record') && (
                    <FormDialog
                        title="Match payment"
                        description={`${formatKes(payment.amount)} from ${payment.payer_name ?? payment.phone ?? 'unknown'}`}
                        action={`/payments/${payment.id}/assign`}
                        submitLabel="Match and allocate"
                        initial={{
                            member_id: '',
                            purpose: 'contribution',
                            target_id: '',
                        }}
                        trigger={<Button size="sm">Match</Button>}
                    >
                        {(form) => (
                            <>
                                <Field
                                    label="Member"
                                    htmlFor={`as-m-${payment.id}`}
                                    error={form.errors.member_id}
                                >
                                    <MemberSelect
                                        id={`as-m-${payment.id}`}
                                        members={members}
                                        placeholder="Not a member"
                                        value={form.data.member_id}
                                        onChange={(e) =>
                                            form.setData(
                                                'member_id',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <PurposeFields
                                    idPrefix={`as-${payment.id}`}
                                    purpose={form.data.purpose}
                                    targetId={form.data.target_id}
                                    onPurpose={(v) =>
                                        form.setData('purpose', v)
                                    }
                                    onTarget={(v) =>
                                        form.setData('target_id', v)
                                    }
                                    targets={targets}
                                    errors={form.errors}
                                />
                            </>
                        )}
                    </FormDialog>
                )}
            {payment.status === 'succeeded' &&
                payment.allocated &&
                ['contribution', 'donation'].includes(payment.purpose) &&
                can('ledger.manage') && (
                    <FormDialog
                        title="Reverse payment"
                        description="Reverses the ledger entry and takes back what it paid off. Use this for payments recorded against the wrong member."
                        action={`/payments/${payment.id}/reverse`}
                        submitLabel="Reverse"
                        destructive
                        initial={{ reason: '' }}
                        trigger={
                            <Button size="sm" variant="ghost">
                                Reverse
                            </Button>
                        }
                    >
                        {(form) => (
                            <Field
                                label="Reason"
                                htmlFor={`rev-${payment.id}`}
                                error={form.errors.reason}
                            >
                                <Input
                                    id={`rev-${payment.id}`}
                                    value={form.data.reason}
                                    onChange={(e) =>
                                        form.setData('reason', e.target.value)
                                    }
                                />
                            </Field>
                        )}
                    </FormDialog>
                )}
        </div>
    );
}

PaymentsIndex.layout = {
    breadcrumbs: [{ title: 'Payments', href: '/payments' }],
};
