import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import UserController from '@/actions/App/Http/Controllers/Users/UserController';
import UserPasswordController from '@/actions/App/Http/Controllers/Users/UserPasswordController';
import { Can } from '@/components/can';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { UserStatusBadge } from '@/components/users/user-status-badge';
import AppLayout from '@/layouts/app-layout';
import { edit, index } from '@/routes/users';
import type { BreadcrumbItem, UserListItem } from '@/types';

export default function UsersEdit({
    user,
    availableRoles,
}: {
    user: UserListItem;
    availableRoles: string[];
}) {
    const [isPasswordModalOpen, setIsPasswordModalOpen] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'User Management',
            href: index(),
        },
        {
            title: `Edit ${user.name}`,
            href: edit.url({ user: user.id }),
        },
    ];

    const form = useForm({
        name: user.name,
        email: user.email,
        is_active: user.is_active,
        roles: user.roles,
    });

    const passwordForm = useForm({
        password: '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        form.put(UserController.update.url({ user: user.id }));
    };

    const handlePasswordSubmit = (e: FormEvent) => {
        e.preventDefault();
        passwordForm.put(UserPasswordController.url({ user: user.id }), {
            preserveScroll: true,
            onSuccess: () => {
                setIsPasswordModalOpen(false);
                passwordForm.reset();
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${user.name}`} />

            <div className="max-w-2xl space-y-8">
                <div className="flex items-center justify-between">
                    <Heading
                        title={`Edit ${user.name}`}
                        description="Update account information, active status, and role assignments."
                    />
                    <UserStatusBadge isActive={user.is_active} />
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="space-y-2">
                        <Label htmlFor="user-name">Name</Label>
                        <Input
                            id="user-name"
                            name="name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            placeholder="John Doe"
                            required
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="user-email">Email Address</Label>
                        <Input
                            id="user-email"
                            name="email"
                            type="email"
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                            placeholder="john@example.com"
                            required
                        />
                        <InputError message={form.errors.email} />
                    </div>

                    <div className="flex items-center space-x-2 pt-2">
                        <Checkbox
                            id="user-is-active"
                            name="is_active"
                            checked={form.data.is_active}
                            onCheckedChange={(checked) =>
                                form.setData('is_active', Boolean(checked))
                            }
                        />
                        <Label
                            htmlFor="user-is-active"
                            className="cursor-pointer"
                        >
                            Active account (allow login)
                        </Label>
                        <InputError message={form.errors.is_active} />
                    </div>

                    {availableRoles.length > 0 && (
                        <div className="space-y-3 pt-2">
                            <Label>Assigned Roles</Label>
                            <div className="grid gap-2 sm:grid-cols-2">
                                {availableRoles.map((role) => {
                                    const isChecked =
                                        form.data.roles.includes(role);

                                    return (
                                        <div
                                            key={role}
                                            className="flex items-center space-x-2 rounded-lg border p-3"
                                        >
                                            <Checkbox
                                                id={`role-${role}`}
                                                checked={isChecked}
                                                onCheckedChange={(checked) => {
                                                    const updated = checked
                                                        ? [
                                                              ...form.data
                                                                  .roles,
                                                              role,
                                                          ]
                                                        : form.data.roles.filter(
                                                              (r) => r !== role,
                                                          );
                                                    form.setData(
                                                        'roles',
                                                        updated,
                                                    );
                                                }}
                                            />
                                            <Label
                                                htmlFor={`role-${role}`}
                                                className="cursor-pointer font-medium"
                                            >
                                                {role}
                                            </Label>
                                        </div>
                                    );
                                })}
                            </div>
                            <InputError message={form.errors.roles} />
                        </div>
                    )}

                    <div className="flex items-center justify-between border-t pt-4">
                        <div className="flex items-center gap-4">
                            <Button type="submit" disabled={form.processing}>
                                Save Changes
                            </Button>

                            <Button type="button" variant="outline" asChild>
                                <Link href={index.url()}>Cancel</Link>
                            </Button>
                        </div>

                        <Can permission="users.manage-password">
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={() => setIsPasswordModalOpen(true)}
                            >
                                Change Password
                            </Button>
                        </Can>
                    </div>
                </form>
            </div>

            {/* Change Password Modal */}
            <Dialog
                open={isPasswordModalOpen}
                onOpenChange={setIsPasswordModalOpen}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Change Password</DialogTitle>
                        <DialogDescription>
                            Set a new password for{' '}
                            <span className="font-semibold text-foreground">
                                {user.name}
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
                                onClick={() => setIsPasswordModalOpen(false)}
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
        </AppLayout>
    );
}
