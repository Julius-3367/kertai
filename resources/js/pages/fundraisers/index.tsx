import { Head, Link } from '@inertiajs/react';
import { ExternalLink, Plus } from 'lucide-react';
import {
    EmptyState,
    Page,
    PageHeader,
    Progress,
} from '@/components/kertai/page';
import { StatusBadge } from '@/components/kertai/status-badge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { formatDate, formatKes, percent } from '@/lib/format';

type Row = {
    id: number;
    title: string;
    slug: string;
    status: string;
    target: number;
    raised: number;
    starts_on: string;
    ends_on: string | null;
    public_url: string;
};

export default function FundraisersIndex({
    fundraisers,
}: {
    fundraisers: Row[];
}) {
    const can = useCan();

    return (
        <>
            <Head title="Fundraisers" />
            <Page>
                <PageHeader
                    title="Fundraisers"
                    description="Harambees and events with a public page anyone can give through."
                    actions={
                        can('fundraisers.manage') && (
                            <Button asChild>
                                <Link href="/fundraisers/create">
                                    <Plus /> New fundraiser
                                </Link>
                            </Button>
                        )
                    }
                />
                {fundraisers.length === 0 ? (
                    <EmptyState title="No fundraisers yet" />
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {fundraisers.map((f) => (
                            <div
                                key={f.id}
                                className="flex flex-col gap-3 rounded-xl border bg-card p-4 shadow-xs"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <Link
                                        href={`/fundraisers/${f.id}`}
                                        className="font-semibold hover:underline"
                                    >
                                        {f.title}
                                    </Link>
                                    <StatusBadge status={f.status} />
                                </div>
                                <Progress value={percent(f.raised, f.target)} />
                                <div className="flex justify-between text-sm">
                                    <span className="font-medium">
                                        {formatKes(f.raised)}
                                    </span>
                                    <span className="text-muted-foreground">
                                        of {formatKes(f.target)}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between text-xs text-muted-foreground">
                                    <span>
                                        {formatDate(f.starts_on)}
                                        {f.ends_on
                                            ? ` – ${formatDate(f.ends_on)}`
                                            : ''}
                                    </span>
                                    {f.status !== 'draft' && (
                                        <a
                                            href={f.public_url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-1 underline"
                                        >
                                            Public page{' '}
                                            <ExternalLink className="size-3" />
                                        </a>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </Page>
        </>
    );
}

FundraisersIndex.layout = {
    breadcrumbs: [{ title: 'Fundraisers', href: '/fundraisers' }],
};
