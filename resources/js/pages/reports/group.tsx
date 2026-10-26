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
import { Page, PageHeader } from '@/components/kertai/page';
import { Input } from '@/components/ui/input';

type Props = {
    year: number;
    overview: Overview;
    grid: Grid;
    arrears: ArrearsRow[];
};

type Tab = 'overview' | 'grid' | 'arrears';

export default function GroupFinances({
    year,
    overview,
    grid,
    arrears,
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
            </Page>
        </>
    );
}

GroupFinances.layout = {
    breadcrumbs: [{ title: 'Group finances', href: '/group-finances' }],
};
