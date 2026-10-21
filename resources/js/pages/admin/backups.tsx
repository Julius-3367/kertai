import { Head } from '@inertiajs/react';
import { Archive, Download } from 'lucide-react';
import { ActionButton } from '@/components/kertai/actions';
import {
    EmptyState,
    Page,
    PageHeader,
    Section,
} from '@/components/kertai/page';
import { Table, TBody, TD, TH, THead, TR } from '@/components/kertai/table';
import { Button } from '@/components/ui/button';
import { fileSize, formatDateTime } from '@/lib/format';

export default function Backups({
    files,
}: {
    files: { name: string; size: number; at: string }[];
}) {
    return (
        <>
            <Head title="Backups" />
            <Page className="max-w-4xl">
                <PageHeader
                    title="Data exports"
                    description="All the group's records as spreadsheets (CSV) in one zip. One is made every night; the server also keeps database backups."
                    actions={
                        <ActionButton url="/admin/backups">
                            <Archive /> Export now
                        </ActionButton>
                    }
                />
                <Section>
                    {files.length === 0 ? (
                        <EmptyState title="No exports yet" />
                    ) : (
                        <Table>
                            <THead>
                                <TR>
                                    <TH>File</TH>
                                    <TH>Made</TH>
                                    <TH>Size</TH>
                                    <TH />
                                </TR>
                            </THead>
                            <TBody>
                                {files.map((f) => (
                                    <TR key={f.name}>
                                        <TD className="font-mono text-xs">
                                            {f.name}
                                        </TD>
                                        <TD>{formatDateTime(f.at)}</TD>
                                        <TD>{fileSize(f.size)}</TD>
                                        <TD className="text-right">
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                            >
                                                <a
                                                    href={`/admin/backups/${encodeURIComponent(f.name)}`}
                                                >
                                                    <Download /> Download
                                                </a>
                                            </Button>
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

Backups.layout = {
    breadcrumbs: [{ title: 'Backups', href: '/admin/backups' }],
};
