import type { ReactNode } from 'react';
import { useAuthorization } from '@/hooks/use-authorization';

/* @chisel-roles-permissions */
type CanProps = {
    children: ReactNode;
} & (
    | { permission: string; permissions?: never; mode?: never }
    | { permission?: never; permissions: string[]; mode?: 'any' | 'all' }
);

export function Can({
    children,
    permission,
    permissions,
    mode = 'any',
}: CanProps): ReactNode {
    const { can, canAny, canAll } = useAuthorization();

    if (permission) {
        return can(permission) ? children : null;
    }

    if (permissions) {
        const isAuthorized =
            mode === 'all' ? canAll(permissions) : canAny(permissions);

        return isAuthorized ? children : null;
    }

    return null;
}
/* @end-chisel-roles-permissions */
