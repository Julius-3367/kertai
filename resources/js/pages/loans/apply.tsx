import { Head, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { actionError, fieldError } from '@/components/kertai/actions';
import {
    ActionError,
    Field,
    MemberSelect,
    MoneyInput,
    Textarea,
} from '@/components/kertai/fields';
import { RuleList } from '@/components/kertai/loans';
import { Page, PageHeader, Section } from '@/components/kertai/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { formatKes } from '@/lib/format';
import type { Option } from '@/types/kertai';

type Props = {
    rules: { key: string; ok: boolean; label: string }[];
    limits: {
        max_amount: number | null;
        max_term: number;
        guarantors_required: number;
        free_savings: number;
        interest_rate: number;
        interest_method: string;
        interest_period: string;
        interest_on: boolean;
    };
    members: Option[];
};

type Form = {
    amount: string;
    term_months: string;
    purpose: string;
    guarantors: { member_id: string; amount: string }[];
};

function shillings(value: string): number {
    const n = Number(value.replace(/,/g, ''));

    return Number.isFinite(n) ? Math.round(n * 100) : 0;
}

export default function ApplyForLoan({ rules, limits, members }: Props) {
    const form = useForm<Form>({
        amount: '',
        term_months: String(Math.min(6, limits.max_term)),
        purpose: '',
        guarantors: Array.from({ length: limits.guarantors_required }, () => ({
            member_id: '',
            amount: '',
        })),
    });

    const blocked = rules.some((r) => !r.ok);
    const amount = shillings(form.data.amount);
    const term = Number(form.data.term_months) || 0;
    const cover = Math.max(0, amount - limits.free_savings);
    const pledged = form.data.guarantors.reduce(
        (sum, g) => sum + shillings(g.amount),
        0,
    );
    const monthly = term > 0 ? Math.ceil(amount / term) : 0;

    const setGuarantor = (
        i: number,
        key: 'member_id' | 'amount',
        value: string,
    ) =>
        form.setData(
            'guarantors',
            form.data.guarantors.map((g, j) =>
                j === i ? { ...g, [key]: value } : g,
            ),
        );

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/me/loans');
    };

    return (
        <>
            <Head title="Apply for a loan" />
            <Page className="max-w-3xl">
                <PageHeader
                    title="Apply for a loan"
                    description="Your guarantors are asked to accept in the app. Then the treasurer sends it for the usual two approvals."
                />

                <Section title="The group's lending rules">
                    <RuleList rules={rules} />
                    {blocked && (
                        <p className="mt-3 text-sm text-muted-foreground">
                            You can apply once every rule above is met. Ask the
                            treasurer if something looks wrong.
                        </p>
                    )}
                </Section>

                {!blocked && (
                    <form onSubmit={submit} className="grid gap-5">
                        <ActionError message={actionError(form.errors)} />

                        <Section title="The loan">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label="Amount"
                                    htmlFor="ap-amount"
                                    error={form.errors.amount}
                                    hint={
                                        limits.max_amount !== null
                                            ? `Up to ${formatKes(limits.max_amount)}`
                                            : undefined
                                    }
                                >
                                    <MoneyInput
                                        id="ap-amount"
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
                                    label="Repay over (months)"
                                    htmlFor="ap-term"
                                    error={form.errors.term_months}
                                    hint={`At most ${limits.max_term} months`}
                                >
                                    <Input
                                        id="ap-term"
                                        inputMode="numeric"
                                        value={form.data.term_months}
                                        onChange={(e) =>
                                            form.setData(
                                                'term_months',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </div>
                            <Field
                                label="What is it for?"
                                htmlFor="ap-purpose"
                                error={form.errors.purpose}
                                className="mt-4"
                            >
                                <Textarea
                                    id="ap-purpose"
                                    value={form.data.purpose}
                                    onChange={(e) =>
                                        form.setData('purpose', e.target.value)
                                    }
                                    className="min-h-16"
                                />
                            </Field>
                            {amount > 0 && term > 0 && (
                                <p className="mt-3 rounded-lg bg-muted px-3 py-2 text-sm">
                                    About <strong>{formatKes(monthly)}</strong>{' '}
                                    a month for {term} months
                                    {limits.interest_on &&
                                    limits.interest_rate > 0
                                        ? `, plus interest of ${limits.interest_rate}% ${limits.interest_period === 'once' ? 'once' : `a month on ${limits.interest_method === 'flat' ? 'the amount lent' : 'what is left'}`}.`
                                        : '. No interest is being charged at the moment.'}
                                </p>
                            )}
                        </Section>

                        <Section
                            title="Guarantors"
                            description={
                                limits.guarantors_required > 0
                                    ? `Name at least ${limits.guarantors_required}. Together they must cover what your own savings do not${amount > 0 ? `: ${formatKes(cover)}` : ''}.`
                                    : 'Optional.'
                            }
                            actions={
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        form.setData('guarantors', [
                                            ...form.data.guarantors,
                                            { member_id: '', amount: '' },
                                        ])
                                    }
                                >
                                    <Plus /> Add
                                </Button>
                            }
                        >
                            <div className="grid gap-3">
                                {form.data.guarantors.map((g, i) => (
                                    <div
                                        key={i}
                                        className="grid grid-cols-[1fr_auto] gap-2 sm:grid-cols-[1fr_10rem_auto]"
                                    >
                                        <MemberSelect
                                            aria-label={`Guarantor ${i + 1}`}
                                            members={members}
                                            value={g.member_id}
                                            onChange={(e) =>
                                                setGuarantor(
                                                    i,
                                                    'member_id',
                                                    e.target.value,
                                                )
                                            }
                                            className="col-span-2 sm:col-span-1"
                                        />
                                        <MoneyInput
                                            aria-label={`Amount guarantor ${i + 1} covers`}
                                            placeholder="Amount"
                                            value={g.amount}
                                            onChange={(e) =>
                                                setGuarantor(
                                                    i,
                                                    'amount',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Remove"
                                            onClick={() =>
                                                form.setData(
                                                    'guarantors',
                                                    form.data.guarantors.filter(
                                                        (_, j) => j !== i,
                                                    ),
                                                )
                                            }
                                        >
                                            <Trash2 />
                                        </Button>
                                        {(fieldError(
                                            form.errors,
                                            `guarantors.${i}.member_id`,
                                        ) ||
                                            fieldError(
                                                form.errors,
                                                `guarantors.${i}.amount`,
                                            )) && (
                                            <p className="col-span-full text-sm text-red-600">
                                                {fieldError(
                                                    form.errors,
                                                    `guarantors.${i}.member_id`,
                                                ) ??
                                                    fieldError(
                                                        form.errors,
                                                        `guarantors.${i}.amount`,
                                                    )}
                                            </p>
                                        )}
                                    </div>
                                ))}
                                {amount > 0 && (
                                    <p
                                        className={
                                            pledged >= cover
                                                ? 'text-sm text-emerald-700 dark:text-emerald-400'
                                                : 'text-sm text-muted-foreground'
                                        }
                                    >
                                        Guaranteed so far: {formatKes(pledged)}{' '}
                                        of {formatKes(cover)} needed
                                    </p>
                                )}
                            </div>
                        </Section>

                        <div className="flex justify-end">
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <Spinner />}
                                Send application
                            </Button>
                        </div>
                    </form>
                )}
            </Page>
        </>
    );
}

ApplyForLoan.layout = {
    breadcrumbs: [
        { title: 'My loans', href: '/me/loans' },
        { title: 'Apply', href: '/me/loans/apply' },
    ],
};
