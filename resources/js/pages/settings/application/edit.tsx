import { Form, Head } from '@inertiajs/react';
import SettingController from '@/actions/App/Http/Controllers/SettingController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit } from '@/routes/settings';
import type { ApplicationSettings, BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Application settings',
        href: edit(),
    },
];

const commonTimezones = [
    'UTC',
    'America/New_York',
    'America/Chicago',
    'America/Denver',
    'America/Los_Angeles',
    'America/Toronto',
    'America/Sao_Paulo',
    'Europe/London',
    'Europe/Paris',
    'Europe/Berlin',
    'Europe/Moscow',
    'Asia/Tokyo',
    'Asia/Shanghai',
    'Asia/Kolkata',
    'Asia/Dubai',
    'Australia/Sydney',
    'Pacific/Auckland',
    'Africa/Cairo',
    'Africa/Lagos',
    'Africa/Casablanca',
];

export default function Edit({ settings }: { settings: ApplicationSettings }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Application settings" />

            <h1 className="sr-only">Application settings</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Application settings"
                        description="Configure your application's general settings"
                    />

                    <Form
                        {...SettingController.update.form()}
                        options={{
                            preserveScroll: true,
                        }}
                        className="space-y-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="settings-application-name">
                                        Application Name
                                    </Label>

                                    <Input
                                        id="settings-application-name"
                                        className="mt-1 block w-full"
                                        defaultValue={
                                            settings['application.name']
                                        }
                                        name="settings[application.name]"
                                        required
                                        autoComplete="off"
                                        placeholder="My Application"
                                    />

                                    <InputError
                                        className="mt-2"
                                        message={
                                            errors['settings.application.name']
                                        }
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="settings-application-timezone">
                                        Timezone
                                    </Label>

                                    <Select
                                        name="settings[application.timezone]"
                                        defaultValue={
                                            settings['application.timezone']
                                        }
                                    >
                                        <SelectTrigger
                                            id="settings-application-timezone"
                                            className="mt-1 w-full"
                                        >
                                            <SelectValue placeholder="Select timezone" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {commonTimezones.map((timezone) => (
                                                <SelectItem
                                                    key={timezone}
                                                    value={timezone}
                                                >
                                                    {timezone}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>

                                    <InputError
                                        className="mt-2"
                                        message={
                                            errors[
                                                'settings.application.timezone'
                                            ]
                                        }
                                    />
                                </div>

                                <div className="flex items-center gap-4">
                                    <Button
                                        disabled={processing}
                                        data-test="update-settings-button"
                                    >
                                        Save
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
