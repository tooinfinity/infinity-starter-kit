import { BellOff } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    title?: string;
    description?: string;
    className?: string;
};

export function NotificationEmptyState({
    title,
    description,
    className = '',
}: Props) {
    const { __ } = useTranslation();
    const displayTitle = title ?? __('No notifications yet');
    const displayDescription =
        description ??
        __(
            'You are all caught up! We will notify you when something important arrives.',
        );

    return (
        <div
            className={`flex flex-col items-center justify-center p-8 text-center ${className}`}
        >
            <div className="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-muted text-muted-foreground">
                <BellOff className="h-6 w-6" />
            </div>
            <h3 className="text-sm font-medium text-foreground">
                {displayTitle}
            </h3>
            <p className="mt-1 max-w-xs text-xs text-muted-foreground">
                {displayDescription}
            </p>
        </div>
    );
}
