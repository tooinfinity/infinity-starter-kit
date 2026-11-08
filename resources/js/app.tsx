import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { ComponentType } from 'react';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { toast } from 'sonner';
import { ErrorBoundary } from '@/components/error-boundary';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import '../css/app.css';

const defaultAppName = import.meta.env.VITE_APP_NAME || 'Laravel';

router.on('httpException', (event) => {
    const response = event.detail.response;
    const isInertia = Boolean(
        response.headers &&
        ('x-inertia' in response.headers || 'X-Inertia' in response.headers),
    );

    if (response.status === 419 && !isInertia) {
        event.preventDefault();
        toast.error(
            'Your session has expired. Please refresh the page or sign in again.',
            {
                action: {
                    label: 'Refresh',
                    onClick: () => window.location.reload(),
                },
            },
        );
    }
});

router.on('networkError', () => {
    toast.error('Network error. Please check your connection and try again.');
});

void createInertiaApp({
    title: (title, page) => {
        const appName = (page?.props?.name as string) || defaultAppName;

        return title ? `${title} - ${appName}` : appName;
    },
    resolve: (name) =>
        resolvePageComponent<ComponentType>(
            `./pages/${name}.tsx`,
            import.meta.glob<ComponentType>('./pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <StrictMode>
                <ErrorBoundary>
                    <TooltipProvider delayDuration={0}>
                        <App {...props} />
                        <Toaster />
                    </TooltipProvider>
                </ErrorBoundary>
            </StrictMode>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
