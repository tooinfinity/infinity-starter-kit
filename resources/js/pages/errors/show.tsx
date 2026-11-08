import { Head, Link } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    Clock,
    FileQuestion,
    Home,
    RefreshCw,
    ServerOff,
    ShieldAlert,
} from 'lucide-react';
import type { ComponentType } from 'react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import { home } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type Props = {
    status: number;
};

type ErrorContent = {
    title: string;
    description: string;
    icon: ComponentType<{ className?: string }>;
    primaryAction: 'reload' | 'home';
};

export default function ErrorShow({ status }: Props) {
    const { __ } = useTranslation();

    const getErrorContent = (code: number): ErrorContent => {
        switch (code) {
            case 403:
                return {
                    title: __('Forbidden'),
                    description: __(
                        'You do not have permission to access this page.',
                    ),
                    icon: ShieldAlert,
                    primaryAction: 'home',
                };
            case 404:
                return {
                    title: __('Page Not Found'),
                    description: __(
                        'The page you are looking for could not be found.',
                    ),
                    icon: FileQuestion,
                    primaryAction: 'home',
                };
            case 419:
                return {
                    title: __('Page Expired'),
                    description: __(
                        'Your session has expired. Please refresh the page or sign in again.',
                    ),
                    icon: Clock,
                    primaryAction: 'reload',
                };
            case 500:
                return {
                    title: __('Server Error'),
                    description: __(
                        'An internal server error occurred. Please try again later.',
                    ),
                    icon: ServerOff,
                    primaryAction: 'reload',
                };
            case 503:
                return {
                    title: __('Service Unavailable'),
                    description: __(
                        'The service is temporarily unavailable. Please try again soon.',
                    ),
                    icon: AlertCircle,
                    primaryAction: 'reload',
                };
            default:
                return {
                    title: __('An error occurred'),
                    description: __(
                        'An unexpected error occurred. Please try again.',
                    ),
                    icon: AlertCircle,
                    primaryAction: 'home',
                };
        }
    };

    const content = getErrorContent(status);
    const Icon = content.icon;

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: __('Error :status', { status }),
            href: '#',
        },
    ];

    const handleReload = () => {
        window.location.reload();
    };

    const handleBack = () => {
        if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = '/';
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${status} - ${content.title}`} />
            <div className="flex h-full flex-1 flex-col items-center justify-center p-6 text-center">
                <div className="mx-auto flex max-w-md flex-col items-center">
                    <div className="flex size-16 items-center justify-center rounded-2xl bg-muted text-muted-foreground shadow-sm">
                        <Icon className="size-8" />
                    </div>

                    <span className="mt-6 rounded-full border border-border px-3 py-1 font-mono text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                        {status}
                    </span>

                    <h1 className="mt-4 text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                        {content.title}
                    </h1>

                    <p className="mt-2 text-sm text-muted-foreground sm:text-base">
                        {content.description}
                    </p>

                    <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
                        {content.primaryAction === 'reload' ? (
                            <Button
                                type="button"
                                variant="default"
                                onClick={handleReload}
                            >
                                <RefreshCw className="me-2 size-4" />
                                {__('Refresh page')}
                            </Button>
                        ) : (
                            <Button variant="default" asChild>
                                <Link href={home()}>
                                    <Home className="me-2 size-4" />
                                    {__('Go to home')}
                                </Link>
                            </Button>
                        )}

                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleBack}
                        >
                            <ArrowLeft className="me-2 size-4" />
                            {__('Go back')}
                        </Button>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
