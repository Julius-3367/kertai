import { Head } from '@inertiajs/react';
import { CalendarPlus, Pencil, Plus } from 'lucide-react';
import { ActionButton, FormDialog } from '@/components/kertai/actions';
import {
    Field,
    MemberSelect,
    MoneyInput,
    Select,
    Textarea,
} from '@/components/kertai/fields';
import {
    EmptyState,
    Page,
    PageHeader,
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
import {
    formatDate,
    formatKes,
    titleCase,
    today,
    toShillings,
} from '@/lib/format';
import type { Option } from '@/types/kertai';

type Asset = {
    id: number;
    name: string;
    category: string;
    quantity: number;
    available_today: number;
    hire_rate: number | null;
    hire_rate_input: string;
    purchase_cost: number | null;
    purchase_cost_input: string;
    acquired_on: string | null;
    condition: string;
    notes: string | null;
};

type Hire = {
    id: number;
    asset: string;
    hirer_name: string;
    hirer_phone: string | null;
    quantity: number;
    starts_on: string;
    ends_on: string;
    days: number;
    rate: number;
    total: number;
    paid: number;
    balance: number;
    status: string;
    notes: string | null;
};

const CONDITIONS = [
    { value: 'new', label: 'New' },
    { value: 'good', label: 'Good' },
    { value: 'fair', label: 'Fair' },
    { value: 'needs_repair', label: 'Needs repair' },
];

export default function AssetsIndex({
    assets,
    hires,
    categories,
    members,
    canManage,
}: {
    assets: Asset[];
    hires: Hire[];
    categories: Record<string, string>;
    members: Option[];
    canManage: boolean;
}) {
    const assetForm = (a: Asset | null) => ({
        name: a?.name ?? '',
        category: a?.category ?? 'tents',
        quantity: a ? String(a.quantity) : '',
        hire_rate: a?.hire_rate_input ?? '',
        purchase_cost: a?.purchase_cost_input ?? '',
        acquired_on: a?.acquired_on ?? '',
        condition: a?.condition ?? 'good',
        notes: a?.notes ?? '',
    });

    const assetFields = (
        form: {
            data: ReturnType<typeof assetForm>;
            setData: (k: keyof ReturnType<typeof assetForm>, v: string) => void;
            errors: Partial<Record<string, string>>;
        },
        prefix: string,
    ) => (
        <>
            <Field
                label="Name"
                htmlFor={`${prefix}-name`}
                error={form.errors.name}
            >
                <Input
                    id={`${prefix}-name`}
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                />
            </Field>
            <div className="grid grid-cols-2 gap-3">
                <Field label="Category" htmlFor={`${prefix}-cat`}>
                    <Select
                        id={`${prefix}-cat`}
                        value={form.data.category}
                        onChange={(e) =>
                            form.setData('category', e.target.value)
                        }
                        options={Object.entries(categories).map(
                            ([value, label]) => ({ value, label }),
                        )}
                    />
                </Field>
                <Field
                    label="How many"
                    htmlFor={`${prefix}-qty`}
                    error={form.errors.quantity}
                >
                    <Input
                        id={`${prefix}-qty`}
                        inputMode="numeric"
                        value={form.data.quantity}
                        onChange={(e) =>
                            form.setData('quantity', e.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Hire rate per unit per day"
                    htmlFor={`${prefix}-rate`}
                    error={form.errors.hire_rate}
                    hint="Leave empty until the group agrees a rate."
                >
                    <MoneyInput
                        id={`${prefix}-rate`}
                        value={form.data.hire_rate}
                        onChange={(e) =>
                            form.setData('hire_rate', e.target.value)
                        }
                    />
                </Field>
                <Field label="Condition" htmlFor={`${prefix}-cond`}>
                    <Select
                        id={`${prefix}-cond`}
                        value={form.data.condition}
                        onChange={(e) =>
                            form.setData('condition', e.target.value)
                        }
                        options={CONDITIONS}
                    />
                </Field>
                <Field
                    label="Bought for"
                    htmlFor={`${prefix}-cost`}
                    error={form.errors.purchase_cost}
                >
                    <MoneyInput
                        id={`${prefix}-cost`}
                        value={form.data.purchase_cost}
                        onChange={(e) =>
                            form.setData('purchase_cost', e.target.value)
                        }
                    />
                </Field>
                <Field label="Bought on" htmlFor={`${prefix}-date`}>
                    <Input
                        id={`${prefix}-date`}
                        type="date"
                        value={form.data.acquired_on}
                        onChange={(e) =>
                            form.setData('acquired_on', e.target.value)
                        }
                    />
                </Field>
            </div>
            <Field label="Notes" htmlFor={`${prefix}-notes`}>
                <Textarea
                    id={`${prefix}-notes`}
                    value={form.data.notes}
                    onChange={(e) => form.setData('notes', e.target.value)}
                />
            </Field>
        </>
    );

    return (
        <>
            <Head title="Assets and hire" />
            <Page>
                <PageHeader
                    title="Assets and hire"
                    description="Tents, chairs and equipment the group owns and hires out. Hire income goes to the assets fund."
                    actions={
                        canManage && (
                            <>
                                <FormDialog
                                    title="Add an asset"
                                    action="/assets"
                                    submitLabel="Add"
                                    initial={assetForm(null)}
                                    trigger={
                                        <Button variant="outline">
                                            <Plus /> Add asset
                                        </Button>
                                    }
                                >
                                    {(form) => assetFields(form, 'na')}
                                </FormDialog>
                                {assets.length > 0 && (
                                    <FormDialog
                                        title="Book a hire"
                                        description="The rate defaults to the asset's hire rate; type one in if none is set yet."
                                        action="/hires"
                                        submitLabel="Book"
                                        initial={{
                                            asset_id: String(assets[0].id),
                                            member_id: '',
                                            hirer_name: '',
                                            hirer_phone: '',
                                            quantity: '1',
                                            starts_on: today(),
                                            ends_on: today(),
                                            rate: '',
                                            notes: '',
                                        }}
                                        trigger={
                                            <Button>
                                                <CalendarPlus /> Book hire
                                            </Button>
                                        }
                                    >
                                        {(form) => (
                                            <>
                                                <Field
                                                    label="What"
                                                    htmlFor="hr-asset"
                                                    error={form.errors.asset_id}
                                                >
                                                    <Select
                                                        id="hr-asset"
                                                        value={
                                                            form.data.asset_id
                                                        }
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'asset_id',
                                                                e.target.value,
                                                            )
                                                        }
                                                        options={assets.map(
                                                            (a) => ({
                                                                value: a.id,
                                                                label: `${a.name} (${a.quantity})`,
                                                            }),
                                                        )}
                                                    />
                                                </Field>
                                                <div className="grid grid-cols-3 gap-3">
                                                    <Field
                                                        label="How many"
                                                        htmlFor="hr-qty"
                                                        error={
                                                            form.errors.quantity
                                                        }
                                                    >
                                                        <Input
                                                            id="hr-qty"
                                                            inputMode="numeric"
                                                            value={
                                                                form.data
                                                                    .quantity
                                                            }
                                                            onChange={(e) =>
                                                                form.setData(
                                                                    'quantity',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    </Field>
                                                    <Field
                                                        label="From"
                                                        htmlFor="hr-from"
                                                        error={
                                                            form.errors
                                                                .starts_on
                                                        }
                                                    >
                                                        <Input
                                                            id="hr-from"
                                                            type="date"
                                                            value={
                                                                form.data
                                                                    .starts_on
                                                            }
                                                            onChange={(e) =>
                                                                form.setData(
                                                                    'starts_on',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    </Field>
                                                    <Field
                                                        label="To"
                                                        htmlFor="hr-to"
                                                        error={
                                                            form.errors.ends_on
                                                        }
                                                    >
                                                        <Input
                                                            id="hr-to"
                                                            type="date"
                                                            value={
                                                                form.data
                                                                    .ends_on
                                                            }
                                                            onChange={(e) =>
                                                                form.setData(
                                                                    'ends_on',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    </Field>
                                                </div>
                                                <Field
                                                    label="Hirer is a member?"
                                                    htmlFor="hr-member"
                                                    error={
                                                        form.errors.member_id
                                                    }
                                                >
                                                    <MemberSelect
                                                        id="hr-member"
                                                        members={members}
                                                        placeholder="No"
                                                        value={
                                                            form.data.member_id
                                                        }
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'member_id',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </Field>
                                                {!form.data.member_id && (
                                                    <div className="grid grid-cols-2 gap-3">
                                                        <Field
                                                            label="Hirer name"
                                                            htmlFor="hr-name"
                                                            error={
                                                                form.errors
                                                                    .hirer_name
                                                            }
                                                        >
                                                            <Input
                                                                id="hr-name"
                                                                value={
                                                                    form.data
                                                                        .hirer_name
                                                                }
                                                                onChange={(e) =>
                                                                    form.setData(
                                                                        'hirer_name',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                            />
                                                        </Field>
                                                        <Field
                                                            label="Phone"
                                                            htmlFor="hr-phone"
                                                        >
                                                            <Input
                                                                id="hr-phone"
                                                                inputMode="tel"
                                                                value={
                                                                    form.data
                                                                        .hirer_phone
                                                                }
                                                                onChange={(e) =>
                                                                    form.setData(
                                                                        'hirer_phone',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                            />
                                                        </Field>
                                                    </div>
                                                )}
                                                <Field
                                                    label="Rate per unit per day"
                                                    htmlFor="hr-rate"
                                                    error={form.errors.rate}
                                                >
                                                    <MoneyInput
                                                        id="hr-rate"
                                                        placeholder={
                                                            toShillings(
                                                                assets.find(
                                                                    (a) =>
                                                                        String(
                                                                            a.id,
                                                                        ) ===
                                                                        form
                                                                            .data
                                                                            .asset_id,
                                                                )?.hire_rate ??
                                                                    null,
                                                            ) || 'Not set'
                                                        }
                                                        value={form.data.rate}
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'rate',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </Field>
                                                <Field
                                                    label="Notes"
                                                    htmlFor="hr-notes"
                                                >
                                                    <Input
                                                        id="hr-notes"
                                                        placeholder="Venue, delivery"
                                                        value={form.data.notes}
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'notes',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </Field>
                                            </>
                                        )}
                                    </FormDialog>
                                )}
                            </>
                        )
                    }
                />

                <Section title="What we own">
                    {assets.length === 0 ? (
                        <EmptyState title="No assets recorded" />
                    ) : (
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Asset</TH>
                                    <TH className="text-right">Owned</TH>
                                    <TH className="text-right">Free today</TH>
                                    <TH className="text-right">Hire rate</TH>
                                    <TH>Condition</TH>
                                    <TH />
                                </TR>
                            </THead>
                            <TBody>
                                {assets.map((a) => (
                                    <TR key={a.id}>
                                        <TD>
                                            <span className="font-medium">
                                                {a.name}
                                            </span>
                                            <div className="text-xs text-muted-foreground">
                                                {categories[a.category] ??
                                                    a.category}
                                            </div>
                                        </TD>
                                        <Num>{a.quantity}</Num>
                                        <Num>{a.available_today}</Num>
                                        <Num>
                                            {a.hire_rate === null ? (
                                                <span className="text-muted-foreground">
                                                    to be set
                                                </span>
                                            ) : (
                                                `${formatKes(a.hire_rate)}/day`
                                            )}
                                        </Num>
                                        <TD>{titleCase(a.condition)}</TD>
                                        <TD className="text-right">
                                            {canManage && (
                                                <FormDialog
                                                    title={`Edit ${a.name}`}
                                                    action={`/assets/${a.id}`}
                                                    method="put"
                                                    initial={assetForm(a)}
                                                    trigger={
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            aria-label={`Edit ${a.name}`}
                                                        >
                                                            <Pencil />
                                                        </Button>
                                                    }
                                                >
                                                    {(form) =>
                                                        assetFields(
                                                            form,
                                                            `ea-${a.id}`,
                                                        )
                                                    }
                                                </FormDialog>
                                            )}
                                        </TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                    )}
                </Section>

                <Section title="Hires">
                    {hires.length === 0 ? (
                        <EmptyState title="No hires yet" />
                    ) : (
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Dates</TH>
                                    <TH>Hirer</TH>
                                    <TH>What</TH>
                                    <TH className="text-right">Total</TH>
                                    <TH className="text-right">Owed</TH>
                                    <TH>Status</TH>
                                    <TH />
                                </TR>
                            </THead>
                            <TBody>
                                {hires.map((h) => (
                                    <TR key={h.id}>
                                        <TD className="whitespace-nowrap">
                                            {formatDate(h.starts_on)}
                                            {h.days > 1
                                                ? ` – ${formatDate(h.ends_on)}`
                                                : ''}
                                        </TD>
                                        <TD>
                                            {h.hirer_name}
                                            <div className="text-xs text-muted-foreground">
                                                {h.hirer_phone}
                                            </div>
                                        </TD>
                                        <TD>
                                            {h.quantity} × {h.asset}
                                            <div className="text-xs text-muted-foreground">
                                                {h.days} day(s) at{' '}
                                                {formatKes(h.rate)}
                                            </div>
                                        </TD>
                                        <Num>{formatKes(h.total)}</Num>
                                        <Num>{formatKes(h.balance)}</Num>
                                        <TD>
                                            <StatusBadge status={h.status} />
                                        </TD>
                                        <TD className="text-right whitespace-nowrap">
                                            {canManage && (
                                                <div className="flex justify-end gap-1">
                                                    {h.status === 'booked' && (
                                                        <>
                                                            <ActionButton
                                                                url={`/hires/${h.id}/status`}
                                                                data={{
                                                                    status: 'out',
                                                                }}
                                                                size="sm"
                                                                variant="ghost"
                                                            >
                                                                Handed over
                                                            </ActionButton>
                                                            <ActionButton
                                                                url={`/hires/${h.id}/status`}
                                                                data={{
                                                                    status: 'cancelled',
                                                                }}
                                                                size="sm"
                                                                variant="ghost"
                                                                confirm="Cancel this booking?"
                                                            >
                                                                Cancel
                                                            </ActionButton>
                                                        </>
                                                    )}
                                                    {h.status === 'out' && (
                                                        <ActionButton
                                                            url={`/hires/${h.id}/status`}
                                                            data={{
                                                                status: 'returned',
                                                            }}
                                                            size="sm"
                                                            variant="ghost"
                                                        >
                                                            Returned
                                                        </ActionButton>
                                                    )}
                                                    {h.balance > 0 &&
                                                        h.status !==
                                                            'cancelled' && (
                                                            <FormDialog
                                                                title={`Payment from ${h.hirer_name}`}
                                                                action={`/hires/${h.id}/payments`}
                                                                submitLabel="Record"
                                                                initial={{
                                                                    amount: toShillings(
                                                                        h.balance,
                                                                    ),
                                                                    channel:
                                                                        'cash',
                                                                    reference:
                                                                        '',
                                                                }}
                                                                trigger={
                                                                    <Button size="sm">
                                                                        Paid
                                                                    </Button>
                                                                }
                                                            >
                                                                {(form) => (
                                                                    <>
                                                                        <Field
                                                                            label="Amount"
                                                                            htmlFor={`hp-a-${h.id}`}
                                                                            error={
                                                                                form
                                                                                    .errors
                                                                                    .amount
                                                                            }
                                                                        >
                                                                            <MoneyInput
                                                                                id={`hp-a-${h.id}`}
                                                                                value={
                                                                                    form
                                                                                        .data
                                                                                        .amount
                                                                                }
                                                                                onChange={(
                                                                                    e,
                                                                                ) =>
                                                                                    form.setData(
                                                                                        'amount',
                                                                                        e
                                                                                            .target
                                                                                            .value,
                                                                                    )
                                                                                }
                                                                            />
                                                                        </Field>
                                                                        <Field
                                                                            label="How"
                                                                            htmlFor={`hp-c-${h.id}`}
                                                                        >
                                                                            <Select
                                                                                id={`hp-c-${h.id}`}
                                                                                value={
                                                                                    form
                                                                                        .data
                                                                                        .channel
                                                                                }
                                                                                onChange={(
                                                                                    e,
                                                                                ) =>
                                                                                    form.setData(
                                                                                        'channel',
                                                                                        e
                                                                                            .target
                                                                                            .value,
                                                                                    )
                                                                                }
                                                                                options={[
                                                                                    {
                                                                                        value: 'cash',
                                                                                        label: 'Cash',
                                                                                    },
                                                                                    {
                                                                                        value: 'paybill',
                                                                                        label: 'Paybill',
                                                                                    },
                                                                                    {
                                                                                        value: 'manual',
                                                                                        label: 'Bank',
                                                                                    },
                                                                                ]}
                                                                            />
                                                                        </Field>
                                                                        {form
                                                                            .data
                                                                            .channel ===
                                                                            'paybill' && (
                                                                            <Field
                                                                                label="M-Pesa receipt"
                                                                                htmlFor={`hp-r-${h.id}`}
                                                                                error={
                                                                                    form
                                                                                        .errors
                                                                                        .reference
                                                                                }
                                                                            >
                                                                                <Input
                                                                                    id={`hp-r-${h.id}`}
                                                                                    value={
                                                                                        form
                                                                                            .data
                                                                                            .reference
                                                                                    }
                                                                                    onChange={(
                                                                                        e,
                                                                                    ) =>
                                                                                        form.setData(
                                                                                            'reference',
                                                                                            e
                                                                                                .target
                                                                                                .value,
                                                                                        )
                                                                                    }
                                                                                />
                                                                            </Field>
                                                                        )}
                                                                    </>
                                                                )}
                                                            </FormDialog>
                                                        )}
                                                </div>
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

AssetsIndex.layout = {
    breadcrumbs: [{ title: 'Assets and hire', href: '/assets' }],
};
