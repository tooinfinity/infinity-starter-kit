import { Badge } from '@/components/ui/badge';

export function UserStatusBadge({ isActive }: { isActive: boolean }) {
    if (isActive) {
        return (
            <Badge
                variant="outline"
                className="border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
            >
                Active
            </Badge>
        );
    }

    return (
        <Badge
            variant="outline"
            className="border-destructive/30 bg-destructive/10 text-destructive"
        >
            Inactive
        </Badge>
    );
}
