import { Head, useForm } from '@inertiajs/react';
import { FileUp } from 'lucide-react';
import { actionError } from '@/components/kertai/actions';
import { ActionError, Field } from '@/components/kertai/fields';
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
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/format';

type ImportRow = {
    id: number;
    filename: string;
    rows: number;
    created: number;
    matched: number;
    duplicates: number;
    by: string | null;
    at: string;
};

export default function PaymentsImport({ imports }: { imports: ImportRow[] }) {
    const form = useForm<{ file: File | null }>({ file: null });

    return (
        <>
            <Head title="Import statement" />
            <Page className="max-w-4xl">
                <PageHeader
                    title="Import paybill payments"
                    description="Upload the Equity Bank statement or the M-Pesa paybill statement as CSV. Only money coming in is read; lines already imported are skipped."
                />
                <Section title="Upload">
                    <form
                        className="grid gap-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post('/payments/import', {
                                forceFormData: true,
                            });
                        }}
                    >
                        <ActionError message={actionError(form.errors)} />
                        <Field
                            label="Statement (CSV)"
                            htmlFor="file"
                            error={form.errors.file}
                        >
                            <Input
                                id="file"
                                type="file"
                                accept=".csv,text/csv"
                                onChange={(e) =>
                                    form.setData(
                                        'file',
                                        e.target.files?.[0] ?? null,
                                    )
                                }
                            />
                        </Field>
                        <div className="text-sm text-muted-foreground">
                            <p>Each line is matched to a member by:</p>
                            <ol className="ml-5 list-decimal">
                                <li>the phone number in the line,</li>
                                <li>
                                    a member number (e.g. M004) typed as the
                                    account,
                                </li>
                                <li>
                                    the payer's full name, when it fits exactly
                                    one member.
                                </li>
                            </ol>
                            <p className="mt-1">
                                Anything else waits under Payments → Unmatched
                                for you to match by hand.
                            </p>
                        </div>
                        <div>
                            <Button
                                disabled={form.processing || !form.data.file}
                            >
                                {form.processing ? <Spinner /> : <FileUp />}{' '}
                                Import
                            </Button>
                        </div>
                    </form>
                </Section>

                <Section title="Previous imports">
                    {imports.length === 0 ? (
                        <EmptyState title="No imports yet" />
                    ) : (
                        <Table>
                            <THead>
                                <TR>
                                    <TH>When</TH>
                                    <TH>File</TH>
                                    <TH className="text-right">Lines</TH>
                                    <TH className="text-right">New</TH>
                                    <TH className="text-right">Matched</TH>
                                    <TH className="text-right">Skipped</TH>
                                    <TH>By</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {imports.map((i) => (
                                    <TR key={i.id}>
                                        <TD>{formatDateTime(i.at)}</TD>
                                        <TD>{i.filename}</TD>
                                        <Num>{i.rows}</Num>
                                        <Num>{i.created}</Num>
                                        <Num>{i.matched}</Num>
                                        <Num>{i.duplicates}</Num>
                                        <TD>{i.by ?? '—'}</TD>
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

PaymentsImport.layout = {
    breadcrumbs: [
        { title: 'Payments', href: '/payments' },
        { title: 'Import', href: '/payments/import' },
    ],
};
