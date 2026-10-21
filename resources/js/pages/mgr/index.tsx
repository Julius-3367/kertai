import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { FormDialog } from '@/components/kertai/actions';
import { Field, MoneyInput } from '@/components/kertai/fields';
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
import { formatDate, formatKes, today } from '@/lib/format';
import type { Option } from '@/types/kertai';

type Cycle = {
    id: number;
    name: string;
    starts_on: string;
    status: string;
    payout_amount: number;
    slots: number;
    paid: number;
};

export default function MgrIndex({
    cycles,
    pot,
    mgrShare,
    members,
}: {
    cycles: Cycle[];
    pot: number;
    mgrShare: number;
    members: Option[];
}) {
    const can = useCan();

    return (
        <>
            <Head title="Merry-go-round" />
            <Page>
                <PageHeader
                    title="Merry-go-round"
                    description="Every month each member pays into the pot and one member, in ballot order, receives it."
                    actions={
                        can('mgr.manage') && (
                            <FormDialog
                                title="New cycle"
                                description={`Leave the payout empty to use ${formatKes(mgrShare)} times the number of members in the ballot.`}
                                action="/mgr"
                                submitLabel="Create"
                                initial={{
                                    name: `Merry-go-round ${new Date().getFullYear()}`,
                                    starts_on: today(),
                                    payout_amount: '',
                                }}
                                trigger={
                                    <Button>
                                        <Plus /> New cycle
                                    </Button>
                                }
                            >
                                {(form) => (
                                    <>
                                        <Field
                                            label="Name"
                                            htmlFor="cy-name"
                                            error={form.errors.name}
                                        >
                                            <Input
                                                id="cy-name"
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
                                            label="First payout date"
                                            htmlFor="cy-start"
                                            error={form.errors.starts_on}
                                        >
                                            <Input
                                                id="cy-start"
                                                type="date"
                                                value={form.data.starts_on}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'starts_on',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                        <Field
                                            label="Payout per turn (optional)"
                                            htmlFor="cy-amount"
                                            error={form.errors.payout_amount}
                                        >
                                            <MoneyInput
                                                id="cy-amount"
                                                value={form.data.payout_amount}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'payout_amount',
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

                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard
                        label="In the pot"
                        value={can('finance.view') ? formatKes(pot) : '—'}
                    />
                    <StatCard
                        label="Each member pays per month"
                        value={formatKes(mgrShare)}
                    />
                    <StatCard label="Members" value={members.length} />
                </div>

                <Section title="Cycles">
                    {cycles.length === 0 ? (
                        <EmptyState title="No cycles yet" />
                    ) : (
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Cycle</TH>
                                    <TH>Starts</TH>
                                    <TH>Progress</TH>
                                    <TH>Status</TH>
                                    <TH className="text-right">
                                        Payout per turn
                                    </TH>
                                </TR>
                            </THead>
                            <TBody>
                                {cycles.map((c) => (
                                    <TR key={c.id}>
                                        <TD>
                                            <Link
                                                href={`/mgr/${c.id}`}
                                                className="font-medium hover:underline"
                                            >
                                                {c.name}
                                            </Link>
                                        </TD>
                                        <TD>{formatDate(c.starts_on)}</TD>
                                        <TD>
                                            {c.slots
                                                ? `${c.paid} of ${c.slots} paid`
                                                : 'Not balloted'}
                                        </TD>
                                        <TD>
                                            <StatusBadge status={c.status} />
                                        </TD>
                                        <Num>{formatKes(c.payout_amount)}</Num>
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

MgrIndex.layout = { breadcrumbs: [{ title: 'Merry-go-round', href: '/mgr' }] };
