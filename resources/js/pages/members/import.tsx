import { Head, useForm } from '@inertiajs/react';
import { Download, Upload } from 'lucide-react';
import { actionError } from '@/components/kertai/actions';
import { ActionError, CheckField, Field } from '@/components/kertai/fields';
import { Page, PageHeader, Section } from '@/components/kertai/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { today } from '@/lib/format';

export default function MembersImport() {
    const form = useForm<{
        file: File | null;
        joined_on: string;
        send_logins: boolean;
    }>({
        file: null,
        joined_on: today(),
        send_logins: false,
    });

    return (
        <>
            <Head title="Import members" />
            <Page className="max-w-3xl">
                <PageHeader
                    title="Import members"
                    description="Add many members at once from a list in Excel or Google Sheets."
                    actions={
                        <Button variant="outline" asChild>
                            <a href="/members/import/template">
                                <Download /> Download template
                            </a>
                        </Button>
                    }
                />

                <Section title="How it works">
                    <ol className="list-decimal space-y-1 pl-5 text-sm text-muted-foreground">
                        <li>
                            Download the template, or use your own sheet with
                            the column names in the first row.{' '}
                            <strong>Name</strong> and <strong>Phone</strong> are
                            required; Member number, Email, ID number, Gender,
                            Date of birth and Joined on are optional.
                        </li>
                        <li>
                            Save it as CSV: in Excel, File &gt; Save As &gt;
                            CSV. In Google Sheets, File &gt; Download &gt; CSV.
                        </li>
                        <li>
                            Upload it below. Every row is checked first: if any
                            row is wrong, nothing is saved and you are told
                            which rows to fix.
                        </li>
                        <li>
                            Anyone already on the register (same phone number)
                            is skipped, so uploading the same list twice is
                            safe. Blank member numbers are given the next free
                            number.
                        </li>
                    </ol>
                </Section>

                <Section title="Upload">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post('/members/import', {
                                forceFormData: true,
                            });
                        }}
                        className="grid gap-4"
                    >
                        <ActionError message={actionError(form.errors)} />
                        <Field
                            label="CSV file"
                            htmlFor="i-file"
                            error={form.errors.file}
                        >
                            <Input
                                id="i-file"
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
                        <Field
                            label="Joined on, for rows without a date"
                            htmlFor="i-joined"
                            error={form.errors.joined_on}
                        >
                            <Input
                                id="i-joined"
                                type="date"
                                value={form.data.joined_on}
                                onChange={(e) =>
                                    form.setData('joined_on', e.target.value)
                                }
                            />
                        </Field>
                        <CheckField
                            id="i-logins"
                            label="Send each new member their login by SMS and email"
                            hint="Leave this off to add the register first and issue logins later from each member's page."
                            checked={form.data.send_logins}
                            onChange={(v) => form.setData('send_logins', v)}
                        />
                        <Button
                            className="justify-self-start"
                            disabled={form.processing || !form.data.file}
                        >
                            {form.processing ? <Spinner /> : <Upload />}
                            Import members
                        </Button>
                    </form>
                </Section>
            </Page>
        </>
    );
}

MembersImport.layout = {
    breadcrumbs: [
        { title: 'Members', href: '/members' },
        { title: 'Import', href: '/members/import' },
    ],
};
