import { Head } from '@inertiajs/react';
import { CalendarCheck, Download, Trash2, Upload } from 'lucide-react';
import { ActionButton, FormDialog } from '@/components/kertai/actions';
import { Field, Select, Textarea } from '@/components/kertai/fields';
import {
    EmptyState,
    Page,
    PageHeader,
    Section,
} from '@/components/kertai/page';
import { StatusBadge } from '@/components/kertai/status-badge';
import { Table, TBody, TD, TH, THead, TR } from '@/components/kertai/table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { fileSize, formatDate, today } from '@/lib/format';

type Doc = {
    id: number;
    title: string;
    category: string;
    original_name: string;
    size: number;
    visibility: string;
    uploaded_by: string | null;
    at: string;
};
type Item = {
    id: number;
    title: string;
    description: string | null;
    due_on: string;
    recurrence: string;
    position: string | null;
    position_id: number | null;
    status: string;
    completed_on: string | null;
    overdue: boolean;
};

export default function DocumentsIndex({
    documents,
    compliance,
    categories,
    positions,
    canManage,
}: {
    documents: Doc[];
    compliance: Item[];
    categories: Record<string, string>;
    positions: { id: number; name: string }[];
    canManage: boolean;
}) {
    const complianceFields = (
        form: {
            data: {
                title: string;
                description: string;
                due_on: string;
                recurrence: string;
                position_id: string;
            };
            setData: (
                k:
                    | 'title'
                    | 'description'
                    | 'due_on'
                    | 'recurrence'
                    | 'position_id',
                v: string,
            ) => void;
            errors: Partial<Record<string, string>>;
        },
        prefix: string,
    ) => (
        <>
            <Field
                label="What"
                htmlFor={`${prefix}-title`}
                error={form.errors.title}
            >
                <Input
                    id={`${prefix}-title`}
                    value={form.data.title}
                    onChange={(e) => form.setData('title', e.target.value)}
                />
            </Field>
            <div className="grid grid-cols-2 gap-3">
                <Field
                    label="Due"
                    htmlFor={`${prefix}-due`}
                    error={form.errors.due_on}
                >
                    <Input
                        id={`${prefix}-due`}
                        type="date"
                        value={form.data.due_on}
                        onChange={(e) => form.setData('due_on', e.target.value)}
                    />
                </Field>
                <Field label="Repeats" htmlFor={`${prefix}-rec`}>
                    <Select
                        id={`${prefix}-rec`}
                        value={form.data.recurrence}
                        onChange={(e) =>
                            form.setData('recurrence', e.target.value)
                        }
                        options={[
                            { value: 'none', label: 'Once' },
                            { value: 'monthly', label: 'Every month' },
                            { value: 'yearly', label: 'Every year' },
                        ]}
                    />
                </Field>
            </div>
            <Field label="Responsible" htmlFor={`${prefix}-pos`}>
                <Select
                    id={`${prefix}-pos`}
                    value={form.data.position_id}
                    onChange={(e) =>
                        form.setData('position_id', e.target.value)
                    }
                    placeholder="Nobody in particular"
                    options={positions.map((p) => ({
                        value: p.id,
                        label: p.name,
                    }))}
                />
            </Field>
            <Field label="Notes" htmlFor={`${prefix}-desc`}>
                <Textarea
                    id={`${prefix}-desc`}
                    value={form.data.description}
                    onChange={(e) =>
                        form.setData('description', e.target.value)
                    }
                />
            </Field>
        </>
    );

    return (
        <>
            <Head title="Documents" />
            <Page>
                <PageHeader
                    title="Documents and compliance"
                    description="The constitution, certificate, minutes and the dates the group must not miss."
                />

                <Section
                    title="Compliance calendar"
                    description="The responsible official gets a reminder two weeks before."
                    actions={
                        canManage && (
                            <FormDialog
                                title="Add a deadline"
                                action="/compliance"
                                submitLabel="Add"
                                initial={{
                                    title: '',
                                    description: '',
                                    due_on: today(),
                                    recurrence: 'yearly',
                                    position_id: '',
                                }}
                                trigger={
                                    <Button size="sm" variant="outline">
                                        <CalendarCheck /> Add
                                    </Button>
                                }
                            >
                                {(form) => complianceFields(form, 'nc')}
                            </FormDialog>
                        )
                    }
                >
                    {compliance.length === 0 ? (
                        <EmptyState title="No deadlines" />
                    ) : (
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Due</TH>
                                    <TH>What</TH>
                                    <TH>Who</TH>
                                    <TH>Status</TH>
                                    <TH />
                                </TR>
                            </THead>
                            <TBody>
                                {compliance.map((c) => (
                                    <TR key={c.id}>
                                        <TD className="whitespace-nowrap">
                                            {formatDate(c.due_on)}
                                        </TD>
                                        <TD>
                                            {c.title}
                                            {c.recurrence !== 'none' && (
                                                <div className="text-xs text-muted-foreground">
                                                    Repeats {c.recurrence}
                                                </div>
                                            )}
                                        </TD>
                                        <TD>{c.position ?? '—'}</TD>
                                        <TD>
                                            <StatusBadge
                                                status={
                                                    c.overdue
                                                        ? 'overdue'
                                                        : c.status === 'done'
                                                          ? 'done'
                                                          : 'pending'
                                                }
                                            />
                                            {c.completed_on && (
                                                <div className="text-xs text-muted-foreground">
                                                    {formatDate(c.completed_on)}
                                                </div>
                                            )}
                                        </TD>
                                        <TD className="text-right whitespace-nowrap">
                                            {canManage && (
                                                <div className="flex justify-end gap-1">
                                                    {c.status === 'pending' && (
                                                        <ActionButton
                                                            url={`/compliance/${c.id}/done`}
                                                            size="sm"
                                                            variant="ghost"
                                                        >
                                                            Done
                                                        </ActionButton>
                                                    )}
                                                    <FormDialog
                                                        title="Edit deadline"
                                                        action={`/compliance/${c.id}`}
                                                        method="put"
                                                        initial={{
                                                            title: c.title,
                                                            description:
                                                                c.description ??
                                                                '',
                                                            due_on: c.due_on,
                                                            recurrence:
                                                                c.recurrence,
                                                            position_id:
                                                                c.position_id
                                                                    ? String(
                                                                          c.position_id,
                                                                      )
                                                                    : '',
                                                        }}
                                                        trigger={
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                            >
                                                                Edit
                                                            </Button>
                                                        }
                                                    >
                                                        {(form) =>
                                                            complianceFields(
                                                                form,
                                                                `ec-${c.id}`,
                                                            )
                                                        }
                                                    </FormDialog>
                                                    <ActionButton
                                                        url={`/compliance/${c.id}`}
                                                        method="delete"
                                                        size="sm"
                                                        variant="ghost"
                                                        confirm="Remove this deadline?"
                                                        aria-label="Remove"
                                                    >
                                                        <Trash2 />
                                                    </ActionButton>
                                                </div>
                                            )}
                                        </TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                    )}
                </Section>

                <Section
                    title="Document vault"
                    actions={
                        canManage && (
                            <FormDialog
                                title="Upload a document"
                                action="/documents"
                                submitLabel="Upload"
                                initial={{
                                    title: '',
                                    category: 'constitution',
                                    visibility: 'members',
                                    file: null as File | null,
                                }}
                                trigger={
                                    <Button size="sm">
                                        <Upload /> Upload
                                    </Button>
                                }
                            >
                                {(form) => (
                                    <>
                                        <Field
                                            label="Title"
                                            htmlFor="doc-title"
                                            error={form.errors.title}
                                        >
                                            <Input
                                                id="doc-title"
                                                value={form.data.title}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'title',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                        <div className="grid grid-cols-2 gap-3">
                                            <Field
                                                label="Kind"
                                                htmlFor="doc-cat"
                                            >
                                                <Select
                                                    id="doc-cat"
                                                    value={form.data.category}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'category',
                                                            e.target.value,
                                                        )
                                                    }
                                                    options={Object.entries(
                                                        categories,
                                                    ).map(([value, label]) => ({
                                                        value,
                                                        label,
                                                    }))}
                                                />
                                            </Field>
                                            <Field
                                                label="Who can see it"
                                                htmlFor="doc-vis"
                                            >
                                                <Select
                                                    id="doc-vis"
                                                    value={form.data.visibility}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'visibility',
                                                            e.target.value,
                                                        )
                                                    }
                                                    options={[
                                                        {
                                                            value: 'members',
                                                            label: 'All members',
                                                        },
                                                        {
                                                            value: 'officials',
                                                            label: 'Officials only',
                                                        },
                                                    ]}
                                                />
                                            </Field>
                                        </div>
                                        <Field
                                            label="File (PDF, image, Word or Excel, up to 10 MB)"
                                            htmlFor="doc-file"
                                            error={form.errors.file}
                                        >
                                            <Input
                                                id="doc-file"
                                                type="file"
                                                onChange={(e) =>
                                                    form.setData(
                                                        'file',
                                                        e.target.files?.[0] ??
                                                            null,
                                                    )
                                                }
                                            />
                                        </Field>
                                    </>
                                )}
                            </FormDialog>
                        )
                    }
                >
                    {documents.length === 0 ? (
                        <EmptyState title="No documents yet">
                            Upload the constitution and registration certificate
                            first.
                        </EmptyState>
                    ) : (
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Document</TH>
                                    <TH>Kind</TH>
                                    <TH>Uploaded</TH>
                                    <TH />
                                </TR>
                            </THead>
                            <TBody>
                                {documents.map((d) => (
                                    <TR key={d.id}>
                                        <TD>
                                            <span className="font-medium">
                                                {d.title}
                                            </span>
                                            <div className="text-xs text-muted-foreground">
                                                {d.original_name} ·{' '}
                                                {fileSize(d.size)}
                                                {d.visibility === 'officials'
                                                    ? ' · officials only'
                                                    : ''}
                                            </div>
                                        </TD>
                                        <TD>
                                            {categories[d.category] ??
                                                d.category}
                                        </TD>
                                        <TD>
                                            {formatDate(d.at)}
                                            {d.uploaded_by && (
                                                <div className="text-xs text-muted-foreground">
                                                    {d.uploaded_by}
                                                </div>
                                            )}
                                        </TD>
                                        <TD className="text-right whitespace-nowrap">
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="ghost"
                                                aria-label="Download"
                                            >
                                                <a
                                                    href={`/documents/${d.id}/download`}
                                                >
                                                    <Download />
                                                </a>
                                            </Button>
                                            {canManage && (
                                                <ActionButton
                                                    url={`/documents/${d.id}`}
                                                    method="delete"
                                                    size="sm"
                                                    variant="ghost"
                                                    confirm={`Delete ${d.title}?`}
                                                    aria-label="Delete"
                                                >
                                                    <Trash2 />
                                                </ActionButton>
                                            )}
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

DocumentsIndex.layout = {
    breadcrumbs: [{ title: 'Documents', href: '/documents' }],
};
