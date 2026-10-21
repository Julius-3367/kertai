import { Head, router } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { Page, PageHeader, Section, StatCard } from '@/components/kertai/page';
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
import { formatKes, percent, titleCase } from '@/lib/format';

type Report = {
    year: number;
    group: string;
    members: { active: number; joined: number; left: number };
    collected: {
        total: number;
        by_purpose: Record<string, number>;
        by_channel: Record<string, number>;
    };
    paid_out: { total: number; by_type: Record<string, number> };
    funds: {
        code: string;
        name: string;
        kind: string;
        opening: number;
        in: number;
        out: number;
        closing: number;
    }[];
    projects: {
        name: string;
        status: string;
        contributions: number;
        income: number;
        expenses: number;
        profit: number;
        distributed: number;
    }[];
    fundraisers: {
        title: string;
        target: number;
        raised: number;
        status: string;
    }[];
    welfare: { cases: number; paid: number };
    meetings: { held: number };
    arrears: number;
};

function Breakdown({ data }: { data: Record<string, number> }) {
    const entries = Object.entries(data);

    if (entries.length === 0) {
        return <p className="text-sm text-muted-foreground">None.</p>;
    }

    return (
        <Table>
            <TBody>
                {entries.map(([k, v]) => (
                    <TR key={k}>
                        <TD>{titleCase(k)}</TD>
                        <Num>{formatKes(v)}</Num>
                    </TR>
                ))}
            </TBody>
        </Table>
    );
}

export default function AnnualReport({ report }: { report: Report }) {
    return (
        <>
            <Head title={`AGM report ${report.year}`} />
            <Page>
                <PageHeader
                    title={`${report.group}: report for ${report.year}`}
                    description="For the annual general meeting. Print or save as PDF from the browser."
                    actions={
                        <>
                            <Input
                                type="number"
                                className="w-28"
                                value={report.year}
                                aria-label="Year"
                                onChange={(e) =>
                                    router.get(
                                        '/reports/annual',
                                        { year: e.target.value },
                                        { preserveState: true, replace: true },
                                    )
                                }
                            />
                            <Button
                                variant="outline"
                                onClick={() => window.print()}
                            >
                                <Printer /> Print
                            </Button>
                        </>
                    }
                />

                <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                    <StatCard
                        label="Active members"
                        value={report.members.active}
                        hint={`${report.members.joined} joined, ${report.members.left} left`}
                    />
                    <StatCard
                        label="Money received"
                        value={formatKes(report.collected.total)}
                        tone="good"
                    />
                    <StatCard
                        label="Money paid out"
                        value={formatKes(report.paid_out.total)}
                    />
                    <StatCard
                        label="Arrears outstanding"
                        value={formatKes(report.arrears)}
                        tone={report.arrears ? 'warn' : 'good'}
                    />
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <Section title="Received, by purpose">
                        <Breakdown data={report.collected.by_purpose} />
                    </Section>
                    <Section title="Received, by channel">
                        <Breakdown data={report.collected.by_channel} />
                    </Section>
                    <Section title="Paid out, by type">
                        <Breakdown data={report.paid_out.by_type} />
                    </Section>
                </div>

                <Section title="Funds for the year">
                    <Table>
                        <THead>
                            <TR>
                                <TH>Account</TH>
                                <TH className="text-right">1 Jan</TH>
                                <TH className="text-right">In</TH>
                                <TH className="text-right">Out</TH>
                                <TH className="text-right">31 Dec</TH>
                            </TR>
                        </THead>
                        <TBody>
                            {report.funds.map((f) => (
                                <TR key={f.code}>
                                    <TD>{f.name}</TD>
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
                </Section>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Section title="Projects">
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Project</TH>
                                    <TH className="text-right">Income</TH>
                                    <TH className="text-right">Expenses</TH>
                                    <TH className="text-right">Profit</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {report.projects.map((p) => (
                                    <TR key={p.name}>
                                        <TD>{p.name}</TD>
                                        <Num>{formatKes(p.income)}</Num>
                                        <Num>{formatKes(p.expenses)}</Num>
                                        <Num>{formatKes(p.profit)}</Num>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                    </Section>
                    <Section title="Fundraisers, welfare and meetings">
                        <Table>
                            <TBody>
                                {report.fundraisers.map((f) => (
                                    <TR key={f.title}>
                                        <TD>{f.title}</TD>
                                        <Num>
                                            {formatKes(f.raised)} of{' '}
                                            {formatKes(f.target)} (
                                            {percent(f.raised, f.target)}%)
                                        </Num>
                                    </TR>
                                ))}
                                <TR>
                                    <TD>Welfare cases</TD>
                                    <Num>
                                        {report.welfare.cases} cases,{' '}
                                        {formatKes(report.welfare.paid)} paid
                                    </Num>
                                </TR>
                                <TR>
                                    <TD>Meetings held</TD>
                                    <Num>{report.meetings.held}</Num>
                                </TR>
                            </TBody>
                        </Table>
                    </Section>
                </div>
            </Page>
        </>
    );
}

AnnualReport.layout = {
    breadcrumbs: [
        { title: 'Reports', href: '/reports' },
        { title: 'AGM report', href: '/reports/annual' },
    ],
};
