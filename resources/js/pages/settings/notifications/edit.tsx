/* @chisel-notifications */

import { Head, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit, update } from '@/routes/notification-preferences';
import type { BreadcrumbItem } from '@/types';
import type { NotificationPreference } from '@/types/notifications';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Notification preferences',
        href: edit(),
    },
];

type Props = {
    preferences: NotificationPreference[];
};

export default function NotificationPreferencesEdit({ preferences }: Props) {
    const initialPreferences = preferences.reduce(
        (acc, pref) => {
            acc[pref.type] = {
                database_enabled: pref.database_enabled,
            };
            return acc;
        },
        {} as Record<string, { database_enabled: boolean }>,
    );

    const { data, setData, put, processing, recentlySuccessful } = useForm({
        preferences: initialPreferences,
    });

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        put(update().url, {
            preserveScroll: true,
        });
    };

    const handleToggle = (type: string, checked: boolean) => {
        setData('preferences', {
            ...data.preferences,
            [type]: {
                ...data.preferences[type],
                database_enabled: checked,
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Notification preferences" />

            <h1 className="sr-only">Notification preferences</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Notification preferences"
                        description="Control which notifications you receive inside the application."
                    />

                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="space-y-4 rounded-lg border p-4">
                            {preferences.map((pref) => {
                                const isChecked = pref.mandatory
                                    ? true
                                    : (data.preferences[pref.type]
                                          ?.database_enabled ?? true);

                                return (
                                    <div
                                        key={pref.type}
                                        className="flex items-center justify-between space-x-4 border-b py-2 last:border-0"
                                    >
                                        <div className="space-y-0.5">
                                            <div className="flex items-center gap-2">
                                                <Label
                                                    htmlFor={`pref-${pref.type}`}
                                                    className="text-sm font-medium"
                                                >
                                                    {pref.label}
                                                </Label>
                                                {pref.mandatory && (
                                                    <Badge
                                                        variant="secondary"
                                                        className="px-1.5 py-0 text-[10px]"
                                                    >
                                                        Mandatory
                                                    </Badge>
                                                )}
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                {pref.mandatory
                                                    ? 'Mandatory notifications cannot be disabled for security reasons.'
                                                    : `Receive in-app notifications for ${pref.label.toLowerCase()} activity.`}
                                            </p>
                                        </div>

                                        <Checkbox
                                            id={`pref-${pref.type}`}
                                            checked={isChecked}
                                            disabled={pref.mandatory}
                                            onCheckedChange={(checked) =>
                                                handleToggle(
                                                    pref.type,
                                                    checked === true,
                                                )
                                            }
                                        />
                                    </div>
                                );
                            })}
                        </div>

                        <div className="flex items-center gap-4">
                            <Button type="submit" disabled={processing}>
                                Save preferences
                            </Button>

                            {recentlySuccessful && (
                                <p className="text-sm text-muted-foreground">
                                    Saved.
                                </p>
                            )}
                        </div>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}

/* @end-chisel-notifications */
