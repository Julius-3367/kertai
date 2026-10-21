import { Head, Link, router } from '@inertiajs/react';
import { CheckCheck, Plus } from 'lucide-react';
import { useState } from 'react';
import { FormDialog } from '@/components/kertai/actions';
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
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { useCan } from '@/hooks/use-can';
import { formatDate, formatKes } from '@/lib/format';
import type { Option, Paginated } from '@/types/kertai';

type PayoutRow = {
    id: number;
    number: number;
    type: string;
    type_label: string;
    status: string;
    amount: number;
    fund: string;
    payee_name: string;
    description: string;
    approvals_count: number;
    created_at: string;
    can_decide: boolean;
};

export default function PayoutsIndex({
    payouts,
    filters,
    funds,
    members,
    types,
}: {
    payouts: Paginated<PayoutRow>;
    filters: { status: string };
    funds: {
        id: number;
        label: string;
        per_member: boolean;
        balance: number | null;
    }[];
    members: Option[];
    types: Record<string, string>;
}) {
    const can = useCan();
    const [selected, setSelected] = useState<number[]>([]);
    const decidable = payouts.data.filter((p) => p.can_decide);

    return (
        <>
            <Head title="Payouts" />
            <Page>
                <PageHeader
                    title="Payouts"
                    description="Money going out. Every payout needs two approvals from different officials, and never from the person who asked for it."
                    actions={
                        can('payouts.request') && (
                            <FormDialog
                                title="Request a payout"
                                action="/payouts"
                                submitLabel="Request"
                                wide
                                initial={{
                                    type: 'expense',
                                    account_id: String(
                                        funds.find((f) => !f.per_member)?.id ??
                                            '',
                                    ),
                                    amount: '',
                                    description: '',
                                    payee_member_id: '',
                                    payee_name: '',
                                    payee_phone: '',
                                }}
                                trigger={
                                    <Button>
                                        <Plus /> Request payout
                                    </Button>
                                }
                            >
                                {(form) => (
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field
                                            label="Type"
                                            htmlFor="po-type"
                                            error={form.errors.type}
                                        >
                                            <Select
                                                id="po-type"
                                                value={form.data.type}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'type',
                                                        e.target.value,
                                                    )
                                                }
                                                options={Object.entries(
                                                    types,
                                                ).map(([value, label]) => ({
                                                    value,
                                                    label,
                                                }))}
                                            />
                                        </Field>
                                        <Field
                                            label="From fund"
                                            htmlFor="po-fund"
                                            error={form.errors.account_id}
                                        >
                                            <Select
                                                id="po-fund"
                                                value={form.data.account_id}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'account_id',
                                                        e.target.value,
                                                    )
                                                }
                                                options={funds.map((f) => ({
                                                    value: f.id,
                                                    label:
                                                        f.balance === null
                                                            ? `${f.label} (per member)`
                                                            : `${f.label} (${formatKes(f.balance)})`,
                                                }))}
                                            />
                                        </Field>
                                        <Field
                                            label="Amount"
                                            htmlFor="po-amount"
                                            error={form.errors.amount}
                                        >
                                            <MoneyInput
                                                id="po-amount"
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
                                            label="What for"
                                            htmlFor="po-desc"
                                            error={form.errors.description}
                                        >
                                            <Input
                                                id="po-desc"
                                                value={form.data.description}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'description',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                        <Field
                                            label="Paying a member?"
                                            htmlFor="po-member"
                                            error={form.errors.payee_member_id}
                                        >
                                            <MemberSelect
                                                id="po-member"
                                                members={members}
                                                placeholder="No, someone else"
                                                value={
                                                    form.data.payee_member_id
                                                }
                                                onChange={(e) =>
                                                    form.setData(
                                                        'payee_member_id',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                        {!form.data.payee_member_id && (
                                            <Field
                                                label="Payee name"
                                                htmlFor="po-name"
                                                error={form.errors.payee_name}
                                            >
                                                <Input
                                                    id="po-name"
                                                    value={form.data.payee_name}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'payee_name',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                        )}
                                        <Field
                                            label="Payee phone (M-Pesa)"
                                            htmlFor="po-phone"
                                            error={form.errors.payee_phone}
                                        >
                                            <Input
                                                id="po-phone"
                                                value={form.data.payee_phone}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'payee_phone',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                    </div>
                                )}
                            </FormDialog>
                        )
                    }
                />

                <Section>
                    <div className="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <Select
                            className="sm:max-w-56"
                            value={filters.status}
                            onChange={(e) =>
                                router.get(
                                    '/payouts',
                                    { status: e.target.value },
                                    { preserveState: true, replace: true },
                                )
                            }
                            placeholder="All payouts"
                            options={[
                                {
                                    value: 'mine',
                                    label: 'Waiting for my approval',
                                },
                                { value: 'pending', label: 'Pending approval' },
                                {
                                    value: 'approved',
                                    label: 'Approved, to pay',
                                },
                                { value: 'paid', label: 'Paid' },
                                { value: 'rejected', label: 'Rejected' },
                                { value: 'cancelled', label: 'Cancelled' },
                            ]}
                        />
                        {can('payouts.approve') && decidable.length > 0 && (
                            <div className="flex items-center gap-2">
                                <span className="text-sm text-muted-foreground">
                                    {selected.length} selected
                                </span>
                                <Button
                                    size="sm"
                                    disabled={selected.length === 0}
                                    onClick={() => {
                                        if (
                                            window.confirm(
                                                `Approve ${selected.length} payouts?`,
                                            )
                                        ) {
                                            router.post(
                                                '/payouts/approve-many',
                                                { ids: selected },
                                                {
                                                    preserveScroll: true,
                                                    onSuccess: () =>
                                                        setSelected([]),
                                                },
                                            );
                                        }
                                    }}
                                >
                                    <CheckCheck /> Approve selected
                                </Button>
                            </div>
                        )}
                    </div>

                    {payouts.data.length === 0 ? (
                        <EmptyState title="No payouts here" />
                    ) : (
                        <Table>
                            <THead>
                                <TR>
                                    {decidable.length > 0 && (
                                        <TH>
                                            <Checkbox
                                                aria-label="Select all"
                                                checked={
                                                    selected.length > 0 &&
                                                    selected.length ===
                                                        decidable.length
                                                }
                                                onCheckedChange={(v) =>
                                                    setSelected(
                                                        v === true
                                                            ? decidable.map(
                                                                  (p) => p.id,
                                                              )
                                                            : [],
                                                    )
                                                }
                                            />
                                        </TH>
                                    )}
                                    <TH>No.</TH>
                                    <TH>Payee</TH>
                                    <TH>For</TH>
                                    <TH>From</TH>
                                    <TH>Requested</TH>
                                    <TH>Status</TH>
                                    <TH className="text-right">Amount</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {payouts.data.map((p) => (
                                    <TR key={p.id}>
                                        {decidable.length > 0 && (
                                            <TD>
                                                {p.can_decide && (
                                                    <Checkbox
                                                        aria-label={`Select payout ${p.number}`}
                                                        checked={selected.includes(
                                                            p.id,
                                                        )}
                                                        onCheckedChange={(v) =>
                                                            setSelected(
                                                                v === true
                                                                    ? [
                                                                          ...selected,
                                                                          p.id,
                                                                      ]
                                                                    : selected.filter(
                                                                          (
                                                                              id,
                                                                          ) =>
                                                                              id !==
                                                                              p.id,
                                                                      ),
                                                            )
                                                        }
                                                    />
                                                )}
                                            </TD>
                                        )}
                                        <TD>
                                            <Link
                                                href={`/payouts/${p.id}`}
                                                className="font-medium hover:underline"
                                            >
                                                #{p.number}
                                            </Link>
                                        </TD>
                                        <TD>{p.payee_name}</TD>
                                        <TD>
                                            {p.description}
                                            <div className="text-xs text-muted-foreground">
                                                {p.type_label}
                                            </div>
                                        </TD>
                                        <TD className="text-sm">{p.fund}</TD>
                                        <TD>{formatDate(p.created_at)}</TD>
                                        <TD>
                                            <StatusBadge status={p.status} />
                                            {p.status === 'pending' && (
                                                <div className="text-xs text-muted-foreground">
                                                    {p.approvals_count} of 2
                                                    approvals
                                                </div>
                                            )}
                                        </TD>
                                        <Num>{formatKes(p.amount)}</Num>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                    )}
                    <Pagination page={payouts} />
                </Section>
            </Page>
        </>
    );
}

PayoutsIndex.layout = { breadcrumbs: [{ title: 'Payouts', href: '/payouts' }] };
