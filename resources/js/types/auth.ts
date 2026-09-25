export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    /* @chisel-email-verification */
    email_verified_at: string | null;
    /* @end-chisel-email-verification */
    /* @chisel-two-factor-authentication */
    two_factor_enabled?: boolean;
    /* @end-chisel-two-factor-authentication */
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

/* @chisel-two-factor-authentication */
export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
/* @end-chisel-two-factor-authentication */
