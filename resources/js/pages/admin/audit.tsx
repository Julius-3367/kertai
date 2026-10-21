import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    EmptyState,
    Page,
    PageHeader,
    Section,
} from '@/components/kertai/page';
import { Pagination } from '@/components/kertai/pagination';
import { Table, TBody, TD, TH, THead, TR } from '@/components/kertai/table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDateTime } from '@/lib/format';
import type { Paginated } from '@/types/kertai';

type Log = {
    id: number;
    at: string;
    user: string;
    action: string;
    description: string;
    properties: Record<string, unknown> | null;
    ip: string | null;
};

export default function AuditIndex({
    logs,
    filters,
}: {
    logs: Paginated<Log>;
    filters: { q?: string };
}) {
    const [q, setQ] = useState(filters.q ?? '');

    return (
        <>
            <Head title="Audit log" />
            <Page>
                <PageHeader
                    title="Audit log"
                    description="Who did what and when. Entries cannot be edited or deleted."
                />
                <Section>
                    <form
                        className="mb-4 flex gap-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            router.get(
                                '/admin/audit',
                                { q },
                                { preserveState: true, replace: true },
                            );
                        }}
                    >
                        <Input
                            placeholder="Search"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            className="max-w-xs"
                        />
                        <Button variant="outline">Search</Button>
                    </form>
                    {logs.data.length === 0 ? (
                        <EmptyState title="Nothing logged" />
                    ) : (
                        <Table>
                            <THead>
                                <TR>
                                    <TH>When</TH>
                                    <TH>Who</TH>
                                    <TH>What</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {logs.data.map((l) => (
                                    <TR key={l.id}>
                                        <TD className="whitespace-nowrap">
                                            {formatDateTime(l.at)}
                                        </TD>
                                        <TD className="whitespace-nowrap">
                                            {l.user}
                                            {l.ip && (
                                                <div className="text-xs text-muted-foreground">
                                                    {l.ip}
                                                </div>
                                            )}
                                        </TD>
                                        <TD>
                                            {l.description}
                                            <div className="font-mono text-xs text-muted-foreground">
                                                {l.action}
                                            </div>
                                            {l.properties &&
                                                Object.keys(l.properties)
                                                    .length > 0 && (
                                                    <details className="text-xs text-muted-foreground">
                                                        <summary className="cursor-pointer">
                                                            Details
                                                        </summary>
                                                        <pre className="mt-1 overflow-x-auto rounded bg-muted p-2">
                                                            {JSON.stringify(
                                                                l.properties,
                                                                null,
                                                                2,
                                                            )}
                                                        </pre>
                                                    </details>
                                                )}
                                        </TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                    )}
                    <Pagination page={logs} />
                </Section>
            </Page>
        </>
    );
}

AuditIndex.layout = {
    breadcrumbs: [{ title: 'Audit log', href: '/admin/audit' }],
};
