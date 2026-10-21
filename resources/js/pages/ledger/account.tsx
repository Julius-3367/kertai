import { Head } from '@inertiajs/react';
import {
    EmptyState,
    Page,
    PageHeader,
    Section,
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
import { formatDate, formatKes } from '@/lib/format';

type Props = {
    account: {
        id: number;
        code: string;
        name: string;
        kind: string;
        per_member: boolean;
        balance: number;
    };
    lines: {
        id: number;
        date: string;
        number: number;
        description: string;
        memo: string | null;
        member: string | null;
        debit: number;
        credit: number;
        balance: number;
    }[];
    memberBalances: {
        member_id: number;
        name: string | null;
        balance: number;
    }[];
};

export default function LedgerAccount({
    account,
    lines,
    memberBalances,
}: Props) {
    const inLabel = account.kind === 'asset' ? 'In (Dr)' : 'Out (Dr)';
    const outLabel = account.kind === 'asset' ? 'Out (Cr)' : 'In (Cr)';

    return (
        <>
            <Head title={account.name} />
            <Page>
                <PageHeader
                    title={`${account.code} ${account.name}`}
                    description={`Balance ${formatKes(account.balance)}`}
                />

                {account.per_member && (
                    <Section title="By member">
                        <Table>
                            <TBody>
                                {memberBalances.map((m) => (
                                    <TR key={m.member_id}>
                                        <TD>{m.name}</TD>
                                        <Num>{formatKes(m.balance)}</Num>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                    </Section>
                )}

                <Section title="Movements" description="Newest first.">
                    {lines.length === 0 ? (
                        <EmptyState title="No movements yet" />
                    ) : (
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Date</TH>
                                    <TH>Entry</TH>
                                    <TH>Description</TH>
                                    <TH>Member</TH>
                                    <TH className="text-right">{inLabel}</TH>
                                    <TH className="text-right">{outLabel}</TH>
                                    <TH className="text-right">Balance</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {lines.map((l) => (
                                    <TR key={l.id}>
                                        <TD className="whitespace-nowrap">
                                            {formatDate(l.date)}
                                        </TD>
                                        <TD className="font-mono text-xs">
                                            #{l.number}
                                        </TD>
                                        <TD>
                                            {l.description}
                                            {l.memo && (
                                                <div className="text-xs text-muted-foreground">
                                                    {l.memo}
                                                </div>
                                            )}
                                        </TD>
                                        <TD className="text-sm">
                                            {l.member ?? ''}
                                        </TD>
                                        <Num>
                                            {l.debit ? formatKes(l.debit) : ''}
                                        </Num>
                                        <Num>
                                            {l.credit
                                                ? formatKes(l.credit)
                                                : ''}
                                        </Num>
                                        <Num>{formatKes(l.balance)}</Num>
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

LedgerAccount.layout = { breadcrumbs: [{ title: 'Ledger', href: '/ledger' }] };
