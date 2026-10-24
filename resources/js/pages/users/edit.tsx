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
import { useTranslation } from '@/hooks/use-translation';
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
    const { __ } = useTranslation();
    const [isPasswordModalOpen, setIsPasswordModalOpen] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: __('User Management'),
            href: index(),
        },
        {
            title: __('Edit :name', { name: user.name }),
            href: edit.url({ user: user.id }),
        },
    ];

    const form = useForm({
        name: user.name,
        email: user.email,
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
            <Head title={__('Edit :name', { name: user.name })} />

            <div className="max-w-2xl space-y-8">
                <div className="flex items-center justify-between">
                    <Heading
                        title={__('Edit :name', { name: user.name })}
                        description={__(
                            'Update account information and role assignments.',
                        )}
                    />
                    <UserStatusBadge isActive={user.is_active} />
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="space-y-2">
                        <Label htmlFor="user-name">{__('Name')}</Label>
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
                        <Label htmlFor="user-email">
                            {__('Email Address')}
                        </Label>
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

                    <Can permission="users.manage-roles">
                        {availableRoles.length > 0 && (
                            <div className="space-y-3 pt-2">
                                <Label>{__('Assigned Roles')}</Label>
                                <div className="grid gap-2 sm:grid-cols-2">
                                    {availableRoles.map((role) => {
                                        const isChecked =
                                            form.data.roles.includes(role);

                                        return (
                                            <div
                                                key={role}
                                                className="flex items-center gap-2 rounded-lg border p-3"
                                            >
                                                <Checkbox
                                                    id={`role-${role}`}
                                                    checked={isChecked}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) => {
                                                        const updated = checked
                                                            ? [
                                                                  ...form.data
                                                                      .roles,
                                                                  role,
                                                              ]
                                                            : form.data.roles.filter(
                                                                  (r) =>
                                                                      r !==
                                                                      role,
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
                    </Can>

                    <div className="flex items-center justify-between border-t pt-4">
                        <div className="flex items-center gap-4">
                            <Button type="submit" disabled={form.processing}>
                                {__('Save Changes')}
                            </Button>

                            <Button type="button" variant="outline" asChild>
                                <Link href={index.url()}>{__('Cancel')}</Link>
                            </Button>
                        </div>

                        <Can permission="users.manage-password">
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={() => setIsPasswordModalOpen(true)}
                            >
                                {__('Change Password')}
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
                        <DialogTitle>{__('Change Password')}</DialogTitle>
                        <DialogDescription>
                            {__('Set a new password for :name.', {
                                name: user.name,
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
                                onClick={() => setIsPasswordModalOpen(false)}
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
        </AppLayout>
    );
}
