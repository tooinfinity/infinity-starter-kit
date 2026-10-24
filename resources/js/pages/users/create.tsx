import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import UserController from '@/actions/App/Http/Controllers/Users/UserController';
import { Can } from '@/components/can';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import { create, index } from '@/routes/users';
import type { BreadcrumbItem } from '@/types';

export default function UsersCreate({
    availableRoles,
}: {
    availableRoles: string[];
}) {
    const { __ } = useTranslation();
    const form = useForm({
        name: '',
        email: '',
        password: '',
        is_active: true,
        roles: [] as string[],
    });

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: __('User Management'),
            href: index(),
        },
        {
            title: __('Add User'),
            href: create(),
        },
    ];

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        form.post(UserController.store.url());
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={__('Add User')} />

            <div className="max-w-2xl space-y-6">
                <Heading
                    title={__('Add User')}
                    description={__(
                        'Create a new administrative or standard user account.',
                    )}
                />

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

                    <div className="space-y-2">
                        <Label htmlFor="user-password">{__('Password')}</Label>
                        <Input
                            id="user-password"
                            name="password"
                            type="password"
                            value={form.data.password}
                            onChange={(e) =>
                                form.setData('password', e.target.value)
                            }
                            placeholder="••••••••"
                            required
                        />
                        <InputError message={form.errors.password} />
                    </div>

                    <div className="flex items-center gap-2 pt-2">
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
                            {__('Active account (allow login)')}
                        </Label>
                        <InputError message={form.errors.is_active} />
                    </div>

                    <Can permission="users.manage-roles">
                        {availableRoles.length > 0 && (
                            <div className="space-y-3 pt-2">
                                <Label>{__('Assign Roles')}</Label>
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

                    <div className="flex items-center gap-4 pt-4">
                        <Button type="submit" disabled={form.processing}>
                            {__('Create User')}
                        </Button>

                        <Button type="button" variant="outline" asChild>
                            <Link href={index.url()}>{__('Cancel')}</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
