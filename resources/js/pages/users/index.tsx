import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    Edit,
    FilterX,
    KeyRound,
    MoreHorizontal,
    Plus,
    Search,
    Shield,
    Trash2,
    UserCheck,
    Users,
    UserX,
    X,
} from 'lucide-react';
import { FormEvent, useState } from 'react';
import ActivateUserController from '@/actions/App/Http/Controllers/Users/ActivateUserController';
import DeactivateUserController from '@/actions/App/Http/Controllers/Users/DeactivateUserController';
import UserController from '@/actions/App/Http/Controllers/Users/UserController';
import UserPasswordController from '@/actions/App/Http/Controllers/Users/UserPasswordController';
import { Can } from '@/components/can';
import InputError from '@/components/input-error';
import { TableEmptyState } from '@/components/table-empty-state';
import { TablePagination } from '@/components/table-pagination';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { UserStatusBadge } from '@/components/users/user-status-badge';
import { useInitials } from '@/hooks/use-initials';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/formatters';
import { create, edit, index } from '@/routes/users';
import type {
    BreadcrumbItem,
    PaginatedUsers,
    UserFilters,
    UserListItem,
} from '@/types';

export default function UsersIndex({
    users,
    filters,
    availableRoles: _availableRoles,
}: {
    users: PaginatedUsers;
    filters: UserFilters;
    availableRoles: string[];
}) {
    const { locale, timezone } = usePage().props;
    const { __, transChoice } = useTranslation();
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? 'all');
    const [deleteTarget, setDeleteTarget] = useState<UserListItem | null>(null);
    const [passwordTarget, setPasswordTarget] = useState<UserListItem | null>(
        null,
    );

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: __('User Management'),
            href: index(),
        },
    ];

    const getInitials = useInitials();

    const passwordForm = useForm({
        password: '',
    });

    const handleSearchSubmit = (e?: FormEvent) => {
        if (e) {
            e.preventDefault();
        }

        router.get(
            index.url(),
            {
                search: search || undefined,
                status: status !== 'all' ? status : undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const handleStatusFilter = (newStatus: string) => {
        setStatus(newStatus);
        router.get(
            index.url(),
            {
                search: search || undefined,
                status: newStatus !== 'all' ? newStatus : undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const handleClearSearch = () => {
        setSearch('');
        router.get(
            index.url(),
            {
                search: undefined,
                status: status !== 'all' ? status : undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const handleResetFilters = () => {
        setSearch('');
        setStatus('all');
        router.get(index.url(), {}, { preserveState: true, replace: true });
    };

    const hasActiveFilters = Boolean(search) || status !== 'all';

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

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-1">
                        <div className="flex items-center gap-3">
                            <h1 className="text-xl font-semibold tracking-tight text-foreground">
                                {__('User Management')}
                            </h1>
                            <Badge
                                variant="secondary"
                                className="text-xs font-medium"
                            >
                                {transChoice('users.total', users.total ?? 0, {
                                    count: users.total ?? 0,
                                })}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {__(
                                'Manage user accounts, roles, permissions, and security.',
                            )}
                        </p>
                    </div>

                    <Can permission="users.create">
                        <Button asChild className="self-start sm:self-auto">
                            <Link href={create.url()}>
                                <Plus className="me-2 size-4" />
                                {__('Add User')}
                            </Link>
                        </Button>
                    </Can>
                </div>

                {/* Filters Bar */}
                <div className="rounded-xl border bg-card p-4 text-card-foreground shadow-sm">
                    <form onSubmit={handleSearchSubmit} className="space-y-3">
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {/* Search */}
                            <div className="relative lg:col-span-2">
                                <Search className="absolute start-2.5 top-2.5 size-4 text-muted-foreground" />
                                <Input
                                    placeholder={__(
                                        'Search by name or email...',
                                    )}
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="ps-8 pe-8"
                                />
                                {search && (
                                    <button
                                        type="button"
                                        onClick={handleClearSearch}
                                        className="absolute end-2.5 top-2.5 text-muted-foreground hover:text-foreground"
                                        aria-label={__('Clear search')}
                                    >
                                        <X className="size-4" />
                                    </button>
                                )}
                            </div>

                            {/* Status Filter */}
                            <div>
                                <Select
                                    value={status}
                                    onValueChange={(val) =>
                                        handleStatusFilter(val)
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue
                                            placeholder={__('All Statuses')}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            {__('All Statuses')}
                                        </SelectItem>
                                        <SelectItem value="active">
                                            {__('Active')}
                                        </SelectItem>
                                        <SelectItem value="inactive">
                                            {__('Inactive')}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="flex items-center justify-end gap-2 pt-1">
                            {hasActiveFilters && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={handleResetFilters}
                                    className="gap-1 text-xs"
                                >
                                    <FilterX className="size-3.5" />
                                    {__('Reset')}
                                </Button>
                            )}
                            <Button type="submit" size="sm" className="text-xs">
                                {__('Apply Filters')}
                            </Button>
                        </div>
                    </form>
                </div>

                {/* Table */}
                <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-start text-sm">
                            <thead className="border-b bg-muted/40 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3.5">
                                        {__('User')}
                                    </th>
                                    <th className="px-4 py-3.5">
                                        {__('Status')}
                                    </th>
                                    <th className="px-4 py-3.5">
                                        {__('Roles')}
                                    </th>
                                    <th className="px-4 py-3.5">
                                        {__('Joined')}
                                    </th>
                                    <th className="px-4 py-3.5 text-end">
                                        {__('Actions')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {users.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="p-0">
                                            <TableEmptyState
                                                icon={Users}
                                                title={
                                                    hasActiveFilters
                                                        ? __(
                                                              'No matching users found',
                                                          )
                                                        : __('No users yet')
                                                }
                                                description={
                                                    hasActiveFilters
                                                        ? __(
                                                              'Try adjusting or clearing your search or filter options.',
                                                          )
                                                        : __(
                                                              'Get started by creating your first user account.',
                                                          )
                                                }
                                                action={
                                                    hasActiveFilters ? (
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={
                                                                handleResetFilters
                                                            }
                                                            className="gap-1.5 text-xs"
                                                        >
                                                            <FilterX className="size-3.5" />
                                                            {__(
                                                                'Reset filters',
                                                            )}
                                                        </Button>
                                                    ) : (
                                                        <Can permission="users.create">
                                                            <Button
                                                                asChild
                                                                size="sm"
                                                                className="gap-1.5 text-xs"
                                                            >
                                                                <Link
                                                                    href={create.url()}
                                                                >
                                                                    <Plus className="size-3.5" />
                                                                    {__(
                                                                        'Add User',
                                                                    )}
                                                                </Link>
                                                            </Button>
                                                        </Can>
                                                    )
                                                }
                                            />
                                        </td>
                                    </tr>
                                ) : (
                                    users.data.map((user) => (
                                        <tr
                                            key={user.id}
                                            className="transition-colors hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3.5">
                                                <div className="flex items-center gap-3">
                                                    <Avatar className="size-8 shrink-0">
                                                        <AvatarFallback className="bg-muted text-xs font-medium text-foreground uppercase">
                                                            {getInitials(
                                                                user.name,
                                                            )}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div className="flex flex-col">
                                                        <span className="font-medium text-foreground">
                                                            {user.name}
                                                        </span>
                                                        <span className="text-xs text-muted-foreground">
                                                            {user.email}
                                                        </span>
                                                    </div>
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
                                                                    className="text-xs font-normal"
                                                                >
                                                                    <Shield className="me-1 size-3 text-muted-foreground" />
                                                                    {role}
                                                                </Badge>
                                                            ),
                                                        )
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3.5 text-xs text-muted-foreground">
                                                {formatDate(
                                                    user.created_at,
                                                    locale,
                                                    timezone,
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5 text-end">
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
                                                                {__(
                                                                    'Open menu',
                                                                )}
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
                                                                    <Edit className="me-2 size-4" />
                                                                    {__(
                                                                        'Edit User',
                                                                    )}
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
                                                                <KeyRound className="me-2 size-4" />
                                                                {__(
                                                                    'Change Password',
                                                                )}
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
                                                                    <UserX className="me-2 size-4 text-amber-500" />
                                                                    {__(
                                                                        'Deactivate',
                                                                    )}
                                                                </DropdownMenuItem>
                                                            ) : (
                                                                <DropdownMenuItem
                                                                    onClick={() =>
                                                                        handleActivate(
                                                                            user,
                                                                        )
                                                                    }
                                                                >
                                                                    <UserCheck className="me-2 size-4 text-emerald-500" />
                                                                    {__(
                                                                        'Activate',
                                                                    )}
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
                                                                <Trash2 className="me-2 size-4" />
                                                                {__(
                                                                    'Delete User',
                                                                )}
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
                    <TablePagination
                        links={users.links}
                        from={users.from}
                        to={users.to}
                        total={users.total}
                        itemName="users"
                    />
                </div>
            </div>

            {/* Change Password Modal */}
            <Dialog
                open={!!passwordTarget}
                onOpenChange={(open) => !open && setPasswordTarget(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{__('Change Password')}</DialogTitle>
                        <DialogDescription>
                            {__('Set a new password for :name.', {
                                name: passwordTarget?.name ?? '',
                            })}
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        onSubmit={handlePasswordSubmit}
                        className="space-y-4 py-2"
                    >
                        <div className="space-y-2">
                            <Label htmlFor="admin-new-password">
                                {__('New Password')}
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
                                {__('Cancel')}
                            </Button>
                            <Button
                                type="submit"
                                disabled={passwordForm.processing}
                            >
                                {__('Update Password')}
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
                        <DialogTitle>{__('Delete User')}</DialogTitle>
                        <DialogDescription>
                            {__(
                                'Are you sure you want to delete :name? This action cannot be undone.',
                                { name: deleteTarget?.name ?? '' },
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setDeleteTarget(null)}
                        >
                            {__('Cancel')}
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={handleDeleteConfirm}
                        >
                            {__('Delete')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
