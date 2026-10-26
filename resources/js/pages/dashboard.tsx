import { Head, Link, usePage } from '@inertiajs/react';
import { CalendarDays, Megaphone, Smartphone, Video } from 'lucide-react';
import { FormDialog } from '@/components/kertai/actions';
import { Meter } from '@/components/kertai/charts';
import { Field, Select } from '@/components/kertai/fields';
import {
    EmptyState,
    Page,
    PageHeader,
    Progress,
    Section,
    StatCard,
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
    percent,
    titleCase,
} from '@/lib/format';

type Props = {
    loans: {
        mine: {
            id: number;
            reference: string;
            status: string;
            owed: number;
            due_on: string | null;
            overdue: boolean;
            behind: number;
        }[];
        asked: { loan_id: number; borrower: string; amount: number }[];
    };
    me: {
        savings: number;
        prepayment: number;
        outstanding: number;
        arrears: number;
        fines: number;
        phone: string;
        next_turn: {
            cycle: string;
            position: number;
            date: string;
            amount: number;
        } | null;
        recent_payments: {
            id: number;
            amount: number;
            status: string;
            channel: string;
            purpose: string;
            date: string;
            receipt: string | null;
        }[];
    };
    meetings: {
        id: number;
        title: string;
        scheduled_at: string;
        venue: string | null;
        online_link: string | null;
    }[];
    fundraisers: {
        id: number;
        title: string;
        slug: string;
        target: number;
        raised: number;
    }[];
    finance: {
        accounts: {
            code: string;
            name: string;
            kind: string;
            balance: number;
        }[];
        collected_this_month: number;
        arrears: number;
        members: number;
        unmatched: number;
        pending_stk: number;
        awaiting_my_approval: number;
        awaiting_payment: number;
    } | null;
    books: {
        held: number;
        due_so_far: number;
        paid_so_far: number;
        arrears: number;
        in_arrears: number;
    } | null;
    admin: {
        failed_messages: number;
        queued_messages: number;
        members_without_login: number;
    } | null;
};

export default function Dashboard({
    loans,
    me,
    meetings,
    fundraisers,
    finance,
    books,
    admin,
}: Props) {
    const { auth, group } = usePage().props;
    const suggested = Math.max(0, Math.ceil(me.outstanding / 100));
    const running = loans.mine.filter((l) => l.status === 'active');

    return (
        <>
            <Head title="Dashboard" />
            <Page>
                <PageHeader
                    title={`Hello, ${auth.member?.name.split(' ')[0] ?? auth.user.name}`}
                    description={
                        auth.member?.positions.length
                            ? auth.member.positions.join(' · ')
                            : `Member ${auth.member?.member_number ?? ''}`
                    }
                    actions={
                        <FormDialog
                            title="Pay with M-Pesa"
                            description="We will send an M-Pesa prompt to your phone. Enter your PIN to complete."
                            action="/me/pay"
                            submitLabel="Send prompt"
                            initial={{
                                amount: suggested > 0 ? String(suggested) : '',
                                phone: me.phone,
                                purpose: 'contribution',
                                target_id: '',
                            }}
                            trigger={
                                <Button>
                                    <Smartphone /> Pay now
                                </Button>
                            }
                        >
                            {(form) => (
                                <>
                                    <Field
                                        label="Amount (KES, whole shillings)"
                                        htmlFor="pay-amount"
                                        error={form.errors.amount}
                                    >
                                        <Input
                                            id="pay-amount"
                                            inputMode="numeric"
                                            value={form.data.amount}
                                            onChange={(e) =>
                                                form.setData(
                                                    'amount',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                    <Field
                                        label="M-Pesa number"
                                        htmlFor="pay-phone"
                                        error={form.errors.phone}
                                    >
                                        <Input
                                            id="pay-phone"
                                            inputMode="tel"
                                            value={form.data.phone}
                                            onChange={(e) =>
                                                form.setData(
                                                    'phone',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                    <Field
                                        label="Paying for"
                                        htmlFor="pay-purpose"
                                    >
                                        <Select
                                            id="pay-purpose"
                                            value={
                                                form.data.purpose === 'loan'
                                                    ? `loan-${form.data.target_id}`
                                                    : form.data.purpose
                                            }
                                            onChange={(e) => {
                                                const [purpose, id] =
                                                    e.target.value.split('-');
                                                form.setData({
                                                    ...form.data,
                                                    purpose,
                                                    target_id: id ?? '',
                                                });
                                            }}
                                            options={[
                                                {
                                                    value: 'contribution',
                                                    label: 'Contributions, levies and fines',
                                                },
                                                ...running.map((l) => ({
                                                    value: `loan-${l.id}`,
                                                    label: `My loan ${l.reference} (${formatKes(l.owed)} owed)`,
                                                })),
                                                {
                                                    value: 'donation',
                                                    label: 'Donation to the group fund',
                                                },
                                            ]}
                                        />
                                    </Field>
                                    {group?.paybill && (
                                        <p className="text-xs text-muted-foreground">
                                            Or pay through paybill{' '}
                                            {group.paybill}, account{' '}
                                            {group.paybill_account}.
                                        </p>
                                    )}
                                </>
                            )}
                        </FormDialog>
                    }
                />

                <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                    <StatCard
                        label="My savings"
                        value={formatKes(me.savings)}
                        tone="good"
                    />
                    <StatCard
                        label="Arrears"
                        value={formatKes(me.arrears)}
                        tone={me.arrears > 0 ? 'bad' : 'good'}
                        hint={
                            me.fines > 0
                                ? `Includes fines of ${formatKes(me.fines)}`
                                : undefined
                        }
                    />
                    <StatCard
                        label="Owed this period"
                        value={formatKes(me.outstanding)}
                        tone={me.outstanding > 0 ? 'warn' : 'default'}
                        hint={
                            me.prepayment > 0
                                ? `Paid in advance: ${formatKes(me.prepayment)}`
                                : undefined
                        }
                    />
                    <StatCard
                        label="Next merry-go-round turn"
                        value={
                            me.next_turn ? formatDate(me.next_turn.date) : '—'
                        }
                        hint={
                            me.next_turn
                                ? `${me.next_turn.cycle}, turn ${me.next_turn.position}: ${formatKes(me.next_turn.amount)}`
                                : 'Not in an active cycle'
                        }
                    />
                </div>

                {loans.asked.length > 0 && (
                    <Section
                        title="Waiting for your answer"
                        className="border-amber-300 dark:border-amber-800"
                    >
                        <ul className="space-y-2 text-sm">
                            {loans.asked.map((a) => (
                                <li key={a.loan_id}>
                                    <Link
                                        href={`/loans/${a.loan_id}`}
                                        className="font-medium underline"
                                    >
                                        {a.borrower} asks you to guarantee{' '}
                                        {formatKes(a.amount)} of their loan
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </Section>
                )}

                {loans.mine.length > 0 && (
                    <Section
                        title="My loans"
                        actions={
                            <Button asChild size="sm" variant="outline">
                                <Link href="/me/loans">All</Link>
                            </Button>
                        }
                    >
                        <ul className="divide-y">
                            {loans.mine.map((l) => (
                                <li key={l.id}>
                                    <Link
                                        href={`/loans/${l.id}`}
                                        className="flex items-center justify-between gap-3 py-2 text-sm hover:underline"
                                    >
                                        <span>
                                            <span className="font-medium">
                                                {l.reference}
                                            </span>
                                            <span className="block text-xs text-muted-foreground">
                                                {l.status !== 'active'
                                                    ? 'Application in progress'
                                                    : l.overdue
                                                      ? 'Past the date agreed'
                                                      : l.behind > 0
                                                        ? ` behind`
                                                        : l.due_on
                                                          ? `Due ${formatDate(l.due_on)}`
                                                          : 'Running'}
                                            </span>
                                        </span>
                                        <span
                                            className={
                                                l.overdue || l.behind > 0
                                                    ? 'font-semibold text-red-700 dark:text-red-400'
                                                    : 'font-semibold'
                                            }
                                        >
                                            {l.status === 'active'
                                                ? formatKes(l.owed)
                                                : ''}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </Section>
                )}

                {books && (
                    <Section
                        title="How the group is doing"
                        actions={
                            <Button asChild size="sm" variant="outline">
                                <Link href="/group-finances">See all</Link>
                            </Button>
                        }
                    >
                        <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3">
                            <StatCard
                                label="Held for the group"
                                value={formatKes(books.held)}
                                tone="good"
                            />
                            <StatCard
                                label="Contributions paid this year"
                                value={
                                    books.due_so_far > 0
                                        ? `${percent(books.paid_so_far, books.due_so_far)}%`
                                        : '—'
                                }
                                hint={
                                    books.due_so_far > 0 ? (
                                        <Meter
                                            value={books.paid_so_far}
                                            max={books.due_so_far}
                                        />
                                    ) : (
                                        'Nothing due yet'
                                    )
                                }
                            />
                            <StatCard
                                label="Group arrears"
                                value={formatKes(books.arrears)}
                                tone={books.arrears > 0 ? 'warn' : 'good'}
                                hint={`${books.in_arrears} ${books.in_arrears === 1 ? 'member' : 'members'}`}
                            />
                        </div>
                    </Section>
                )}

                {finance && (
                    <Section
                        title="Group finances"
                        actions={
                            <>
                                {finance.awaiting_my_approval > 0 && (
                                    <Button asChild size="sm">
                                        <Link href="/payouts?status=mine">
                                            {finance.awaiting_my_approval}{' '}
                                            payout(s) need your approval
                                        </Link>
                                    </Button>
                                )}
                                {finance.unmatched > 0 && (
                                    <Button asChild size="sm" variant="outline">
                                        <Link href="/payments?status=unmatched">
                                            {finance.unmatched} unmatched
                                            payment(s)
                                        </Link>
                                    </Button>
                                )}
                            </>
                        }
                    >
                        <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                            <StatCard
                                label="Collected this month"
                                value={formatKes(finance.collected_this_month)}
                            />
                            <StatCard
                                label="Total arrears"
                                value={formatKes(finance.arrears)}
                                tone={finance.arrears > 0 ? 'warn' : 'good'}
                            />
                            <StatCard
                                label="Active members"
                                value={finance.members}
                            />
                            <StatCard
                                label="Payouts to pay"
                                value={finance.awaiting_payment}
                                hint={
                                    finance.pending_stk > 0
                                        ? `${finance.pending_stk} M-Pesa prompts pending`
                                        : undefined
                                }
                            />
                        </div>
                        <div className="mt-4 grid gap-4 lg:grid-cols-2">
                            {(['asset', 'fund'] as const).map((kind) => (
                                <div key={kind}>
                                    <h3 className="mb-2 text-sm font-medium text-muted-foreground">
                                        {kind === 'asset'
                                            ? 'Where the money is'
                                            : 'What the money is for'}
                                    </h3>
                                    <Table>
                                        <TBody>
                                            {finance.accounts
                                                .filter((a) => a.kind === kind)
                                                .map((a) => (
                                                    <TR key={a.code}>
                                                        <TD>{a.name}</TD>
                                                        <Num>
                                                            {formatKes(
                                                                a.balance,
                                                            )}
                                                        </Num>
                                                    </TR>
                                                ))}
                                        </TBody>
                                    </Table>
                                </div>
                            ))}
                        </div>
                    </Section>
                )}

                {admin &&
                    (admin.failed_messages > 0 ||
                        admin.members_without_login > 0) && (
                        <Section title="Needs attention">
                            <ul className="list-inside list-disc space-y-1 text-sm">
                                {admin.failed_messages > 0 && (
                                    <li>
                                        {admin.failed_messages} messages failed
                                        to send.{' '}
                                        <Link
                                            className="underline"
                                            href="/messages?status=failed"
                                        >
                                            Review
                                        </Link>
                                    </li>
                                )}
                                {admin.members_without_login > 0 && (
                                    <li>
                                        {admin.members_without_login} active
                                        members have no login yet.{' '}
                                        <Link
                                            className="underline"
                                            href="/members"
                                        >
                                            Open the register
                                        </Link>
                                    </li>
                                )}
                            </ul>
                        </Section>
                    )}

                <div className="grid gap-6 lg:grid-cols-2">
                    <Section
                        title="Fundraisers"
                        actions={
                            <Button asChild size="sm" variant="outline">
                                <Link href="/fundraisers">All</Link>
                            </Button>
                        }
                    >
                        {fundraisers.length === 0 ? (
                            <EmptyState title="No active fundraisers" />
                        ) : (
                            <div className="space-y-4">
                                {fundraisers.map((f) => (
                                    <Link
                                        key={f.id}
                                        href={`/fundraisers/${f.id}`}
                                        className="block space-y-1.5 rounded-lg p-2 hover:bg-muted/50"
                                    >
                                        <div className="flex items-center justify-between gap-2 text-sm">
                                            <span className="flex items-center gap-2 font-medium">
                                                <Megaphone className="size-4" />{' '}
                                                {f.title}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {percent(f.raised, f.target)}%
                                            </span>
                                        </div>
                                        <Progress
                                            value={percent(f.raised, f.target)}
                                        />
                                        <div className="text-xs text-muted-foreground">
                                            {formatKes(f.raised)} of{' '}
                                            {formatKes(f.target)}
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </Section>

                    <Section
                        title="Upcoming meetings"
                        actions={
                            <Button asChild size="sm" variant="outline">
                                <Link href="/meetings">All</Link>
                            </Button>
                        }
                    >
                        {meetings.length === 0 ? (
                            <EmptyState title="No meetings scheduled" />
                        ) : (
                            <ul className="space-y-3">
                                {meetings.map((m) => (
                                    <li
                                        key={m.id}
                                        className="flex items-start justify-between gap-2"
                                    >
                                        <Link
                                            href={`/meetings/${m.id}`}
                                            className="flex min-w-0 flex-1 items-start gap-3 rounded-lg p-2 hover:bg-muted/50"
                                        >
                                            <CalendarDays className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                            <div className="text-sm">
                                                <div className="font-medium">
                                                    {m.title}
                                                </div>
                                                <div className="text-muted-foreground">
                                                    {formatDateTime(
                                                        m.scheduled_at,
                                                    )}
                                                    {m.venue
                                                        ? ` · ${m.venue}`
                                                        : ''}
                                                    {m.online_link
                                                        ? ' · online'
                                                        : ''}
                                                </div>
                                            </div>
                                        </Link>
                                        {m.online_link && (
                                            <Button
                                                asChild
                                                size="sm"
                                                className="mt-1"
                                            >
                                                <a
                                                    href={m.online_link}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    <Video /> Join
                                                </a>
                                            </Button>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Section>
                </div>

                <Section
                    title="My recent payments"
                    actions={
                        auth.member && (
                            <Button asChild size="sm" variant="outline">
                                <Link href={`/statements/${auth.member.id}`}>
                                    Full statement
                                </Link>
                            </Button>
                        )
                    }
                >
                    {me.recent_payments.length === 0 ? (
                        <EmptyState title="No payments yet" />
                    ) : (
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Date</TH>
                                    <TH>For</TH>
                                    <TH>Channel</TH>
                                    <TH>Receipt</TH>
                                    <TH>Status</TH>
                                    <TH className="text-right">Amount</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {me.recent_payments.map((p) => (
                                    <TR key={p.id}>
                                        <TD>{formatDate(p.date)}</TD>
                                        <TD>{titleCase(p.purpose)}</TD>
                                        <TD>{titleCase(p.channel)}</TD>
                                        <TD className="font-mono text-xs">
                                            {p.receipt ?? '—'}
                                        </TD>
                                        <TD>
                                            <StatusBadge status={p.status} />
                                        </TD>
                                        <Num>{formatKes(p.amount)}</Num>
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

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: '/dashboard' }],
};
