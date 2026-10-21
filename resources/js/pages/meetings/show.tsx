import { Head, useForm } from '@inertiajs/react';
import {
    BellRing,
    CalendarPlus,
    Coins,
    Download,
    Gavel,
    Plus,
    Trash2,
    Video,
} from 'lucide-react';
import { useState } from 'react';
import {
    ActionButton,
    FormDialog,
    Tabs,
    actionError,
} from '@/components/kertai/actions';
import {
    ActionError,
    Field,
    MemberSelect,
    MoneyInput,
    Select,
    Textarea,
} from '@/components/kertai/fields';
import { OnlineMeetingFields } from '@/components/kertai/online-meeting-fields';
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
import {
    formatDate,
    formatDateTime,
    formatKes,
    titleCase,
    today,
} from '@/lib/format';

type Props = {
    meeting: {
        id: number;
        title: string;
        kind: string;
        scheduled_at: string;
        scheduled_at_input: string;
        venue: string | null;
        online_link: string | null;
        duration_minutes: number;
        google_calendar_url: string;
        agenda: string | null;
        minutes: string | null;
        status: string;
    };
    roll: {
        member_id: number;
        name: string;
        member_number: string;
        status: string | null;
    }[];
    resolutions: {
        id: number;
        number: string;
        title: string;
        body: string | null;
        adopted_on: string;
    }[];
    collections: {
        id: number;
        member: string | null;
        amount: number;
        purpose: string;
    }[];
    kinds: Record<string, string>;
    canManage: boolean;
    canCollect: boolean;
    nextResolutionNumber: string;
};

type Tab = 'details' | 'attendance' | 'resolutions' | 'cash';

export default function MeetingShow({
    meeting,
    roll,
    resolutions,
    collections,
    kinds,
    canManage,
    canCollect,
    nextResolutionNumber,
}: Props) {
    const [tab, setTab] = useState<Tab>('details');
    const present = roll.filter(
        (r) => r.status === 'present' || r.status === 'late',
    ).length;

    return (
        <>
            <Head title={meeting.title} />
            <Page>
                <PageHeader
                    title={meeting.title}
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            {kinds[meeting.kind] ?? meeting.kind} ·{' '}
                            {formatDateTime(meeting.scheduled_at)}
                            {meeting.venue ? ` · ${meeting.venue}` : ''}
                            <StatusBadge status={meeting.status} />
                        </span>
                    }
                    actions={
                        canManage &&
                        meeting.status === 'scheduled' && (
                            <ActionButton
                                url={`/meetings/${meeting.id}/notify`}
                                variant="outline"
                                confirm="Send the meeting notice to every active member by SMS and email?"
                            >
                                <BellRing /> Send notice
                            </ActionButton>
                        )
                    }
                />

                {meeting.status === 'scheduled' && (
                    <div className="flex flex-col gap-2 rounded-xl border border-emerald-200 bg-emerald-50 p-3 sm:flex-row sm:items-center sm:justify-between dark:border-emerald-900 dark:bg-emerald-950/40">
                        <p className="text-sm">
                            {meeting.online_link
                                ? 'This meeting is online. Join from your phone or computer at the time.'
                                : 'Add it to your calendar so your phone reminds you.'}
                        </p>
                        <div className="flex flex-wrap gap-2">
                            {meeting.online_link && (
                                <Button asChild>
                                    <a
                                        href={meeting.online_link}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <Video /> Join meeting
                                    </a>
                                </Button>
                            )}
                            <Button asChild variant="outline">
                                <a
                                    href={meeting.google_calendar_url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <CalendarPlus /> Add to Google Calendar
                                </a>
                            </Button>
                            <Button asChild variant="outline">
                                <a
                                    href={`/meetings/${meeting.id}/calendar.ics`}
                                >
                                    <Download /> Calendar file
                                </a>
                            </Button>
                        </div>
                    </div>
                )}

                <Tabs
                    value={tab}
                    onChange={setTab}
                    tabs={[
                        { value: 'details', label: 'Agenda and minutes' },
                        {
                            value: 'attendance',
                            label: 'Attendance',
                            count: present,
                        },
                        {
                            value: 'resolutions',
                            label: 'Resolutions',
                            count: resolutions.length,
                        },
                        ...(canCollect
                            ? [
                                  {
                                      value: 'cash' as const,
                                      label: 'Cash collected',
                                      count: collections.length,
                                  },
                              ]
                            : []),
                    ]}
                />

                {tab === 'details' && (
                    <DetailsForm
                        meeting={meeting}
                        kinds={kinds}
                        canManage={canManage}
                    />
                )}

                {tab === 'attendance' && (
                    <AttendanceForm
                        meetingId={meeting.id}
                        roll={roll}
                        canManage={canManage}
                    />
                )}

                {tab === 'resolutions' && (
                    <Section
                        title="Resolutions"
                        actions={
                            canManage && (
                                <FormDialog
                                    title="Record a resolution"
                                    action={`/meetings/${meeting.id}/resolutions`}
                                    submitLabel="Record"
                                    initial={{
                                        number: nextResolutionNumber,
                                        title: '',
                                        body: '',
                                        adopted_on:
                                            meeting.scheduled_at_input.slice(
                                                0,
                                                10,
                                            ),
                                    }}
                                    trigger={
                                        <Button size="sm" variant="outline">
                                            <Gavel /> Add resolution
                                        </Button>
                                    }
                                >
                                    {(form) => (
                                        <>
                                            <div className="grid grid-cols-2 gap-3">
                                                <Field
                                                    label="Number"
                                                    htmlFor="rs-num"
                                                    error={form.errors.number}
                                                >
                                                    <Input
                                                        id="rs-num"
                                                        value={form.data.number}
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'number',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </Field>
                                                <Field
                                                    label="Adopted on"
                                                    htmlFor="rs-date"
                                                    error={
                                                        form.errors.adopted_on
                                                    }
                                                >
                                                    <Input
                                                        id="rs-date"
                                                        type="date"
                                                        value={
                                                            form.data.adopted_on
                                                        }
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'adopted_on',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </Field>
                                            </div>
                                            <Field
                                                label="Resolution"
                                                htmlFor="rs-title"
                                                error={form.errors.title}
                                            >
                                                <Input
                                                    id="rs-title"
                                                    placeholder="Monthly contribution raised to KES 1,500 from January"
                                                    value={form.data.title}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'title',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                            <Field
                                                label="Details"
                                                htmlFor="rs-body"
                                            >
                                                <Textarea
                                                    id="rs-body"
                                                    value={form.data.body}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'body',
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
                    >
                        {resolutions.length === 0 ? (
                            <EmptyState title="No resolutions from this meeting" />
                        ) : (
                            <ul className="space-y-3">
                                {resolutions.map((r) => (
                                    <li
                                        key={r.id}
                                        className="rounded-lg border p-3 text-sm"
                                    >
                                        <div className="flex justify-between gap-2">
                                            <span className="font-medium">
                                                <span className="font-mono text-xs text-muted-foreground">
                                                    {r.number}
                                                </span>{' '}
                                                {r.title}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {formatDate(r.adopted_on)}
                                            </span>
                                        </div>
                                        {r.body && (
                                            <p className="mt-1 whitespace-pre-line text-muted-foreground">
                                                {r.body}
                                            </p>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Section>
                )}

                {tab === 'cash' && canCollect && (
                    <CashCollection
                        meetingId={meeting.id}
                        roll={roll}
                        collections={collections}
                    />
                )}
            </Page>
        </>
    );
}

function DetailsForm({
    meeting,
    kinds,
    canManage,
}: {
    meeting: Props['meeting'];
    kinds: Record<string, string>;
    canManage: boolean;
}) {
    const form = useForm({
        title: meeting.title,
        kind: meeting.kind,
        scheduled_at: meeting.scheduled_at_input,
        venue: meeting.venue ?? '',
        online_link: meeting.online_link ?? '',
        duration_minutes: String(meeting.duration_minutes),
        agenda: meeting.agenda ?? '',
        minutes: meeting.minutes ?? '',
        status: meeting.status,
    });

    if (!canManage) {
        return (
            <div className="grid gap-6 lg:grid-cols-2">
                <Section title="Agenda">
                    <p className="text-sm whitespace-pre-line">
                        {meeting.agenda ?? 'No agenda yet.'}
                    </p>
                </Section>
                <Section title="Minutes">
                    <p className="text-sm whitespace-pre-line">
                        {meeting.minutes ??
                            'Minutes will appear here after the meeting.'}
                    </p>
                </Section>
            </div>
        );
    }

    return (
        <form
            className="grid gap-6"
            onSubmit={(e) => {
                e.preventDefault();
                form.put(`/meetings/${meeting.id}`, { preserveScroll: true });
            }}
        >
            <ActionError message={actionError(form.errors)} />
            <Section>
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Field
                        label="Title"
                        htmlFor="md-title"
                        error={form.errors.title}
                    >
                        <Input
                            id="md-title"
                            value={form.data.title}
                            onChange={(e) =>
                                form.setData('title', e.target.value)
                            }
                        />
                    </Field>
                    <Field label="Type" htmlFor="md-kind">
                        <Select
                            id="md-kind"
                            value={form.data.kind}
                            onChange={(e) =>
                                form.setData('kind', e.target.value)
                            }
                            options={Object.entries(kinds).map(
                                ([value, label]) => ({ value, label }),
                            )}
                        />
                    </Field>
                    <Field
                        label="When"
                        htmlFor="md-when"
                        error={form.errors.scheduled_at}
                    >
                        <Input
                            id="md-when"
                            type="datetime-local"
                            value={form.data.scheduled_at}
                            onChange={(e) =>
                                form.setData('scheduled_at', e.target.value)
                            }
                        />
                    </Field>
                    <Field label="Status" htmlFor="md-status">
                        <Select
                            id="md-status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData('status', e.target.value)
                            }
                            options={['scheduled', 'held', 'cancelled'].map(
                                (s) => ({ value: s, label: titleCase(s) }),
                            )}
                        />
                    </Field>
                    <Field
                        label="Venue"
                        htmlFor="md-venue"
                        className="sm:col-span-2 lg:col-span-4"
                    >
                        <Input
                            id="md-venue"
                            value={form.data.venue}
                            onChange={(e) =>
                                form.setData('venue', e.target.value)
                            }
                        />
                    </Field>
                </div>
                <div className="mt-4 grid gap-4 sm:grid-cols-[3fr_1fr]">
                    <OnlineMeetingFields
                        idPrefix="md"
                        link={form.data.online_link}
                        duration={form.data.duration_minutes}
                        onLink={(v) => form.setData('online_link', v)}
                        onDuration={(v) => form.setData('duration_minutes', v)}
                        linkError={form.errors.online_link}
                    />
                </div>
            </Section>
            <div className="grid gap-6 lg:grid-cols-2">
                <Section title="Agenda">
                    <Textarea
                        rows={10}
                        value={form.data.agenda}
                        onChange={(e) => form.setData('agenda', e.target.value)}
                    />
                </Section>
                <Section title="Minutes">
                    <Textarea
                        rows={10}
                        value={form.data.minutes}
                        onChange={(e) =>
                            form.setData('minutes', e.target.value)
                        }
                        placeholder="Type the minutes during or after the meeting."
                    />
                </Section>
            </div>
            <div>
                <Button disabled={form.processing || !form.isDirty}>
                    Save meeting
                </Button>
            </div>
        </form>
    );
}

function AttendanceForm({
    meetingId,
    roll,
    canManage,
}: {
    meetingId: number;
    roll: Props['roll'];
    canManage: boolean;
}) {
    const form = useForm({
        attendance: Object.fromEntries(
            roll.map((r) => [String(r.member_id), r.status ?? '']),
        ) as Record<string, string>,
    });

    const setAll = (status: string) =>
        form.setData(
            'attendance',
            Object.fromEntries(roll.map((r) => [String(r.member_id), status])),
        );

    return (
        <Section
            title="Attendance"
            actions={
                canManage && (
                    <Button
                        size="sm"
                        variant="outline"
                        type="button"
                        onClick={() => setAll('present')}
                    >
                        Mark all present
                    </Button>
                )
            }
        >
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(`/meetings/${meetingId}/attendance`, {
                        preserveScroll: true,
                    });
                }}
            >
                <Table>
                    <TBody>
                        {roll.map((r) => (
                            <TR key={r.member_id}>
                                <TD>
                                    {r.name}{' '}
                                    <span className="text-xs text-muted-foreground">
                                        {r.member_number}
                                    </span>
                                </TD>
                                <TD className="w-48">
                                    {canManage ? (
                                        <Select
                                            aria-label={`Attendance for ${r.name}`}
                                            value={
                                                form.data.attendance[
                                                    String(r.member_id)
                                                ] ?? ''
                                            }
                                            onChange={(e) =>
                                                form.setData('attendance', {
                                                    ...form.data.attendance,
                                                    [String(r.member_id)]:
                                                        e.target.value,
                                                })
                                            }
                                            placeholder="Not marked"
                                            options={[
                                                'present',
                                                'late',
                                                'apology',
                                                'absent',
                                            ].map((s) => ({
                                                value: s,
                                                label: titleCase(s),
                                            }))}
                                        />
                                    ) : r.status ? (
                                        <StatusBadge status={r.status} />
                                    ) : (
                                        '—'
                                    )}
                                </TD>
                            </TR>
                        ))}
                    </TBody>
                </Table>
                {canManage && (
                    <Button className="mt-4" disabled={form.processing}>
                        Save attendance
                    </Button>
                )}
            </form>
        </Section>
    );
}

function CashCollection({
    meetingId,
    roll,
    collections,
}: {
    meetingId: number;
    roll: Props['roll'];
    collections: Props['collections'];
}) {
    const members = roll.map((r) => ({
        id: r.member_id,
        label: `${r.name} (${r.member_number})`,
    }));
    const form = useForm({ rows: [{ member_id: '', amount: '' }] });
    const total = collections.reduce((sum, c) => sum + c.amount, 0);

    return (
        <div className="grid gap-6 lg:grid-cols-2">
            <Section
                title="Collect cash"
                description={`Each row is recorded as a contribution and receipted by SMS. Today is ${formatDate(today())}.`}
            >
                <form
                    className="space-y-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(`/meetings/${meetingId}/collect`, {
                            preserveScroll: true,
                            onSuccess: () => form.reset(),
                        });
                    }}
                >
                    <ActionError message={actionError(form.errors)} />
                    {form.data.rows.map((row, i) => (
                        <div key={i} className="flex items-end gap-2">
                            <Field
                                label={i === 0 ? 'Member' : ''}
                                htmlFor={`cc-m-${i}`}
                                className="flex-1"
                            >
                                <MemberSelect
                                    id={`cc-m-${i}`}
                                    members={members}
                                    value={row.member_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'rows',
                                            form.data.rows.map((r, j) =>
                                                j === i
                                                    ? {
                                                          ...r,
                                                          member_id:
                                                              e.target.value,
                                                      }
                                                    : r,
                                            ),
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                label={i === 0 ? 'Amount' : ''}
                                htmlFor={`cc-a-${i}`}
                                className="w-36"
                            >
                                <MoneyInput
                                    id={`cc-a-${i}`}
                                    value={row.amount}
                                    onChange={(e) =>
                                        form.setData(
                                            'rows',
                                            form.data.rows.map((r, j) =>
                                                j === i
                                                    ? {
                                                          ...r,
                                                          amount: e.target
                                                              .value,
                                                      }
                                                    : r,
                                            ),
                                        )
                                    }
                                />
                            </Field>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="Remove row"
                                disabled={form.data.rows.length === 1}
                                onClick={() =>
                                    form.setData(
                                        'rows',
                                        form.data.rows.filter(
                                            (_, j) => j !== i,
                                        ),
                                    )
                                }
                            >
                                <Trash2 />
                            </Button>
                        </div>
                    ))}
                    {Object.keys(form.errors).length > 0 &&
                        !actionError(form.errors) && (
                            <p className="text-sm text-red-600">
                                Every row needs a member and an amount.
                            </p>
                        )}
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                form.setData('rows', [
                                    ...form.data.rows,
                                    { member_id: '', amount: '' },
                                ])
                            }
                        >
                            <Plus /> Row
                        </Button>
                        <Button disabled={form.processing}>
                            <Coins /> Record cash
                        </Button>
                    </div>
                </form>
            </Section>
            <Section
                title="Recorded at this meeting"
                description={`Total ${formatKes(total)}`}
            >
                {collections.length === 0 ? (
                    <EmptyState title="Nothing collected yet" />
                ) : (
                    <Table>
                        <THead>
                            <TR>
                                <TH>Member</TH>
                                <TH className="text-right">Amount</TH>
                            </TR>
                        </THead>
                        <TBody>
                            {collections.map((c) => (
                                <TR key={c.id}>
                                    <TD>{c.member ?? '—'}</TD>
                                    <Num>{formatKes(c.amount)}</Num>
                                </TR>
                            ))}
                        </TBody>
                    </Table>
                )}
            </Section>
        </div>
    );
}

MeetingShow.layout = {
    breadcrumbs: [{ title: 'Meetings', href: '/meetings' }],
};
