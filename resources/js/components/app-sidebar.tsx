import { Link, usePage } from '@inertiajs/react';
import {
    Archive,
    BadgeCheck,
    BookOpenText,
    CalendarDays,
    ChartColumn,
    ChartPie,
    FileText,
    HandCoins,
    HeartHandshake,
    KeyRound,
    Landmark,
    LayoutGrid,
    Megaphone,
    MessageSquare,
    PiggyBank,
    Receipt,
    RefreshCw,
    ScrollText,
    Settings2,
    ShieldCheck,
    Sprout,
    Tent,
    Users,
    Vote,
    Wallet,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';

type Entry = NavItem & { can?: string | string[] };

const sections: { label: string; items: Entry[] }[] = [
    {
        label: 'My group',
        items: [
            { title: 'Dashboard', href: '/dashboard', icon: LayoutGrid },
            { title: 'Merry-go-round', href: '/mgr', icon: RefreshCw },
            { title: 'Fundraisers', href: '/fundraisers', icon: Megaphone },
            { title: 'Meetings', href: '/meetings', icon: CalendarDays },
            { title: 'Votes', href: '/votes', icon: Vote },
            { title: 'Documents', href: '/documents', icon: FileText },
        ],
    },
    {
        label: 'Money',
        items: [
            {
                title: 'Contributions',
                href: '/dues',
                icon: PiggyBank,
                can: 'finance.view',
            },
            {
                title: 'Payments',
                href: '/payments',
                icon: Receipt,
                can: 'finance.view',
            },
            {
                title: 'Payouts',
                href: '/payouts',
                icon: HandCoins,
                can: 'finance.view',
            },
            {
                title: 'Ledger',
                href: '/ledger',
                icon: BookOpenText,
                can: 'finance.view',
            },
            {
                title: 'Reports',
                href: '/reports',
                icon: ChartColumn,
                can: 'finance.view',
            },
            {
                title: 'Loans',
                href: '/loans',
                icon: Landmark,
                can: 'finance.view',
            },
            {
                title: 'Share-out',
                href: '/share-out',
                icon: Wallet,
                can: 'finance.view',
            },
        ],
    },
    {
        label: 'Programmes',
        items: [
            {
                title: 'Welfare',
                href: '/welfare',
                icon: HeartHandshake,
                can: 'welfare.manage',
            },
            {
                title: 'Projects',
                href: '/projects',
                icon: Sprout,
                can: 'projects.view',
            },
            { title: 'Assets & hire', href: '/assets', icon: Tent },
        ],
    },
    {
        label: 'Administration',
        items: [
            {
                title: 'Members',
                href: '/members',
                icon: Users,
                can: 'members.view',
            },
            {
                title: 'Positions',
                href: '/positions',
                icon: BadgeCheck,
                can: 'positions.manage',
            },
            {
                title: 'Messages',
                href: '/messages',
                icon: MessageSquare,
                can: 'messages.send',
            },
            {
                title: 'Group rules',
                href: '/rules',
                icon: Settings2,
                can: 'settings.manage',
            },
            {
                title: 'Integrations',
                href: '/admin/integrations',
                icon: KeyRound,
                can: 'integrations.manage',
            },
            {
                title: 'Audit log',
                href: '/admin/audit',
                icon: ScrollText,
                can: 'audit.view',
            },
            {
                title: 'Backups',
                href: '/admin/backups',
                icon: Archive,
                can: 'backups.manage',
            },
        ],
    },
];

export function AppSidebar() {
    const { auth, group } = usePage().props;
    const permissions = auth?.permissions ?? [];
    const memberId = auth?.member?.id;

    const allowed = (entry: Entry) => {
        if (!entry.can) {
            return true;
        }

        const needed = Array.isArray(entry.can) ? entry.can : [entry.can];

        return needed.some((p) => permissions.includes(p));
    };

    const mine: Entry[] = memberId
        ? [
              ...(group?.open_books
                  ? [
                        {
                            title: 'Group finances',
                            href: '/group-finances',
                            icon: ChartPie,
                        },
                    ]
                  : []),
              ...(auth?.member?.has_loans || group?.loans_enabled
                  ? [
                        {
                            title: 'My loans',
                            href: '/me/loans',
                            icon: HandCoins,
                        },
                    ]
                  : []),
              {
                  title: 'My statement',
                  href: `/statements/${memberId}`,
                  icon: ShieldCheck,
              },
              {
                  title: 'My profile',
                  href: `/members/${memberId}`,
                  icon: Users,
              },
          ]
        : [];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {sections.map((section, i) => (
                    <NavMain
                        key={section.label}
                        label={section.label}
                        items={[
                            ...section.items.filter(allowed),
                            ...(i === 0 ? mine : []),
                        ]}
                    />
                ))}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
