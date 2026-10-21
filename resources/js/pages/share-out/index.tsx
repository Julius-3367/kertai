import { Head, Link, router } from '@inertiajs/react';
import { Wallet } from 'lucide-react';
import { useState } from 'react';
import { FormDialog } from '@/components/kertai/actions';
import { Field } from '@/components/kertai/fields';
import {
    EmptyState,
    Page,
    PageHeader,
    Section,
    StatCard,
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
import { useCan } from '@/hooks/use-can';
import { formatKes } from '@/lib/format';

type Row = {
    member_id: number;
    name: string;
    member_number: string;
    savings: number;
    owed: number;
    from_savings: number;
    from_group_fund: number;
    total: number;
};

export default function ShareOut({
    percent,
    plan,
    batches,
}: {
    percent: number;
    plan: { group_fund: number; distribute: number; rows: Row[] };
    batches: {
        batch: string;
        payouts: number;
        total: number;
        pending: number;
        approved: number;
        paid: number;
    }[];
}) {
    const can = useCan();
    const [pct, setPct] = useState(String(percent));
    const total = plan.rows.reduce((s, r) => s + r.total, 0);

    return (
        <>
            <Head title="Share-out" />
            <Page>
                <PageHeader
                    title="Year-end share-out"
                    description="Savings go back to members less anything they owe. The meeting can also agree to share part of the group fund in proportion to savings."
                    actions={
                        can('payouts.request') && (
                            <FormDialog
                                title="Raise share-out payouts"
                                description={`About ${formatKes(total)} across ${plan.rows.filter((r) => r.total > 0).length} members. Each payout still needs two approvals.`}
                                action="/share-out"
                                submitLabel="Raise payouts"
                                initial={{
                                    percent: Number(pct) || 0,
                                    label: `Share-out ${new Date().getFullYear()}`,
                                }}
                                trigger={
                                    <Button>
                                        <Wallet /> Raise payouts
                                    </Button>
                                }
                            >
                                {(form) => (
                                    <>
                                        <Field
                                            label="Label"
                                            htmlFor="so-label"
                                            error={form.errors.label}
                                        >
                                            <Input
                                                id="so-label"
                                                value={form.data.label}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'label',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                        <Field
                                            label="Share of the group fund (%)"
                                            htmlFor="so-pct"
                                            error={form.errors.percent}
                                        >
                                            <Input
                                                id="so-pct"
                                                type="number"
                                                min={0}
                                                max={100}
                                                value={form.data.percent}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'percent',
                                                        Number(e.target.value),
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

                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard
                        label="Group fund"
                        value={formatKes(plan.group_fund)}
                    />
                    <StatCard
                        label="Of which to share"
                        value={formatKes(plan.distribute)}
                        hint={`${percent}% of the group fund`}
                    />
                    <StatCard
                        label="Total to pay out"
                        value={formatKes(total)}
                        tone="good"
                    />
                </div>

                <Section
                    title="Preview"
                    description="Amounts are estimates until the payouts are raised; debts are cleared from savings first."
                    actions={
                        <form
                            className="flex items-center gap-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                router.get(
                                    '/share-out',
                                    { percent: pct },
                                    { preserveState: true, replace: true },
                                );
                            }}
                        >
                            <Input
                                type="number"
                                min={0}
                                max={100}
                                className="h-8 w-20"
                                value={pct}
                                onChange={(e) => setPct(e.target.value)}
                                aria-label="Percent of group fund"
                            />
                            <span className="text-sm text-muted-foreground">
                                % of group fund
                            </span>
                            <Button size="sm" variant="outline">
                                Preview
                            </Button>
                        </form>
                    }
                >
                    {plan.rows.length === 0 ? (
                        <EmptyState title="No active members" />
                    ) : (
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Member</TH>
                                    <TH className="text-right">Savings</TH>
                                    <TH className="text-right">Owes</TH>
                                    <TH className="text-right">From savings</TH>
                                    <TH className="text-right">
                                        Group fund share
                                    </TH>
                                    <TH className="text-right">Total</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {plan.rows.map((r) => (
                                    <TR key={r.member_id}>
                                        <TD>
                                            {r.name}{' '}
                                            <span className="text-xs text-muted-foreground">
                                                {r.member_number}
                                            </span>
                                        </TD>
                                        <Num>{formatKes(r.savings)}</Num>
                                        <Num
                                            className={
                                                r.owed
                                                    ? 'text-red-700 dark:text-red-400'
                                                    : ''
                                            }
                                        >
                                            {formatKes(r.owed)}
                                        </Num>
                                        <Num>{formatKes(r.from_savings)}</Num>
                                        <Num>
                                            {formatKes(r.from_group_fund)}
                                        </Num>
                                        <Num className="font-medium">
                                            {formatKes(r.total)}
                                        </Num>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                    )}
                </Section>

                {batches.length > 0 && (
                    <Section title="Raised share-outs">
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Batch</TH>
                                    <TH>Payouts</TH>
                                    <TH>Progress</TH>
                                    <TH className="text-right">Total</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {batches.map((b) => (
                                    <TR key={b.batch}>
                                        <TD className="font-mono text-xs">
                                            {b.batch}
                                        </TD>
                                        <TD>{b.payouts}</TD>
                                        <TD>
                                            {b.pending} pending · {b.approved}{' '}
                                            approved · {b.paid} paid{' '}
                                            {b.pending > 0 && (
                                                <Link
                                                    href="/payouts?status=mine"
                                                    className="ml-2 text-sm underline"
                                                >
                                                    Approve
                                                </Link>
                                            )}
                                        </TD>
                                        <Num>{formatKes(b.total)}</Num>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                    </Section>
                )}
            </Page>
        </>
    );
}

ShareOut.layout = { breadcrumbs: [{ title: 'Share-out', href: '/share-out' }] };
