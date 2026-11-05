import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, Check, Copy, ShieldCheck } from 'lucide-react';
import type { ComponentType, ReactNode, SVGAttributes } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import {
    FundraiserArt,
    HeroArt,
    ProjectsArt,
    RoundArt,
    SavingsArt,
    WelfareArt,
    Wave,
} from '@/components/kertai/welcome-art';
import { Progress } from '@/components/kertai/page';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import { formatKes, percent } from '@/lib/format';

type Props = {
    canRegister: boolean;
    group: {
        name: string;
        location: string | null;
        paybill: string | null;
        paybill_account: string | null;
    } | null;
    fundraisers: {
        slug: string;
        title: string;
        summary: string | null;
        target: number;
        raised: number;
    }[];
};

const PROGRAMMES: {
    art: ComponentType<SVGAttributes<SVGElement>>;
    title: string;
    text: string;
}[] = [
    {
        art: SavingsArt,
        title: 'Savings and contributions',
        text: 'Every shilling recorded and receipted by SMS, so your balance is never a guess.',
    },
    {
        art: RoundArt,
        title: 'Merry-go-round',
        text: 'A fair ballot, swaps by agreement, and payouts on time.',
    },
    {
        art: WelfareArt,
        title: 'Welfare',
        text: 'Support for members and their families when it matters most.',
    },
    {
        art: ProjectsArt,
        title: 'Projects',
        text: 'Coffee seedlings, sugarcane, and hire of tents and chairs.',
    },
];

const PROMISES = [
    'Every payment receipted by SMS',
    'Two officials approve every payout',
    'Open books for every member',
];

export default function Welcome({ canRegister, group, fundraisers }: Props) {
    const { auth } = usePage().props;
    const name = group?.name ?? 'Kertai';
    const signedIn = Boolean(auth?.user);

    return (
        <>
            <Head title="Welcome" />
            <div className="relative min-h-screen overflow-hidden bg-[#f7fbf6] text-foreground dark:bg-background">
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute -top-40 -left-32 size-[32rem] rounded-full bg-emerald-200/50 blur-3xl dark:bg-emerald-900/30"
                />
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute top-24 -right-40 size-[28rem] rounded-full bg-amber-200/40 blur-3xl dark:bg-amber-900/20"
                />

                <div className="relative mx-auto max-w-6xl px-4">
                    <header className="flex items-center justify-between gap-4 py-6">
                        <Link href="/" className="flex items-center gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-emerald-700 text-white shadow-sm shadow-emerald-900/20">
                                <AppLogoIcon className="size-6" />
                            </span>
                            <span className="font-semibold">{name}</span>
                        </Link>
                        <nav className="flex items-center gap-2">
                            {group?.paybill && (
                                <a
                                    href="#pay"
                                    className="hidden rounded-lg px-3 py-2 text-sm font-medium text-muted-foreground hover:text-foreground sm:block"
                                >
                                    How to pay
                                </a>
                            )}
                            {fundraisers.length > 0 && (
                                <a
                                    href="#fundraisers"
                                    className="hidden rounded-lg px-3 py-2 text-sm font-medium text-muted-foreground hover:text-foreground sm:block"
                                >
                                    Fundraisers
                                </a>
                            )}
                            <Button asChild>
                                <Link href={signedIn ? '/dashboard' : '/login'}>
                                    {signedIn
                                        ? 'Open dashboard'
                                        : 'Member login'}
                                </Link>
                            </Button>
                        </nav>
                    </header>

                    <section className="grid items-center gap-10 py-8 md:grid-cols-[1.05fr_1fr] md:py-14">
                        <div className="space-y-6">
                            <span className="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-white/70 px-3 py-1 text-xs font-medium text-emerald-800 backdrop-blur dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-200">
                                <span className="size-1.5 rounded-full bg-emerald-500" />
                                Self help group
                                {group?.location ? ` · ${group.location}` : ''}
                            </span>
                            <h1 className="text-4xl leading-[1.05] font-semibold tracking-tight md:text-6xl">
                                Saving, investing and caring for each other,{' '}
                                <span className="relative inline-block text-emerald-700 dark:text-emerald-400">
                                    together.
                                    <svg
                                        aria-hidden="true"
                                        viewBox="0 0 220 16"
                                        className="absolute -bottom-2 left-0 h-3 w-full text-amber-400"
                                        preserveAspectRatio="none"
                                    >
                                        <path
                                            d="M3 11C50 4 120 3 217 9"
                                            stroke="currentColor"
                                            strokeWidth="5"
                                            strokeLinecap="round"
                                            fill="none"
                                        />
                                    </svg>
                                </span>
                            </h1>
                            <p className="max-w-xl text-lg text-muted-foreground">
                                {name} keeps its books, merry-go-round, welfare
                                and projects here, open to every member.
                            </p>
                            <div className="flex flex-wrap gap-3">
                                <Button size="lg" asChild>
                                    <Link
                                        href={
                                            signedIn ? '/dashboard' : '/login'
                                        }
                                    >
                                        {signedIn
                                            ? 'Open dashboard'
                                            : 'Member login'}
                                        <ArrowRight />
                                    </Link>
                                </Button>
                                {canRegister && !signedIn ? (
                                    <Button size="lg" variant="outline" asChild>
                                        <Link href="/register">
                                            Request a login
                                        </Link>
                                    </Button>
                                ) : (
                                    group?.paybill && (
                                        <Button
                                            size="lg"
                                            variant="outline"
                                            asChild
                                        >
                                            <a href="#pay">How to pay</a>
                                        </Button>
                                    )
                                )}
                            </div>
                            <ul className="grid gap-2 pt-2 text-sm text-muted-foreground sm:grid-cols-3 sm:gap-4">
                                {PROMISES.map((p) => (
                                    <li
                                        key={p}
                                        className="flex items-start gap-2"
                                    >
                                        <ShieldCheck className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                                        {p}
                                    </li>
                                ))}
                            </ul>
                        </div>

                        <div className="relative">
                            <div
                                aria-hidden="true"
                                className="absolute inset-4 -z-10 rotate-3 rounded-[2.5rem] bg-emerald-700/10 dark:bg-emerald-400/10"
                            />
                            <HeroArt className="w-full drop-shadow-xl" />
                        </div>
                    </section>
                </div>

                {group?.paybill && (
                    <section id="pay" className="relative scroll-mt-6">
                        <Wave className="block h-12 w-full text-emerald-800 md:h-16 dark:text-emerald-950" />
                        <div className="bg-emerald-800 text-white dark:bg-emerald-950">
                            <div className="mx-auto grid max-w-6xl gap-8 px-4 py-12 md:grid-cols-[1fr_1.4fr] md:items-center">
                                <div className="space-y-3">
                                    <h2 className="text-3xl font-semibold tracking-tight">
                                        Pay your contribution with{' '}
                                        <span className="whitespace-nowrap">
                                            M-Pesa
                                        </span>
                                    </h2>
                                    <p className="text-emerald-100/90">
                                        Payments to the group paybill are
                                        matched to your account and you get an
                                        SMS receipt.
                                    </p>
                                </div>
                                <ol className="grid gap-3 sm:grid-cols-2">
                                    <Step n={1} title="Open M-Pesa">
                                        Lipa na M-Pesa, then Pay Bill
                                    </Step>
                                    <Step n={2} title="Business number">
                                        <CopyValue value={group.paybill} />
                                    </Step>
                                    <Step n={3} title="Account number">
                                        <CopyValue
                                            value={group.paybill_account ?? ''}
                                        />
                                    </Step>
                                    <Step n={4} title="Amount and PIN">
                                        Enter the amount, then your M-Pesa PIN
                                    </Step>
                                </ol>
                            </div>
                        </div>
                        <Wave className="block h-12 w-full rotate-180 text-emerald-800 md:h-16 dark:text-emerald-950" />
                    </section>
                )}

                <div className="relative mx-auto max-w-6xl space-y-20 px-4 py-14">
                    <section className="space-y-8">
                        <div className="max-w-2xl space-y-2">
                            <h2 className="text-3xl font-semibold tracking-tight">
                                What we do together
                            </h2>
                            <p className="text-muted-foreground">
                                Everything the group runs, in one place, so
                                every member can see where the money goes.
                            </p>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {PROGRAMMES.map((p) => (
                                <div
                                    key={p.title}
                                    className="group space-y-3 rounded-3xl border bg-card p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-lg"
                                >
                                    <p.art className="size-16 transition group-hover:scale-105" />
                                    <h3 className="font-semibold">{p.title}</h3>
                                    <p className="text-sm text-muted-foreground">
                                        {p.text}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </section>

                    {fundraisers.length > 0 && (
                        <section
                            id="fundraisers"
                            className="scroll-mt-6 space-y-8"
                        >
                            <div className="max-w-2xl space-y-2">
                                <h2 className="text-3xl font-semibold tracking-tight">
                                    Support our fundraisers
                                </h2>
                                <p className="text-muted-foreground">
                                    Anyone can give, member or not. Every
                                    contribution is recorded against the
                                    fundraiser.
                                </p>
                            </div>
                            <div className="grid gap-4 md:grid-cols-3">
                                {fundraisers.map((f) => {
                                    const done = percent(f.raised, f.target);

                                    return (
                                        <Link
                                            key={f.slug}
                                            href={`/f/${f.slug}`}
                                            className="group flex flex-col overflow-hidden rounded-3xl border bg-card shadow-sm transition hover:-translate-y-1 hover:shadow-lg"
                                        >
                                            <div className="flex items-end justify-between bg-gradient-to-br from-emerald-50 to-amber-50 px-6 pt-6 dark:from-emerald-950/60 dark:to-amber-950/30">
                                                <FundraiserArt className="h-16 w-auto" />
                                                <span className="mb-4 rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-emerald-800 shadow-sm dark:bg-emerald-900 dark:text-emerald-100">
                                                    {done}% raised
                                                </span>
                                            </div>
                                            <div className="flex flex-1 flex-col gap-3 p-6">
                                                <h3 className="font-semibold">
                                                    {f.title}
                                                </h3>
                                                {f.summary && (
                                                    <p className="text-sm text-muted-foreground">
                                                        {f.summary}
                                                    </p>
                                                )}
                                                <div className="mt-auto space-y-2 pt-2">
                                                    <Progress value={done} />
                                                    <div className="flex items-center justify-between text-sm">
                                                        <span>
                                                            {formatKes(
                                                                f.raised,
                                                            )}{' '}
                                                            <span className="text-muted-foreground">
                                                                of{' '}
                                                                {formatKes(
                                                                    f.target,
                                                                )}
                                                            </span>
                                                        </span>
                                                        <span className="inline-flex items-center gap-1 font-medium text-emerald-700 dark:text-emerald-400">
                                                            Give
                                                            <ArrowRight className="size-4 transition group-hover:translate-x-0.5" />
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </Link>
                                    );
                                })}
                            </div>
                        </section>
                    )}

                    <section className="relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-emerald-700 to-emerald-900 px-6 py-12 text-white md:px-12">
                        <svg
                            aria-hidden="true"
                            viewBox="0 0 600 200"
                            className="absolute right-0 bottom-0 w-[36rem] max-w-none opacity-20"
                        >
                            <path
                                d="M0 200C80 120 180 100 280 140C380 180 480 110 600 90V200Z"
                                fill="#a7f3d0"
                            />
                            <path
                                d="M120 200C200 150 300 150 400 170C480 186 540 160 600 150V200Z"
                                fill="#ffffff"
                            />
                        </svg>
                        <div className="relative max-w-xl space-y-4">
                            <h2 className="text-3xl font-semibold tracking-tight">
                                {signedIn
                                    ? 'Welcome back'
                                    : 'Already a member?'}
                            </h2>
                            <p className="text-emerald-100">
                                Check your savings, statements, loans and
                                merry-go-round turn from your phone, any time.
                            </p>
                            <div className="flex flex-wrap gap-3">
                                <Button size="lg" variant="secondary" asChild>
                                    <Link
                                        href={
                                            signedIn ? '/dashboard' : '/login'
                                        }
                                    >
                                        {signedIn
                                            ? 'Open dashboard'
                                            : 'Member login'}
                                    </Link>
                                </Button>
                                {canRegister && !signedIn && (
                                    <Button
                                        size="lg"
                                        variant="outline"
                                        className="border-white/40 bg-transparent text-white hover:bg-white/10 hover:text-white"
                                        asChild
                                    >
                                        <Link href="/register">
                                            Request a login
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        </div>
                    </section>
                </div>

                <footer className="border-t">
                    <div className="mx-auto flex max-w-6xl flex-col gap-2 px-4 py-6 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                        <span>
                            © {new Date().getFullYear()} {name}
                        </span>
                        <span>
                            Members sign in with the phone number the group has
                            on record.
                        </span>
                    </div>
                </footer>
            </div>
        </>
    );
}

function Step({
    n,
    title,
    children,
}: {
    n: number;
    title: string;
    children: ReactNode;
}) {
    return (
        <li className="flex gap-3 rounded-2xl bg-white/10 p-4 ring-1 ring-white/15 backdrop-blur">
            <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-amber-400 text-sm font-bold text-emerald-950">
                {n}
            </span>
            <div className="min-w-0 space-y-1">
                <div className="text-sm font-semibold">{title}</div>
                <div className="text-sm text-emerald-50/90">{children}</div>
            </div>
        </li>
    );
}

function CopyValue({ value }: { value: string }) {
    const [copied, copy] = useClipboard();

    return (
        <button
            type="button"
            onClick={() => copy(value)}
            className="inline-flex items-center gap-2 rounded-lg bg-white/15 px-2.5 py-1 font-mono text-base font-semibold tracking-wide text-white transition hover:bg-white/25"
            aria-label={`Copy ${value}`}
        >
            {value}
            {copied === value ? (
                <Check className="size-4 text-amber-300" />
            ) : (
                <Copy className="size-4 opacity-70" />
            )}
        </button>
    );
}
