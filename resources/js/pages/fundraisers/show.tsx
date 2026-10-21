import { Head, Link } from '@inertiajs/react';
import {
    Copy,
    Download,
    ExternalLink,
    HandHeart,
    Pencil,
    Trash2,
} from 'lucide-react';
import { toast } from 'sonner';
import { ActionButton, FormDialog } from '@/components/kertai/actions';
import { Field, MemberSelect, MoneyInput } from '@/components/kertai/fields';
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
import { useCan } from '@/hooks/use-can';
import {
    formatDate,
    formatDateTime,
    formatKes,
    percent,
    titleCase,
} from '@/lib/format';
import type { Option } from '@/types/kertai';

type Props = {
    fundraiser: {
        id: number;
        title: string;
        summary: string | null;
        description: string | null;
        beneficiary: string | null;
        target: number;
        raised: number;
        balance: number;
        starts_on: string;
        ends_on: string | null;
        status: string;
        public_url: string;
    };
    stats: {
        contributions: number;
        by_channel: Record<string, number>;
        members: number;
        non_members: number;
        pledged: number;
    };
    payments: {
        id: number;
        name: string;
        phone: string | null;
        amount: number;
        channel: string;
        status: string;
        receipt: string | null;
        show_name: boolean;
        date: string;
    }[];
    pledges: {
        id: number;
        name: string;
        phone: string | null;
        amount: number;
        paid: number;
        note: string | null;
    }[];
    canManage: boolean;
    members: Option[];
};

export default function FundraiserShow({
    fundraiser,
    stats,
    payments,
    pledges,
    canManage,
    members,
}: Props) {
    const can = useCan();
    const share = `${fundraiser.title}: ${fundraiser.summary ?? ''} Give here: ${fundraiser.public_url}`;

    return (
        <>
            <Head title={fundraiser.title} />
            <Page>
                <PageHeader
                    title={fundraiser.title}
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            {formatDate(fundraiser.starts_on)}
                            {fundraiser.ends_on
                                ? ` – ${formatDate(fundraiser.ends_on)}`
                                : ''}
                            <StatusBadge status={fundraiser.status} />
                        </span>
                    }
                    actions={
                        <>
                            {fundraiser.status !== 'draft' && (
                                <>
                                    <Button
                                        variant="outline"
                                        onClick={() => {
                                            // Phones open their share sheet
                                            // (WhatsApp, SMS); computers copy.
                                            if (navigator.share) {
                                                void navigator
                                                    .share({
                                                        title: fundraiser.title,
                                                        text: share,
                                                    })
                                                    .catch(() => undefined);

                                                return;
                                            }

                                            void navigator.clipboard
                                                ?.writeText(share)
                                                .then(() =>
                                                    toast.success(
                                                        'Link and message copied. Paste it in WhatsApp.',
                                                    ),
                                                )
                                                .catch(() =>
                                                    toast.error(
                                                        `Copy this link: ${fundraiser.public_url}`,
                                                    ),
                                                );
                                        }}
                                    >
                                        <Copy /> Share
                                    </Button>
                                    <Button asChild variant="outline">
                                        <a
                                            href={fundraiser.public_url}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            <ExternalLink /> Public page
                                        </a>
                                    </Button>
                                </>
                            )}
                            {canManage && (
                                <>
                                    {fundraiser.status === 'draft' && (
                                        <ActionButton
                                            url={`/fundraisers/${fundraiser.id}/status`}
                                            data={{ status: 'active' }}
                                            confirm="Publish? The public page will start taking payments."
                                        >
                                            Publish
                                        </ActionButton>
                                    )}
                                    {fundraiser.status === 'active' && (
                                        <ActionButton
                                            url={`/fundraisers/${fundraiser.id}/status`}
                                            data={{ status: 'closed' }}
                                            variant="outline"
                                            confirm="Close the fundraiser? The public page stops taking payments."
                                        >
                                            Close
                                        </ActionButton>
                                    )}
                                    {fundraiser.status === 'closed' && (
                                        <ActionButton
                                            url={`/fundraisers/${fundraiser.id}/status`}
                                            data={{ status: 'active' }}
                                            variant="outline"
                                        >
                                            Reopen
                                        </ActionButton>
                                    )}
                                    <Button asChild variant="outline">
                                        <Link
                                            href={`/fundraisers/${fundraiser.id}/edit`}
                                        >
                                            <Pencil /> Edit
                                        </Link>
                                    </Button>
                                </>
                            )}
                            {can('finance.view') && (
                                <Button asChild variant="outline">
                                    <a
                                        href={`/fundraisers/${fundraiser.id}/export`}
                                    >
                                        <Download /> CSV
                                    </a>
                                </Button>
                            )}
                        </>
                    }
                />

                <Section>
                    <div className="space-y-2">
                        <Progress
                            value={percent(
                                fundraiser.raised,
                                fundraiser.target,
                            )}
                        />
                        <div className="flex flex-wrap justify-between gap-2 text-sm">
                            <span>
                                <span className="text-lg font-semibold">
                                    {formatKes(fundraiser.raised)}
                                </span>{' '}
                                raised of {formatKes(fundraiser.target)} (
                                {percent(fundraiser.raised, fundraiser.target)}
                                %)
                            </span>
                            <span className="text-muted-foreground">
                                {stats.contributions} contributions
                            </span>
                        </div>
                    </div>
                    {fundraiser.description && (
                        <p className="mt-4 text-sm whitespace-pre-line">
                            {fundraiser.description}
                        </p>
                    )}
                </Section>

                {can('finance.view') && (
                    <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                        <StatCard
                            label="From members"
                            value={formatKes(stats.members)}
                        />
                        <StatCard
                            label="From well-wishers"
                            value={formatKes(stats.non_members)}
                        />
                        <StatCard
                            label="Pledged"
                            value={formatKes(stats.pledged)}
                        />
                        <StatCard
                            label="Fund balance"
                            value={formatKes(fundraiser.balance)}
                            hint="After any disbursements"
                        />
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-2">
                    <Section
                        title="Contributions"
                        description={Object.entries(stats.by_channel)
                            .map(([c, v]) => `${titleCase(c)} ${formatKes(v)}`)
                            .join(' · ')}
                    >
                        {payments.length === 0 ? (
                            <EmptyState title="No contributions yet" />
                        ) : (
                            <Table>
                                <THead>
                                    <TR>
                                        <TH>When</TH>
                                        <TH>From</TH>
                                        <TH>Status</TH>
                                        <TH className="text-right">Amount</TH>
                                    </TR>
                                </THead>
                                <TBody>
                                    {payments.map((p) => (
                                        <TR key={p.id}>
                                            <TD className="whitespace-nowrap">
                                                {formatDateTime(p.date)}
                                            </TD>
                                            <TD>
                                                {p.name}
                                                <div className="text-xs text-muted-foreground">
                                                    {p.phone} ·{' '}
                                                    {titleCase(p.channel)}{' '}
                                                    {p.show_name
                                                        ? ''
                                                        : '· anonymous on page'}
                                                </div>
                                            </TD>
                                            <TD>
                                                <StatusBadge
                                                    status={p.status}
                                                />
                                            </TD>
                                            <Num>{formatKes(p.amount)}</Num>
                                        </TR>
                                    ))}
                                </TBody>
                            </Table>
                        )}
                    </Section>

                    <Section
                        title="Pledges"
                        actions={
                            canManage && (
                                <FormDialog
                                    title="Record a pledge"
                                    action={`/fundraisers/${fundraiser.id}/pledges`}
                                    submitLabel="Save pledge"
                                    initial={{
                                        member_id: '',
                                        name: '',
                                        phone: '',
                                        amount: '',
                                        note: '',
                                    }}
                                    trigger={
                                        <Button size="sm" variant="outline">
                                            <HandHeart /> Add pledge
                                        </Button>
                                    }
                                >
                                    {(form) => (
                                        <>
                                            <Field
                                                label="Member"
                                                htmlFor="pl-member"
                                                error={form.errors.member_id}
                                            >
                                                <MemberSelect
                                                    id="pl-member"
                                                    members={members}
                                                    placeholder="Not a member"
                                                    value={form.data.member_id}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'member_id',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                            {!form.data.member_id && (
                                                <>
                                                    <Field
                                                        label="Name"
                                                        htmlFor="pl-name"
                                                        error={form.errors.name}
                                                    >
                                                        <Input
                                                            id="pl-name"
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
                                                    <Field
                                                        label="Phone"
                                                        htmlFor="pl-phone"
                                                        error={
                                                            form.errors.phone
                                                        }
                                                        hint="Payments from this number count against the pledge."
                                                    >
                                                        <Input
                                                            id="pl-phone"
                                                            value={
                                                                form.data.phone
                                                            }
                                                            onChange={(e) =>
                                                                form.setData(
                                                                    'phone',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    </Field>
                                                </>
                                            )}
                                            <Field
                                                label="Amount pledged"
                                                htmlFor="pl-amount"
                                                error={form.errors.amount}
                                            >
                                                <MoneyInput
                                                    id="pl-amount"
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
                                                label="Note"
                                                htmlFor="pl-note"
                                            >
                                                <Input
                                                    id="pl-note"
                                                    value={form.data.note}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'note',
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
                        {pledges.length === 0 ? (
                            <EmptyState title="No pledges recorded" />
                        ) : (
                            <Table>
                                <THead>
                                    <TR>
                                        <TH>Name</TH>
                                        <TH className="text-right">Pledged</TH>
                                        <TH className="text-right">Paid</TH>
                                        <TH />
                                    </TR>
                                </THead>
                                <TBody>
                                    {pledges.map((p) => (
                                        <TR key={p.id}>
                                            <TD>
                                                {p.name}
                                                {p.note && (
                                                    <div className="text-xs text-muted-foreground">
                                                        {p.note}
                                                    </div>
                                                )}
                                            </TD>
                                            <Num>{formatKes(p.amount)}</Num>
                                            <Num
                                                className={
                                                    p.paid >= p.amount
                                                        ? 'text-emerald-700 dark:text-emerald-400'
                                                        : ''
                                                }
                                            >
                                                {formatKes(p.paid)}
                                            </Num>
                                            <TD className="text-right">
                                                {canManage && (
                                                    <ActionButton
                                                        url={`/fundraisers/${fundraiser.id}/pledges/${p.id}`}
                                                        method="delete"
                                                        size="sm"
                                                        variant="ghost"
                                                        confirm="Remove this pledge?"
                                                        aria-label="Remove pledge"
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
                </div>

                {can('payouts.request') && fundraiser.balance > 0 && (
                    <p className="text-sm text-muted-foreground">
                        To spend the money raised, request a payout from the
                        fund "Fundraiser: {fundraiser.title}" under{' '}
                        <Link href="/payouts" className="underline">
                            Payouts
                        </Link>
                        .
                    </p>
                )}
            </Page>
        </>
    );
}

FundraiserShow.layout = {
    breadcrumbs: [{ title: 'Fundraisers', href: '/fundraisers' }],
};
