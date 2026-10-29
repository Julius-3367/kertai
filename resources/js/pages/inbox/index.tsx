import { Head, router } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';
import { ActionButton } from '@/components/kertai/actions';
import {
    EmptyState,
    Page,
    PageHeader,
    Section,
} from '@/components/kertai/page';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';

type Item = {
    id: number;
    title: string;
    body: string;
    url: string | null;
    read: boolean;
    at: string;
};

export default function InboxIndex({ items }: { items: Item[] }) {
    const unread = items.filter((i) => !i.read).length;

    return (
        <>
            <Head title="Notifications" />
            <Page>
                <PageHeader
                    title="Notifications"
                    description="Everything Kertai has sent you by SMS or email, in one place."
                    actions={
                        unread > 0 && (
                            <ActionButton
                                url="/inbox/read-all"
                                variant="outline"
                                size="sm"
                            >
                                <CheckCheck /> Mark all read
                            </ActionButton>
                        )
                    }
                />
                <Section>
                    {items.length === 0 ? (
                        <EmptyState title="No notifications yet">
                            Payments, reminders, votes and approvals will show
                            here.
                        </EmptyState>
                    ) : (
                        <ul className="-my-2 divide-y">
                            {items.map((item) => (
                                <li key={item.id}>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            router.post(
                                                `/inbox/${item.id}/open`,
                                            )
                                        }
                                        className="flex w-full gap-3 py-3 text-left hover:bg-muted/40"
                                    >
                                        <Bell
                                            className={cn(
                                                'mt-0.5 size-4 shrink-0',
                                                item.read
                                                    ? 'text-muted-foreground'
                                                    : 'text-emerald-700 dark:text-emerald-400',
                                            )}
                                        />
                                        <span className="min-w-0 flex-1">
                                            <span className="flex flex-wrap items-baseline justify-between gap-x-3">
                                                <span
                                                    className={cn(
                                                        'text-sm',
                                                        !item.read &&
                                                            'font-semibold',
                                                    )}
                                                >
                                                    {item.title}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {formatDateTime(item.at)}
                                                </span>
                                            </span>
                                            <span className="mt-0.5 block text-sm break-words text-muted-foreground">
                                                {item.body}
                                            </span>
                                        </span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </Section>
            </Page>
        </>
    );
}

InboxIndex.layout = {
    breadcrumbs: [{ title: 'Notifications', href: '/inbox' }],
};
