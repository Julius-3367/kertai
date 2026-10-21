import { Head, router } from '@inertiajs/react';
import { Download, FileDown } from 'lucide-react';
import {
    EmptyState,
    Page,
    PageHeader,
    Section,
    StatCard,
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

type Props = {
    member: { id: number; name: string; member_number: string; status: string };
    lines: {
        id: number;
        date: string;
        entry_number: number;
        description: string;
        memo: string | null;
        fund: string;
        purpose: string;
        in: number;
        out: number;
        savings_balance: number;
    }[];
    summary: {
        savings: number;
        prepayment: number;
        outstanding: number;
        arrears: number;
        fines: number;
    };
    filters: { from: string | null; to: string | null };
};

export default function Statement({ member, lines, summary, filters }: Props) {
    const query = new URLSearchParams(
        Object.entries(filters).filter(([, v]) => v) as [string, string][],
    ).toString();
    const apply = (next: Partial<Props['filters']>) =>
        router.get(
            `/statements/${member.id}`,
            { ...filters, ...next },
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title={`Statement: ${member.name}`} />
            <Page>
                <PageHeader
                    title={`Statement: ${member.name}`}
                    description={`Member ${member.member_number}`}
                    actions={
                        <>
                            <Button asChild variant="outline">
                                <a
                                    href={`/statements/${member.id}?format=pdf${query ? `&${query}` : ''}`}
                                >
                                    <FileDown /> PDF
                                </a>
                            </Button>
                            <Button asChild variant="outline">
                                <a
                                    href={`/statements/${member.id}?format=csv${query ? `&${query}` : ''}`}
                                >
                                    <Download /> CSV
                                </a>
                            </Button>
                        </>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        label="Savings"
                        value={formatKes(summary.savings)}
                        tone="good"
                    />
                    <StatCard
                        label="Arrears"
                        value={formatKes(summary.arrears)}
                        tone={summary.arrears ? 'bad' : 'good'}
                    />
                    <StatCard
                        label="Fines owed"
                        value={formatKes(summary.fines)}
                    />
                    <StatCard
                        label="Paid in advance"
                        value={formatKes(summary.prepayment)}
                    />
                </div>

                <Section
                    title="Transactions"
                    description="Newest first. Every shilling you paid and which fund it went to."
                    actions={
                        <div className="flex items-center gap-2">
                            <Input
                                type="date"
                                className="h-8 w-40"
                                value={filters.from ?? ''}
                                onChange={(e) =>
                                    apply({ from: e.target.value || null })
                                }
                                aria-label="From"
                            />
                            <span className="text-sm text-muted-foreground">
                                to
                            </span>
                            <Input
                                type="date"
                                className="h-8 w-40"
                                value={filters.to ?? ''}
                                onChange={(e) =>
                                    apply({ to: e.target.value || null })
                                }
                                aria-label="To"
                            />
                        </div>
                    }
                >
                    {lines.length === 0 ? (
                        <EmptyState title="No transactions in this period" />
                    ) : (
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Date</TH>
                                    <TH>Description</TH>
                                    <TH>Fund</TH>
                                    <TH className="text-right">In</TH>
                                    <TH className="text-right">Out</TH>
                                    <TH className="text-right">Savings</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {lines.map((l) => (
                                    <TR key={l.id}>
                                        <TD className="whitespace-nowrap">
                                            {formatDate(l.date)}
                                        </TD>
                                        <TD>
                                            {l.description}
                                            {l.memo && (
                                                <div className="text-xs text-muted-foreground">
                                                    {l.memo}
                                                </div>
                                            )}
                                        </TD>
                                        <TD className="text-sm">{l.fund}</TD>
                                        <Num>{l.in ? formatKes(l.in) : ''}</Num>
                                        <Num>
                                            {l.out ? formatKes(l.out) : ''}
                                        </Num>
                                        <Num>
                                            {formatKes(l.savings_balance)}
                                        </Num>
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

Statement.layout = { breadcrumbs: [{ title: 'Statement', href: '#' }] };
