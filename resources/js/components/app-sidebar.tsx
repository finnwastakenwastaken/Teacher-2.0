import { Link } from '@inertiajs/react';
import {
    DatabaseBackup,
    FolderTree,
    GraduationCap,
    Globe,
    Images,
    KeyRound,
    Palette,
    Settings,
    LayoutGrid,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import LocaleSwitcher from '@/components/locale-switcher';
import { t } from '@/lib/i18n';
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
import { edit as themeEdit } from '@/routes/admin/theme';
import { index as topicsIndex } from '@/routes/admin/topics';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: t('ui.nav.dashboard'),
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: t('ui.nav.content'),
        href: topicsIndex(),
        icon: FolderTree,
    },
    {
        title: t('ui.nav.media'),
        href: mediaIndex(),
        icon: Images,
    },
    {
        title: t('ui.nav.levels'),
        href: levelsIndex(),
        icon: GraduationCap,
    },
    {
        title: t('ui.nav.passwords'),
        href: passwordsIndex(),
        icon: KeyRound,
    },
    {
        title: t('ui.nav.backups'),
        href: backupsIndex(),
        icon: DatabaseBackup,
    },
    {
        title: t('ui.nav.settings'),
        href: siteSettingsEdit(),
        icon: Settings,
    },
    {
        title: t('ui.nav.theme'),
        href: themeEdit(),
        icon: Palette,
    },
];

// Replaces the starter kit's links to Laravel's repo/docs — irrelevant to a
// non-technical owner; the public site is the one link worth showing here.
const footerNavItems: NavItem[] = [
    {
        title: t('ui.nav.view_site'),
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
                {/* Hidden when the sidebar is collapsed to icons: a select is
                    not something that survives a 3rem-wide rail. */}
                <LocaleSwitcher className="px-2 group-data-[collapsible=icon]:hidden" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
