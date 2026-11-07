import { AlertCircle, Home, RefreshCw } from 'lucide-react';
import { Component, type ErrorInfo, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    children: ReactNode;
    fallback?:
        | ReactNode
        | ((props: { error: Error; reset: () => void }) => ReactNode);
};

type State = {
    hasError: boolean;
    error: Error | null;
};

function DefaultErrorFallback({ error }: { error: Error | null }) {
    let t: (key: string) => string = (k) => k;
    try {
        const translation = useTranslation();
        t = translation.__;
    } catch {
        // Fallback to identity function if rendered outside Inertia context
    }

    const handleReload = () => {
        window.location.reload();
    };

    const handleHome = () => {
        window.location.href = '/';
    };

    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-background px-4 py-12 text-foreground sm:px-6 lg:px-8">
            <div className="w-full max-w-md rounded-2xl border border-border bg-card p-6 shadow-sm sm:p-8">
                <div className="flex flex-col items-center text-center">
                    <div className="flex size-12 items-center justify-center rounded-full bg-destructive/10 text-destructive">
                        <AlertCircle className="size-6" />
                    </div>
                    <h1 className="mt-4 text-xl font-semibold tracking-tight">
                        {t('Something went wrong')}
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        {t(
                            'An unexpected error occurred while rendering this page.',
                        )}
                    </p>
                </div>

                {import.meta.env.DEV && error?.message && (
                    <div className="mt-4 rounded-lg bg-muted p-3 text-start">
                        <p className="font-mono text-xs break-words text-destructive">
                            {error.message}
                        </p>
                    </div>
                )}

                <div className="mt-6 flex flex-col gap-2 sm:flex-row">
                    <Button
                        type="button"
                        variant="default"
                        className="flex-1"
                        onClick={handleReload}
                    >
                        <RefreshCw className="me-2 size-4" />
                        {t('Refresh page')}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        className="flex-1"
                        onClick={handleHome}
                    >
                        <Home className="me-2 size-4" />
                        {t('Go to home')}
                    </Button>
                </div>
            </div>
        </div>
    );
}

export class ErrorBoundary extends Component<Props, State> {
    public constructor(props: Props) {
        super(props);
        this.state = { hasError: false, error: null };
    }

    public static getDerivedStateFromError(error: Error): State {
        return { hasError: true, error };
    }

    public override componentDidCatch(
        error: Error,
        errorInfo: ErrorInfo,
    ): void {
        console.error('Unhandled React client render error:', error, errorInfo);
    }

    public reset = (): void => {
        this.setState({ hasError: false, error: null });
    };

    public override render(): ReactNode {
        if (!this.state.hasError) {
            return this.props.children;
        }

        if (typeof this.props.fallback === 'function') {
            return this.props.fallback({
                error: this.state.error ?? new Error('Unknown error'),
                reset: this.reset,
            });
        }

        if (this.props.fallback) {
            return this.props.fallback;
        }

        return <DefaultErrorFallback error={this.state.error} />;
    }
}
