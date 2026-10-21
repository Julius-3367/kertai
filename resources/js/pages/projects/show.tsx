import { Head, Link } from '@inertiajs/react';
import {
    Banknote,
    PackagePlus,
    Pencil,
    Receipt,
    ShoppingCart,
    Trash2,
    UserPlus,
    Wallet,
} from 'lucide-react';
import { useState } from 'react';
import { ActionButton, FormDialog, Tabs } from '@/components/kertai/actions';
import {
    CheckField,
    Field,
    MemberSelect,
    MoneyInput,
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
    formatKes,
    titleCase,
    today,
    toShillings,
} from '@/lib/format';
import type { Option } from '@/types/kertai';

type Props = {
    project: {
        id: number;
        name: string;
        category: string;
        description: string | null;
        status: string;
        starts_on: string;
        ends_on: string | null;
        share_basis: string;
        coordinator_member_id: number | null;
        coordinator: string | null;
        balance: number;
    };
    pnl: {
        contributions: number;
        income: number;
        expenses: number;
        profit: number;
        distributed: number;
        undistributed: number;
    };
    participants: {
        id: number;
        member_id: number;
        name: string;
        member_number: string;
        contributed: number;
        joined_on: string;
    }[];
    entries: {
        id: number;
        kind: string;
        amount: number;
        entry_on: string;
        description: string;
        member: string | null;
    }[];
    items: {
        id: number;
        name: string;
        unit: string;
        unit_price: number;
        quantity: number;
        movements: {
            id: number;
            kind: string;
            quantity: number;
            note: string | null;
            at: string;
        }[];
    }[];
    sales: {
        id: number;
        item: string;
        unit: string;
        quantity: number;
        unit_price: number;
        total: number;
        paid: number;
        balance: number;
        buyer_name: string;
        buyer_phone: string | null;
        status: string;
        sold_on: string;
    }[];
    expenses: {
        id: number;
        number: number;
        amount: number;
        description: string;
        status: string;
    }[];
    sharePreview: {
        member_id: number;
        name: string;
        contributed: number;
        amount: number;
    }[];
    canManage: boolean;
    canRecordMoney: boolean;
    members: Option[];
    categories: Record<string, string>;
};

type Tab = 'overview' | 'participants' | 'stock' | 'book';

export default function ProjectShow(props: Props) {
    const {
        project,
        pnl,
        participants,
        entries,
        items,
        sales,
        expenses,
        sharePreview,
        canManage,
        canRecordMoney,
        members,
        categories,
    } = props;
    const can = useCan();
    const [tab, setTab] = useState<Tab>('overview');

    return (
        <>
            <Head title={project.name} />
            <Page>
                <PageHeader
                    title={project.name}
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            {categories[project.category] ?? project.category} ·
                            since {formatDate(project.starts_on)}
                            {project.coordinator
                                ? ` · coordinated by ${project.coordinator}`
                                : ''}
                            <StatusBadge status={project.status} />
                        </span>
                    }
                    actions={
                        <>
                            {canRecordMoney && (
                                <FormDialog
                                    title="Money into the project"
                                    action={`/projects/${project.id}/money`}
                                    submitLabel="Record"
                                    initial={{
                                        kind: 'contribution',
                                        member_id: '',
                                        payer_name: '',
                                        amount: '',
                                        channel: 'cash',
                                        phone: '',
                                        reference: '',
                                        description: '',
                                        received_on: today(),
                                    }}
                                    trigger={
                                        <Button>
                                            <Banknote /> Money in
                                        </Button>
                                    }
                                >
                                    {(form) => (
                                        <>
                                            <Field
                                                label="Type"
                                                htmlFor="pm-kind"
                                            >
                                                <Select
                                                    id="pm-kind"
                                                    value={form.data.kind}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'kind',
                                                            e.target.value,
                                                        )
                                                    }
                                                    options={[
                                                        {
                                                            value: 'contribution',
                                                            label: "A member's contribution (counts for profit share)",
                                                        },
                                                        {
                                                            value: 'income',
                                                            label: 'Income, e.g. harvest sold in bulk',
                                                        },
                                                    ]}
                                                />
                                            </Field>
                                            {form.data.kind ===
                                            'contribution' ? (
                                                <Field
                                                    label="Member"
                                                    htmlFor="pm-member"
                                                    error={
                                                        form.errors.member_id
                                                    }
                                                >
                                                    <MemberSelect
                                                        id="pm-member"
                                                        members={members}
                                                        value={
                                                            form.data.member_id
                                                        }
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'member_id',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </Field>
                                            ) : (
                                                <>
                                                    <Field
                                                        label="From"
                                                        htmlFor="pm-payer"
                                                    >
                                                        <Input
                                                            id="pm-payer"
                                                            value={
                                                                form.data
                                                                    .payer_name
                                                            }
                                                            onChange={(e) =>
                                                                form.setData(
                                                                    'payer_name',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    </Field>
                                                    <Field
                                                        label="Description"
                                                        htmlFor="pm-desc"
                                                    >
                                                        <Input
                                                            id="pm-desc"
                                                            value={
                                                                form.data
                                                                    .description
                                                            }
                                                            onChange={(e) =>
                                                                form.setData(
                                                                    'description',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    </Field>
                                                </>
                                            )}
                                            <div className="grid grid-cols-2 gap-3">
                                                <Field
                                                    label="Amount"
                                                    htmlFor="pm-amount"
                                                    error={form.errors.amount}
                                                >
                                                    <MoneyInput
                                                        id="pm-amount"
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
                                                    label="How"
                                                    htmlFor="pm-channel"
                                                    error={form.errors.channel}
                                                >
                                                    <Select
                                                        id="pm-channel"
                                                        value={
                                                            form.data.channel
                                                        }
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'channel',
                                                                e.target.value,
                                                            )
                                                        }
                                                        options={[
                                                            {
                                                                value: 'cash',
                                                                label: 'Cash',
                                                            },
                                                            {
                                                                value: 'paybill',
                                                                label: 'Paybill',
                                                            },
                                                            {
                                                                value: 'manual',
                                                                label: 'Bank',
                                                            },
                                                            {
                                                                value: 'mpesa',
                                                                label: 'Send M-Pesa prompt',
                                                            },
                                                        ]}
                                                    />
                                                </Field>
                                            </div>
                                            {form.data.channel === 'mpesa' && (
                                                <Field
                                                    label="Phone to prompt"
                                                    htmlFor="pm-phone"
                                                    error={form.errors.phone}
                                                >
                                                    <Input
                                                        id="pm-phone"
                                                        value={form.data.phone}
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'phone',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </Field>
                                            )}
                                            {form.data.channel ===
                                                'paybill' && (
                                                <Field
                                                    label="M-Pesa receipt"
                                                    htmlFor="pm-ref"
                                                    error={
                                                        form.errors.reference
                                                    }
                                                >
                                                    <Input
                                                        id="pm-ref"
                                                        value={
                                                            form.data.reference
                                                        }
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'reference',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </Field>
                                            )}
                                        </>
                                    )}
                                </FormDialog>
                            )}
                            {can('payouts.request') && (
                                <FormDialog
                                    title="Project expense"
                                    description="Raised as a payout from the project fund; it needs two approvals before money leaves."
                                    action={`/projects/${project.id}/expenses`}
                                    submitLabel="Request"
                                    initial={{
                                        amount: '',
                                        description: '',
                                        payee_name: '',
                                        payee_phone: '',
                                    }}
                                    trigger={
                                        <Button variant="outline">
                                            <Receipt /> Expense
                                        </Button>
                                    }
                                >
                                    {(form) => (
                                        <>
                                            <Field
                                                label="What for"
                                                htmlFor="pe-desc"
                                                error={form.errors.description}
                                            >
                                                <Input
                                                    id="pe-desc"
                                                    placeholder="Polythene sleeves, 2,000 pcs"
                                                    value={
                                                        form.data.description
                                                    }
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'description',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                            <Field
                                                label="Amount"
                                                htmlFor="pe-amount"
                                                error={form.errors.amount}
                                            >
                                                <MoneyInput
                                                    id="pe-amount"
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
                                                label="Pay to"
                                                htmlFor="pe-payee"
                                                error={form.errors.payee_name}
                                            >
                                                <Input
                                                    id="pe-payee"
                                                    value={form.data.payee_name}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'payee_name',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                            <Field
                                                label="Their phone"
                                                htmlFor="pe-phone"
                                            >
                                                <Input
                                                    id="pe-phone"
                                                    value={
                                                        form.data.payee_phone
                                                    }
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'payee_phone',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                        </>
                                    )}
                                </FormDialog>
                            )}
                            {canManage && (
                                <FormDialog
                                    title="Edit project"
                                    action={`/projects/${project.id}`}
                                    method="put"
                                    initial={{
                                        name: project.name,
                                        category: project.category,
                                        description: project.description ?? '',
                                        starts_on: project.starts_on,
                                        ends_on: project.ends_on ?? '',
                                        share_basis: project.share_basis,
                                        coordinator_member_id:
                                            project.coordinator_member_id
                                                ? String(
                                                      project.coordinator_member_id,
                                                  )
                                                : '',
                                        status: project.status,
                                    }}
                                    trigger={
                                        <Button variant="outline">
                                            <Pencil /> Edit
                                        </Button>
                                    }
                                >
                                    {(form) => (
                                        <>
                                            <Field
                                                label="Name"
                                                htmlFor="ep-name"
                                                error={form.errors.name}
                                            >
                                                <Input
                                                    id="ep-name"
                                                    value={form.data.name}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'name',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                            <div className="grid grid-cols-2 gap-3">
                                                <Field
                                                    label="Category"
                                                    htmlFor="ep-cat"
                                                >
                                                    <Select
                                                        id="ep-cat"
                                                        value={
                                                            form.data.category
                                                        }
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'category',
                                                                e.target.value,
                                                            )
                                                        }
                                                        options={Object.entries(
                                                            categories,
                                                        ).map(
                                                            ([
                                                                value,
                                                                label,
                                                            ]) => ({
                                                                value,
                                                                label,
                                                            }),
                                                        )}
                                                    />
                                                </Field>
                                                <Field
                                                    label="Status"
                                                    htmlFor="ep-status"
                                                >
                                                    <Select
                                                        id="ep-status"
                                                        value={form.data.status}
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'status',
                                                                e.target.value,
                                                            )
                                                        }
                                                        options={[
                                                            'planning',
                                                            'active',
                                                            'closed',
                                                        ].map((s) => ({
                                                            value: s,
                                                            label: titleCase(s),
                                                        }))}
                                                    />
                                                </Field>
                                                <Field
                                                    label="Starts"
                                                    htmlFor="ep-start"
                                                >
                                                    <Input
                                                        id="ep-start"
                                                        type="date"
                                                        value={
                                                            form.data.starts_on
                                                        }
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'starts_on',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </Field>
                                                <Field
                                                    label="Ends"
                                                    htmlFor="ep-end"
                                                    error={form.errors.ends_on}
                                                >
                                                    <Input
                                                        id="ep-end"
                                                        type="date"
                                                        value={
                                                            form.data.ends_on
                                                        }
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'ends_on',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </Field>
                                            </div>
                                            <Field
                                                label="Profit shared"
                                                htmlFor="ep-basis"
                                            >
                                                <Select
                                                    id="ep-basis"
                                                    value={
                                                        form.data.share_basis
                                                    }
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'share_basis',
                                                            e.target.value,
                                                        )
                                                    }
                                                    options={[
                                                        {
                                                            value: 'contribution',
                                                            label: 'By contribution',
                                                        },
                                                        {
                                                            value: 'equal',
                                                            label: 'Equally',
                                                        },
                                                    ]}
                                                />
                                            </Field>
                                            <Field
                                                label="Coordinator"
                                                htmlFor="ep-coord"
                                            >
                                                <MemberSelect
                                                    id="ep-coord"
                                                    members={members}
                                                    placeholder="None"
                                                    value={
                                                        form.data
                                                            .coordinator_member_id
                                                    }
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'coordinator_member_id',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                            <Field
                                                label="Description"
                                                htmlFor="ep-desc"
                                            >
                                                <Textarea
                                                    id="ep-desc"
                                                    value={
                                                        form.data.description
                                                    }
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'description',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                        </>
                                    )}
                                </FormDialog>
                            )}
                        </>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <StatCard
                        label="Members put in"
                        value={formatKes(pnl.contributions)}
                    />
                    <StatCard
                        label="Income and sales"
                        value={formatKes(pnl.income)}
                        tone="good"
                    />
                    <StatCard
                        label="Expenses"
                        value={formatKes(pnl.expenses)}
                    />
                    <StatCard
                        label="Profit"
                        value={formatKes(pnl.profit)}
                        tone={pnl.profit >= 0 ? 'good' : 'bad'}
                        hint={`${formatKes(pnl.distributed)} already shared`}
                    />
                    <StatCard
                        label="Fund balance"
                        value={formatKes(project.balance)}
                    />
                </div>

                <Tabs
                    value={tab}
                    onChange={setTab}
                    tabs={[
                        { value: 'overview', label: 'Overview' },
                        {
                            value: 'participants',
                            label: 'Participants',
                            count: participants.length,
                        },
                        {
                            value: 'stock',
                            label: 'Stock and sales',
                            count: items.length,
                        },
                        {
                            value: 'book',
                            label: 'Project book',
                            count: entries.length,
                        },
                    ]}
                />

                {tab === 'overview' && (
                    <div className="grid gap-6 lg:grid-cols-2">
                        <Section title="About">
                            <p className="text-sm whitespace-pre-line">
                                {project.description ?? 'No description yet.'}
                            </p>
                            <p className="mt-3 text-sm text-muted-foreground">
                                Profit is shared{' '}
                                {project.share_basis === 'equal'
                                    ? 'equally between participants'
                                    : 'in proportion to what each participant put in'}
                                .
                            </p>
                        </Section>
                        <Section
                            title="Share profit"
                            description="Credits each participant's savings. The money stays in the group until the share-out."
                            actions={
                                canManage &&
                                pnl.undistributed > 0 && (
                                    <FormDialog
                                        title="Share profit"
                                        description={`Up to ${formatKes(Math.min(pnl.undistributed, project.balance))} can be shared now.`}
                                        action={`/projects/${project.id}/distribute`}
                                        submitLabel="Share"
                                        initial={{
                                            amount: toShillings(
                                                Math.min(
                                                    pnl.undistributed,
                                                    project.balance,
                                                ),
                                            ),
                                        }}
                                        trigger={
                                            <Button size="sm">
                                                <Wallet /> Share profit
                                            </Button>
                                        }
                                    >
                                        {(form) => (
                                            <Field
                                                label="Amount to share"
                                                htmlFor="ds-amount"
                                                error={form.errors.amount}
                                            >
                                                <MoneyInput
                                                    id="ds-amount"
                                                    value={form.data.amount}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'amount',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                        )}
                                    </FormDialog>
                                )
                            }
                        >
                            {sharePreview.length === 0 ? (
                                <EmptyState title="No profit waiting to be shared" />
                            ) : (
                                <Table>
                                    <THead>
                                        <TR>
                                            <TH>Member</TH>
                                            <TH className="text-right">
                                                Put in
                                            </TH>
                                            <TH className="text-right">
                                                Would get
                                            </TH>
                                        </TR>
                                    </THead>
                                    <TBody>
                                        {sharePreview.map((s) => (
                                            <TR key={s.member_id}>
                                                <TD>{s.name}</TD>
                                                <Num>
                                                    {formatKes(s.contributed)}
                                                </Num>
                                                <Num>{formatKes(s.amount)}</Num>
                                            </TR>
                                        ))}
                                    </TBody>
                                </Table>
                            )}
                        </Section>
                        <Section title="Expenses requested">
                            {expenses.length === 0 ? (
                                <EmptyState title="No expenses yet" />
                            ) : (
                                <Table>
                                    <TBody>
                                        {expenses.map((e) => (
                                            <TR key={e.id}>
                                                <TD>
                                                    <Link
                                                        href={`/payouts/${e.id}`}
                                                        className="hover:underline"
                                                    >
                                                        #{e.number}{' '}
                                                        {e.description}
                                                    </Link>
                                                </TD>
                                                <TD>
                                                    <StatusBadge
                                                        status={e.status}
                                                    />
                                                </TD>
                                                <Num>{formatKes(e.amount)}</Num>
                                            </TR>
                                        ))}
                                    </TBody>
                                </Table>
                            )}
                        </Section>
                    </div>
                )}

                {tab === 'participants' && (
                    <Section
                        title="Participants"
                        actions={
                            canManage && (
                                <FormDialog
                                    title="Add participants"
                                    action={`/projects/${project.id}/participants`}
                                    submitLabel="Add"
                                    wide
                                    initial={{ member_ids: [] as number[] }}
                                    trigger={
                                        <Button size="sm" variant="outline">
                                            <UserPlus /> Add
                                        </Button>
                                    }
                                >
                                    {(form) => (
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            {members
                                                .filter(
                                                    (m) =>
                                                        !participants.some(
                                                            (p) =>
                                                                p.member_id ===
                                                                m.id,
                                                        ),
                                                )
                                                .map((m) => (
                                                    <CheckField
                                                        key={m.id}
                                                        id={`pp-${m.id}`}
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
                                </FormDialog>
                            )
                        }
                    >
                        {participants.length === 0 ? (
                            <EmptyState title="No participants yet" />
                        ) : (
                            <Table>
                                <THead>
                                    <TR>
                                        <TH>Member</TH>
                                        <TH>Joined</TH>
                                        <TH className="text-right">Put in</TH>
                                        <TH />
                                    </TR>
                                </THead>
                                <TBody>
                                    {participants.map((p) => (
                                        <TR key={p.id}>
                                            <TD>
                                                {p.name}{' '}
                                                <span className="text-xs text-muted-foreground">
                                                    {p.member_number}
                                                </span>
                                            </TD>
                                            <TD>{formatDate(p.joined_on)}</TD>
                                            <Num>
                                                {formatKes(p.contributed)}
                                            </Num>
                                            <TD className="text-right">
                                                {canManage &&
                                                    p.contributed === 0 && (
                                                        <ActionButton
                                                            url={`/projects/${project.id}/participants/${p.id}`}
                                                            method="delete"
                                                            size="sm"
                                                            variant="ghost"
                                                            confirm={`Remove ${p.name}?`}
                                                            aria-label="Remove participant"
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
                )}

                {tab === 'stock' && (
                    <div className="grid gap-6">
                        <Section
                            title="Stock"
                            actions={
                                canManage && (
                                    <>
                                        <FormDialog
                                            title="New stock item"
                                            action={`/projects/${project.id}/items`}
                                            submitLabel="Add"
                                            initial={{
                                                name: '',
                                                unit: 'seedlings',
                                                unit_price: '',
                                                quantity: '',
                                            }}
                                            trigger={
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <PackagePlus /> New item
                                                </Button>
                                            }
                                        >
                                            {(form) => (
                                                <>
                                                    <Field
                                                        label="Item"
                                                        htmlFor="it-name"
                                                        error={form.errors.name}
                                                    >
                                                        <Input
                                                            id="it-name"
                                                            placeholder="Coffee seedling (Ruiru 11)"
                                                            value={
                                                                form.data.name
                                                            }
                                                            onChange={(e) =>
                                                                form.setData(
                                                                    'name',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    </Field>
                                                    <div className="grid grid-cols-2 gap-3">
                                                        <Field
                                                            label="Unit"
                                                            htmlFor="it-unit"
                                                            error={
                                                                form.errors.unit
                                                            }
                                                        >
                                                            <Input
                                                                id="it-unit"
                                                                value={
                                                                    form.data
                                                                        .unit
                                                                }
                                                                onChange={(e) =>
                                                                    form.setData(
                                                                        'unit',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                            />
                                                        </Field>
                                                        <Field
                                                            label="Selling price per unit"
                                                            htmlFor="it-price"
                                                            error={
                                                                form.errors
                                                                    .unit_price
                                                            }
                                                        >
                                                            <MoneyInput
                                                                id="it-price"
                                                                value={
                                                                    form.data
                                                                        .unit_price
                                                                }
                                                                onChange={(e) =>
                                                                    form.setData(
                                                                        'unit_price',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                            />
                                                        </Field>
                                                    </div>
                                                    <Field
                                                        label="Opening stock"
                                                        htmlFor="it-qty"
                                                        error={
                                                            form.errors.quantity
                                                        }
                                                    >
                                                        <Input
                                                            id="it-qty"
                                                            inputMode="numeric"
                                                            value={
                                                                form.data
                                                                    .quantity
                                                            }
                                                            onChange={(e) =>
                                                                form.setData(
                                                                    'quantity',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    </Field>
                                                </>
                                            )}
                                        </FormDialog>
                                        {items.length > 0 && (
                                            <FormDialog
                                                title="Record a sale"
                                                action={`/projects/${project.id}/sales`}
                                                submitLabel="Record sale"
                                                initial={{
                                                    project_item_id: String(
                                                        items[0].id,
                                                    ),
                                                    quantity: '',
                                                    unit_price: toShillings(
                                                        items[0].unit_price,
                                                    ),
                                                    buyer_name: '',
                                                    buyer_phone: '',
                                                    member_id: '',
                                                    sold_on: today(),
                                                    payment: 'cash',
                                                }}
                                                trigger={
                                                    <Button size="sm">
                                                        <ShoppingCart /> Sell
                                                    </Button>
                                                }
                                            >
                                                {(form) => (
                                                    <>
                                                        <Field
                                                            label="Item"
                                                            htmlFor="sl-item"
                                                            error={
                                                                form.errors
                                                                    .project_item_id
                                                            }
                                                        >
                                                            <Select
                                                                id="sl-item"
                                                                value={
                                                                    form.data
                                                                        .project_item_id
                                                                }
                                                                onChange={(
                                                                    e,
                                                                ) => {
                                                                    const item =
                                                                        items.find(
                                                                            (
                                                                                i,
                                                                            ) =>
                                                                                String(
                                                                                    i.id,
                                                                                ) ===
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                        );
                                                                    form.setData(
                                                                        (
                                                                            d,
                                                                        ) => ({
                                                                            ...d,
                                                                            project_item_id:
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                            unit_price:
                                                                                toShillings(
                                                                                    item?.unit_price ??
                                                                                        0,
                                                                                ),
                                                                        }),
                                                                    );
                                                                }}
                                                                options={items.map(
                                                                    (i) => ({
                                                                        value: i.id,
                                                                        label: `${i.name} (${i.quantity} in stock)`,
                                                                    }),
                                                                )}
                                                            />
                                                        </Field>
                                                        <div className="grid grid-cols-2 gap-3">
                                                            <Field
                                                                label="Quantity"
                                                                htmlFor="sl-qty"
                                                                error={
                                                                    form.errors
                                                                        .quantity
                                                                }
                                                            >
                                                                <Input
                                                                    id="sl-qty"
                                                                    inputMode="numeric"
                                                                    value={
                                                                        form
                                                                            .data
                                                                            .quantity
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        form.setData(
                                                                            'quantity',
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                />
                                                            </Field>
                                                            <Field
                                                                label="Price per unit"
                                                                htmlFor="sl-price"
                                                                error={
                                                                    form.errors
                                                                        .unit_price
                                                                }
                                                            >
                                                                <MoneyInput
                                                                    id="sl-price"
                                                                    value={
                                                                        form
                                                                            .data
                                                                            .unit_price
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        form.setData(
                                                                            'unit_price',
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                />
                                                            </Field>
                                                        </div>
                                                        <Field
                                                            label="Buyer"
                                                            htmlFor="sl-buyer"
                                                            error={
                                                                form.errors
                                                                    .buyer_name
                                                            }
                                                        >
                                                            <Input
                                                                id="sl-buyer"
                                                                value={
                                                                    form.data
                                                                        .buyer_name
                                                                }
                                                                onChange={(e) =>
                                                                    form.setData(
                                                                        'buyer_name',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                            />
                                                        </Field>
                                                        <div className="grid grid-cols-2 gap-3">
                                                            <Field
                                                                label="Buyer phone"
                                                                htmlFor="sl-phone"
                                                                error={
                                                                    form.errors
                                                                        .buyer_phone
                                                                }
                                                            >
                                                                <Input
                                                                    id="sl-phone"
                                                                    value={
                                                                        form
                                                                            .data
                                                                            .buyer_phone
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        form.setData(
                                                                            'buyer_phone',
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                />
                                                            </Field>
                                                            <Field
                                                                label="Date"
                                                                htmlFor="sl-date"
                                                                error={
                                                                    form.errors
                                                                        .sold_on
                                                                }
                                                            >
                                                                <Input
                                                                    id="sl-date"
                                                                    type="date"
                                                                    value={
                                                                        form
                                                                            .data
                                                                            .sold_on
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        form.setData(
                                                                            'sold_on',
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                />
                                                            </Field>
                                                        </div>
                                                        <Field
                                                            label="Buyer is a member?"
                                                            htmlFor="sl-member"
                                                        >
                                                            <MemberSelect
                                                                id="sl-member"
                                                                members={
                                                                    members
                                                                }
                                                                placeholder="No"
                                                                value={
                                                                    form.data
                                                                        .member_id
                                                                }
                                                                onChange={(e) =>
                                                                    form.setData(
                                                                        'member_id',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                            />
                                                        </Field>
                                                        <Field
                                                            label="Payment"
                                                            htmlFor="sl-pay"
                                                            error={
                                                                form.errors
                                                                    .payment
                                                            }
                                                        >
                                                            <Select
                                                                id="sl-pay"
                                                                value={
                                                                    form.data
                                                                        .payment
                                                                }
                                                                onChange={(e) =>
                                                                    form.setData(
                                                                        'payment',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                                options={[
                                                                    {
                                                                        value: 'cash',
                                                                        label: 'Paid in cash now',
                                                                    },
                                                                    {
                                                                        value: 'mpesa',
                                                                        label: 'Send M-Pesa prompt to buyer',
                                                                    },
                                                                    {
                                                                        value: 'none',
                                                                        label: 'On credit, pay later',
                                                                    },
                                                                ]}
                                                            />
                                                        </Field>
                                                    </>
                                                )}
                                            </FormDialog>
                                        )}
                                    </>
                                )
                            }
                        >
                            {items.length === 0 ? (
                                <EmptyState title="No stock items" />
                            ) : (
                                <Table>
                                    <THead>
                                        <TR>
                                            <TH>Item</TH>
                                            <TH className="text-right">
                                                In stock
                                            </TH>
                                            <TH className="text-right">
                                                Price
                                            </TH>
                                            <TH>Recent movements</TH>
                                            <TH />
                                        </TR>
                                    </THead>
                                    <TBody>
                                        {items.map((i) => (
                                            <TR key={i.id}>
                                                <TD className="font-medium">
                                                    {i.name}
                                                </TD>
                                                <Num>
                                                    {i.quantity.toLocaleString()}{' '}
                                                    {i.unit}
                                                </Num>
                                                <Num>
                                                    {formatKes(i.unit_price)}
                                                </Num>
                                                <TD className="text-xs text-muted-foreground">
                                                    {i.movements
                                                        .slice(0, 3)
                                                        .map((m) => (
                                                            <div key={m.id}>
                                                                {formatDate(
                                                                    m.at,
                                                                )}
                                                                :{' '}
                                                                {m.quantity > 0
                                                                    ? '+'
                                                                    : ''}
                                                                {m.quantity} (
                                                                {m.kind})
                                                                {m.note
                                                                    ? ` ${m.note}`
                                                                    : ''}
                                                            </div>
                                                        ))}
                                                </TD>
                                                <TD className="text-right">
                                                    {canManage && (
                                                        <FormDialog
                                                            title={`Stock: ${i.name}`}
                                                            description="Add new stock (germinated, bought) or take out losses."
                                                            action={`/projects/${project.id}/items/${i.id}/stock`}
                                                            submitLabel="Save"
                                                            initial={{
                                                                kind: 'in',
                                                                quantity: '',
                                                                unit_cost: '',
                                                                note: '',
                                                            }}
                                                            trigger={
                                                                <Button
                                                                    size="sm"
                                                                    variant="ghost"
                                                                >
                                                                    Adjust
                                                                </Button>
                                                            }
                                                        >
                                                            {(form) => (
                                                                <>
                                                                    <Field
                                                                        label="Movement"
                                                                        htmlFor={`st-k-${i.id}`}
                                                                    >
                                                                        <Select
                                                                            id={`st-k-${i.id}`}
                                                                            value={
                                                                                form
                                                                                    .data
                                                                                    .kind
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                form.setData(
                                                                                    'kind',
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                            options={[
                                                                                {
                                                                                    value: 'in',
                                                                                    label: 'Stock in',
                                                                                },
                                                                                {
                                                                                    value: 'out',
                                                                                    label: 'Stock out (losses, given away)',
                                                                                },
                                                                                {
                                                                                    value: 'adjust',
                                                                                    label: 'Count correction (+ or -)',
                                                                                },
                                                                            ]}
                                                                        />
                                                                    </Field>
                                                                    <Field
                                                                        label="Quantity"
                                                                        htmlFor={`st-q-${i.id}`}
                                                                        error={
                                                                            form
                                                                                .errors
                                                                                .quantity
                                                                        }
                                                                    >
                                                                        <Input
                                                                            id={`st-q-${i.id}`}
                                                                            inputMode="numeric"
                                                                            value={
                                                                                form
                                                                                    .data
                                                                                    .quantity
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                form.setData(
                                                                                    'quantity',
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                        />
                                                                    </Field>
                                                                    <Field
                                                                        label="Note"
                                                                        htmlFor={`st-n-${i.id}`}
                                                                    >
                                                                        <Input
                                                                            id={`st-n-${i.id}`}
                                                                            value={
                                                                                form
                                                                                    .data
                                                                                    .note
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                form.setData(
                                                                                    'note',
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                        />
                                                                    </Field>
                                                                </>
                                                            )}
                                                        </FormDialog>
                                                    )}
                                                </TD>
                                            </TR>
                                        ))}
                                    </TBody>
                                </Table>
                            )}
                        </Section>

                        <Section title="Sales">
                            {sales.length === 0 ? (
                                <EmptyState title="No sales yet" />
                            ) : (
                                <Table>
                                    <THead>
                                        <TR>
                                            <TH>Date</TH>
                                            <TH>Buyer</TH>
                                            <TH>Item</TH>
                                            <TH className="text-right">
                                                Total
                                            </TH>
                                            <TH className="text-right">Owed</TH>
                                            <TH>Status</TH>
                                            <TH />
                                        </TR>
                                    </THead>
                                    <TBody>
                                        {sales.map((s) => (
                                            <TR key={s.id}>
                                                <TD>{formatDate(s.sold_on)}</TD>
                                                <TD>
                                                    {s.buyer_name}
                                                    <div className="text-xs text-muted-foreground">
                                                        {s.buyer_phone}
                                                    </div>
                                                </TD>
                                                <TD>
                                                    {s.quantity} {s.unit}{' '}
                                                    {s.item}
                                                </TD>
                                                <Num>{formatKes(s.total)}</Num>
                                                <Num>
                                                    {formatKes(s.balance)}
                                                </Num>
                                                <TD>
                                                    <StatusBadge
                                                        status={s.status}
                                                    />
                                                </TD>
                                                <TD className="text-right">
                                                    {canManage &&
                                                        s.balance > 0 && (
                                                            <FormDialog
                                                                title={`Payment from ${s.buyer_name}`}
                                                                action={`/projects/${project.id}/sales/${s.id}/payments`}
                                                                submitLabel="Record"
                                                                initial={{
                                                                    amount: toShillings(
                                                                        s.balance,
                                                                    ),
                                                                    channel:
                                                                        'cash',
                                                                    reference:
                                                                        '',
                                                                }}
                                                                trigger={
                                                                    <Button
                                                                        size="sm"
                                                                        variant="ghost"
                                                                    >
                                                                        Record
                                                                        payment
                                                                    </Button>
                                                                }
                                                            >
                                                                {(form) => (
                                                                    <>
                                                                        <Field
                                                                            label="Amount"
                                                                            htmlFor={`sp-a-${s.id}`}
                                                                            error={
                                                                                form
                                                                                    .errors
                                                                                    .amount
                                                                            }
                                                                        >
                                                                            <MoneyInput
                                                                                id={`sp-a-${s.id}`}
                                                                                value={
                                                                                    form
                                                                                        .data
                                                                                        .amount
                                                                                }
                                                                                onChange={(
                                                                                    e,
                                                                                ) =>
                                                                                    form.setData(
                                                                                        'amount',
                                                                                        e
                                                                                            .target
                                                                                            .value,
                                                                                    )
                                                                                }
                                                                            />
                                                                        </Field>
                                                                        <Field
                                                                            label="How"
                                                                            htmlFor={`sp-c-${s.id}`}
                                                                        >
                                                                            <Select
                                                                                id={`sp-c-${s.id}`}
                                                                                value={
                                                                                    form
                                                                                        .data
                                                                                        .channel
                                                                                }
                                                                                onChange={(
                                                                                    e,
                                                                                ) =>
                                                                                    form.setData(
                                                                                        'channel',
                                                                                        e
                                                                                            .target
                                                                                            .value,
                                                                                    )
                                                                                }
                                                                                options={[
                                                                                    {
                                                                                        value: 'cash',
                                                                                        label: 'Cash',
                                                                                    },
                                                                                    {
                                                                                        value: 'paybill',
                                                                                        label: 'Paybill',
                                                                                    },
                                                                                    {
                                                                                        value: 'manual',
                                                                                        label: 'Bank',
                                                                                    },
                                                                                ]}
                                                                            />
                                                                        </Field>
                                                                        {form
                                                                            .data
                                                                            .channel ===
                                                                            'paybill' && (
                                                                            <Field
                                                                                label="M-Pesa receipt"
                                                                                htmlFor={`sp-r-${s.id}`}
                                                                                error={
                                                                                    form
                                                                                        .errors
                                                                                        .reference
                                                                                }
                                                                            >
                                                                                <Input
                                                                                    id={`sp-r-${s.id}`}
                                                                                    value={
                                                                                        form
                                                                                            .data
                                                                                            .reference
                                                                                    }
                                                                                    onChange={(
                                                                                        e,
                                                                                    ) =>
                                                                                        form.setData(
                                                                                            'reference',
                                                                                            e
                                                                                                .target
                                                                                                .value,
                                                                                        )
                                                                                    }
                                                                                />
                                                                            </Field>
                                                                        )}
                                                                    </>
                                                                )}
                                                            </FormDialog>
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

                {tab === 'book' && (
                    <Section title="Project book">
                        {entries.length === 0 ? (
                            <EmptyState title="Nothing recorded yet" />
                        ) : (
                            <Table>
                                <THead>
                                    <TR>
                                        <TH>Date</TH>
                                        <TH>Type</TH>
                                        <TH>Description</TH>
                                        <TH className="text-right">Amount</TH>
                                    </TR>
                                </THead>
                                <TBody>
                                    {entries.map((e) => (
                                        <TR key={e.id}>
                                            <TD>{formatDate(e.entry_on)}</TD>
                                            <TD>
                                                <StatusBadge
                                                    status={
                                                        e.kind === 'expense'
                                                            ? 'pending'
                                                            : e.kind ===
                                                                'distribution'
                                                              ? 'cancelled'
                                                              : 'active'
                                                    }
                                                    label={titleCase(e.kind)}
                                                />
                                            </TD>
                                            <TD>{e.description}</TD>
                                            <Num
                                                className={
                                                    e.kind === 'expense' ||
                                                    e.kind === 'distribution'
                                                        ? 'text-red-700 dark:text-red-400'
                                                        : ''
                                                }
                                            >
                                                {e.kind === 'expense' ||
                                                e.kind === 'distribution'
                                                    ? '-'
                                                    : ''}
                                                {formatKes(e.amount)}
                                            </Num>
                                        </TR>
                                    ))}
                                </TBody>
                            </Table>
                        )}
                    </Section>
                )}
            </Page>
        </>
    );
}

ProjectShow.layout = {
    breadcrumbs: [{ title: 'Projects', href: '/projects' }],
};
