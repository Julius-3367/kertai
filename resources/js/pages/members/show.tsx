import { Head, Link } from '@inertiajs/react';
import { FileDown, KeyRound, Pencil, Trash2, UserPlus } from 'lucide-react';
import { useState } from 'react';
import { ActionButton, FormDialog, Tabs } from '@/components/kertai/actions';
import {
    CheckField,
    Field,
    Select,
    Textarea,
} from '@/components/kertai/fields';
import {
    DefinitionList,
    EmptyState,
    Page,
    PageHeader,
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
import { useCan } from '@/hooks/use-can';
import {
    formatDate,
    formatDateTime,
    formatKes,
    titleCase,
    today,
} from '@/lib/format';

type Props = {
    member: {
        id: number;
        member_number: string;
        name: string;
        phone: string;
        email: string | null;
        id_number: string | null;
        gender: string | null;
        date_of_birth: string | null;
        joined_on: string;
        status: string;
        nominee_name: string | null;
        nominee_phone: string | null;
        nominee_relationship: string | null;
        nominee_id_number: string | null;
        notify_sms: boolean;
        notify_email: boolean;
        reminders_opt_out: boolean;
        locale: string;
        exit_notice_on: string | null;
        exited_on: string | null;
        exit_reason: string | null;
        notes: string | null;
        has_login: boolean;
        last_login_at: string | null;
        notice_ends_on: string | null;
        positions: {
            id: number;
            position: string;
            starts_on: string;
            ends_on: string | null;
            is_acting: boolean;
            current: boolean;
        }[];
        dependants: {
            id: number;
            name: string;
            relationship: string;
            date_of_birth: string | null;
            phone: string | null;
        }[];
    };
    finance: {
        savings: number;
        prepayment: number;
        outstanding: number;
        arrears: number;
        fines: number;
        exit_refund: number;
        dues: {
            id: number;
            type: string;
            description: string;
            amount: number;
            paid: number;
            due_on: string;
            status: string;
        }[];
        payments: {
            id: number;
            amount: number;
            channel: string;
            purpose: string;
            status: string;
            receipt: string | null;
            date: string;
        }[];
    } | null;
    isSelf: boolean;
};

export default function MemberShow({ member, finance, isSelf }: Props) {
    const can = useCan();
    const [tab, setTab] = useState<'profile' | 'money' | 'actions'>(
        finance ? 'money' : 'profile',
    );
    const manage = can('members.manage');
    const active = ['active', 'exiting'].includes(member.status);

    const tabs: { value: 'profile' | 'money' | 'actions'; label: string }[] = [
        { value: 'profile', label: 'Profile' },
        ...(finance ? [{ value: 'money' as const, label: 'Money' }] : []),
        ...(manage || can('payouts.request')
            ? [{ value: 'actions' as const, label: 'Membership actions' }]
            : []),
    ];

    return (
        <>
            <Head title={member.name} />
            <Page>
                <PageHeader
                    title={member.name}
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            {member.member_number} · {member.phone}
                            <StatusBadge status={member.status} />
                        </span>
                    }
                    actions={
                        <>
                            {finance && (
                                <Button asChild variant="outline">
                                    <Link href={`/statements/${member.id}`}>
                                        <FileDown /> Statement
                                    </Link>
                                </Button>
                            )}
                            {manage && (
                                <Button asChild variant="outline">
                                    <Link href={`/members/${member.id}/edit`}>
                                        <Pencil /> Edit
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                {member.status === 'exiting' && member.notice_ends_on && (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                        Gave notice on {formatDate(member.exit_notice_on)} (
                        {member.exit_reason}). The refund can be processed from{' '}
                        {formatDate(member.notice_ends_on)}.
                    </div>
                )}

                <Tabs tabs={tabs} value={tab} onChange={setTab} />

                {tab === 'profile' && (
                    <div className="grid gap-6 lg:grid-cols-2">
                        <Section title="Details">
                            <DefinitionList
                                items={[
                                    { label: 'Email', value: member.email },
                                    {
                                        label: 'National ID',
                                        value: member.id_number,
                                    },
                                    {
                                        label: 'Gender',
                                        value: member.gender
                                            ? titleCase(member.gender)
                                            : null,
                                    },
                                    {
                                        label: 'Date of birth',
                                        value: formatDate(member.date_of_birth),
                                    },
                                    {
                                        label: 'Joined',
                                        value: formatDate(member.joined_on),
                                    },
                                    {
                                        label: 'Messages',
                                        value: `${member.notify_sms ? 'SMS' : ''}${member.notify_sms && member.notify_email ? ' + ' : ''}${member.notify_email ? 'email' : ''} (${member.locale === 'sw' ? 'Kiswahili' : 'English'})${member.reminders_opt_out ? ', reminders off' : ''}`,
                                    },
                                    {
                                        label: 'Login',
                                        value: member.has_login
                                            ? `Yes${member.last_login_at ? `, last ${formatDateTime(member.last_login_at)}` : ', never signed in'}`
                                            : 'No login yet',
                                    },
                                    {
                                        label: 'Exited',
                                        value: member.exited_on
                                            ? `${formatDate(member.exited_on)} (${member.exit_reason ?? ''})`
                                            : null,
                                    },
                                ]}
                            />
                            {member.notes && (
                                <p className="mt-4 text-sm whitespace-pre-line text-muted-foreground">
                                    {member.notes}
                                </p>
                            )}
                        </Section>

                        <Section title="Nominee">
                            <DefinitionList
                                items={[
                                    {
                                        label: 'Name',
                                        value: member.nominee_name,
                                    },
                                    {
                                        label: 'Phone',
                                        value: member.nominee_phone,
                                    },
                                    {
                                        label: 'Relationship',
                                        value: member.nominee_relationship,
                                    },
                                    {
                                        label: 'ID number',
                                        value: member.nominee_id_number,
                                    },
                                ]}
                            />
                        </Section>

                        <Section
                            title="Welfare beneficiaries"
                            description="Spouse, children and parents covered by welfare."
                            actions={
                                manage && (
                                    <FormDialog
                                        title="Add beneficiary"
                                        action={`/members/${member.id}/dependants`}
                                        initial={{
                                            name: '',
                                            relationship: 'spouse',
                                            date_of_birth: '',
                                            phone: '',
                                        }}
                                        trigger={
                                            <Button size="sm" variant="outline">
                                                <UserPlus /> Add
                                            </Button>
                                        }
                                    >
                                        {(form) => (
                                            <>
                                                <Field
                                                    label="Name"
                                                    htmlFor="dep-name"
                                                    error={form.errors.name}
                                                >
                                                    <Input
                                                        id="dep-name"
                                                        value={form.data.name}
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'name',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </Field>
                                                <Field
                                                    label="Relationship"
                                                    htmlFor="dep-rel"
                                                    error={
                                                        form.errors.relationship
                                                    }
                                                >
                                                    <Select
                                                        id="dep-rel"
                                                        value={
                                                            form.data
                                                                .relationship
                                                        }
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'relationship',
                                                                e.target.value,
                                                            )
                                                        }
                                                        options={[
                                                            'spouse',
                                                            'child',
                                                            'parent',
                                                        ].map((r) => ({
                                                            value: r,
                                                            label: titleCase(r),
                                                        }))}
                                                    />
                                                </Field>
                                                <Field
                                                    label="Date of birth"
                                                    htmlFor="dep-dob"
                                                    error={
                                                        form.errors
                                                            .date_of_birth
                                                    }
                                                >
                                                    <Input
                                                        id="dep-dob"
                                                        type="date"
                                                        value={
                                                            form.data
                                                                .date_of_birth
                                                        }
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'date_of_birth',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </Field>
                                                <Field
                                                    label="Phone"
                                                    htmlFor="dep-phone"
                                                    error={form.errors.phone}
                                                >
                                                    <Input
                                                        id="dep-phone"
                                                        value={form.data.phone}
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'phone',
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
                            {member.dependants.length === 0 ? (
                                <EmptyState title="No beneficiaries registered" />
                            ) : (
                                <Table>
                                    <TBody>
                                        {member.dependants.map((d) => (
                                            <TR key={d.id}>
                                                <TD className="font-medium">
                                                    {d.name}
                                                </TD>
                                                <TD>
                                                    {titleCase(d.relationship)}
                                                </TD>
                                                <TD>
                                                    {formatDate(
                                                        d.date_of_birth,
                                                    )}
                                                </TD>
                                                <TD className="text-right">
                                                    {manage && (
                                                        <ActionButton
                                                            url={`/members/${member.id}/dependants/${d.id}`}
                                                            method="delete"
                                                            size="sm"
                                                            variant="ghost"
                                                            confirm={`Remove ${d.name}?`}
                                                            aria-label={`Remove ${d.name}`}
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

                        <Section title="Positions held">
                            {member.positions.length === 0 ? (
                                <EmptyState title="No positions" />
                            ) : (
                                <Table>
                                    <TBody>
                                        {member.positions.map((p) => (
                                            <TR key={p.id}>
                                                <TD className="font-medium">
                                                    {p.is_acting
                                                        ? 'Acting '
                                                        : ''}
                                                    {p.position}
                                                </TD>
                                                <TD>
                                                    {formatDate(p.starts_on)} –{' '}
                                                    {p.ends_on
                                                        ? formatDate(p.ends_on)
                                                        : 'present'}
                                                </TD>
                                                <TD>
                                                    {p.current && (
                                                        <StatusBadge
                                                            status="active"
                                                            label="Current"
                                                        />
                                                    )}
                                                </TD>
                                            </TR>
                                        ))}
                                    </TBody>
                                </Table>
                            )}
                        </Section>
                    </div>
                )}

                {tab === 'money' && finance && (
                    <>
                        <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-5">
                            <StatCard
                                label="Savings"
                                value={formatKes(finance.savings)}
                                tone="good"
                            />
                            <StatCard
                                label="Arrears"
                                value={formatKes(finance.arrears)}
                                tone={finance.arrears > 0 ? 'bad' : 'good'}
                            />
                            <StatCard
                                label="Fines owed"
                                value={formatKes(finance.fines)}
                            />
                            <StatCard
                                label="Paid in advance"
                                value={formatKes(finance.prepayment)}
                            />
                            <StatCard
                                label="Refund if leaving"
                                value={formatKes(finance.exit_refund)}
                                hint="Savings less what is owed"
                            />
                        </div>
                        <div className="grid gap-6 lg:grid-cols-2">
                            <Section title="Contributions, levies and fines">
                                {finance.dues.length === 0 ? (
                                    <EmptyState title="Nothing raised yet" />
                                ) : (
                                    <Table>
                                        <THead>
                                            <TR>
                                                <TH>Due</TH>
                                                <TH>Item</TH>
                                                <TH className="text-right">
                                                    Amount
                                                </TH>
                                                <TH className="text-right">
                                                    Paid
                                                </TH>
                                                <TH>Status</TH>
                                            </TR>
                                        </THead>
                                        <TBody>
                                            {finance.dues.map((d) => (
                                                <TR key={d.id}>
                                                    <TD>
                                                        {formatDate(d.due_on)}
                                                    </TD>
                                                    <TD>{d.description}</TD>
                                                    <Num>
                                                        {formatKes(d.amount)}
                                                    </Num>
                                                    <Num>
                                                        {formatKes(d.paid)}
                                                    </Num>
                                                    <TD>
                                                        <StatusBadge
                                                            status={
                                                                d.status ===
                                                                    'open' &&
                                                                d.due_on <
                                                                    today()
                                                                    ? 'overdue'
                                                                    : d.status
                                                            }
                                                        />
                                                    </TD>
                                                </TR>
                                            ))}
                                        </TBody>
                                    </Table>
                                )}
                            </Section>
                            <Section title="Payments">
                                {finance.payments.length === 0 ? (
                                    <EmptyState title="No payments yet" />
                                ) : (
                                    <Table>
                                        <THead>
                                            <TR>
                                                <TH>Date</TH>
                                                <TH>For</TH>
                                                <TH>Receipt</TH>
                                                <TH>Status</TH>
                                                <TH className="text-right">
                                                    Amount
                                                </TH>
                                            </TR>
                                        </THead>
                                        <TBody>
                                            {finance.payments.map((p) => (
                                                <TR key={p.id}>
                                                    <TD>
                                                        {formatDate(p.date)}
                                                    </TD>
                                                    <TD>
                                                        {titleCase(p.purpose)}
                                                        <div className="text-xs text-muted-foreground">
                                                            {titleCase(
                                                                p.channel,
                                                            )}
                                                        </div>
                                                    </TD>
                                                    <TD className="font-mono text-xs">
                                                        {p.receipt ?? '—'}
                                                    </TD>
                                                    <TD>
                                                        <StatusBadge
                                                            status={p.status}
                                                        />
                                                    </TD>
                                                    <Num>
                                                        {formatKes(p.amount)}
                                                    </Num>
                                                </TR>
                                            ))}
                                        </TBody>
                                    </Table>
                                )}
                            </Section>
                        </div>
                    </>
                )}

                {tab === 'actions' && (
                    <div className="grid gap-4 md:grid-cols-2">
                        {manage && active && (
                            <Section
                                title="Login"
                                description="Creates or resets the login and sends a one-time password by SMS and email."
                            >
                                <ActionButton
                                    url={`/members/${member.id}/login`}
                                    variant="outline"
                                    confirm={
                                        member.has_login
                                            ? `Reset ${member.name}'s password and send a new one?`
                                            : `Send login details to ${member.name}?`
                                    }
                                >
                                    <KeyRound />{' '}
                                    {member.has_login
                                        ? 'Reset password'
                                        : 'Send login details'}
                                </ActionButton>
                            </Section>
                        )}

                        {manage && member.status === 'active' && (
                            <Section
                                title="Leaving the group"
                                description="Record the member's written notice. The refund is processed after the notice period."
                            >
                                <FormDialog
                                    title="Record exit notice"
                                    action={`/members/${member.id}/notice`}
                                    submitLabel="Record notice"
                                    initial={{ reason: '', notice_on: today() }}
                                    trigger={
                                        <Button variant="outline">
                                            Record exit notice
                                        </Button>
                                    }
                                >
                                    {(form) => (
                                        <>
                                            <Field
                                                label="Date notice was given"
                                                htmlFor="notice_on"
                                                error={form.errors.notice_on}
                                            >
                                                <Input
                                                    id="notice_on"
                                                    type="date"
                                                    value={form.data.notice_on}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'notice_on',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                            <Field
                                                label="Reason"
                                                htmlFor="reason"
                                                error={form.errors.reason}
                                            >
                                                <Textarea
                                                    id="reason"
                                                    value={form.data.reason}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'reason',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                        </>
                                    )}
                                </FormDialog>
                            </Section>
                        )}

                        {member.status === 'exiting' && (
                            <Section
                                title="Exit"
                                description="Clears what is owed from savings and raises the refund for two approvals."
                            >
                                <div className="flex flex-wrap gap-2">
                                    {can('payouts.request') && (
                                        <FormDialog
                                            title="Process exit refund"
                                            description={`Refund about ${formatKes(finance?.exit_refund ?? 0)} to ${member.name}.`}
                                            action={`/members/${member.id}/exit`}
                                            submitLabel="Raise refund"
                                            initial={{ ignore_notice: false }}
                                            trigger={
                                                <Button>Process exit</Button>
                                            }
                                        >
                                            {(form) => (
                                                <CheckField
                                                    id="ignore_notice"
                                                    label="Process before the notice period ends"
                                                    hint="Only with the committee's agreement."
                                                    checked={
                                                        form.data.ignore_notice
                                                    }
                                                    onChange={(v) =>
                                                        form.setData(
                                                            'ignore_notice',
                                                            v,
                                                        )
                                                    }
                                                />
                                            )}
                                        </FormDialog>
                                    )}
                                    {manage && (
                                        <ActionButton
                                            url={`/members/${member.id}/notice/withdraw`}
                                            variant="outline"
                                            confirm="Withdraw the exit notice?"
                                        >
                                            Withdraw notice
                                        </ActionButton>
                                    )}
                                </div>
                            </Section>
                        )}

                        {member.status === 'deceased' &&
                            can('payouts.request') && (
                                <Section
                                    title="Pay savings to the nominee"
                                    description={`Savings go to ${member.nominee_name ?? 'the estate'}.`}
                                >
                                    <ActionButton
                                        url={`/members/${member.id}/exit`}
                                        confirm="Raise the savings payout to the nominee?"
                                    >
                                        Raise nominee payout
                                    </ActionButton>
                                </Section>
                            )}

                        {manage && active && (
                            <Section
                                title="Bereavement"
                                description="Records the member's death, ends their positions and opens a welfare case."
                            >
                                <FormDialog
                                    title={`Record the death of ${member.name}`}
                                    action={`/members/${member.id}/death`}
                                    submitLabel="Record"
                                    destructive
                                    initial={{ died_on: today() }}
                                    trigger={
                                        <Button variant="outline">
                                            Record death
                                        </Button>
                                    }
                                >
                                    {(form) => (
                                        <Field
                                            label="Date of death"
                                            htmlFor="died_on"
                                            error={form.errors.died_on}
                                        >
                                            <Input
                                                id="died_on"
                                                type="date"
                                                value={form.data.died_on}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'died_on',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                    )}
                                </FormDialog>
                            </Section>
                        )}
                    </div>
                )}

                {isSelf && (
                    <p className="text-sm text-muted-foreground">
                        Notification and privacy choices are under{' '}
                        <Link href="/settings/profile" className="underline">
                            your settings
                        </Link>
                        .
                    </p>
                )}
            </Page>
        </>
    );
}

MemberShow.layout = { breadcrumbs: [{ title: 'Members', href: '/members' }] };
