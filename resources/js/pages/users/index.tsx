import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Edit,
    KeyRound,
    MoreHorizontal,
    Plus,
    Search,
    Shield,
    Trash2,
    UserCheck,
    UserX,
} from 'lucide-react';
import { FormEvent, useState } from 'react';
import ActivateUserController from '@/actions/App/Http/Controllers/Users/ActivateUserController';
import DeactivateUserController from '@/actions/App/Http/Controllers/Users/DeactivateUserController';
import UserController from '@/actions/App/Http/Controllers/Users/UserController';
import UserPasswordController from '@/actions/App/Http/Controllers/Users/UserPasswordController';
import { Can } from '@/components/can';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { UserStatusBadge } from '@/components/users/user-status-badge';
import AppLayout from '@/layouts/app-layout';
import { create, edit, index } from '@/routes/users';
import type {
    BreadcrumbItem,
    PaginatedUsers,
    UserFilters,
    UserListItem,
} from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'User Management',
        href: index(),
    },
];

export default function UsersIndex({
    users,
    filters,
}: {
    users: PaginatedUsers;
    filters: UserFilters;
    availableRoles: string[];
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? 'all');
    const [deleteTarget, setDeleteTarget] = useState<UserListItem | null>(null);
    const [passwordTarget, setPasswordTarget] = useState<UserListItem | null>(
        null,
    );

    const passwordForm = useForm({
        password: '',
    });

    const handleSearchSubmit = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            index.url(),
            { search, status },
            { preserveState: true, replace: true },
        );
    };

    const handleStatusFilter = (newStatus: string) => {
        setStatus(newStatus);
        router.get(
            index.url(),
            { search, status: newStatus },
            { preserveState: true, replace: true },
        );
    };

    const handleActivate = (user: UserListItem) => {
        router.patch(
            ActivateUserController.url({ user: user.id }),
            {},
            { preserveScroll: true },
        );
    };

    const handleDeactivate = (user: UserListItem) => {
        router.patch(
            DeactivateUserController.url({ user: user.id }),
            {},
            { preserveScroll: true },
        );
    };

    const handleDeleteConfirm = () => {
        if (!deleteTarget) return;
        router.delete(UserController.destroy.url({ user: deleteTarget.id }), {
            preserveScroll: true,
            onSuccess: () => setDeleteTarget(null),
        });
    };

    const handlePasswordSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (!passwordTarget) return;
        passwordForm.put(
            UserPasswordController.url({ user: passwordTarget.id }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setPasswordTarget(null);
                    passwordForm.reset();
                },
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="User Management" />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="User Management"
                        description="Manage user accounts, roles, permissions, and security."
                    />

                    <Can permission="users.create">
                        <Button asChild>
                            <Link href={create.url()}>
                                <Plus className="mr-2 size-4" />
                                Add User
                            </Link>
                        </Button>
                    </Can>
                </div>

                {/* Filters Bar */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <form
                        onSubmit={handleSearchSubmit}
                        className="relative max-w-md flex-1"
                    >
                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Search by name or email..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="pl-9"
                        />
                    </form>

                    <div className="flex items-center gap-1 self-start rounded-lg border bg-muted/40 p-1 sm:self-auto">
                        {(['all', 'active', 'inactive'] as const).map((tab) => (
                            <Button
                                key={tab}
                                type="button"
                                variant={status === tab ? 'secondary' : 'ghost'}
                                size="sm"
                                onClick={() => handleStatusFilter(tab)}
                                className="text-xs capitalize"
                            >
                                {tab}
                            </Button>
                        ))}
                    </div>
                </div>

                {/* Table */}
                <div className="overflow-hidden rounded-xl border bg-card">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b bg-muted/50 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3.5">User</th>
                                    <th className="px-4 py-3.5">Status</th>
                                    <th className="px-4 py-3.5">Roles</th>
                                    <th className="px-4 py-3.5">Joined</th>
                                    <th className="px-4 py-3.5 text-right">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {users.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-4 py-8 text-center text-muted-foreground"
                                        >
                                            No users found matching your
                                            criteria.
                                        </td>
                                    </tr>
                                ) : (
                                    users.data.map((user) => (
                                        <tr
                                            key={user.id}
                                            className="transition-colors hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3.5">
                                                <div className="flex flex-col">
                                                    <span className="font-medium text-foreground">
                                                        {user.name}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {user.email}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <UserStatusBadge
                                                    isActive={user.is_active}
                                                />
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <div className="flex flex-wrap gap-1">
                                                    {user.roles.length === 0 ? (
                                                        <span className="text-xs text-muted-foreground">
                                                            —
                                                        </span>
                                                    ) : (
                                                        user.roles.map(
                                                            (role) => (
                                                                <Badge
                                                                    key={role}
                                                                    variant="secondary"
                                                                    className="text-xs"
                                                                >
                                                                    <Shield className="mr-1 size-3 text-muted-foreground" />
                                                                    {role}
                                                                </Badge>
                                                            ),
                                                        )
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3.5 text-xs text-muted-foreground">
                                                {new Date(
                                                    user.created_at,
                                                ).toLocaleDateString()}
                                            </td>
                                            <td className="px-4 py-3.5 text-right">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-8"
                                                        >
                                                            <MoreHorizontal className="size-4" />
                                                            <span className="sr-only">
                                                                Open menu
                                                            </span>
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent
                                                        align="end"
                                                        className="w-48"
                                                    >
                                                        <Can permission="users.update">
                                                            <DropdownMenuItem
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={edit.url(
                                                                        {
                                                                            user: user.id,
                                                                        },
                                                                    )}
                                                                >
                                                                    <Edit className="mr-2 size-4" />
                                                                    Edit User
                                                                </Link>
                                                            </DropdownMenuItem>
                                                        </Can>

                                                        <Can permission="users.manage-password">
                                                            <DropdownMenuItem
                                                                onClick={() =>
                                                                    setPasswordTarget(
                                                                        user,
                                                                    )
                                                                }
                                                            >
                                                                <KeyRound className="mr-2 size-4" />
                                                                Change Password
                                                            </DropdownMenuItem>
                                                        </Can>

                                                        <Can permission="users.update">
                                                            {user.is_active ? (
                                                                <DropdownMenuItem
                                                                    onClick={() =>
                                                                        handleDeactivate(
                                                                            user,
                                                                        )
                                                                    }
                                                                >
                                                                    <UserX className="mr-2 size-4 text-amber-500" />
                                                                    Deactivate
                                                                </DropdownMenuItem>
                                                            ) : (
                                                                <DropdownMenuItem
                                                                    onClick={() =>
                                                                        handleActivate(
                                                                            user,
                                                                        )
                                                                    }
                                                                >
                                                                    <UserCheck className="mr-2 size-4 text-emerald-500" />
                                                                    Activate
                                                                </DropdownMenuItem>
                                                            )}
                                                        </Can>

                                                        <Can permission="users.delete">
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem
                                                                onClick={() =>
                                                                    setDeleteTarget(
                                                                        user,
                                                                    )
                                                                }
                                                                className="text-destructive focus:text-destructive"
                                                            >
                                                                <Trash2 className="mr-2 size-4" />
                                                                Delete User
                                                            </DropdownMenuItem>
                                                        </Can>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {users.links.length > 3 && (
                        <div className="flex items-center justify-between border-t bg-muted/20 px-4 py-3">
                            <div className="text-xs text-muted-foreground">
                                Showing {users.from ?? 0} to {users.to ?? 0} of{' '}
                                {users.total ?? 0} users
                            </div>
                            <div className="flex gap-1">
                                {users.links.map((link, i) => (
                                    <Button
                                        key={i}
                                        variant={
                                            link.active ? 'default' : 'outline'
                                        }
                                        size="sm"
                                        disabled={!link.url}
                                        asChild={!!link.url}
                                        className="h-8 text-xs"
                                    >
                                        {link.url ? (
                                            <Link
                                                href={link.url}
                                                dangerouslySetInnerHTML={{
                                                    __html: link.label,
                                                }}
                                            />
                                        ) : (
                                            <span
                                                dangerouslySetInnerHTML={{
                                                    __html: link.label,
                                                }}
                                            />
                                        )}
                                    </Button>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Change Password Modal */}
            <Dialog
                open={!!passwordTarget}
                onOpenChange={(open) => !open && setPasswordTarget(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Change Password</DialogTitle>
                        <DialogDescription>
                            Set a new password for{' '}
                            <span className="font-semibold text-foreground">
                                {passwordTarget?.name}
                            </span>
                            .
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        onSubmit={handlePasswordSubmit}
                        className="space-y-4 py-2"
                    >
                        <div className="space-y-2">
                            <Label htmlFor="admin-new-password">
                                New Password
                            </Label>
                            <Input
                                id="admin-new-password"
                                type="password"
                                value={passwordForm.data.password}
                                onChange={(e) =>
                                    passwordForm.setData(
                                        'password',
                                        e.target.value,
                                    )
                                }
                                placeholder="••••••••"
                                required
                            />
                            <InputError
                                message={passwordForm.errors.password}
                            />
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setPasswordTarget(null)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={passwordForm.processing}
                            >
                                Update Password
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Confirmation Modal */}
            <Dialog
                open={!!deleteTarget}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete User</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete{' '}
                            <span className="font-semibold text-foreground">
                                {deleteTarget?.name}
                            </span>
                            ? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setDeleteTarget(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={handleDeleteConfirm}
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
