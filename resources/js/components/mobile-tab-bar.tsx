import { Link, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    FileText,
    LayoutGrid,
    Menu,
    RefreshCw,
} from 'lucide-react';
import { useSidebar } from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';

/**
 * Bottom bar for phones: the screens a member opens most, one tap away.
 * Everything else is under Menu (the sidebar as a drawer).
 */
export function MobileTabBar() {
    const { auth } = usePage().props;
    const { setOpenMobile } = useSidebar();
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
    const memberId = auth?.member?.id;

    const tabs = [
        { label: 'Home', href: '/dashboard', icon: LayoutGrid, exact: true },
        ...(memberId
            ? [
                  {
                      label: 'Statement',
                      href: `/statements/${memberId}`,
                      icon: FileText,
                      exact: true,
                  },
              ]
            : []),
        { label: 'Rotation', href: '/mgr', icon: RefreshCw, exact: false },
        {
            label: 'Meetings',
            href: '/meetings',
            icon: CalendarDays,
            exact: false,
        },
    ];

    return (
        <nav
            aria-label="Main"
            className="fixed inset-x-0 bottom-0 z-40 border-t bg-background/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden"
        >
            <ul className="grid grid-cols-5">
                {tabs.map((tab) => {
                    const active = tab.exact
                        ? isCurrentUrl(tab.href)
                        : isCurrentOrParentUrl(tab.href);

                    return (
                        <li key={tab.href}>
                            <Link
                                href={tab.href}
                                prefetch
                                className={cn(
                                    'flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium',
                                    active
                                        ? 'text-primary'
                                        : 'text-muted-foreground',
                                )}
                            >
                                <tab.icon className="size-5" />
                                {tab.label}
                            </Link>
                        </li>
                    );
                })}
                <li className={tabs.length < 4 ? 'col-start-5' : undefined}>
                    <button
                        type="button"
                        onClick={() => setOpenMobile(true)}
                        className="flex w-full flex-col items-center gap-0.5 py-2 text-[11px] font-medium text-muted-foreground"
                    >
                        <Menu className="size-5" />
                        Menu
                    </button>
                </li>
            </ul>
        </nav>
    );
}
