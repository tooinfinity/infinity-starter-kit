import { Link } from '@inertiajs/react';
/* @chisel-roles-permissions */
import { Can } from '@/components/can';
/* @end-chisel-roles-permissions */
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useTranslation } from '@/hooks/use-translation';
import type { NavItem } from '@/types';

export function NavMain({ items }: { items: NavItem[] }) {
    const { isCurrentUrl } = useCurrentUrl();
    const { __ } = useTranslation();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>{__('Platform')}</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => {
                    const title = __(item.title);
                    const menuItem = (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={isCurrentUrl(item.href)}
                                tooltip={{ children: title }}
                            >
                                <Link href={item.href} prefetch>
                                    {item.icon && <item.icon />}
                                    <span>{title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    );

                    /* @chisel-roles-permissions */
                    if (item.permission) {
                        return (
                            <Can key={item.title} permission={item.permission}>
                                {menuItem}
                            </Can>
                        );
                    }
                    /* @end-chisel-roles-permissions */

                    return menuItem;
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
