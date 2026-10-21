import { Head, Link, router } from '@inertiajs/react';
import { FileText, RotateCcw, Send } from 'lucide-react';
import { ActionButton, FormDialog } from '@/components/kertai/actions';
import {
    CheckField,
    Field,
    Select,
    Textarea,
} from '@/components/kertai/fields';
import {
    EmptyState,
    Page,
    PageHeader,
    Section,
    StatCard,
} from '@/components/kertai/page';
import { Pagination } from '@/components/kertai/pagination';
import { StatusBadge } from '@/components/kertai/status-badge';
import { Table, TBody, TD, TH, THead, TR } from '@/components/kertai/table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDateTime } from '@/lib/format';
import type { Option, Paginated } from '@/types/kertai';

type Message = {
    id: number;
    member: string | null;
    channel: string;
    recipient: string;
    subject: string | null;
    body: string;
    template_key: string | null;
    status: string;
    provider: string | null;
    attempts: number;
    error: string | null;
    created_at: string;
    sent_at: string | null;
};

export default function MessagesIndex({
    messages,
    filters,
    members,
    counts,
}: {
    messages: Paginated<Message>;
    filters: { status?: string; channel?: string };
    members: Option[];
    counts: { queued: number; failed: number; sent_today: number };
}) {
    const apply = (next: Record<string, string>) =>
        router.get(
            '/messages',
            { ...filters, ...next },
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title="Messages" />
            <Page>
                <PageHeader
                    title="Messages"
                    description="Every SMS and email the system sends, with delivery status."
                    actions={
                        <>
                            <FormDialog
                                title="Send a message"
                                description="Placeholders are filled per member: {name}, {savings}, {arrears}, {fines}, {member_number}, {paybill}, {account}."
                                action="/messages"
                                submitLabel="Send"
                                wide
                                initial={{
                                    audience: 'all',
                                    member_ids: [] as number[],
                                    channels: ['sms'],
                                    subject: '',
                                    body: '',
                                }}
                                trigger={
                                    <Button>
                                        <Send /> New message
                                    </Button>
                                }
                            >
                                {(form) => (
                                    <>
                                        <Field
                                            label="To"
                                            htmlFor="msg-aud"
                                            error={form.errors.audience}
                                        >
                                            <Select
                                                id="msg-aud"
                                                value={form.data.audience}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'audience',
                                                        e.target.value,
                                                    )
                                                }
                                                options={[
                                                    {
                                                        value: 'all',
                                                        label: 'All active members',
                                                    },
                                                    {
                                                        value: 'arrears',
                                                        label: 'Members in arrears',
                                                    },
                                                    {
                                                        value: 'officials',
                                                        label: 'Officials',
                                                    },
                                                    {
                                                        value: 'selected',
                                                        label: 'Selected members',
                                                    },
                                                ]}
                                            />
                                        </Field>
                                        {form.data.audience === 'selected' && (
                                            <div className="grid max-h-48 gap-2 overflow-y-auto rounded-md border p-3 sm:grid-cols-2">
                                                {members.map((m) => (
                                                    <CheckField
                                                        key={m.id}
                                                        id={`msg-m-${m.id}`}
                                                        label={m.label}
                                                        checked={form.data.member_ids.includes(
                                                            m.id,
                                                        )}
                                                        onChange={(on) =>
                                                            form.setData(
                                                                'member_ids',
                                                                on
                                                                    ? [
                                                                          ...form
                                                                              .data
                                                                              .member_ids,
                                                                          m.id,
                                                                      ]
                                                                    : form.data.member_ids.filter(
                                                                          (x) =>
                                                                              x !==
                                                                              m.id,
                                                                      ),
                                                            )
                                                        }
                                                    />
                                                ))}
                                            </div>
                                        )}
                                        <div className="flex gap-6">
                                            {['sms', 'email'].map((c) => (
                                                <CheckField
                                                    key={c}
                                                    id={`msg-c-${c}`}
                                                    label={
                                                        c === 'sms'
                                                            ? 'SMS'
                                                            : 'Email'
                                                    }
                                                    checked={form.data.channels.includes(
                                                        c,
                                                    )}
                                                    onChange={(on) =>
                                                        form.setData(
                                                            'channels',
                                                            on
                                                                ? [
                                                                      ...form
                                                                          .data
                                                                          .channels,
                                                                      c,
                                                                  ]
                                                                : form.data.channels.filter(
                                                                      (x) =>
                                                                          x !==
                                                                          c,
                                                                  ),
                                                        )
                                                    }
                                                />
                                            ))}
                                        </div>
                                        {form.data.channels.includes(
                                            'email',
                                        ) && (
                                            <Field
                                                label="Email subject"
                                                htmlFor="msg-subject"
                                            >
                                                <Input
                                                    id="msg-subject"
                                                    value={form.data.subject}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'subject',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                        )}
                                        <Field
                                            label="Message"
                                            htmlFor="msg-body"
                                            error={form.errors.body}
                                            hint={`${form.data.body.length} characters (one SMS page is 160).`}
                                        >
                                            <Textarea
                                                id="msg-body"
                                                rows={5}
                                                value={form.data.body}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'body',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Dear {name}, ..."
                                            />
                                        </Field>
                                    </>
                                )}
                            </FormDialog>
                            <Button asChild variant="outline">
                                <Link href="/messages/templates">
                                    <FileText /> Templates
                                </Link>
                            </Button>
                            {counts.failed > 0 && (
                                <ActionButton
                                    url="/messages/retry-failed"
                                    variant="outline"
                                    confirm="Retry failed messages from the last 7 days?"
                                >
                                    <RotateCcw /> Retry failed
                                </ActionButton>
                            )}
                        </>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard label="Sent today" value={counts.sent_today} />
                    <StatCard label="Waiting" value={counts.queued} />
                    <StatCard
                        label="Failed"
                        value={counts.failed}
                        tone={counts.failed ? 'bad' : 'default'}
                    />
                </div>

                <Section>
                    <div className="mb-4 flex gap-2">
                        <Select
                            className="max-w-44"
                            value={filters.status ?? ''}
                            onChange={(e) => apply({ status: e.target.value })}
                            placeholder="Any status"
                            options={[
                                { value: 'sent', label: 'Sent' },
                                { value: 'queued', label: 'Waiting' },
                                { value: 'failed', label: 'Failed' },
                            ]}
                        />
                        <Select
                            className="max-w-44"
                            value={filters.channel ?? ''}
                            onChange={(e) => apply({ channel: e.target.value })}
                            placeholder="SMS and email"
                            options={[
                                { value: 'sms', label: 'SMS' },
                                { value: 'email', label: 'Email' },
                            ]}
                        />
                    </div>
                    {messages.data.length === 0 ? (
                        <EmptyState title="No messages yet" />
                    ) : (
                        <Table>
                            <THead>
                                <TR>
                                    <TH>When</TH>
                                    <TH>To</TH>
                                    <TH>Message</TH>
                                    <TH>Status</TH>
                                    <TH />
                                </TR>
                            </THead>
                            <TBody>
                                {messages.data.map((m) => (
                                    <TR key={m.id}>
                                        <TD className="whitespace-nowrap">
                                            {formatDateTime(m.created_at)}
                                        </TD>
                                        <TD>
                                            {m.member ?? m.recipient}
                                            <div className="text-xs text-muted-foreground">
                                                {m.channel.toUpperCase()} ·{' '}
                                                {m.recipient}
                                            </div>
                                        </TD>
                                        <TD className="max-w-md">
                                            {m.subject &&
                                                m.channel === 'email' && (
                                                    <div className="font-medium">
                                                        {m.subject}
                                                    </div>
                                                )}
                                            <div
                                                className="line-clamp-2 text-sm text-muted-foreground"
                                                title={m.body}
                                            >
                                                {m.body}
                                            </div>
                                        </TD>
                                        <TD>
                                            <StatusBadge status={m.status} />
                                            <div className="text-xs text-muted-foreground">
                                                {m.provider ?? ''}
                                            </div>
                                            {m.error && (
                                                <div
                                                    className="max-w-xs truncate text-xs text-red-700 dark:text-red-400"
                                                    title={m.error}
                                                >
                                                    {m.error}
                                                </div>
                                            )}
                                        </TD>
                                        <TD className="text-right">
                                            {m.status === 'failed' && (
                                                <ActionButton
                                                    url={`/messages/${m.id}/retry`}
                                                    size="sm"
                                                    variant="ghost"
                                                >
                                                    Retry
                                                </ActionButton>
                                            )}
                                        </TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                    )}
                    <Pagination page={messages} />
                </Section>
            </Page>
        </>
    );
}

MessagesIndex.layout = {
    breadcrumbs: [{ title: 'Messages', href: '/messages' }],
};
