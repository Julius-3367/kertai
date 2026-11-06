import type { InertiaFormProps } from '@inertiajs/react';
import {
    Download,
    HandCoins,
    MapPin,
    Pencil,
    Plus,
    Receipt,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { ActionButton, FormDialog } from '@/components/kertai/actions';
import {
    Field,
    MemberSelect,
    MoneyInput,
    Select,
    Textarea,
} from '@/components/kertai/fields';
import { DefinitionList, EmptyState, Section } from '@/components/kertai/page';
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
import { formatDate, formatKes, today, toShillings } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Option } from '@/types/kertai';

export type CostSummary = {
    costs: number;
    by_category: Record<string, number>;
    paid: number;
    awaiting: number;
    owed: number;
    in_kind: number;
    sales: number;
    income: number;
    revenue: number;
    profit: number;
    budget: Record<string, number>;
    budget_total: number;
    acres: number;
    cost_per_acre: number | null;
    profit_per_acre: number | null;
    yield: number | null;
    yield_unit: string | null;
    cost_per_unit: number | null;
    labour: number;
};

export type Plot = {
    id: number;
    name: string;
    location: string | null;
    size: string;
    size_unit: string;
    acres: number;
    tenure: string;
    member_id: number | null;
    member: string | null;
    owner_name: string | null;
    owner_phone: string | null;
    lease_amount: number | null;
    lease_per: string | null;
    lease_starts_on: string | null;
    lease_ends_on: string | null;
    lease_ending_soon: boolean;
    notes: string | null;
    status: string;
};

export type Season = {
    id: number;
    name: string;
    starts_on: string;
    ends_on: string | null;
    status: string;
    yield_quantity: string | null;
    yield_unit: string | null;
    notes: string | null;
    summary: CostSummary;
};

export type Cost = {
    id: number;
    season_id: number | null;
    plot_id: number | null;
    plot: string | null;
    category: string;
    description: string;
    quantity: string;
    unit: string;
    unit_cost: number;
    amount: number;
    incurred_on: string;
    supplier_name: string | null;
    supplier_phone: string | null;
    receipt_ref: string | null;
    labour_type: string | null;
    workers: number | null;
    days: string | null;
    paid_by: string;
    member: string | null;
    payout_id: number | null;
    payout_number: number | null;
    state: 'paid' | 'awaiting' | 'rejected' | 'owed' | 'in_kind';
};

export type CostOptions = {
    categories: Record<string, string>;
    units: string[];
    labourTypes: Record<string, string>;
    tenures: Record<string, string>;
    leasePer: Record<string, string>;
};

/** 'all' or a season id. */
export type SeasonChoice = 'all' | number;

type Shared = {
    projectId: number;
    seasons: Season[];
    plots: Plot[];
    members: Option[];
    options: CostOptions;
    canManage: boolean;
};

const toOptions = (record: Record<string, string>) =>
    Object.entries(record).map(([value, label]) => ({ value, label }));

/** Plain quantities: 2.5, 18, 1,200 (no trailing .00). */
function qty(value: string | number | null): string {
    if (value === null) {
        return '';
    }

    return Number(value).toLocaleString('en-KE', { maximumFractionDigits: 2 });
}

export function SeasonPicker({
    seasons,
    value,
    onChange,
}: {
    seasons: Season[];
    value: SeasonChoice;
    onChange: (value: SeasonChoice) => void;
}) {
    if (seasons.length === 0) {
        return null;
    }

    return (
        <div className="flex items-center gap-2">
            <label
                htmlFor="season-pick"
                className="text-sm text-muted-foreground"
            >
                Season
            </label>
            <Select
                id="season-pick"
                className="w-auto"
                value={value}
                onChange={(e) =>
                    onChange(
                        e.target.value === 'all'
                            ? 'all'
                            : Number(e.target.value),
                    )
                }
                options={[
                    { value: 'all', label: 'All seasons' },
                    ...seasons.map((s) => ({
                        value: s.id,
                        label: `${s.name}${s.status === 'closed' ? ' (closed)' : ''}`,
                    })),
                ]}
            />
        </div>
    );
}

/* ------------------------------------------------------------------ costs */

type CostForm = {
    category: string;
    description: string;
    project_season_id: string;
    project_plot_id: string;
    labour_type: string;
    workers: string;
    days: string;
    quantity: string;
    unit: string;
    unit_cost: string;
    incurred_on: string;
    supplier_name: string;
    supplier_phone: string;
    receipt_ref: string;
    paid_by: string;
    member_id: string;
};

function costAmount(data: CostForm): { amount: number; manDays: number } {
    const price = Number(data.unit_cost) || 0;

    if (data.category === 'labour') {
        const manDays = (Number(data.workers) || 0) * (Number(data.days) || 0);

        return { amount: manDays * price, manDays };
    }

    return { amount: (Number(data.quantity) || 0) * price, manDays: 0 };
}

const PAID_BY = [
    {
        value: 'fund',
        label: 'From the project fund',
        hint: 'Raises a payout. Two officials approve it, then the treasurer pays.',
    },
    {
        value: 'owed',
        label: 'Owed to the supplier',
        hint: 'Bought on credit. Request the payout later from the cost list.',
    },
    {
        value: 'member',
        label: 'Paid by a member',
        hint: "Counts as a cost and as that member's contribution, so it adds to their share of profit.",
    },
];

function CostFields({
    form,
    shared,
    creating,
}: {
    form: InertiaFormProps<CostForm>;
    shared: Shared;
    creating: boolean;
}) {
    const { data, setData, errors } = form;
    const labour = data.category === 'labour';
    const { amount, manDays } = costAmount(data);
    const openSeasons = shared.seasons.filter((s) => s.status === 'open');

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <Field
                label="Category"
                htmlFor="pc-category"
                error={errors.category}
            >
                <Select
                    id="pc-category"
                    value={data.category}
                    onChange={(e) => setData('category', e.target.value)}
                    options={toOptions(shared.options.categories)}
                />
            </Field>
            <Field label="Date" htmlFor="pc-date" error={errors.incurred_on}>
                <Input
                    id="pc-date"
                    type="date"
                    max={today()}
                    value={data.incurred_on}
                    onChange={(e) => setData('incurred_on', e.target.value)}
                />
            </Field>
            <Field
                label="What was it?"
                htmlFor="pc-desc"
                error={errors.description}
                className="sm:col-span-2"
            >
                <Input
                    id="pc-desc"
                    placeholder={
                        labour ? 'First weeding' : 'DAP fertiliser, 50 kg bags'
                    }
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                />
            </Field>

            {labour ? (
                <>
                    <Field
                        label="Type of labour"
                        htmlFor="pc-ltype"
                        error={errors.labour_type}
                    >
                        <Select
                            id="pc-ltype"
                            value={data.labour_type}
                            onChange={(e) =>
                                setData('labour_type', e.target.value)
                            }
                            options={toOptions(shared.options.labourTypes)}
                        />
                    </Field>
                    <Field
                        label="Number of workers"
                        htmlFor="pc-workers"
                        error={errors.workers}
                    >
                        <Input
                            id="pc-workers"
                            type="number"
                            min={1}
                            step={1}
                            value={data.workers}
                            onChange={(e) => setData('workers', e.target.value)}
                        />
                    </Field>
                    <Field
                        label="Days"
                        htmlFor="pc-days"
                        error={errors.days}
                        hint="Half days are fine, e.g. 2.5"
                    >
                        <Input
                            id="pc-days"
                            type="number"
                            min={0.5}
                            step={0.5}
                            value={data.days}
                            onChange={(e) => setData('days', e.target.value)}
                        />
                    </Field>
                    <Field
                        label="Rate per worker per day"
                        htmlFor="pc-rate"
                        error={errors.unit_cost}
                        hint="For a contract, use 1 worker × 1 day × the contract price"
                    >
                        <MoneyInput
                            id="pc-rate"
                            value={data.unit_cost}
                            onChange={(e) =>
                                setData('unit_cost', e.target.value)
                            }
                        />
                    </Field>
                </>
            ) : (
                <>
                    <Field
                        label="Quantity"
                        htmlFor="pc-qty"
                        error={errors.quantity}
                        hint="Decimals are fine, e.g. 2.5"
                    >
                        <Input
                            id="pc-qty"
                            inputMode="decimal"
                            value={data.quantity}
                            onChange={(e) =>
                                setData('quantity', e.target.value)
                            }
                        />
                    </Field>
                    <Field label="Unit" htmlFor="pc-unit" error={errors.unit}>
                        <Input
                            id="pc-unit"
                            list="pc-units"
                            placeholder="bags, kg, litres…"
                            value={data.unit}
                            onChange={(e) => setData('unit', e.target.value)}
                        />
                        <datalist id="pc-units">
                            {shared.options.units.map((u) => (
                                <option key={u} value={u} />
                            ))}
                        </datalist>
                    </Field>
                    <Field
                        label="Cost per unit"
                        htmlFor="pc-price"
                        error={errors.unit_cost}
                        className="sm:col-span-2"
                    >
                        <MoneyInput
                            id="pc-price"
                            value={data.unit_cost}
                            onChange={(e) =>
                                setData('unit_cost', e.target.value)
                            }
                        />
                    </Field>
                </>
            )}

            <div className="flex flex-wrap items-baseline justify-between gap-2 rounded-md bg-muted px-3 py-2 sm:col-span-2">
                <span className="text-sm text-muted-foreground">
                    Amount
                    {labour && manDays > 0 && ` (${qty(manDays)} man-days)`}
                </span>
                <span className="text-lg font-semibold tabular-nums">
                    {formatKes(Math.round(amount * 100))}
                </span>
            </div>

            {shared.seasons.length > 0 && (
                <Field
                    label="Season"
                    htmlFor="pc-season"
                    error={errors.project_season_id}
                >
                    <Select
                        id="pc-season"
                        value={data.project_season_id}
                        onChange={(e) =>
                            setData('project_season_id', e.target.value)
                        }
                        placeholder="Not in a season"
                        options={openSeasons.map((s) => ({
                            value: s.id,
                            label: s.name,
                        }))}
                    />
                </Field>
            )}
            {shared.plots.length > 0 && (
                <Field
                    label="Plot"
                    htmlFor="pc-plot"
                    error={errors.project_plot_id}
                >
                    <Select
                        id="pc-plot"
                        value={data.project_plot_id}
                        onChange={(e) =>
                            setData('project_plot_id', e.target.value)
                        }
                        placeholder="All the land"
                        options={shared.plots
                            .filter((p) => p.status === 'active')
                            .map((p) => ({ value: p.id, label: p.name }))}
                    />
                </Field>
            )}
            <Field
                label={labour ? 'Paid to' : 'Supplier or paid to'}
                htmlFor="pc-supplier"
                error={errors.supplier_name}
            >
                <Input
                    id="pc-supplier"
                    placeholder={labour ? 'Casual workers' : 'Agrovet name'}
                    value={data.supplier_name}
                    onChange={(e) => setData('supplier_name', e.target.value)}
                />
            </Field>
            <Field
                label="Their phone"
                htmlFor="pc-phone"
                error={errors.supplier_phone}
            >
                <Input
                    id="pc-phone"
                    inputMode="tel"
                    value={data.supplier_phone}
                    onChange={(e) => setData('supplier_phone', e.target.value)}
                />
            </Field>
            <Field
                label="Receipt or invoice no."
                htmlFor="pc-ref"
                error={errors.receipt_ref}
            >
                <Input
                    id="pc-ref"
                    value={data.receipt_ref}
                    onChange={(e) => setData('receipt_ref', e.target.value)}
                />
            </Field>

            {creating && (
                <fieldset className="grid gap-2 sm:col-span-2">
                    <legend className="mb-1 text-sm font-medium">
                        How is it paid?
                    </legend>
                    {PAID_BY.map((p) => (
                        <label
                            key={p.value}
                            className={cn(
                                'flex cursor-pointer items-start gap-3 rounded-md border px-3 py-2',
                                data.paid_by === p.value &&
                                    'border-primary bg-accent',
                            )}
                        >
                            <input
                                type="radio"
                                name="paid_by"
                                value={p.value}
                                checked={data.paid_by === p.value}
                                onChange={() => setData('paid_by', p.value)}
                                className="mt-1 accent-primary"
                            />
                            <span className="grid gap-0.5">
                                <span className="text-sm font-medium">
                                    {p.label}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {p.hint}
                                </span>
                            </span>
                        </label>
                    ))}
                    {data.paid_by === 'member' && (
                        <Field
                            label="Which member paid?"
                            htmlFor="pc-member"
                            error={errors.member_id}
                        >
                            <MemberSelect
                                id="pc-member"
                                members={shared.members}
                                value={data.member_id}
                                onChange={(e) =>
                                    setData('member_id', e.target.value)
                                }
                            />
                        </Field>
                    )}
                </fieldset>
            )}
        </div>
    );
}

export function RecordCostDialog({
    shared,
    seasonId,
    trigger,
}: {
    shared: Shared;
    seasonId: SeasonChoice;
    trigger: ReactNode;
}) {
    const open = shared.seasons.find(
        (s) => s.status === 'open' && (seasonId === 'all' || s.id === seasonId),
    );

    return (
        <FormDialog<CostForm>
            title="Record a cost"
            description="Labour, inputs, lease, transport: anything spent on this project."
            action={`/projects/${shared.projectId}/costs`}
            submitLabel="Save cost"
            wide
            initial={{
                category: 'fertiliser',
                description: '',
                project_season_id: open ? String(open.id) : '',
                project_plot_id: '',
                labour_type: 'casual',
                workers: '',
                days: '',
                quantity: '',
                unit: '',
                unit_cost: '',
                incurred_on: today(),
                supplier_name: '',
                supplier_phone: '',
                receipt_ref: '',
                paid_by: 'fund',
                member_id: '',
            }}
            trigger={trigger}
        >
            {(form) => <CostFields form={form} shared={shared} creating />}
        </FormDialog>
    );
}

function EditCostDialog({ shared, cost }: { shared: Shared; cost: Cost }) {
    return (
        <FormDialog<CostForm>
            title="Edit cost"
            description={
                cost.paid_by === 'owed'
                    ? undefined
                    : 'The amount is fixed once a payout exists or a member has been credited.'
            }
            action={`/projects/${shared.projectId}/costs/${cost.id}`}
            method="put"
            wide
            initial={{
                category: cost.category,
                description: cost.description,
                project_season_id: cost.season_id ? String(cost.season_id) : '',
                project_plot_id: cost.plot_id ? String(cost.plot_id) : '',
                labour_type: cost.labour_type ?? 'casual',
                workers: cost.workers ? String(cost.workers) : '',
                days: cost.days ? String(Number(cost.days)) : '',
                quantity: String(Number(cost.quantity)),
                unit: cost.unit,
                unit_cost: toShillings(cost.unit_cost),
                incurred_on: cost.incurred_on,
                supplier_name: cost.supplier_name ?? '',
                supplier_phone: cost.supplier_phone ?? '',
                receipt_ref: cost.receipt_ref ?? '',
                paid_by: cost.paid_by,
                member_id: '',
            }}
            trigger={
                <Button size="sm" variant="ghost" aria-label="Edit cost">
                    <Pencil />
                </Button>
            }
        >
            {(form) => (
                <CostFields form={form} shared={shared} creating={false} />
            )}
        </FormDialog>
    );
}

const STATE_BADGE: Record<Cost['state'], { status: string; label: string }> = {
    paid: { status: 'paid', label: 'Paid from fund' },
    awaiting: { status: 'pending', label: 'Awaiting approval' },
    rejected: { status: 'rejected', label: 'Payout turned down' },
    owed: { status: 'unpaid', label: 'Owed' },
    in_kind: { status: 'accepted', label: 'Paid by member' },
};

export function CostsPanel({
    shared,
    costs,
    seasonId,
}: {
    shared: Shared;
    costs: Cost[];
    seasonId: SeasonChoice;
}) {
    const [category, setCategory] = useState('all');
    const inSeason = costs.filter(
        (c) => seasonId === 'all' || c.season_id === seasonId,
    );
    const rows = inSeason.filter(
        (c) => category === 'all' || c.category === category,
    );
    const counted = rows.filter((c) => c.state !== 'rejected');
    const used = Array.from(new Set(inSeason.map((c) => c.category)));
    const season = shared.seasons.find((s) => s.id === seasonId);
    const locked = season?.status === 'closed';
    const label = (key: string) => shared.options.categories[key] ?? key;

    return (
        <Section
            title={season ? `Costs: ${season.name}` : 'Costs'}
            description={
                locked
                    ? 'This season is closed, so its costs are locked.'
                    : 'Everything spent on the project: what it was, how much, and how it was paid.'
            }
            actions={
                <div className="flex flex-wrap items-center gap-2">
                    {used.length > 1 && (
                        <Select
                            aria-label="Filter by category"
                            className="w-auto"
                            value={category}
                            onChange={(e) => setCategory(e.target.value)}
                            options={[
                                { value: 'all', label: 'All categories' },
                                ...used.map((k) => ({
                                    value: k,
                                    label: label(k),
                                })),
                            ]}
                        />
                    )}
                    <Button size="sm" variant="outline" asChild>
                        <a
                            href={`/projects/${shared.projectId}/costs.csv${seasonId === 'all' ? '' : `?season=${seasonId}`}`}
                        >
                            <Download /> CSV
                        </a>
                    </Button>
                    {shared.canManage && !locked && (
                        <RecordCostDialog
                            shared={shared}
                            seasonId={seasonId}
                            trigger={
                                <Button size="sm">
                                    <Plus /> Record a cost
                                </Button>
                            }
                        />
                    )}
                </div>
            }
        >
            {rows.length === 0 ? (
                <EmptyState title="No costs recorded yet">
                    {shared.canManage
                        ? 'Use "Record a cost" each time money is spent: labour, seed, fertiliser, lease, transport.'
                        : 'The project coordinator records costs here as they happen.'}
                </EmptyState>
            ) : (
                <Table>
                    <THead>
                        <TR>
                            <TH>Date</TH>
                            <TH>Category</TH>
                            <TH>What</TH>
                            <TH className="text-right">Quantity</TH>
                            <TH className="text-right">Unit cost</TH>
                            <TH className="text-right">Amount</TH>
                            <TH>Payment</TH>
                            {shared.canManage && <TH />}
                        </TR>
                    </THead>
                    <TBody>
                        {rows.map((c) => {
                            const badge = STATE_BADGE[c.state];
                            const costSeason = shared.seasons.find(
                                (s) => s.id === c.season_id,
                            );
                            const editable = costSeason?.status !== 'closed';

                            return (
                                <TR
                                    key={c.id}
                                    className={cn(
                                        c.state === 'rejected' &&
                                            'text-muted-foreground line-through decoration-muted-foreground/40',
                                    )}
                                >
                                    <TD className="whitespace-nowrap">
                                        {formatDate(c.incurred_on)}
                                    </TD>
                                    <TD>{label(c.category)}</TD>
                                    <TD>
                                        <div>{c.description}</div>
                                        <div className="text-xs text-muted-foreground">
                                            {[
                                                c.workers
                                                    ? `${shared.options.labourTypes[c.labour_type ?? ''] ?? ''} · ${c.workers} × ${qty(c.days)} days`
                                                    : null,
                                                c.supplier_name,
                                                c.supplier_phone,
                                                c.plot,
                                                c.receipt_ref
                                                    ? `Receipt ${c.receipt_ref}`
                                                    : null,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </div>
                                    </TD>
                                    <Num className="whitespace-nowrap">
                                        {qty(c.quantity)} {c.unit}
                                    </Num>
                                    <Num>{formatKes(c.unit_cost)}</Num>
                                    <Num className="font-medium">
                                        {formatKes(c.amount)}
                                    </Num>
                                    <TD>
                                        <StatusBadge
                                            status={badge.status}
                                            label={
                                                c.state === 'in_kind' &&
                                                c.member
                                                    ? `Paid by ${c.member}`
                                                    : c.payout_number &&
                                                        c.state !== 'paid'
                                                      ? `${badge.label} · #${c.payout_number}`
                                                      : badge.label
                                            }
                                        />
                                    </TD>
                                    {shared.canManage && (
                                        <TD className="text-right whitespace-nowrap">
                                            {(c.state === 'owed' ||
                                                c.state === 'rejected') && (
                                                <ActionButton
                                                    size="sm"
                                                    variant="outline"
                                                    url={`/projects/${shared.projectId}/costs/${c.id}/payout`}
                                                    confirm={`Request a payout of ${formatKes(c.amount)} to ${c.supplier_name ?? 'the supplier'} from the project fund?`}
                                                >
                                                    <HandCoins /> Pay
                                                </ActionButton>
                                            )}
                                            {editable && (
                                                <EditCostDialog
                                                    shared={shared}
                                                    cost={c}
                                                />
                                            )}
                                            {editable &&
                                                c.state !== 'paid' &&
                                                c.state !== 'awaiting' && (
                                                    <ActionButton
                                                        size="sm"
                                                        variant="ghost"
                                                        aria-label="Delete cost"
                                                        method="delete"
                                                        url={`/projects/${shared.projectId}/costs/${c.id}`}
                                                        confirm={`Delete "${c.description}" (${formatKes(c.amount)})?`}
                                                    >
                                                        <Trash2 />
                                                    </ActionButton>
                                                )}
                                        </TD>
                                    )}
                                </TR>
                            );
                        })}
                    </TBody>
                </Table>
            )}
            {rows.length > 0 && (
                <div className="mt-3 flex justify-between gap-4 border-t pt-3 text-sm font-semibold tabular-nums">
                    <span>
                        Total
                        {category !== 'all' && ` for ${label(category)}`}
                    </span>
                    <span>
                        {formatKes(counted.reduce((t, c) => t + c.amount, 0))}
                    </span>
                </div>
            )}
        </Section>
    );
}

/* ------------------------------------------------------------------- land */

type PlotForm = {
    name: string;
    location: string;
    size: string;
    size_unit: string;
    tenure: string;
    member_id: string;
    owner_name: string;
    owner_phone: string;
    lease_amount: string;
    lease_per: string;
    lease_starts_on: string;
    lease_ends_on: string;
    notes: string;
    status: string;
};

function PlotFields({
    form,
    shared,
    editing,
}: {
    form: InertiaFormProps<PlotForm>;
    shared: Shared;
    editing: boolean;
}) {
    const { data, setData, errors } = form;
    const leased = data.tenure === 'leased';

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <Field
                label="Name"
                htmlFor="pl-name"
                error={errors.name}
                className="sm:col-span-2"
            >
                <Input
                    id="pl-name"
                    placeholder="Plot A, by the road"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                />
            </Field>
            <Field label="Size" htmlFor="pl-size" error={errors.size}>
                <Input
                    id="pl-size"
                    inputMode="decimal"
                    value={data.size}
                    onChange={(e) => setData('size', e.target.value)}
                />
            </Field>
            <Field label="Measured in" htmlFor="pl-unit">
                <Select
                    id="pl-unit"
                    value={data.size_unit}
                    onChange={(e) => setData('size_unit', e.target.value)}
                    options={[
                        { value: 'acres', label: 'Acres' },
                        { value: 'hectares', label: 'Hectares' },
                    ]}
                />
            </Field>
            <Field label="Location" htmlFor="pl-loc" error={errors.location}>
                <Input
                    id="pl-loc"
                    placeholder="Village, landmark"
                    value={data.location}
                    onChange={(e) => setData('location', e.target.value)}
                />
            </Field>
            <Field
                label="Whose land?"
                htmlFor="pl-tenure"
                error={errors.tenure}
            >
                <Select
                    id="pl-tenure"
                    value={data.tenure}
                    onChange={(e) => setData('tenure', e.target.value)}
                    options={toOptions(shared.options.tenures)}
                />
            </Field>
            {data.tenure === 'member' ? (
                <Field
                    label="Member lending it"
                    htmlFor="pl-member"
                    error={errors.member_id}
                    className="sm:col-span-2"
                >
                    <MemberSelect
                        id="pl-member"
                        members={shared.members}
                        value={data.member_id}
                        onChange={(e) => setData('member_id', e.target.value)}
                    />
                </Field>
            ) : (
                <>
                    <Field
                        label={leased ? 'Landlord' : 'Owner'}
                        htmlFor="pl-owner"
                        error={errors.owner_name}
                    >
                        <Input
                            id="pl-owner"
                            value={data.owner_name}
                            onChange={(e) =>
                                setData('owner_name', e.target.value)
                            }
                        />
                    </Field>
                    <Field
                        label="Their phone"
                        htmlFor="pl-phone"
                        error={errors.owner_phone}
                    >
                        <Input
                            id="pl-phone"
                            inputMode="tel"
                            value={data.owner_phone}
                            onChange={(e) =>
                                setData('owner_phone', e.target.value)
                            }
                        />
                    </Field>
                </>
            )}
            {leased && (
                <>
                    <Field
                        label="Lease amount"
                        htmlFor="pl-lease"
                        error={errors.lease_amount}
                        hint="For the whole plot"
                    >
                        <MoneyInput
                            id="pl-lease"
                            value={data.lease_amount}
                            onChange={(e) =>
                                setData('lease_amount', e.target.value)
                            }
                        />
                    </Field>
                    <Field
                        label="Paid"
                        htmlFor="pl-per"
                        error={errors.lease_per}
                    >
                        <Select
                            id="pl-per"
                            value={data.lease_per}
                            onChange={(e) =>
                                setData('lease_per', e.target.value)
                            }
                            options={toOptions(shared.options.leasePer)}
                        />
                    </Field>
                    <Field
                        label="Lease starts"
                        htmlFor="pl-from"
                        error={errors.lease_starts_on}
                    >
                        <Input
                            id="pl-from"
                            type="date"
                            value={data.lease_starts_on}
                            onChange={(e) =>
                                setData('lease_starts_on', e.target.value)
                            }
                        />
                    </Field>
                    <Field
                        label="Lease ends"
                        htmlFor="pl-to"
                        error={errors.lease_ends_on}
                        hint="Kertai warns two months before"
                    >
                        <Input
                            id="pl-to"
                            type="date"
                            value={data.lease_ends_on}
                            onChange={(e) =>
                                setData('lease_ends_on', e.target.value)
                            }
                        />
                    </Field>
                </>
            )}
            <Field
                label="Notes"
                htmlFor="pl-notes"
                error={errors.notes}
                className="sm:col-span-2"
            >
                <Textarea
                    id="pl-notes"
                    className="min-h-16"
                    placeholder="Soil, water, access"
                    value={data.notes}
                    onChange={(e) => setData('notes', e.target.value)}
                />
            </Field>
            {editing && (
                <Field label="Status" htmlFor="pl-status">
                    <Select
                        id="pl-status"
                        value={data.status}
                        onChange={(e) => setData('status', e.target.value)}
                        options={[
                            { value: 'active', label: 'In use' },
                            { value: 'released', label: 'Given back' },
                        ]}
                    />
                </Field>
            )}
        </div>
    );
}

function plotInitial(plot?: Plot): PlotForm {
    return {
        name: plot?.name ?? '',
        location: plot?.location ?? '',
        size: plot ? String(Number(plot.size)) : '',
        size_unit: plot?.size_unit ?? 'acres',
        tenure: plot?.tenure ?? 'leased',
        member_id: plot?.member_id ? String(plot.member_id) : '',
        owner_name: plot?.owner_name ?? '',
        owner_phone: plot?.owner_phone ?? '',
        lease_amount: toShillings(plot?.lease_amount ?? null),
        lease_per: plot?.lease_per ?? 'season',
        lease_starts_on: plot?.lease_starts_on ?? '',
        lease_ends_on: plot?.lease_ends_on ?? '',
        notes: plot?.notes ?? '',
        status: plot?.status ?? 'active',
    };
}

export function LandPanel({ shared }: { shared: Shared }) {
    const active = shared.plots.filter((p) => p.status === 'active');
    const acres = active.reduce((t, p) => t + p.acres, 0);

    return (
        <Section
            title="Land"
            description={
                active.length
                    ? `${qty(acres)} acres in use. Lease payments go under Costs as "Land lease".`
                    : 'The plots this project farms, their size and who owns them.'
            }
            actions={
                shared.canManage && (
                    <FormDialog<PlotForm>
                        title="Add a plot"
                        action={`/projects/${shared.projectId}/plots`}
                        wide
                        initial={plotInitial()}
                        trigger={
                            <Button size="sm">
                                <Plus /> Add a plot
                            </Button>
                        }
                    >
                        {(form) => (
                            <PlotFields
                                form={form}
                                shared={shared}
                                editing={false}
                            />
                        )}
                    </FormDialog>
                )
            }
        >
            {shared.plots.length === 0 ? (
                <EmptyState title="No land recorded">
                    Add each plot once, with its size and lease, so the report
                    can show the cost per acre.
                </EmptyState>
            ) : (
                <div className="grid gap-4 md:grid-cols-2">
                    {shared.plots.map((p) => (
                        <div
                            key={p.id}
                            className={cn(
                                'grid gap-3 rounded-lg border p-4',
                                p.status === 'released' && 'opacity-70',
                            )}
                        >
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <h3 className="font-medium">{p.name}</h3>
                                    {p.location && (
                                        <p className="flex items-center gap-1 text-xs text-muted-foreground">
                                            <MapPin className="size-3" />
                                            {p.location}
                                        </p>
                                    )}
                                </div>
                                <div className="flex items-center gap-1">
                                    <StatusBadge
                                        status={
                                            p.status === 'released'
                                                ? 'released'
                                                : 'neutral'
                                        }
                                        label={
                                            p.status === 'released'
                                                ? 'Given back'
                                                : shared.options.tenures[
                                                      p.tenure
                                                  ]
                                        }
                                    />
                                    {shared.canManage && (
                                        <>
                                            <FormDialog<PlotForm>
                                                title="Edit plot"
                                                action={`/projects/${shared.projectId}/plots/${p.id}`}
                                                method="put"
                                                wide
                                                initial={plotInitial(p)}
                                                trigger={
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        aria-label="Edit plot"
                                                    >
                                                        <Pencil />
                                                    </Button>
                                                }
                                            >
                                                {(form) => (
                                                    <PlotFields
                                                        form={form}
                                                        shared={shared}
                                                        editing
                                                    />
                                                )}
                                            </FormDialog>
                                            <ActionButton
                                                size="sm"
                                                variant="ghost"
                                                aria-label="Delete plot"
                                                method="delete"
                                                url={`/projects/${shared.projectId}/plots/${p.id}`}
                                                confirm={`Delete ${p.name}?`}
                                            >
                                                <Trash2 />
                                            </ActionButton>
                                        </>
                                    )}
                                </div>
                            </div>
                            <DefinitionList
                                items={[
                                    {
                                        label: 'Size',
                                        value:
                                            p.size_unit === 'hectares'
                                                ? `${qty(p.size)} ha (${qty(p.acres)} acres)`
                                                : `${qty(p.size)} acres`,
                                    },
                                    {
                                        label:
                                            p.tenure === 'leased'
                                                ? 'Landlord'
                                                : 'Owner',
                                        value:
                                            [
                                                p.member ?? p.owner_name,
                                                p.owner_phone,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ') || null,
                                    },
                                    ...(p.tenure === 'leased'
                                        ? [
                                              {
                                                  label: 'Lease',
                                                  value: `${formatKes(p.lease_amount)} ${shared.options.leasePer[p.lease_per ?? ''] ?? ''}`,
                                              },
                                              {
                                                  label: 'Lease period',
                                                  value:
                                                      p.lease_starts_on ||
                                                      p.lease_ends_on
                                                          ? `${formatDate(p.lease_starts_on)} to ${formatDate(p.lease_ends_on)}`
                                                          : null,
                                              },
                                          ]
                                        : []),
                                ]}
                            />
                            {p.notes && (
                                <p className="text-sm whitespace-pre-line text-muted-foreground">
                                    {p.notes}
                                </p>
                            )}
                            {p.lease_ending_soon && (
                                <StatusBadge
                                    status="late"
                                    className="justify-self-start"
                                    label={`Lease ends ${formatDate(p.lease_ends_on)}. Renew or plan to hand it back.`}
                                />
                            )}
                        </div>
                    ))}
                </div>
            )}
        </Section>
    );
}

/* ---------------------------------------------------- seasons and budget */

type SeasonForm = {
    name: string;
    starts_on: string;
    ends_on: string;
    yield_quantity: string;
    yield_unit: string;
    notes: string;
};

function SeasonFields({ form }: { form: InertiaFormProps<SeasonForm> }) {
    const { data, setData, errors } = form;

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <Field
                label="Name"
                htmlFor="ss-name"
                error={errors.name}
                className="sm:col-span-2"
            >
                <Input
                    id="ss-name"
                    placeholder="Long rains 2027, or Batch 3"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                />
            </Field>
            <Field label="Starts" htmlFor="ss-from" error={errors.starts_on}>
                <Input
                    id="ss-from"
                    type="date"
                    value={data.starts_on}
                    onChange={(e) => setData('starts_on', e.target.value)}
                />
            </Field>
            <Field label="Ends" htmlFor="ss-to" error={errors.ends_on}>
                <Input
                    id="ss-to"
                    type="date"
                    value={data.ends_on}
                    onChange={(e) => setData('ends_on', e.target.value)}
                />
            </Field>
            <Field
                label="Harvest or output"
                htmlFor="ss-yield"
                error={errors.yield_quantity}
                hint="Fill in after harvest to see the cost per unit"
            >
                <Input
                    id="ss-yield"
                    inputMode="decimal"
                    value={data.yield_quantity}
                    onChange={(e) => setData('yield_quantity', e.target.value)}
                />
            </Field>
            <Field
                label="Counted in"
                htmlFor="ss-yunit"
                error={errors.yield_unit}
            >
                <Input
                    id="ss-yunit"
                    placeholder="bags, kg, trays"
                    value={data.yield_unit}
                    onChange={(e) => setData('yield_unit', e.target.value)}
                />
            </Field>
            <Field
                label="Notes"
                htmlFor="ss-notes"
                error={errors.notes}
                className="sm:col-span-2"
            >
                <Textarea
                    id="ss-notes"
                    className="min-h-16"
                    value={data.notes}
                    onChange={(e) => setData('notes', e.target.value)}
                />
            </Field>
        </div>
    );
}

function Bar({
    value,
    max,
    marker,
    over,
}: {
    value: number;
    max: number;
    marker?: number;
    over?: boolean;
}) {
    const pct = (n: number) => (max > 0 ? Math.min(100, (n / max) * 100) : 0);

    return (
        <div className="relative h-2.5 rounded-r bg-muted">
            <div
                className={cn(
                    'h-full rounded-r',
                    over ? 'bg-red-600 dark:bg-red-400' : 'bg-emerald-600',
                )}
                style={{ width: `${pct(value)}%`, minWidth: value > 0 ? 2 : 0 }}
            />
            {marker !== undefined && marker > 0 && (
                <div
                    className="absolute -top-1 -bottom-1 w-0.5 bg-foreground/60"
                    style={{ left: `calc(${pct(marker)}% - 1px)` }}
                />
            )}
        </div>
    );
}

export function SeasonsPanel({
    shared,
    seasonId,
}: {
    shared: Shared;
    seasonId: SeasonChoice;
}) {
    const season =
        shared.seasons.find((s) => s.id === seasonId) ??
        shared.seasons.find((s) => s.status === 'open') ??
        shared.seasons[0];
    const label = (key: string) => shared.options.categories[key] ?? key;

    return (
        <div className="grid gap-6">
            <Section
                title="Seasons"
                description="Each season (or batch) keeps its own costs and sales, so you can see the profit of each one. Closing a season locks its costs."
                actions={
                    shared.canManage && (
                        <FormDialog<SeasonForm>
                            title="Open a season"
                            action={`/projects/${shared.projectId}/seasons`}
                            submitLabel="Open"
                            initial={{
                                name: '',
                                starts_on: today(),
                                ends_on: '',
                                yield_quantity: '',
                                yield_unit: '',
                                notes: '',
                            }}
                            trigger={
                                <Button size="sm">
                                    <Plus /> Open a season
                                </Button>
                            }
                        >
                            {(form) => <SeasonFields form={form} />}
                        </FormDialog>
                    )
                }
            >
                {shared.seasons.length === 0 ? (
                    <EmptyState title="No seasons yet">
                        Open one for each planting season, batch or cycle. Costs
                        and sales on its dates go into it.
                    </EmptyState>
                ) : (
                    <Table>
                        <THead>
                            <TR>
                                <TH>Season</TH>
                                <TH>Dates</TH>
                                <TH className="text-right">Budget</TH>
                                <TH className="text-right">Spent</TH>
                                <TH className="text-right">Harvest</TH>
                                <TH>Status</TH>
                                {shared.canManage && <TH />}
                            </TR>
                        </THead>
                        <TBody>
                            {shared.seasons.map((s) => (
                                <TR key={s.id}>
                                    <TD className="font-medium">{s.name}</TD>
                                    <TD className="whitespace-nowrap">
                                        {formatDate(s.starts_on)}
                                        {s.ends_on
                                            ? ` to ${formatDate(s.ends_on)}`
                                            : ' onwards'}
                                    </TD>
                                    <Num>
                                        {s.summary.budget_total
                                            ? formatKes(s.summary.budget_total)
                                            : '—'}
                                    </Num>
                                    <Num>{formatKes(s.summary.costs)}</Num>
                                    <Num>
                                        {s.yield_quantity
                                            ? `${qty(s.yield_quantity)} ${s.yield_unit ?? ''}`
                                            : '—'}
                                    </Num>
                                    <TD>
                                        <StatusBadge status={s.status} />
                                    </TD>
                                    {shared.canManage && (
                                        <TD className="text-right whitespace-nowrap">
                                            <FormDialog<SeasonForm>
                                                title={`Edit ${s.name}`}
                                                action={`/projects/${shared.projectId}/seasons/${s.id}`}
                                                method="put"
                                                initial={{
                                                    name: s.name,
                                                    starts_on: s.starts_on,
                                                    ends_on: s.ends_on ?? '',
                                                    yield_quantity:
                                                        s.yield_quantity
                                                            ? String(
                                                                  Number(
                                                                      s.yield_quantity,
                                                                  ),
                                                              )
                                                            : '',
                                                    yield_unit:
                                                        s.yield_unit ?? '',
                                                    notes: s.notes ?? '',
                                                }}
                                                trigger={
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        aria-label="Edit season"
                                                    >
                                                        <Pencil />
                                                    </Button>
                                                }
                                            >
                                                {(form) => (
                                                    <SeasonFields form={form} />
                                                )}
                                            </FormDialog>
                                            <ActionButton
                                                size="sm"
                                                variant="outline"
                                                url={`/projects/${shared.projectId}/seasons/${s.id}/status`}
                                                data={{
                                                    status:
                                                        s.status === 'open'
                                                            ? 'closed'
                                                            : 'open',
                                                }}
                                                confirm={
                                                    s.status === 'open'
                                                        ? `Close ${s.name}? Its costs will be locked.`
                                                        : undefined
                                                }
                                            >
                                                {s.status === 'open'
                                                    ? 'Close'
                                                    : 'Reopen'}
                                            </ActionButton>
                                        </TD>
                                    )}
                                </TR>
                            ))}
                        </TBody>
                    </Table>
                )}
            </Section>

            {season && (
                <BudgetSection shared={shared} season={season} label={label} />
            )}
        </div>
    );
}

function BudgetSection({
    shared,
    season,
    label,
}: {
    shared: Shared;
    season: Season;
    label: (key: string) => string;
}) {
    const { budget, by_category: spent } = season.summary;
    const keys = Array.from(
        new Set([...Object.keys(budget), ...Object.keys(spent)]),
    );
    const max = Math.max(
        1,
        ...keys.map((k) => Math.max(budget[k] ?? 0, spent[k] ?? 0)),
    );

    return (
        <Section
            title={`Budget: ${season.name}`}
            description="Bar is what has been spent. The dark line is the budget. Red means over budget."
            actions={
                shared.canManage && (
                    <FormDialog<{ budget: Record<string, string> }>
                        title={`Budget for ${season.name}`}
                        description="What you plan to spend on each category. Leave blank what you don't need."
                        action={`/projects/${shared.projectId}/seasons/${season.id}/budget`}
                        method="put"
                        wide
                        initial={{
                            budget: Object.fromEntries(
                                Object.keys(shared.options.categories).map(
                                    (k) => [k, toShillings(budget[k] ?? null)],
                                ),
                            ),
                        }}
                        trigger={
                            <Button size="sm" variant="outline">
                                <Receipt /> Set budget
                            </Button>
                        }
                    >
                        {(form) => (
                            <div className="grid gap-3 sm:grid-cols-2">
                                {Object.entries(shared.options.categories).map(
                                    ([k, name]) => (
                                        <Field
                                            key={k}
                                            label={name}
                                            htmlFor={`bg-${k}`}
                                        >
                                            <MoneyInput
                                                id={`bg-${k}`}
                                                value={form.data.budget[k]}
                                                onChange={(e) =>
                                                    form.setData('budget', {
                                                        ...form.data.budget,
                                                        [k]: e.target.value,
                                                    })
                                                }
                                            />
                                        </Field>
                                    ),
                                )}
                            </div>
                        )}
                    </FormDialog>
                )
            }
        >
            {keys.length === 0 ? (
                <EmptyState title="No budget or costs yet" />
            ) : (
                <div className="grid gap-3">
                    {keys.map((k) => {
                        const plan = budget[k] ?? 0;
                        const used = spent[k] ?? 0;
                        const over = plan > 0 && used > plan;

                        return (
                            <div
                                key={k}
                                className="grid items-center gap-x-4 gap-y-1 text-sm sm:grid-cols-[11rem_1fr_15rem]"
                                title={`${label(k)}: spent ${formatKes(used)} of ${formatKes(plan)}`}
                            >
                                <span>{label(k)}</span>
                                <Bar
                                    value={used}
                                    max={max}
                                    marker={plan}
                                    over={over}
                                />
                                <span className="tabular-nums sm:text-right">
                                    {formatKes(used)}{' '}
                                    <span className="text-muted-foreground">
                                        of{' '}
                                        {plan ? formatKes(plan) : 'no budget'}
                                    </span>
                                    {over && (
                                        <StatusBadge
                                            status="overdue"
                                            className="ml-2"
                                            label={`+${formatKes(used - plan)}`}
                                        />
                                    )}
                                </span>
                            </div>
                        );
                    })}
                    <p className="text-sm text-muted-foreground">
                        Spent {formatKes(season.summary.costs)} of a{' '}
                        {formatKes(season.summary.budget_total)} budget.
                    </p>
                </div>
            )}
        </Section>
    );
}

/* ----------------------------------------------------------------- report */

export function ReportPanel({
    shared,
    overall,
    seasonId,
}: {
    shared: Shared;
    overall: CostSummary;
    seasonId: SeasonChoice;
}) {
    const season = shared.seasons.find((s) => s.id === seasonId);
    const sum = season?.summary ?? overall;
    const label = (key: string) => shared.options.categories[key] ?? key;
    const cats = Object.entries(sum.by_category);
    const max = cats.length ? cats[0][1] : 1;
    const pct = (n: number) =>
        sum.costs > 0 ? Math.round((n / sum.costs) * 100) : 0;

    return (
        <div className="grid gap-6">
            <div className="grid gap-6 lg:grid-cols-2">
                <Section
                    title={
                        season
                            ? `Profit and loss: ${season.name}`
                            : 'Profit and loss: all seasons'
                    }
                    description="Costs count when they are incurred, including those still owed. Sales count when made, including on credit."
                >
                    <div className="grid gap-1 text-sm tabular-nums">
                        <Line label="Sales" value={sum.sales} />
                        <Line label="Other income" value={sum.income} />
                        <Line label="Less: costs" value={-sum.costs} />
                        <div className="mt-1 flex justify-between border-t pt-2 text-base font-semibold">
                            <span>Profit</span>
                            <span
                                className={cn(
                                    sum.profit < 0 &&
                                        'text-red-700 dark:text-red-400',
                                )}
                            >
                                {formatKes(sum.profit)}
                            </span>
                        </div>
                    </div>
                    <div className="mt-4">
                        <DefinitionList
                            items={[
                                {
                                    label: `Cost per acre (${qty(sum.acres)} acres)`,
                                    value: formatKes(sum.cost_per_acre),
                                },
                                {
                                    label: 'Profit per acre',
                                    value: formatKes(sum.profit_per_acre),
                                },
                                {
                                    label: sum.yield_unit
                                        ? `Cost per ${sum.yield_unit.replace(/s$/, '')}`
                                        : 'Cost per unit harvested',
                                    value: sum.cost_per_unit
                                        ? `${formatKes(sum.cost_per_unit)} (${qty(sum.yield)} ${sum.yield_unit ?? ''})`
                                        : null,
                                },
                                {
                                    label: 'Labour',
                                    value: `${formatKes(sum.labour)} (${pct(sum.labour)}% of costs)`,
                                },
                                {
                                    label: 'Paid by members',
                                    value: formatKes(sum.in_kind),
                                },
                                {
                                    label: 'Still owed or awaiting approval',
                                    value: formatKes(sum.owed + sum.awaiting),
                                },
                            ]}
                        />
                    </div>
                </Section>
                <Section title="Where the money went">
                    {cats.length === 0 ? (
                        <EmptyState title="No costs yet" />
                    ) : (
                        <div className="grid gap-3">
                            {cats.map(([k, v]) => (
                                <div
                                    key={k}
                                    className="grid items-center gap-x-4 gap-y-1 text-sm sm:grid-cols-[10rem_1fr_9rem]"
                                    title={`${label(k)}: ${formatKes(v)}, ${pct(v)}%`}
                                >
                                    <span>{label(k)}</span>
                                    <Bar value={v} max={max} />
                                    <span className="tabular-nums sm:text-right">
                                        {formatKes(v)}{' '}
                                        <span className="text-muted-foreground">
                                            {pct(v)}%
                                        </span>
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}
                </Section>
            </div>

            {shared.seasons.length > 1 && (
                <Section title="Seasons compared">
                    <Table>
                        <THead>
                            <TR>
                                <TH>Season</TH>
                                <TH className="text-right">Sales and income</TH>
                                <TH className="text-right">Costs</TH>
                                <TH className="text-right">Profit</TH>
                                <TH className="text-right">Cost per acre</TH>
                                <TH className="text-right">Harvest</TH>
                                <TH className="text-right">Cost per unit</TH>
                            </TR>
                        </THead>
                        <TBody>
                            {shared.seasons.map((s) => (
                                <TR key={s.id}>
                                    <TD className="font-medium">{s.name}</TD>
                                    <Num>{formatKes(s.summary.revenue)}</Num>
                                    <Num>{formatKes(s.summary.costs)}</Num>
                                    <Num className="font-medium">
                                        {formatKes(s.summary.profit)}
                                    </Num>
                                    <Num>
                                        {formatKes(s.summary.cost_per_acre)}
                                    </Num>
                                    <Num>
                                        {s.summary.yield
                                            ? `${qty(s.summary.yield)} ${s.summary.yield_unit ?? ''}`
                                            : '—'}
                                    </Num>
                                    <Num>
                                        {formatKes(s.summary.cost_per_unit)}
                                    </Num>
                                </TR>
                            ))}
                        </TBody>
                    </Table>
                </Section>
            )}
        </div>
    );
}

function Line({ label, value }: { label: string; value: number }) {
    return (
        <div className="flex justify-between gap-4 py-0.5">
            <span className="text-muted-foreground">{label}</span>
            <span>{formatKes(value)}</span>
        </div>
    );
}
