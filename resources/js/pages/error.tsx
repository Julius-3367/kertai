import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Clock, SearchX, ServerCrash, ShieldX } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';

type Props = {
    status: number;
    message: string | null;
};

const COPY = {
    403: {
        icon: ShieldX,
        title: "You don't have access to this page",
        text: 'This part of Kertai is for officials whose position needs it, such as the treasurer or secretary. If you think you should have access, ask the chairperson.',
    },
    404: {
        icon: SearchX,
        title: 'Page not found',
        text: 'The link may be old, or the record may have been removed.',
    },
    500: {
        icon: ServerCrash,
        title: 'Something went wrong',
        text: 'The problem has been logged. Please try again in a moment.',
    },
    503: {
        icon: Clock,
        title: 'Kertai is being updated',
        text: 'This usually takes a minute or two. Please try again shortly.',
    },
} as const;

export default function ErrorPage({ status, message }: Props) {
    const { auth } = usePage().props;
    const copy = COPY[status as keyof typeof COPY] ?? COPY[500];
    const Icon = copy.icon;
    const signedIn = Boolean(auth?.user);

    const content = (
        <div className="flex flex-1 items-center justify-center p-6">
            <div className="flex max-w-md flex-col items-center gap-4 text-center">
                <div className="flex size-14 items-center justify-center rounded-full bg-muted">
                    <Icon className="size-7 text-muted-foreground" />
                </div>
                <div className="space-y-2">
                    <h1 className="text-xl font-semibold tracking-tight">
                        {copy.title}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {message ?? copy.text}
                    </p>
                </div>
                <div className="flex flex-wrap justify-center gap-2">
                    <Button
                        variant="outline"
                        onClick={() => window.history.back()}
                    >
                        <ArrowLeft />
                        Go back
                    </Button>
                    <Button asChild>
                        <Link href={signedIn ? '/dashboard' : '/'}>
                            {signedIn ? 'Dashboard' : 'Home'}
                        </Link>
                    </Button>
                </div>
                <p className="text-xs text-muted-foreground">Error {status}</p>
            </div>
        </div>
    );

    if (signedIn) {
        return (
            <AppLayout>
                <Head title={copy.title} />
                {content}
            </AppLayout>
        );
    }

    return (
        <div className="flex min-h-svh flex-col bg-background">
            <Head title={copy.title} />
            <div className="flex items-center gap-2 p-4">
                <AppLogoIcon className="size-7 text-emerald-700" />
                <span className="font-semibold">Kertai</span>
            </div>
            {content}
        </div>
    );
}
