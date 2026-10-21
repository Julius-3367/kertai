import { Head, Link } from '@inertiajs/react';
import { ArrowRightLeft, Dices, Send } from 'lucide-react';
import { ActionButton, FormDialog } from '@/components/kertai/actions';
import { CheckField, Field, Select } from '@/components/kertai/fields';
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
import { useCan } from '@/hooks/use-can';
import { formatDate, formatDateTime, formatKes } from '@/lib/format';
import type { Option } from '@/types/kertai';

type Slot = {
    id: number;
    position: number;
    member_id: number;
    member: string;
    scheduled_on: string;
    status: string;
    deferred_count: number;
    payout_id: number | null;
    payout_status: string | null;
    in_arrears: boolean | null;
    mine: boolean;
};

type Swap = {
    id: number;
    requester: string;
    requester_position: number;
    target: string;
    target_position: number;
    status: string;
    reason: string | null;
    awaiting_me: boolean;
    created_at: string;
};

export default function MgrShow({
    cycle,
    slots,
    swaps,
    mySlotId,
    members,
}: {
    cycle: {
        id: number;
        name: string;
        starts_on: string;
        status: string;
        payout_amount: number;
        ballot_seed: string | null;
        balloted_at: string | null;
    };
    slots: Slot[];
    swaps: Swap[];
    mySlotId: number | null;
    members: Option[];
}) {
    const can = useCan();
    const balloted = cycle.balloted_at !== null;
    const swappable = slots.filter(
        (s) => s.status === 'upcoming' && s.id !== mySlotId,
    );
    const mine = slots.find((s) => s.id === mySlotId);

    return (
        <>
            <Head title={cycle.name} />
            <Page>
                <PageHeader
                    title={cycle.name}
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            {formatKes(cycle.payout_amount)} per turn, monthly
                            from {formatDate(cycle.starts_on)}
                            <StatusBadge status={cycle.status} />
                        </span>
                    }
                    actions={
                        <>
                            {!balloted && can('mgr.manage') && (
                                <FormDialog
                                    title="Run the ballot"
                                    description="Untick anyone who is not taking part. The draw is random and recorded with a seed so it can be checked."
                                    action={`/mgr/${cycle.id}/ballot`}
                                    submitLabel="Draw order"
                                    wide
                                    initial={{
                                        member_ids: members.map((m) => m.id),
                                    }}
                                    trigger={
                                        <Button>
                                            <Dices /> Run ballot
                                        </Button>
                                    }
                                >
                                    {(form) => (
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            {members.map((m) => (
                                                <CheckField
                                                    key={m.id}
                                                    id={`b-${m.id}`}
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
                                                                      (id) =>
                                                                          id !==
                                                                          m.id,
                                                                  ),
                                                        )
                                                    }
                                                />
                                            ))}
                                        </div>
                                    )}
                                </FormDialog>
                            )}
                            {balloted &&
                                cycle.status === 'active' &&
                                can('mgr.manage') && (
                                    <ActionButton
                                        url={`/mgr/${cycle.id}/release`}
                                        confirm="Raise the payout for the next member in line? Members in arrears are moved to the back."
                                    >
                                        <Send /> Pay next turn
                                    </ActionButton>
                                )}
                            {mine &&
                                mine.status === 'upcoming' &&
                                swappable.length > 0 && (
                                    <FormDialog
                                        title="Ask to swap turns"
                                        description="The other member must agree, then the chair approves."
                                        action={`/mgr/${cycle.id}/swaps`}
                                        submitLabel="Send request"
                                        initial={{
                                            target_slot_id: '',
                                            reason: '',
                                        }}
                                        trigger={
                                            <Button variant="outline">
                                                <ArrowRightLeft /> Swap my turn
                                            </Button>
                                        }
                                    >
                                        {(form) => (
                                            <>
                                                <Field
                                                    label="Swap with"
                                                    htmlFor="sw-target"
                                                    error={
                                                        form.errors
                                                            .target_slot_id
                                                    }
                                                >
                                                    <Select
                                                        id="sw-target"
                                                        value={
                                                            form.data
                                                                .target_slot_id
                                                        }
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'target_slot_id',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="Choose"
                                                        options={swappable.map(
                                                            (s) => ({
                                                                value: s.id,
                                                                label: `Turn ${s.position}: ${s.member} (${formatDate(s.scheduled_on)})`,
                                                            }),
                                                        )}
                                                    />
                                                </Field>
                                                <Field
                                                    label="Reason"
                                                    htmlFor="sw-reason"
                                                >
                                                    <Input
                                                        id="sw-reason"
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
                                )}
                        </>
                    }
                />

                {balloted && (
                    <p className="text-xs text-muted-foreground">
                        Balloted {formatDateTime(cycle.balloted_at)} with seed{' '}
                        <span className="font-mono">{cycle.ballot_seed}</span>.
                    </p>
                )}

                <Section title="Order">
                    {!balloted ? (
                        <EmptyState title="Not balloted yet">
                            The order is drawn once everyone taking part is
                            chosen.
                        </EmptyState>
                    ) : (
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Turn</TH>
                                    <TH>Member</TH>
                                    <TH>Date</TH>
                                    <TH>Status</TH>
                                    <TH />
                                </TR>
                            </THead>
                            <TBody>
                                {slots.map((s) => (
                                    <TR
                                        key={s.id}
                                        className={
                                            s.mine
                                                ? 'bg-emerald-50/60 dark:bg-emerald-950/30'
                                                : ''
                                        }
                                    >
                                        <TD className="font-mono">
                                            {s.position}
                                        </TD>
                                        <TD className="font-medium">
                                            {s.member}
                                            {s.mine && (
                                                <span className="ml-2 text-xs text-muted-foreground">
                                                    (you)
                                                </span>
                                            )}
                                            {s.deferred_count > 0 && (
                                                <div className="text-xs text-muted-foreground">
                                                    Moved back{' '}
                                                    {s.deferred_count}× for
                                                    arrears
                                                </div>
                                            )}
                                            {s.in_arrears &&
                                                s.status === 'upcoming' && (
                                                    <div className="text-xs text-red-700 dark:text-red-400">
                                                        In arrears
                                                    </div>
                                                )}
                                        </TD>
                                        <TD>{formatDate(s.scheduled_on)}</TD>
                                        <TD>
                                            <StatusBadge status={s.status} />
                                        </TD>
                                        <TD className="text-right">
                                            {s.payout_id &&
                                                can('finance.view') && (
                                                    <Link
                                                        href={`/payouts/${s.payout_id}`}
                                                        className="text-sm underline"
                                                    >
                                                        Payout (
                                                        {s.payout_status})
                                                    </Link>
                                                )}
                                        </TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                    )}
                </Section>

                <Section title="Swap requests">
                    {swaps.length === 0 ? (
                        <EmptyState title="No swap requests" />
                    ) : (
                        <ul className="space-y-3">
                            {swaps.map((w) => (
                                <li
                                    key={w.id}
                                    className="flex flex-col gap-2 rounded-lg border p-3 text-sm sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <span className="font-medium">
                                            {w.requester}
                                        </span>{' '}
                                        (turn {w.requester_position}) ↔{' '}
                                        <span className="font-medium">
                                            {w.target}
                                        </span>{' '}
                                        (turn {w.target_position})
                                        {w.reason && (
                                            <div className="text-muted-foreground">
                                                "{w.reason}"
                                            </div>
                                        )}
                                        <div className="text-xs text-muted-foreground">
                                            {formatDateTime(w.created_at)}
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <StatusBadge status={w.status} />
                                        {(w.awaiting_me ||
                                            (w.status === 'pending_chair' &&
                                                can('mgr.approve_swaps'))) && (
                                            <>
                                                <ActionButton
                                                    url={`/mgr/swaps/${w.id}`}
                                                    data={{ accept: true }}
                                                    size="sm"
                                                >
                                                    {w.awaiting_me
                                                        ? 'Agree'
                                                        : 'Approve'}
                                                </ActionButton>
                                                <ActionButton
                                                    url={`/mgr/swaps/${w.id}`}
                                                    data={{ accept: false }}
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    Decline
                                                </ActionButton>
                                            </>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Section>
            </Page>
        </>
    );
}

MgrShow.layout = { breadcrumbs: [{ title: 'Merry-go-round', href: '/mgr' }] };
