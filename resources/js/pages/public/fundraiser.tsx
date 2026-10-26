import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    Clock,
    Loader2,
    Smartphone,
    XCircle,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { actionError } from '@/components/kertai/actions';
import { ActionError, CheckField, Field } from '@/components/kertai/fields';
import { Progress } from '@/components/kertai/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDate, formatKes, percent } from '@/lib/format';

type Props = {
    fundraiser: {
        slug: string;
        title: string;
        summary: string | null;
        description: string | null;
        beneficiary: string | null;
        target: number;
        raised: number;
        count: number;
        ends_on: string | null;
        open: boolean;
        group: string;
    };
    contributors: { name: string; amount: number; date: string | null }[];
};

type Status = { status: string; message: string; receipt: string | null };

const QUICK = [500, 1000, 2000, 5000];

export default function PublicFundraiser({ fundraiser, contributors }: Props) {
    const page = usePage();
    const [paymentKey, setPaymentKey] = useState<string | null>(null);
    const [status, setStatus] = useState<Status | null>(null);
    const form = useForm({
        name: '',
        phone: '',
        amount: '1000',
        show_name: true,
    });
    const progress = percent(fundraiser.raised, fundraiser.target);

    useEffect(() => {
        const key = page.flash?.payment_key;

        if (key) {
            setPaymentKey(key);
            setStatus({
                status: 'processing',
                message: 'Check your phone and enter your M-Pesa PIN.',
                receipt: null,
            });
        }
    }, [page.flash]);

    useEffect(() => {
        if (!paymentKey) {
            return;
        }

        let tries = 0;
        const timer = window.setInterval(async () => {
            tries++;

            try {
                const res = await fetch(
                    `/f/${fundraiser.slug}/payments/${paymentKey}`,
                    { headers: { Accept: 'application/json' } },
                );
                const data = (await res.json()) as Status;
                const stillWaiting = ['pending', 'processing'].includes(
                    data.status,
                );

                // About 2.5 minutes with no answer from M-Pesa: stop the
                // spinner, but warn against paying twice.
                if (stillWaiting && tries > 40) {
                    window.clearInterval(timer);
                    setStatus({
                        status: 'unconfirmed',
                        message:
                            "We haven't had a confirmation from M-Pesa yet. If you entered your PIN and got an M-Pesa message, your gift is recorded and will show here shortly, so don't pay again. If you didn't get the prompt, try again.",
                        receipt: null,
                    });

                    return;
                }

                setStatus(data);

                if (!stillWaiting) {
                    window.clearInterval(timer);

                    if (data.status === 'succeeded') {
                        router.reload({ only: ['fundraiser', 'contributors'] });
                    }
                }
            } catch {
                // Network blips: keep polling.
            }
        }, 4000);

        return () => window.clearInterval(timer);
    }, [paymentKey, fundraiser.slug]);

    return (
        <>
            <Head title={fundraiser.title}>
                <meta
                    name="description"
                    content={fundraiser.summary ?? fundraiser.title}
                />
                <meta property="og:title" content={fundraiser.title} />
                <meta
                    property="og:description"
                    content={
                        fundraiser.summary ?? `Support ${fundraiser.group}`
                    }
                />
            </Head>
            <div className="min-h-screen bg-gradient-to-b from-emerald-50 to-background dark:from-emerald-950/40">
                <div className="mx-auto flex max-w-5xl flex-col gap-8 px-4 py-8 md:py-12">
                    <header className="flex items-center gap-3">
                        <div className="flex size-10 items-center justify-center rounded-lg bg-emerald-700 text-white">
                            <AppLogoIcon className="size-6" />
                        </div>
                        <div>
                            <div className="text-sm text-muted-foreground">
                                {fundraiser.group}
                            </div>
                            <h1 className="text-2xl font-semibold tracking-tight md:text-3xl">
                                {fundraiser.title}
                            </h1>
                        </div>
                    </header>

                    <div className="grid gap-8 md:grid-cols-[3fr_2fr]">
                        <div className="space-y-6">
                            <div className="space-y-3 rounded-2xl border bg-card p-5 shadow-sm">
                                <div className="flex items-end justify-between gap-2">
                                    <div>
                                        <div className="text-3xl font-semibold tabular-nums">
                                            {formatKes(fundraiser.raised)}
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            raised of{' '}
                                            {formatKes(fundraiser.target)} goal
                                        </div>
                                    </div>
                                    <div className="text-right text-sm text-muted-foreground">
                                        <div className="text-lg font-semibold text-foreground">
                                            {progress}%
                                        </div>
                                        {fundraiser.count} contributions
                                    </div>
                                </div>
                                <Progress value={progress} />
                                {fundraiser.ends_on && (
                                    <div className="text-xs text-muted-foreground">
                                        Closes {formatDate(fundraiser.ends_on)}
                                    </div>
                                )}
                            </div>

                            {fundraiser.summary && (
                                <p className="text-lg">{fundraiser.summary}</p>
                            )}
                            {fundraiser.description && (
                                <p className="whitespace-pre-line text-muted-foreground">
                                    {fundraiser.description}
                                </p>
                            )}
                            {fundraiser.beneficiary && (
                                <p className="text-sm">
                                    <span className="text-muted-foreground">
                                        Beneficiary:
                                    </span>{' '}
                                    {fundraiser.beneficiary}
                                </p>
                            )}

                            {contributors.length > 0 && (
                                <div className="space-y-2">
                                    <h2 className="font-semibold">
                                        Recent supporters
                                    </h2>
                                    <ul className="divide-y rounded-xl border bg-card">
                                        {contributors.map((c, i) => (
                                            <li
                                                key={i}
                                                className="flex justify-between px-4 py-2 text-sm"
                                            >
                                                <span>{c.name}</span>
                                                <span className="tabular-nums">
                                                    {formatKes(c.amount)}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </div>

                        <aside className="h-fit space-y-4 rounded-2xl border bg-card p-5 shadow-sm md:sticky md:top-6">
                            {status ? (
                                <PaymentStatus
                                    status={status}
                                    onAgain={() => {
                                        setStatus(null);
                                        setPaymentKey(null);
                                    }}
                                />
                            ) : fundraiser.open ? (
                                <form
                                    className="space-y-4"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        form.post(`/f/${fundraiser.slug}/pay`, {
                                            preserveScroll: true,
                                        });
                                    }}
                                >
                                    <h2 className="text-lg font-semibold">
                                        Give with M-Pesa
                                    </h2>
                                    <ActionError
                                        message={actionError(form.errors)}
                                    />
                                    <div className="grid grid-cols-4 gap-2">
                                        {QUICK.map((q) => (
                                            <Button
                                                key={q}
                                                type="button"
                                                size="sm"
                                                variant={
                                                    form.data.amount ===
                                                    String(q)
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                onClick={() =>
                                                    form.setData(
                                                        'amount',
                                                        String(q),
                                                    )
                                                }
                                            >
                                                {q.toLocaleString()}
                                            </Button>
                                        ))}
                                    </div>
                                    <Field
                                        label="Amount (KES)"
                                        htmlFor="amount"
                                        error={form.errors.amount}
                                    >
                                        <Input
                                            id="amount"
                                            inputMode="numeric"
                                            value={form.data.amount}
                                            onChange={(e) =>
                                                form.setData(
                                                    'amount',
                                                    e.target.value.replace(
                                                        /\D/g,
                                                        '',
                                                    ),
                                                )
                                            }
                                        />
                                    </Field>
                                    <Field
                                        label="Your name"
                                        htmlFor="name"
                                        error={form.errors.name}
                                    >
                                        <Input
                                            id="name"
                                            autoComplete="name"
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
                                        label="M-Pesa number"
                                        htmlFor="phone"
                                        error={form.errors.phone}
                                    >
                                        <Input
                                            id="phone"
                                            inputMode="tel"
                                            autoComplete="tel"
                                            placeholder="0712 345 678"
                                            value={form.data.phone}
                                            onChange={(e) =>
                                                form.setData(
                                                    'phone',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                    <CheckField
                                        id="show_name"
                                        label="Show my name as a supporter"
                                        hint="Untick to give anonymously."
                                        checked={form.data.show_name}
                                        onChange={(v) =>
                                            form.setData('show_name', v)
                                        }
                                    />
                                    <Button
                                        className="w-full bg-emerald-700 hover:bg-emerald-800"
                                        disabled={form.processing}
                                    >
                                        {form.processing ? (
                                            <Loader2 className="animate-spin" />
                                        ) : (
                                            <Smartphone />
                                        )}
                                        Send M-Pesa prompt
                                    </Button>
                                    <p className="text-xs text-muted-foreground">
                                        You will get a prompt on your phone.
                                        Payments are processed by SimulinkPay.
                                    </p>
                                </form>
                            ) : (
                                <div className="space-y-2 text-center">
                                    <h2 className="text-lg font-semibold">
                                        This fundraiser is closed
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Thank you to everyone who gave.
                                    </p>
                                </div>
                            )}
                        </aside>
                    </div>
                </div>
            </div>
        </>
    );
}

function PaymentStatus({
    status,
    onAgain,
}: {
    status: Status;
    onAgain: () => void;
}) {
    const waiting = ['pending', 'processing'].includes(status.status);
    const ok = status.status === 'succeeded';
    const unconfirmed = status.status === 'unconfirmed';

    return (
        <div className="space-y-4 py-4 text-center">
            {waiting && (
                <Loader2 className="mx-auto size-10 animate-spin text-emerald-700" />
            )}
            {ok && (
                <CheckCircle2 className="mx-auto size-10 text-emerald-700" />
            )}
            {unconfirmed && (
                <Clock className="mx-auto size-10 text-amber-600" />
            )}
            {!waiting && !ok && !unconfirmed && (
                <XCircle className="mx-auto size-10 text-red-600" />
            )}
            <p className="font-medium">{status.message}</p>
            {status.receipt && (
                <p className="font-mono text-sm text-muted-foreground">
                    Receipt {status.receipt}
                </p>
            )}
            {!waiting && (
                <Button variant="outline" onClick={onAgain}>
                    {ok ? 'Give again' : 'Try again'}
                </Button>
            )}
        </div>
    );
}
