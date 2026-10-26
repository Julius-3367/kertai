import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Tabs } from '@/components/kertai/actions';
import {
    ArrearsTable,
    ContributionGrid,
    GroupOverview,
} from '@/components/kertai/group-books';
import type {
    ArrearsRow,
    Grid,
    Overview,
} from '@/components/kertai/group-books';
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
import { Input } from '@/components/ui/input';
import { formatDate, formatKes } from '@/lib/format';
import { cn } from '@/lib/utils';

type Props = {
    year: number;
    overview: Overview;
    grid: Grid;
    arrears: ArrearsRow[];
    loans: {
        loan_id: number;
        member_id: number;
        name: string;
        member_number: string;
        reference: string;
        owed: number;
        due_on: string | null;
        overdue: boolean;
        behind: number;
    }[];
};

type Tab = 'overview' | 'grid' | 'arrears' | 'loans';

export default function GroupFinances({
    year,
    overview,
    grid,
    arrears,
    loans,
}: Props) {
    const { auth } = usePage().props;
    const me = auth.member?.id;
    const [tab, setTab] = useState<Tab>('overview');

    return (
        <>
            <Head title="Group finances" />
            <Page>
                <PageHeader
                    title="Group finances"
                    description="The group agreed that every member can see where the money stands and who has paid."
                />

                <Tabs
                    value={tab}
                    onChange={setTab}
                    tabs={[
                        { value: 'overview', label: 'Overview' },
                        { value: 'grid', label: 'Who has paid' },
                        {
                            value: 'arrears',
                            label: 'Arrears',
                            count: arrears.length,
                        },
                        { value: 'loans', label: 'Loans', count: loans.length },
                    ]}
                />

                {tab === 'overview' && (
                    <GroupOverview
                        overview={overview}
                        arrears={arrears}
                        me={me}
                        onAllArrears={() => setTab('arrears')}
                    />
                )}

                {tab === 'grid' && (
                    <ContributionGrid
                        grid={grid}
                        title={`Contributions ${year}`}
                        me={me}
                        actions={
                            <Input
                                type="number"
                                className="h-8 w-28"
                                value={year}
                                onChange={(e) =>
                                    router.get(
                                        '/group-finances',
                                        { year: Number(e.target.value) },
                                        { preserveState: true, replace: true },
                                    )
                                }
                                aria-label="Year"
                            />
                        }
                    />
                )}

                {tab === 'arrears' && (
                    <ArrearsTable arrears={arrears} me={me} />
                )}

                {tab === 'loans' && (
                    <Section
                        title="Loans running"
                        description="What each member still owes the group on loans."
                    >
                        {loans.length === 0 ? (
                            <EmptyState title="No loans running" />
                        ) : (
                            <Table>
                                <THead>
                                    <TR>
                                        <TH>Member</TH>
                                        <TH>Due</TH>
                                        <TH>Standing</TH>
                                        <TH className="text-right">Owed</TH>
                                    </TR>
                                </THead>
                                <TBody>
                                    {loans.map((l) => (
                                        <TR
                                            key={l.loan_id}
                                            className={cn(
                                                l.member_id === me &&
                                                    'bg-primary/5',
                                            )}
                                        >
                                            <TD>
                                                {l.name}{' '}
                                                <span className="text-xs text-muted-foreground">
                                                    {l.reference}
                                                </span>
                                            </TD>
                                            <TD>{formatDate(l.due_on)}</TD>
                                            <TD>
                                                {l.overdue ? (
                                                    <StatusBadge
                                                        status="overdue"
                                                        label="Past due date"
                                                    />
                                                ) : l.behind > 0 ? (
                                                    <StatusBadge
                                                        status="late"
                                                        label={`Behind ${formatKes(l.behind)}`}
                                                    />
                                                ) : (
                                                    <StatusBadge
                                                        status="active"
                                                        label="On track"
                                                    />
                                                )}
                                            </TD>
                                            <Num className="font-medium">
                                                {formatKes(l.owed)}
                                            </Num>
                                        </TR>
                                    ))}
                                </TBody>
                            </Table>
                        )}
                    </Section>
                )}
            </Page>
        </>
    );
}

GroupFinances.layout = {
    breadcrumbs: [{ title: 'Group finances', href: '/group-finances' }],
};
