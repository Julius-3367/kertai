import { Head, Link, usePage } from '@inertiajs/react';
import { HeartHandshake, PiggyBank, RefreshCw, Sprout } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Progress } from '@/components/kertai/page';
import { Button } from '@/components/ui/button';
import { formatKes, percent } from '@/lib/format';

type Props = {
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

const FEATURES = [
    {
        icon: PiggyBank,
        title: 'Savings and contributions',
        text: 'Every shilling recorded and receipted by SMS.',
    },
    {
        icon: RefreshCw,
        title: 'Merry-go-round',
        text: 'A fair ballot, swaps by agreement, payouts on time.',
    },
    {
        icon: HeartHandshake,
        title: 'Welfare',
        text: 'Support for members and their families when it matters.',
    },
    {
        icon: Sprout,
        title: 'Projects',
        text: 'Coffee seedlings, sugarcane and hire of tents and chairs.',
    },
];

export default function Welcome({ group, fundraisers }: Props) {
    const { auth } = usePage().props;
    const name = group?.name ?? 'Kertai';

    return (
        <>
            <Head title="Welcome" />
            <div className="min-h-screen bg-gradient-to-b from-emerald-50 to-background dark:from-emerald-950/40">
                <div className="mx-auto flex max-w-5xl flex-col gap-12 px-4 py-8 md:py-14">
                    <header className="flex items-center justify-between gap-4">
                        <div className="flex items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-lg bg-emerald-700 text-white">
                                <AppLogoIcon className="size-6" />
                            </div>
                            <span className="font-semibold">{name}</span>
                        </div>
                        <Button asChild>
                            <Link href={auth?.user ? '/dashboard' : '/login'}>
                                {auth?.user ? 'Open dashboard' : 'Member login'}
                            </Link>
                        </Button>
                    </header>

                    <section className="space-y-4">
                        <h1 className="max-w-2xl text-3xl font-semibold tracking-tight md:text-5xl">
                            Saving, investing and caring for each other,
                            together.
                        </h1>
                        <p className="max-w-2xl text-lg text-muted-foreground">
                            {name}
                            {group?.location
                                ? ` in ${group.location}`
                                : ''}{' '}
                            keeps its books, merry-go-round, welfare and
                            projects here, open to every member.
                        </p>
                        {group?.paybill && (
                            <p className="text-sm">
                                Pay contributions through paybill{' '}
                                <strong>{group.paybill}</strong>, account{' '}
                                <strong>{group.paybill_account}</strong>.
                            </p>
                        )}
                    </section>

                    {fundraisers.length > 0 && (
                        <section className="space-y-4">
                            <h2 className="text-xl font-semibold">
                                Support our fundraisers
                            </h2>
                            <div className="grid gap-4 md:grid-cols-3">
                                {fundraisers.map((f) => (
                                    <Link
                                        key={f.slug}
                                        href={`/f/${f.slug}`}
                                        className="space-y-3 rounded-2xl border bg-card p-5 shadow-sm transition hover:shadow-md"
                                    >
                                        <div className="font-semibold">
                                            {f.title}
                                        </div>
                                        {f.summary && (
                                            <p className="text-sm text-muted-foreground">
                                                {f.summary}
                                            </p>
                                        )}
                                        <Progress
                                            value={percent(f.raised, f.target)}
                                        />
                                        <div className="text-sm">
                                            {formatKes(f.raised)}{' '}
                                            <span className="text-muted-foreground">
                                                of {formatKes(f.target)}
                                            </span>
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        </section>
                    )}

                    <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {FEATURES.map((f) => (
                            <div
                                key={f.title}
                                className="space-y-2 rounded-2xl border bg-card p-5"
                            >
                                <f.icon className="size-6 text-emerald-700" />
                                <div className="font-medium">{f.title}</div>
                                <p className="text-sm text-muted-foreground">
                                    {f.text}
                                </p>
                            </div>
                        ))}
                    </section>

                    <footer className="text-xs text-muted-foreground">
                        © {new Date().getFullYear()} {name}. Members sign in
                        with the phone number the group has on record.
                    </footer>
                </div>
            </div>
        </>
    );
}
