import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import {
    BarList,
    ChartTable,
    ColumnChart,
    Legend,
    Meter,
    monthLabel,
} from '@/components/kertai/charts';
import { EmptyState, Section, StatCard } from '@/components/kertai/page';
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
import { formatDate, formatKes, percent } from '@/lib/format';
import { cn } from '@/lib/utils';

// Shared by the officials' Reports page and the members' Group finances page.

export type Overview = {
    cashflow: { month: string; in: number; out: number }[];
    contributions: { month: string; due: number; paid: number }[];
    funds: { name: string; balance: number }[];
    totals: {
        collected: number;
        paid_out: number;
        due_so_far: number;
        paid_so_far: number;
    };
};

export type Grid = {
    months: string[];
    rows: {
        member_id: number;
        name: string;
        member_number: string;
        status: string;
        cells: ({ amount: number; paid: number; status: string } | null)[];
        total_paid: number;
    }[];
};

export type ArrearsRow = {
    member_id: number;
    name: string;
    member_number: string;
    arrears: number;
    fines: number;
    oldest: string | null;
};

type Naming = {
    /** Officials get links to the member's page; members get plain names. */
    memberHref?: (id: number) => string;
    /** The viewer's own row, marked "you". */
    me?: number;
};

function MemberName({
    id,
    name,
    memberHref,
    me,
}: Naming & { id: number; name: string }) {
    return (
        <>
            {memberHref ? (
                <Link href={memberHref(id)} className="hover:underline">
                    {name}
                </Link>
            ) : (
                name
            )}
            {id === me && (
                <span className="ml-1.5 rounded bg-primary/10 px-1.5 py-0.5 text-[11px] font-medium text-primary">
                    you
                </span>
            )}
        </>
    );
}

export function GroupOverview({
    overview,
    arrears,
    onAllArrears,
    ...naming
}: Naming & {
    overview: Overview;
    arrears: ArrearsRow[];
    onAllArrears: () => void;
}) {
    const { cashflow, contributions, funds, totals } = overview;
    const year = contributions[0]?.month.slice(0, 4);
    const owed = arrears.reduce((sum, a) => sum + a.arrears, 0);
    const fundTotal = funds.reduce((sum, f) => sum + f.balance, 0);
    const raised = contributions.filter((c) => c.due > 0);
    const hasCashflow = cashflow.some((c) => c.in > 0 || c.out > 0);
    const money = [
        { label: 'Money in', color: 'var(--viz-1)' },
        { label: 'Money out', color: 'var(--viz-2)' },
    ];

    return (
        <div className="grid min-w-0 gap-4 *:min-w-0">
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <StatCard
                    label={`Collected in ${year}`}
                    value={formatKes(totals.collected)}
                    tone="good"
                />
                <StatCard
                    label={`Paid out in ${year}`}
                    value={formatKes(totals.paid_out)}
                />
                <StatCard
                    label="Contributions paid"
                    value={
                        totals.due_so_far > 0
                            ? `${percent(totals.paid_so_far, totals.due_so_far)}%`
                            : '—'
                    }
                    hint={
                        totals.due_so_far > 0 ? (
                            <>
                                {formatKes(totals.paid_so_far)} of{' '}
                                {formatKes(totals.due_so_far)} due so far
                                <Meter
                                    value={totals.paid_so_far}
                                    max={totals.due_so_far}
                                />
                            </>
                        ) : (
                            'Nothing due yet'
                        )
                    }
                />
                <StatCard
                    label="Arrears"
                    value={formatKes(owed)}
                    tone={owed > 0 ? 'bad' : 'default'}
                    hint={`${arrears.length} ${arrears.length === 1 ? 'member' : 'members'}`}
                />
            </div>

            <Section
                title="Money in and out"
                description="The last 12 months. Tap a month to see the figures."
            >
                {hasCashflow ? (
                    <>
                        <Legend items={money} />
                        <div className="mt-2">
                            <ColumnChart
                                label="Money in and out over the last 12 months"
                                months={cashflow.map((c) => c.month)}
                                series={[
                                    {
                                        ...money[0],
                                        values: cashflow.map((c) => c.in),
                                    },
                                    {
                                        ...money[1],
                                        values: cashflow.map((c) => c.out),
                                    },
                                ]}
                            />
                        </div>
                        <ChartTable
                            columns={['Month', 'In', 'Out']}
                            rows={cashflow.map((c) => ({
                                key: c.month,
                                cells: [
                                    monthLabel(c.month, 'long'),
                                    formatKes(c.in),
                                    formatKes(c.out),
                                ],
                            }))}
                        />
                    </>
                ) : (
                    <EmptyState title="No money recorded yet" />
                )}
            </Section>

            <Section
                title={`Contributions ${year}`}
                description="What members paid against what was due, month by month."
            >
                {raised.length > 0 ? (
                    <>
                        <Legend
                            items={[
                                { label: 'Paid', color: 'var(--viz-1)' },
                                { label: 'Due', color: 'var(--viz-track)' },
                            ]}
                        />
                        <div className="mt-2">
                            <ColumnChart
                                label={`Contributions paid against due in ${year}`}
                                months={contributions.map((c) => c.month)}
                                series={[
                                    {
                                        label: 'Paid',
                                        color: 'var(--viz-1)',
                                        values: contributions.map(
                                            (c) => c.paid,
                                        ),
                                    },
                                ]}
                                track={contributions.map((c) => c.due)}
                                trackLabel="Due"
                            />
                        </div>
                        <ChartTable
                            columns={['Month', 'Due', 'Paid']}
                            rows={raised.map((c) => ({
                                key: c.month,
                                cells: [
                                    monthLabel(c.month, 'long'),
                                    formatKes(c.due),
                                    formatKes(c.paid),
                                ],
                            }))}
                        />
                    </>
                ) : (
                    <EmptyState title="No contributions raised yet" />
                )}
            </Section>

            <div className="grid gap-4 *:min-w-0 lg:grid-cols-2">
                <Section
                    title="What the money is for"
                    description={`Held for the group: ${formatKes(fundTotal)}`}
                >
                    {funds.length > 0 ? (
                        <BarList
                            items={funds.map((f) => ({
                                key: f.name,
                                label: f.name,
                                value: f.balance,
                            }))}
                        />
                    ) : (
                        <EmptyState title="All funds are empty" />
                    )}
                </Section>

                <Section
                    title="Owing the most"
                    actions={
                        arrears.length > 0 && (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={onAllArrears}
                            >
                                All arrears
                            </Button>
                        )
                    }
                >
                    {arrears.length > 0 ? (
                        <BarList
                            items={arrears.slice(0, 6).map((a) => ({
                                key: a.member_id,
                                label: (
                                    <MemberName
                                        id={a.member_id}
                                        name={a.name}
                                        {...naming}
                                    />
                                ),
                                value: a.arrears,
                            }))}
                        />
                    ) : (
                        <EmptyState title="Nobody is in arrears" />
                    )}
                </Section>
            </div>
        </div>
    );
}

const dot = {
    paid: 'bg-emerald-600',
    part: 'bg-amber-500',
    unpaid: 'bg-red-500',
    waived: 'bg-muted-foreground',
};

export function ContributionGrid({
    grid,
    title,
    actions,
    ...naming
}: Naming & { grid: Grid; title: string; actions?: ReactNode }) {
    return (
        <Section
            title={title}
            description="One dot per month. On a phone, swipe the table sideways for the later months."
            actions={actions}
        >
            <Table stack={false}>
                <THead>
                    <TR>
                        <TH>Member</TH>
                        {grid.months.map((m) => (
                            <TH key={m} className="text-center">
                                {monthLabel(m)}
                            </TH>
                        ))}
                        <TH className="text-right">Paid</TH>
                    </TR>
                </THead>
                <TBody>
                    {grid.rows.map((r) => (
                        <TR
                            key={r.member_id}
                            className={cn(
                                r.member_id === naming.me && 'bg-primary/5',
                            )}
                        >
                            <TD className="whitespace-nowrap">
                                <MemberName
                                    id={r.member_id}
                                    name={r.name}
                                    {...naming}
                                />{' '}
                                <span className="text-xs text-muted-foreground">
                                    {r.member_number}
                                </span>
                            </TD>
                            {r.cells.map((c, i) => (
                                <TD key={i} className="text-center">
                                    {c === null ? (
                                        <span className="text-muted-foreground">
                                            ·
                                        </span>
                                    ) : (
                                        <span
                                            title={`${formatKes(c.paid)} of ${formatKes(c.amount)}`}
                                            className={cn(
                                                'inline-block size-3 rounded-full',
                                                c.status === 'paid' && dot.paid,
                                                c.status === 'open' &&
                                                    (c.paid > 0
                                                        ? dot.part
                                                        : dot.unpaid),
                                                c.status === 'waived' &&
                                                    dot.waived,
                                            )}
                                        />
                                    )}
                                </TD>
                            ))}
                            <Num>{formatKes(r.total_paid)}</Num>
                        </TR>
                    ))}
                </TBody>
            </Table>
            <p className="mt-2 flex flex-wrap gap-4 text-xs text-muted-foreground">
                {(
                    [
                        ['paid', 'paid'],
                        ['part', 'part paid'],
                        ['unpaid', 'unpaid'],
                        ['waived', 'waived'],
                    ] as const
                ).map(([key, label]) => (
                    <span key={key}>
                        <span
                            className={cn(
                                'mr-1 inline-block size-2 rounded-full',
                                dot[key],
                            )}
                        />
                        {label}
                    </span>
                ))}
            </p>
        </Section>
    );
}

export function ArrearsTable({
    arrears,
    actions,
    ...naming
}: Naming & { arrears: ArrearsRow[]; actions?: ReactNode }) {
    return (
        <Section title="Members in arrears" actions={actions}>
            {arrears.length === 0 ? (
                <EmptyState title="Nobody is in arrears" />
            ) : (
                <Table>
                    <THead>
                        <TR>
                            <TH>Member</TH>
                            <TH>Owing since</TH>
                            <TH className="text-right">Fines</TH>
                            <TH className="text-right">Arrears</TH>
                        </TR>
                    </THead>
                    <TBody>
                        {arrears.map((a) => (
                            <TR
                                key={a.member_id}
                                className={cn(
                                    a.member_id === naming.me && 'bg-primary/5',
                                )}
                            >
                                <TD>
                                    <MemberName
                                        id={a.member_id}
                                        name={a.name}
                                        {...naming}
                                    />{' '}
                                    <span className="text-xs text-muted-foreground">
                                        {a.member_number}
                                    </span>
                                </TD>
                                <TD>{formatDate(a.oldest)}</TD>
                                <Num>{formatKes(a.fines)}</Num>
                                <Num className="font-medium text-red-700 dark:text-red-400">
                                    {formatKes(a.arrears)}
                                </Num>
                            </TR>
                        ))}
                    </TBody>
                </Table>
            )}
        </Section>
    );
}
