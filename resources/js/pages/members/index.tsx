import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { Select } from '@/components/kertai/fields';
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
import { useCan } from '@/hooks/use-can';
import { formatDate, formatKes } from '@/lib/format';

type Row = {
    id: number;
    member_number: string;
    name: string;
    phone: string;
    email: string | null;
    status: string;
    joined_on: string;
    has_login: boolean;
    positions: string[];
    savings: number | null;
    arrears: number | null;
};

export default function MembersIndex({
    members,
    filters,
    showMoney,
}: {
    members: Row[];
    filters: { q?: string; status?: string };
    showMoney: boolean;
}) {
    const can = useCan();
    const [q, setQ] = useState(filters.q ?? '');

    const apply = (next: Record<string, string>) =>
        router.get(
            '/members',
            { ...filters, q, ...next },
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title="Members" />
            <Page>
                <PageHeader
                    title="Members"
                    description={`${members.length} on the register`}
                    actions={
                        can('members.manage') && (
                            <Button asChild>
                                <Link href="/members/create">
                                    <Plus /> Add member
                                </Link>
                            </Button>
                        )
                    }
                />
                <Section>
                    <form
                        className="mb-4 flex flex-col gap-2 sm:flex-row"
                        onSubmit={(e) => {
                            e.preventDefault();
                            apply({});
                        }}
                    >
                        <Input
                            placeholder="Search name, number or phone"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            className="sm:max-w-xs"
                        />
                        <Select
                            className="sm:max-w-44"
                            value={filters.status ?? ''}
                            onChange={(e) => apply({ status: e.target.value })}
                            placeholder="All statuses"
                            options={[
                                'active',
                                'exiting',
                                'suspended',
                                'exited',
                                'deceased',
                            ].map((s) => ({
                                value: s,
                                label: s[0].toUpperCase() + s.slice(1),
                            }))}
                        />
                        <Button type="submit" variant="outline">
                            Search
                        </Button>
                    </form>

                    {members.length === 0 ? (
                        <EmptyState title="No members found" />
                    ) : (
                        <Table>
                            <THead>
                                <TR>
                                    <TH>No.</TH>
                                    <TH>Name</TH>
                                    <TH>Phone</TH>
                                    <TH>Position</TH>
                                    <TH>Joined</TH>
                                    <TH>Status</TH>
                                    {showMoney && (
                                        <TH className="text-right">Savings</TH>
                                    )}
                                    {showMoney && (
                                        <TH className="text-right">Arrears</TH>
                                    )}
                                </TR>
                            </THead>
                            <TBody>
                                {members.map((m) => (
                                    <TR key={m.id}>
                                        <TD className="font-mono text-xs">
                                            {m.member_number}
                                        </TD>
                                        <TD>
                                            <Link
                                                href={`/members/${m.id}`}
                                                className="font-medium hover:underline"
                                            >
                                                {m.name}
                                            </Link>
                                            {!m.has_login && (
                                                <span className="ml-2 text-xs text-muted-foreground">
                                                    no login
                                                </span>
                                            )}
                                        </TD>
                                        <TD>{m.phone}</TD>
                                        <TD className="text-sm">
                                            {m.positions.join(', ') || '—'}
                                        </TD>
                                        <TD>{formatDate(m.joined_on)}</TD>
                                        <TD>
                                            <StatusBadge status={m.status} />
                                        </TD>
                                        {showMoney && (
                                            <Num>{formatKes(m.savings)}</Num>
                                        )}
                                        {showMoney && (
                                            <Num
                                                className={
                                                    m.arrears
                                                        ? 'text-red-700 dark:text-red-400'
                                                        : ''
                                                }
                                            >
                                                {formatKes(m.arrears)}
                                            </Num>
                                        )}
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

MembersIndex.layout = { breadcrumbs: [{ title: 'Members', href: '/members' }] };
