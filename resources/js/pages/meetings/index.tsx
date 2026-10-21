import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { FormDialog } from '@/components/kertai/actions';
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
import { formatDate, formatDateTime } from '@/lib/format';

type Meeting = {
    id: number;
    title: string;
    kind: string;
    scheduled_at: string;
    venue: string | null;
    status: string;
    present: number;
};
type Resolution = {
    id: number;
    number: string;
    title: string;
    adopted_on: string;
    meeting_id: number | null;
};

export default function MeetingsIndex({
    meetings,
    resolutions,
    kinds,
    canManage,
}: {
    meetings: Meeting[];
    resolutions: Resolution[];
    kinds: Record<string, string>;
    canManage: boolean;
}) {
    const nextSaturday = (() => {
        const d = new Date();
        d.setDate(d.getDate() + ((6 - d.getDay() + 7) % 7 || 7));
        d.setHours(14, 0, 0, 0);
        const offset = d.getTimezoneOffset() * 60000;

        return new Date(d.getTime() - offset).toISOString().slice(0, 16);
    })();

    return (
        <>
            <Head title="Meetings" />
            <Page>
                <PageHeader
                    title="Meetings"
                    description="Notices, attendance, minutes, resolutions and cash collected at meetings."
                    actions={
                        canManage && (
                            <FormDialog
                                title="Schedule a meeting"
                                action="/meetings"
                                submitLabel="Schedule"
                                initial={{
                                    title: 'Monthly meeting',
                                    kind: 'ordinary',
                                    scheduled_at: nextSaturday,
                                    venue: '',
                                    agenda: '',
                                }}
                                trigger={
                                    <Button>
                                        <Plus /> Schedule meeting
                                    </Button>
                                }
                            >
                                {(form) => (
                                    <>
                                        <Field
                                            label="Title"
                                            htmlFor="mt-title"
                                            error={form.errors.title}
                                        >
                                            <Input
                                                id="mt-title"
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
                                                label="Type"
                                                htmlFor="mt-kind"
                                            >
                                                <Select
                                                    id="mt-kind"
                                                    value={form.data.kind}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'kind',
                                                            e.target.value,
                                                        )
                                                    }
                                                    options={Object.entries(
                                                        kinds,
                                                    ).map(([value, label]) => ({
                                                        value,
                                                        label,
                                                    }))}
                                                />
                                            </Field>
                                            <Field
                                                label="When"
                                                htmlFor="mt-when"
                                                error={form.errors.scheduled_at}
                                            >
                                                <Input
                                                    id="mt-when"
                                                    type="datetime-local"
                                                    value={
                                                        form.data.scheduled_at
                                                    }
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'scheduled_at',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                        </div>
                                        <Field label="Venue" htmlFor="mt-venue">
                                            <Input
                                                id="mt-venue"
                                                value={form.data.venue}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'venue',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                        <Field
                                            label="Agenda"
                                            htmlFor="mt-agenda"
                                        >
                                            <Textarea
                                                id="mt-agenda"
                                                value={form.data.agenda}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'agenda',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                    </>
                                )}
                            </FormDialog>
                        )
                    }
                />

                <Section title="Meetings">
                    {meetings.length === 0 ? (
                        <EmptyState title="No meetings yet" />
                    ) : (
                        <Table>
                            <THead>
                                <TR>
                                    <TH>When</TH>
                                    <TH>Meeting</TH>
                                    <TH>Venue</TH>
                                    <TH>Present</TH>
                                    <TH>Status</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {meetings.map((m) => (
                                    <TR key={m.id}>
                                        <TD className="whitespace-nowrap">
                                            {formatDateTime(m.scheduled_at)}
                                        </TD>
                                        <TD>
                                            <Link
                                                href={`/meetings/${m.id}`}
                                                className="font-medium hover:underline"
                                            >
                                                {m.title}
                                            </Link>
                                            <div className="text-xs text-muted-foreground">
                                                {kinds[m.kind] ?? m.kind}
                                            </div>
                                        </TD>
                                        <TD>{m.venue ?? '—'}</TD>
                                        <TD>{m.present || '—'}</TD>
                                        <TD>
                                            <StatusBadge status={m.status} />
                                        </TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                    )}
                </Section>

                <Section
                    title="Resolutions"
                    description="Rule changes and appointments refer to these numbers."
                >
                    {resolutions.length === 0 ? (
                        <EmptyState title="No resolutions recorded" />
                    ) : (
                        <Table>
                            <TBody>
                                {resolutions.map((r) => (
                                    <TR key={r.id}>
                                        <TD className="font-mono text-xs">
                                            {r.number}
                                        </TD>
                                        <TD>
                                            {r.meeting_id ? (
                                                <Link
                                                    href={`/meetings/${r.meeting_id}`}
                                                    className="hover:underline"
                                                >
                                                    {r.title}
                                                </Link>
                                            ) : (
                                                r.title
                                            )}
                                        </TD>
                                        <TD className="text-right">
                                            {formatDate(r.adopted_on)}
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

MeetingsIndex.layout = {
    breadcrumbs: [{ title: 'Meetings', href: '/meetings' }],
};
