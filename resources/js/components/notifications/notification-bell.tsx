/* @chisel-notifications */

import { usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useState } from 'react';
import { NotificationDropdown } from '@/components/notifications/notification-dropdown';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

export function NotificationBell() {
    const [open, setOpen] = useState(false);
    const { notifications } = usePage().props;
    const unreadCount = notifications?.unreadCount ?? 0;

    return (
        <DropdownMenu open={open} onOpenChange={setOpen}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative h-9 w-9 text-muted-foreground hover:text-foreground"
                    aria-label={`Notifications ${unreadCount > 0 ? `(${unreadCount} unread)` : ''}`}
                >
                    <Bell className="h-5 w-5" />
                    {unreadCount > 0 && (
                        <span className="absolute top-1.5 right-1.5 flex h-4 min-w-4 animate-in items-center justify-center rounded-full bg-primary px-1 text-[10px] font-bold text-primary-foreground zoom-in-50">
                            {unreadCount > 99 ? '99+' : unreadCount}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <NotificationDropdown onClose={() => setOpen(false)} />
        </DropdownMenu>
    );
}

/* @end-chisel-notifications */
