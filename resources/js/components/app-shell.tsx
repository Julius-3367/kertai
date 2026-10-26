import { router, usePage } from '@inertiajs/react';
import { useEffect, type ReactNode } from 'react';
import { SidebarProvider, useSidebar } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

// On phones the sidebar is a drawer, and an Inertia page change doesn't close it,
// so the new page loaded behind the open menu.
function CloseMobileSidebarOnNavigate() {
    const { setOpenMobile } = useSidebar();

    useEffect(
        () => router.on('navigate', () => setOpenMobile(false)),
        [setOpenMobile],
    );

    return null;
}

type Props = {
    children: ReactNode;
    variant?: AppVariant;
};

export function AppShell({ children, variant = 'sidebar' }: Props) {
    const isOpen = usePage().props.sidebarOpen;

    if (variant === 'header') {
        return (
            <div className="flex min-h-screen w-full flex-col">{children}</div>
        );
    }

    return (
        <SidebarProvider defaultOpen={isOpen}>
            <CloseMobileSidebarOnNavigate />
            {children}
        </SidebarProvider>
    );
}
