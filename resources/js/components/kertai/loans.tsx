import { Link } from '@inertiajs/react';
import { CircleCheck, CircleX } from 'lucide-react';
import { CheckField, Field, Select } from '@/components/kertai/fields';
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
import { Input } from '@/components/ui/input';
import { formatDate, formatKes } from '@/lib/format';
import { cn } from '@/lib/utils';

export type Owed = { principal: number; interest: number; total: number };

export type Interest = {
    rate: number;
    method: string;
    period: string;
    paused: boolean;
};

export type LoanRow = {
    id: number;
    reference: string;
    member: { id: number; name: string; number: string };
    source: string;
    status: string;
    principal: number;
    owed: Owed;
    issued_on: string | null;
    due_on: string | null;
    overdue: boolean;
    behind: number;
    interest: Interest;
};

/** "10% a month on what is left", "Stopped", and so on. */
export function interestText(interest: Interest, groupOn = true): string {
    if (interest.paused) {
        return 'Stopped on this loan';
    }

    if (!groupOn) {
        return 'Off for the group';
    }

    if (interest.rate <= 0) {
        return 'None';
    }

    if (interest.period === 'once') {
        return `${interest.rate}% once`;
    }

    return interest.method === 'flat'
        ? `${interest.rate}% a month on the amount lent`
        : `${interest.rate}% a month on what is left`;
}

export function LoanStatus({ loan }: { loan: LoanRow }) {
    if (loan.overdue) {
        return <StatusBadge status="overdue" label="Past due date" />;
    }

    if (loan.behind > 0) {
        return <StatusBadge status="late" label="Behind" />;
    }

    return <StatusBadge status={loan.status} />;
}

export function LoansTable({
    loans,
    showMember = true,
    groupInterestOn = true,
}: {
    loans: LoanRow[];
    showMember?: boolean;
    groupInterestOn?: boolean;
}) {
    return (
        <Table>
            <THead>
                <TR>
                    <TH>Loan</TH>
                    <TH className="text-right">Owed</TH>
                    <TH>Due</TH>
                    <TH>Interest</TH>
                    <TH>Status</TH>
                </TR>
            </THead>
            <TBody>
                {loans.map((loan) => (
                    <TR key={loan.id}>
                        <TD>
                            <div>
                                <Link
                                    href={`/loans/${loan.id}`}
                                    className="font-medium hover:underline"
                                >
                                    {loan.reference}
                                </Link>
                                {showMember && (
                                    <span className="ml-1.5">
                                        {loan.member.name}
                                    </span>
                                )}
                            </div>
                        </TD>
                        <Num>
                            <div>
                                <span className="font-medium">
                                    {formatKes(loan.owed.total)}
                                </span>
                                {loan.owed.interest > 0 && (
                                    <span className="block text-xs text-muted-foreground">
                                        incl. {formatKes(loan.owed.interest)}{' '}
                                        interest
                                    </span>
                                )}
                            </div>
                        </Num>
                        <TD>
                            <div>
                                {loan.due_on ? formatDate(loan.due_on) : '—'}
                                {loan.behind > 0 && (
                                    <span className="block text-xs text-amber-700 dark:text-amber-400">
                                        {formatKes(loan.behind)} behind
                                    </span>
                                )}
                            </div>
                        </TD>
                        <TD className="text-xs text-muted-foreground">
                            {loan.status === 'active'
                                ? interestText(loan.interest, groupInterestOn)
                                : '—'}
                        </TD>
                        <TD>
                            <LoanStatus loan={loan} />
                        </TD>
                    </TR>
                ))}
            </TBody>
        </Table>
    );
}

export type InterestData = {
    interest_rate: string;
    interest_method: string;
    interest_period: string;
    interest_paused: boolean;
};

/** Rate, how it is worked out, and the stop switch. */
export function InterestFields({
    data,
    errors,
    set,
    idPrefix,
}: {
    data: InterestData;
    errors: Partial<Record<keyof InterestData, string>>;
    set: (key: keyof InterestData, value: string | boolean) => void;
    idPrefix: string;
}) {
    return (
        <div className="grid gap-4 rounded-lg border p-3">
            <div className="grid gap-4 sm:grid-cols-3">
                <Field
                    label="Interest rate (%)"
                    htmlFor={`${idPrefix}-rate`}
                    error={errors.interest_rate}
                >
                    <Input
                        id={`${idPrefix}-rate`}
                        inputMode="decimal"
                        value={data.interest_rate}
                        onChange={(e) => set('interest_rate', e.target.value)}
                    />
                </Field>
                <Field
                    label="Charged"
                    htmlFor={`${idPrefix}-period`}
                    error={errors.interest_period}
                >
                    <Select
                        id={`${idPrefix}-period`}
                        value={data.interest_period}
                        onChange={(e) => set('interest_period', e.target.value)}
                        options={[
                            { value: 'month', label: 'Every month' },
                            { value: 'once', label: 'Once, when lent' },
                        ]}
                    />
                </Field>
                <Field
                    label="Worked out on"
                    htmlFor={`${idPrefix}-method`}
                    error={errors.interest_method}
                >
                    <Select
                        id={`${idPrefix}-method`}
                        value={data.interest_method}
                        onChange={(e) => set('interest_method', e.target.value)}
                        options={[
                            {
                                value: 'reducing',
                                label: 'What is left (reducing)',
                            },
                            { value: 'flat', label: 'The amount lent (flat)' },
                        ]}
                    />
                </Field>
            </div>
            <CheckField
                id={`${idPrefix}-paused`}
                label="Stop interest on this loan (repay principal only)"
                checked={data.interest_paused}
                onChange={(v) => set('interest_paused', v)}
            />
        </div>
    );
}

export type Rule = { key: string; ok: boolean; label: string };

/** The lending rules as a checklist: met in green, not met in red. */
export function RuleList({ rules }: { rules: Rule[] }) {
    return (
        <ul className="space-y-1.5 text-sm">
            {rules.map((r) => (
                <li key={r.key} className="flex items-start gap-2">
                    {r.ok ? (
                        <CircleCheck className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                    ) : (
                        <CircleX className="mt-0.5 size-4 shrink-0 text-red-600" />
                    )}
                    <span className={cn(!r.ok && 'font-medium')}>
                        {r.label}
                    </span>
                </li>
            ))}
        </ul>
    );
}
