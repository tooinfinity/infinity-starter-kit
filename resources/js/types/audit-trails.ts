/* @chisel-audit-trails */

export type AuditEventName =
    | 'user.created'
    | 'user.updated'
    | 'user.activated'
    | 'user.deactivated'
    | 'user.deleted'
    | 'user.password_changed'
    | 'settings.updated';

export type AuditUser = {
    id: string;
    name: string;
    email: string;
};

export type AuditTrailItem = {
    id: string;
    user_id: string | null;
    user: AuditUser | null;
    event: AuditEventName;
    event_label: string;
    auditable_type: string | null;
    auditable_id: string | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    url: string | null;
    ip_address: string | null;
    user_agent: string | null;
    tags: string[] | null;
    created_at: string;
};

export type PaginatedAuditTrails = {
    data: AuditTrailItem[];
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
    meta?: {
        current_page: number;
        from: number | null;
        last_page: number;
        path: string;
        per_page: number;
        to: number | null;
        total: number;
    };
    current_page?: number;
    last_page?: number;
    per_page?: number;
    total?: number;
    from?: number | null;
    to?: number | null;
};

export type AuditTrailFilters = {
    search?: string | null;
    event?: string | null;
    user_id?: string | null;
    date_from?: string | null;
    date_to?: string | null;
};

export type AvailableEvent = {
    value: string;
    label: string;
};

/* @end-chisel-audit-trails */
