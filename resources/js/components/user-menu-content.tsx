import { Link } from '@inertiajs/react';
/* @chisel-logout */
import { router } from '@inertiajs/react';
/* @end-chisel-logout */
import { Settings } from 'lucide-react';
/* @chisel-logout */
import { LogOut } from 'lucide-react';
/* @end-chisel-logout */
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
/* @chisel-logout */
import { logout } from '@/routes';
/* @end-chisel-logout */
import { edit } from '@/routes/user-profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

export function UserMenuContent({ user }: Props) {
    const cleanup = useMobileNavigation();

    /* @chisel-logout */
    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };
    /* @end-chisel-logout */

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={edit()}
                        prefetch
                        onClick={cleanup}
                    >
                        <Settings className="mr-2" />
                        Settings
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>
            {/* @chisel-logout */}
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full cursor-pointer"
                    href={logout()}
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2" />
                    Log out
                </Link>
            </DropdownMenuItem>
            {/* @end-chisel-logout */}
        </>
    );
}
