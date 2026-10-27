import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/hooks/use-translation';

export function UserStatusBadge({ isActive }: { isActive: boolean }) {
    const { __ } = useTranslation();

    if (isActive) {
        return (
            <Badge
                variant="outline"
                className="border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
            >
                {__('Active')}
            </Badge>
        );
    }

    return (
        <Badge
            variant="outline"
            className="border-destructive/30 bg-destructive/10 text-destructive"
        >
            {__('Inactive')}
        </Badge>
    );
}
