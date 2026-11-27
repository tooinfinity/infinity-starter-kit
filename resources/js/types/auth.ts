export type User = {
    id: string;
    name: string;
    email: string;
    avatar?: string;
    /* @chisel-email-verification */
    email_verified_at: string | null;
    /* @end-chisel-email-verification */
    /* @chisel-two-factor */
    two_factor_enabled?: boolean;
    /* @end-chisel-two-factor */
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    /* @chisel-roles-permissions */
    permissions: string[];
    roles: string[];
    /* @end-chisel-roles-permissions */
};
