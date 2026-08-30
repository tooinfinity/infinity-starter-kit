import { usePage } from '@inertiajs/react';

/* @chisel-roles-permissions */
type AuthorizationHelpers = {
    can: (permission: string) => boolean;
    canAny: (permissions: string[]) => boolean;
    canAll: (permissions: string[]) => boolean;
    hasRole: (role: string) => boolean;
};

export function useAuthorization(): AuthorizationHelpers {
    const { auth } = usePage().props;
    const permissions = auth.permissions ?? [];
    const roles = auth.roles ?? [];

    return {
        can: (permission: string): boolean => permissions.includes(permission),
        canAny: (perms: string[]): boolean =>
            perms.some((p) => permissions.includes(p)),
        canAll: (perms: string[]): boolean =>
            perms.every((p) => permissions.includes(p)),
        hasRole: (role: string): boolean => roles.includes(role),
    };
}
/* @end-chisel-roles-permissions */
