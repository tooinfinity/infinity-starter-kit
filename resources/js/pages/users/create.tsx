import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import UserController from '@/actions/App/Http/Controllers/Users/UserController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { create, index } from '@/routes/users';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'User Management',
        href: index(),
    },
    {
        title: 'Add User',
        href: create(),
    },
];

export default function UsersCreate({
    availableRoles,
}: {
    availableRoles: string[];
}) {
    const form = useForm({
        name: '',
        email: '',
        password: '',
        is_active: true,
        roles: [] as string[],
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        form.post(UserController.store.url());
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Add User" />

            <div className="max-w-2xl space-y-6">
                <Heading
                    title="Add User"
                    description="Create a new administrative or standard user account."
                />

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

                    <div className="space-y-2">
                        <Label htmlFor="user-password">Password</Label>
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
                            <Label>Assign Roles</Label>
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

                    <div className="flex items-center gap-4 pt-4">
                        <Button type="submit" disabled={form.processing}>
                            Create User
                        </Button>

                        <Button type="button" variant="outline" asChild>
                            <Link href={index.url()}>Cancel</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
