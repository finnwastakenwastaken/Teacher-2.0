import { Link } from '@inertiajs/react';
import {
    DatabaseBackup,
    FolderTree,
    GraduationCap,
    Globe,
    Images,
    KeyRound,
    Settings,
    LayoutGrid,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
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
import { dashboard } from '@/routes';
import { index as levelsIndex } from '@/routes/admin/levels';
import { index as mediaIndex } from '@/routes/admin/media';
import { index as passwordsIndex } from '@/routes/admin/passwords';
import { index as backupsIndex } from '@/routes/admin/backups';
import { edit as siteSettingsEdit } from '@/routes/admin/site-settings';
import { index as topicsIndex } from '@/routes/admin/topics';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Inhoud',
        href: topicsIndex(),
        icon: FolderTree,
    },
    {
        title: 'Media',
        href: mediaIndex(),
        icon: Images,
    },
    {
        title: 'Niveaus',
        href: levelsIndex(),
        icon: GraduationCap,
    },
    {
        title: 'Wachtwoorden',
        href: passwordsIndex(),
        icon: KeyRound,
    },
    {
        title: 'Back-ups',
        href: backupsIndex(),
        icon: DatabaseBackup,
    },
    {
        title: 'Instellingen',
        href: siteSettingsEdit(),
        icon: Settings,
    },
];

// The starter kit shipped links to Laravel's own repository and docs here.
// Nothing on this site's admin panel should point a non-technical owner at
// framework documentation; the one link that is genuinely useful from every
// admin screen is the public site itself.
const footerNavItems: NavItem[] = [
    {
        title: 'Bekijk de website',
        href: '/',
        icon: Globe,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
