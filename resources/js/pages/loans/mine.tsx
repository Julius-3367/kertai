import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { LoansTable } from '@/components/kertai/loans';
import type { LoanRow } from '@/components/kertai/loans';
import {
    EmptyState,
    Page,
    PageHeader,
    Section,
    StatCard,
} from '@/components/kertai/page';
import { StatusBadge } from '@/components/kertai/status-badge';
import { Button } from '@/components/ui/button';
import { formatKes } from '@/lib/format';

type Props = {
    loans: LoanRow[];
    guarantees: {
        id: number;
        loan_id: number;
        reference: string;
        borrower: string;
        amount: number;
        status: string;
        loan_status: string;
    }[];
    lending: {
        enabled: boolean;
        eligible: boolean;
        max_amount: number | null;
        savings: number;
        pledged: number;
    };
};

export default function MyLoans({ loans, guarantees, lending }: Props) {
    const owed = loans
        .filter((l) => l.status === 'active')
        .reduce((sum, l) => sum + l.owed.total, 0);
    const asked = guarantees.filter(
        (g) => g.status === 'pending' && g.loan_status === 'pending',
    );

    return (
        <>
            <Head title="My loans" />
            <Page>
                <PageHeader
                    title="My loans"
                    description={
                        lending.enabled
                            ? 'Apply here, and follow what you owe and what you guarantee.'
                            : 'The group is not giving new loans at the moment. Loans already running are repaid as usual.'
                    }
                    actions={
                        lending.enabled && (
                            <Button asChild>
                                <Link href="/me/loans/apply">
                                    <Plus /> Apply for a loan
                                </Link>
                            </Button>
                        )
                    }
                />

                {asked.length > 0 && (
                    <Section
                        title="Waiting for your answer"
                        className="border-amber-300 dark:border-amber-800"
                    >
                        <ul className="space-y-2 text-sm">
                            {asked.map((g) => (
                                <li key={g.id}>
                                    <Link
                                        href={`/loans/${g.loan_id}`}
                                        className="font-medium underline"
                                    >
                                        {g.borrower} asks you to guarantee{' '}
                                        {formatKes(g.amount)}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </Section>
                )}

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-3">
                    <StatCard
                        label="I owe"
                        value={formatKes(owed)}
                        tone={owed > 0 ? 'warn' : 'good'}
                    />
                    <StatCard
                        label="My savings"
                        value={formatKes(lending.savings)}
                        hint={
                            lending.pledged > 0
                                ? `${formatKes(lending.pledged)} pledged as guarantor`
                                : undefined
                        }
                    />
                    {lending.enabled && (
                        <StatCard
                            label="I could borrow up to"
                            value={
                                lending.max_amount === null
                                    ? '—'
                                    : formatKes(lending.max_amount)
                            }
                            hint={
                                lending.eligible
                                    ? 'You meet the lending rules'
                                    : 'See the rules when you apply'
                            }
                        />
                    )}
                </div>

                <Section title="My loans">
                    {loans.length === 0 ? (
                        <EmptyState title="You have no loans" />
                    ) : (
                        <LoansTable loans={loans} showMember={false} />
                    )}
                </Section>

                {guarantees.length > 0 && (
                    <Section
                        title="Loans I guarantee"
                        description="If the borrower does not repay, up to this much can be taken from your savings."
                    >
                        <ul className="divide-y">
                            {guarantees.map((g) => (
                                <li
                                    key={g.id}
                                    className="flex items-center justify-between gap-3 py-2 text-sm"
                                >
                                    <Link
                                        href={`/loans/${g.loan_id}`}
                                        className="hover:underline"
                                    >
                                        <span className="font-medium">
                                            {g.borrower}
                                        </span>{' '}
                                        <span className="text-muted-foreground">
                                            {g.reference} ·{' '}
                                            {formatKes(g.amount)}
                                        </span>
                                    </Link>
                                    <StatusBadge status={g.status} />
                                </li>
                            ))}
                        </ul>
                    </Section>
                )}
            </Page>
        </>
    );
}

MyLoans.layout = { breadcrumbs: [{ title: 'My loans', href: '/me/loans' }] };
