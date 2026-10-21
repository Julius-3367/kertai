import { Head, Link, router } from '@inertiajs/react';
import { Download, FileBarChart } from 'lucide-react';
import { useState } from 'react';
import { Tabs } from '@/components/kertai/actions';
import { MemberSelect } from '@/components/kertai/fields';
import {
    EmptyState,
    Page,
    PageHeader,
    Section,
} from '@/components/kertai/page';
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
import { formatDate, formatKes } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Option } from '@/types/kertai';

type Props = {
    filters: { from: string; to: string; year: number };
    funds: {
        code: string;
        name: string;
        kind: string;
        opening: number;
        in: number;
        out: number;
        closing: number;
    }[];
    trial: {
        rows: { code: string; name: string; debit: number; credit: number }[];
        debits: number;
        credits: number;
    };
    grid: {
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
    arrears: {
        member_id: number;
        name: string;
        member_number: string;
        phone: string;
        arrears: number;
        fines: number;
        oldest: string | null;
    }[];
    members: Option[];
};

type Tab = 'funds' | 'grid' | 'arrears' | 'trial' | 'statements';

export default function ReportsIndex({
    filters,
    funds,
    trial,
    grid,
    arrears,
    members,
}: Props) {
    const [tab, setTab] = useState<Tab>('funds');
    const [member, setMember] = useState('');
    const apply = (next: Partial<Props['filters']>) =>
        router.get(
            '/reports',
            { ...filters, ...next },
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title="Reports" />
            <Page>
                <PageHeader
                    title="Reports"
                    actions={
                        <Button asChild variant="outline">
                            <Link href={`/reports/annual?year=${filters.year}`}>
                                <FileBarChart /> AGM report {filters.year}
                            </Link>
                        </Button>
                    }
                />

                <Tabs
                    value={tab}
                    onChange={setTab}
                    tabs={[
                        { value: 'funds', label: 'Funds' },
                        { value: 'grid', label: 'Contribution grid' },
                        {
                            value: 'arrears',
                            label: 'Arrears',
                            count: arrears.length,
                        },
                        { value: 'trial', label: 'Trial balance' },
                        { value: 'statements', label: 'Member statements' },
                    ]}
                />

                {tab === 'funds' && (
                    <Section
                        title="Money in and out by fund"
                        actions={
                            <div className="flex items-center gap-2">
                                <Input
                                    type="date"
                                    className="h-8 w-40"
                                    value={filters.from}
                                    onChange={(e) =>
                                        apply({ from: e.target.value })
                                    }
                                    aria-label="From"
                                />
                                <span className="text-sm text-muted-foreground">
                                    to
                                </span>
                                <Input
                                    type="date"
                                    className="h-8 w-40"
                                    value={filters.to}
                                    onChange={(e) =>
                                        apply({ to: e.target.value })
                                    }
                                    aria-label="To"
                                />
                            </div>
                        }
                    >
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Account</TH>
                                    <TH className="text-right">Opening</TH>
                                    <TH className="text-right">In</TH>
                                    <TH className="text-right">Out</TH>
                                    <TH className="text-right">Closing</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {funds.map((f) => (
                                    <TR
                                        key={f.code}
                                        className={
                                            f.kind === 'asset'
                                                ? 'bg-muted/30'
                                                : ''
                                        }
                                    >
                                        <TD>
                                            <span className="font-mono text-xs text-muted-foreground">
                                                {f.code}
                                            </span>{' '}
                                            {f.name}
                                        </TD>
                                        <Num>{formatKes(f.opening)}</Num>
                                        <Num>{formatKes(f.in)}</Num>
                                        <Num>{formatKes(f.out)}</Num>
                                        <Num className="font-medium">
                                            {formatKes(f.closing)}
                                        </Num>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                        <p className="mt-2 text-xs text-muted-foreground">
                            Shaded rows are where the money is held; the rest
                            are what it is for.
                        </p>
                    </Section>
                )}

                {tab === 'grid' && (
                    <Section
                        title={`Contributions ${filters.year}`}
                        actions={
                            <Input
                                type="number"
                                className="h-8 w-28"
                                value={filters.year}
                                onChange={(e) =>
                                    apply({ year: Number(e.target.value) })
                                }
                                aria-label="Year"
                            />
                        }
                    >
                        <Table stack={false}>
                            <THead>
                                <TR>
                                    <TH>Member</TH>
                                    {grid.months.map((m) => (
                                        <TH key={m} className="text-center">
                                            {new Date(
                                                `${m}-01T00:00:00`,
                                            ).toLocaleString('en', {
                                                month: 'short',
                                            })}
                                        </TH>
                                    ))}
                                    <TH className="text-right">Paid</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {grid.rows.map((r) => (
                                    <TR key={r.member_id}>
                                        <TD className="whitespace-nowrap">
                                            {r.name}{' '}
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
                                                            c.status ===
                                                                'paid' &&
                                                                'bg-emerald-600',
                                                            c.status ===
                                                                'open' &&
                                                                c.paid > 0 &&
                                                                'bg-amber-500',
                                                            c.status ===
                                                                'open' &&
                                                                c.paid === 0 &&
                                                                'bg-red-500',
                                                            c.status ===
                                                                'waived' &&
                                                                'bg-muted-foreground',
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
                            <span>
                                <span className="mr-1 inline-block size-2 rounded-full bg-emerald-600" />
                                paid
                            </span>
                            <span>
                                <span className="mr-1 inline-block size-2 rounded-full bg-amber-500" />
                                part paid
                            </span>
                            <span>
                                <span className="mr-1 inline-block size-2 rounded-full bg-red-500" />
                                unpaid
                            </span>
                        </p>
                    </Section>
                )}

                {tab === 'arrears' && (
                    <Section
                        title="Members in arrears"
                        actions={
                            <Button asChild size="sm" variant="outline">
                                <a href="/reports/arrears.csv">
                                    <Download /> CSV
                                </a>
                            </Button>
                        }
                    >
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
                                        <TR key={a.member_id}>
                                            <TD>
                                                <Link
                                                    href={`/members/${a.member_id}`}
                                                    className="hover:underline"
                                                >
                                                    {a.name}
                                                </Link>{' '}
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
                )}

                {tab === 'trial' && (
                    <Section
                        title={`Trial balance at ${formatDate(filters.to)}`}
                    >
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Account</TH>
                                    <TH className="text-right">Debit</TH>
                                    <TH className="text-right">Credit</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {trial.rows.map((r) => (
                                    <TR key={r.code}>
                                        <TD>
                                            <span className="font-mono text-xs text-muted-foreground">
                                                {r.code}
                                            </span>{' '}
                                            {r.name}
                                        </TD>
                                        <Num>
                                            {r.debit ? formatKes(r.debit) : ''}
                                        </Num>
                                        <Num>
                                            {r.credit
                                                ? formatKes(r.credit)
                                                : ''}
                                        </Num>
                                    </TR>
                                ))}
                                <TR className="font-semibold">
                                    <TD>Totals</TD>
                                    <Num>{formatKes(trial.debits)}</Num>
                                    <Num>{formatKes(trial.credits)}</Num>
                                </TR>
                            </TBody>
                        </Table>
                    </Section>
                )}

                {tab === 'statements' && (
                    <Section
                        title="Member statement"
                        description="View on screen, or download as PDF or CSV."
                    >
                        <div className="flex flex-col gap-2 sm:flex-row">
                            <MemberSelect
                                members={members}
                                value={member}
                                onChange={(e) => setMember(e.target.value)}
                                className="sm:max-w-sm"
                                aria-label="Member"
                            />
                            <Button disabled={!member} asChild={!!member}>
                                {member ? (
                                    <Link href={`/statements/${member}`}>
                                        Open statement
                                    </Link>
                                ) : (
                                    <span>Open statement</span>
                                )}
                            </Button>
                        </div>
                    </Section>
                )}
            </Page>
        </>
    );
}

ReportsIndex.layout = { breadcrumbs: [{ title: 'Reports', href: '/reports' }] };
