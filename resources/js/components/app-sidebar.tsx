import { Link } from '@inertiajs/react';
import { BookOpen, FolderGit2, LayoutGrid, Users } from 'lucide-react';
/* @end-chisel-user-management */
/* @chisel-audit-trails */
import { History } from 'lucide-react';
/* @end-chisel-audit-trails */
/* @chisel-reporting */
import { BarChart3 } from 'lucide-react';
/* @end-chisel-user-management */
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
import { index as auditTrailsIndex } from '@/routes/audit-trails';
/* @chisel-reporting */
import { index as reportsIndex } from '@/routes/reports';
/* @end-chisel-reporting */
/* @chisel-user-management */
import { index as usersIndex } from '@/routes/users';
/* @end-chisel-reporting */
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    /* @chisel-user-management */
    {
        title: 'Users',
        href: usersIndex(),
        icon: Users,
        permission: 'users.view',
    },
    /* @end-chisel-user-management */
    /* @chisel-audit-trails */
    {
        title: 'Audit Trails',
        href: auditTrailsIndex(),
        icon: History,
        permission: 'audit.view',
    },
    /* @end-chisel-audit-trails */
    /* @chisel-reporting */
    {
        title: 'Reports',
        href: reportsIndex(),
        icon: BarChart3,
        permission: 'reports.view',
    },
    /* @end-chisel-reporting */
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
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
