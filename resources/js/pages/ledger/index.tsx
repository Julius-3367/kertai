import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeftRight, BookPlus } from 'lucide-react';
import { useState } from 'react';
import { FormDialog } from '@/components/kertai/actions';
import {
    Field,
    MemberSelect,
    MoneyInput,
    Select,
} from '@/components/kertai/fields';
import { Page, PageHeader, Section } from '@/components/kertai/page';
import { Pagination } from '@/components/kertai/pagination';
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
import type { Option, Paginated } from '@/types/kertai';

type Account = {
    id: number;
    code: string;
    name: string;
    kind: string;
    purpose: string;
    balance: number;
};
type Entry = {
    id: number;
    number: number;
    date: string;
    description: string;
    reference: string | null;
    source: string | null;
    reversal_of_id: number | null;
    by: string | null;
    total: number;
    lines: {
        account: string;
        member: string | null;
        debit: number;
        credit: number;
        memo: string | null;
    }[];
};

export default function LedgerIndex({
    accounts,
    totals,
    entries,
    filters,
    members,
}: {
    accounts: Account[];
    totals: { assets: number; funds: number };
    entries: Paginated<Entry>;
    filters: { q?: string };
    members: Option[];
}) {
    const can = useCan();
    const [q, setQ] = useState(filters.q ?? '');
    const [open, setOpen] = useState<number | null>(null);
    const assets = accounts.filter((a) => a.kind === 'asset');
    const funds = accounts.filter((a) => a.kind === 'fund');
    const balanced = totals.assets === totals.funds;

    return (
        <>
            <Head title="Ledger" />
            <Page>
                <PageHeader
                    title="Ledger"
                    description="Double-entry books. Entries are never edited; corrections are made with reversals."
                    actions={
                        can('ledger.manage') && (
                            <>
                                <FormDialog
                                    title="Transfer between accounts"
                                    description="For example, banking the cash collected at a meeting, or SimulinkPay settling to the bank."
                                    action="/ledger/transfers"
                                    submitLabel="Record transfer"
                                    initial={{
                                        from_account_id: String(
                                            assets[2]?.id ?? '',
                                        ),
                                        to_account_id: String(
                                            assets[0]?.id ?? '',
                                        ),
                                        amount: '',
                                        description: 'Cash banked',
                                        reference: '',
                                        date: today(),
                                    }}
                                    trigger={
                                        <Button variant="outline">
                                            <ArrowLeftRight /> Transfer
                                        </Button>
                                    }
                                >
                                    {(form) => (
                                        <>
                                            <div className="grid grid-cols-2 gap-3">
                                                <Field
                                                    label="From"
                                                    htmlFor="tr-from"
                                                    error={
                                                        form.errors
                                                            .from_account_id
                                                    }
                                                >
                                                    <Select
                                                        id="tr-from"
                                                        value={
                                                            form.data
                                                                .from_account_id
                                                        }
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'from_account_id',
                                                                e.target.value,
                                                            )
                                                        }
                                                        options={assets.map(
                                                            (a) => ({
                                                                value: a.id,
                                                                label: a.name,
                                                            }),
                                                        )}
                                                    />
                                                </Field>
                                                <Field
                                                    label="To"
                                                    htmlFor="tr-to"
                                                    error={
                                                        form.errors
                                                            .to_account_id
                                                    }
                                                >
                                                    <Select
                                                        id="tr-to"
                                                        value={
                                                            form.data
                                                                .to_account_id
                                                        }
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'to_account_id',
                                                                e.target.value,
                                                            )
                                                        }
                                                        options={assets.map(
                                                            (a) => ({
                                                                value: a.id,
                                                                label: a.name,
                                                            }),
                                                        )}
                                                    />
                                                </Field>
                                            </div>
                                            <Field
                                                label="Amount"
                                                htmlFor="tr-amount"
                                                error={form.errors.amount}
                                            >
                                                <MoneyInput
                                                    id="tr-amount"
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
                                                label="Description"
                                                htmlFor="tr-desc"
                                                error={form.errors.description}
                                            >
                                                <Input
                                                    id="tr-desc"
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
                                            <div className="grid grid-cols-2 gap-3">
                                                <Field
                                                    label="Reference"
                                                    htmlFor="tr-ref"
                                                >
                                                    <Input
                                                        id="tr-ref"
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
                                                <Field
                                                    label="Date"
                                                    htmlFor="tr-date"
                                                    error={form.errors.date}
                                                >
                                                    <Input
                                                        id="tr-date"
                                                        type="date"
                                                        value={form.data.date}
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'date',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </Field>
                                            </div>
                                        </>
                                    )}
                                </FormDialog>
                                <FormDialog
                                    title="Opening balance"
                                    description="Bring balances over from the paper books: where the money is held, and which fund (or member's savings) it belongs to."
                                    action="/ledger/opening"
                                    submitLabel="Post"
                                    initial={{
                                        asset_account_id: String(
                                            assets[0]?.id ?? '',
                                        ),
                                        fund_account_id: String(
                                            funds[0]?.id ?? '',
                                        ),
                                        member_id: '',
                                        amount: '',
                                        date: today(),
                                        reference: '',
                                    }}
                                    trigger={
                                        <Button variant="outline">
                                            <BookPlus /> Opening balance
                                        </Button>
                                    }
                                >
                                    {(form) => (
                                        <>
                                            <Field
                                                label="Held in"
                                                htmlFor="ob-asset"
                                                error={
                                                    form.errors.asset_account_id
                                                }
                                            >
                                                <Select
                                                    id="ob-asset"
                                                    value={
                                                        form.data
                                                            .asset_account_id
                                                    }
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'asset_account_id',
                                                            e.target.value,
                                                        )
                                                    }
                                                    options={assets.map(
                                                        (a) => ({
                                                            value: a.id,
                                                            label: a.name,
                                                        }),
                                                    )}
                                                />
                                            </Field>
                                            <Field
                                                label="Belongs to fund"
                                                htmlFor="ob-fund"
                                                error={
                                                    form.errors.fund_account_id
                                                }
                                            >
                                                <Select
                                                    id="ob-fund"
                                                    value={
                                                        form.data
                                                            .fund_account_id
                                                    }
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'fund_account_id',
                                                            e.target.value,
                                                        )
                                                    }
                                                    options={funds.map((a) => ({
                                                        value: a.id,
                                                        label: a.name,
                                                    }))}
                                                />
                                            </Field>
                                            <Field
                                                label="Member (for savings and prepayments)"
                                                htmlFor="ob-member"
                                                error={form.errors.member_id}
                                            >
                                                <MemberSelect
                                                    id="ob-member"
                                                    members={members}
                                                    placeholder="Not member-specific"
                                                    value={form.data.member_id}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'member_id',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                            <Field
                                                label="Amount"
                                                htmlFor="ob-amount"
                                                error={form.errors.amount}
                                            >
                                                <MoneyInput
                                                    id="ob-amount"
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
                                                label="As at"
                                                htmlFor="ob-date"
                                                error={form.errors.date}
                                            >
                                                <Input
                                                    id="ob-date"
                                                    type="date"
                                                    value={form.data.date}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'date',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                        </>
                                    )}
                                </FormDialog>
                            </>
                        )
                    }
                />

                <div className="grid gap-6 lg:grid-cols-2">
                    {[
                        {
                            title: 'Where the money is',
                            rows: assets,
                            total: totals.assets,
                        },
                        {
                            title: 'What the money is for',
                            rows: funds,
                            total: totals.funds,
                        },
                    ].map((block) => (
                        <Section key={block.title} title={block.title}>
                            <Table>
                                <TBody>
                                    {block.rows.map((a) => (
                                        <TR key={a.id}>
                                            <TD className="w-16 font-mono text-xs text-muted-foreground">
                                                {a.code}
                                            </TD>
                                            <TD>
                                                <Link
                                                    href={`/ledger/accounts/${a.id}`}
                                                    className="hover:underline"
                                                >
                                                    {a.name}
                                                </Link>
                                            </TD>
                                            <Num>{formatKes(a.balance)}</Num>
                                        </TR>
                                    ))}
                                    <TR className="font-semibold">
                                        <TD />
                                        <TD>Total</TD>
                                        <Num>{formatKes(block.total)}</Num>
                                    </TR>
                                </TBody>
                            </Table>
                        </Section>
                    ))}
                </div>
                <p className="text-sm">
                    {balanced ? (
                        <StatusBadge status="active" label="Books balance" />
                    ) : (
                        <StatusBadge
                            status="failed"
                            label={`Out of balance by ${formatKes(totals.assets - totals.funds)}`}
                        />
                    )}
                </p>

                <Section
                    title="Journal"
                    actions={
                        <form
                            className="flex gap-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                router.get(
                                    '/ledger',
                                    { q },
                                    { preserveState: true, replace: true },
                                );
                            }}
                        >
                            <Input
                                placeholder="Search"
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                className="h-8 w-48"
                            />
                            <Button size="sm" variant="outline">
                                Search
                            </Button>
                        </form>
                    }
                >
                    <Table>
                        <THead>
                            <TR>
                                <TH>No.</TH>
                                <TH>Date</TH>
                                <TH>Description</TH>
                                <TH>By</TH>
                                <TH className="text-right">Amount</TH>
                                <TH />
                            </TR>
                        </THead>
                        <TBody>
                            {entries.data.map((e) => (
                                <EntryRows
                                    key={e.id}
                                    entry={e}
                                    open={open === e.id}
                                    onToggle={() =>
                                        setOpen(open === e.id ? null : e.id)
                                    }
                                    canReverse={can('ledger.manage')}
                                />
                            ))}
                        </TBody>
                    </Table>
                    <Pagination page={entries} />
                </Section>
            </Page>
        </>
    );
}

function EntryRows({
    entry,
    open,
    onToggle,
    canReverse,
}: {
    entry: Entry;
    open: boolean;
    onToggle: () => void;
    canReverse: boolean;
}) {
    return (
        <>
            <TR className="cursor-pointer" onClick={onToggle}>
                <TD className="font-mono text-xs">#{entry.number}</TD>
                <TD className="whitespace-nowrap">{formatDate(entry.date)}</TD>
                <TD>
                    {entry.description}
                    {entry.reference && (
                        <span className="ml-2 font-mono text-xs text-muted-foreground">
                            {entry.reference}
                        </span>
                    )}
                    {entry.reversal_of_id && (
                        <StatusBadge
                            status="cancelled"
                            label="Reversal"
                            className="ml-2"
                        />
                    )}
                </TD>
                <TD className="text-sm text-muted-foreground">
                    {entry.by ?? 'System'}
                </TD>
                <Num>{formatKes(entry.total)}</Num>
                <TD className="text-right" onClick={(e) => e.stopPropagation()}>
                    {canReverse &&
                        entry.source === null &&
                        entry.reversal_of_id === null && (
                            <FormDialog
                                title={`Reverse entry #${entry.number}`}
                                description="Posts an equal and opposite entry. The original stays on record."
                                action={`/ledger/entries/${entry.id}/reverse`}
                                submitLabel="Reverse"
                                destructive
                                initial={{ reason: '' }}
                                trigger={
                                    <Button size="sm" variant="ghost">
                                        Reverse
                                    </Button>
                                }
                            >
                                {(form) => (
                                    <Field
                                        label="Reason"
                                        htmlFor={`rv-${entry.id}`}
                                        error={form.errors.reason}
                                    >
                                        <Input
                                            id={`rv-${entry.id}`}
                                            value={form.data.reason}
                                            onChange={(e) =>
                                                form.setData(
                                                    'reason',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                )}
                            </FormDialog>
                        )}
                </TD>
            </TR>
            {open && (
                <TR className="bg-muted/30 hover:bg-muted/30">
                    <TD />
                    <TD colSpan={5}>
                        <table className="w-full text-xs">
                            <tbody>
                                {entry.lines.map((l, i) => (
                                    <tr key={i}>
                                        <td className="py-1 pr-2">
                                            {l.account}
                                        </td>
                                        <td className="py-1 pr-2 text-muted-foreground">
                                            {l.member ?? ''}
                                        </td>
                                        <td className="py-1 pr-2 text-muted-foreground">
                                            {l.memo ?? ''}
                                        </td>
                                        <td className="py-1 text-right tabular-nums">
                                            {l.debit
                                                ? `Dr ${formatKes(l.debit)}`
                                                : ''}
                                        </td>
                                        <td className="py-1 text-right tabular-nums">
                                            {l.credit
                                                ? `Cr ${formatKes(l.credit)}`
                                                : ''}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </TD>
                </TR>
            )}
        </>
    );
}

LedgerIndex.layout = { breadcrumbs: [{ title: 'Ledger', href: '/ledger' }] };
