/* @chisel-reporting */

export type ReportSummaryCard = {
    id: string;
    title: string;
    value: number | string;
    description: string;
    icon: string;
};

export type ReportTimeSeriesPoint = {
    date: string;
    value: number;
};

export type ReportBreakdownItem = {
    key: string;
    label: string;
    count: number;
    percentage: number;
};

export type ReportMetadata = {
    identifier: string;
    title: string;
    description: string;
    category: string;
    categoryLabel: string;
    route: string;
    permission: string;
};

export type ReportUserItem = {
    id: string;
    name: string;
    email: string;
    is_active: boolean;
    roles: string[];
    created_at: string;
};

export type PaginatedReportUsers = {
    data: ReportUserItem[];
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

export type UserReportFilters = {
    date_from: string;
    date_to: string;
    status?: string;
    search?: string | null;
    sort?: string;
    direction?: string;
};

export type ReportAuditItem = {
    id: string;
    event: string;
    event_label: string;
    user: {
        id: string;
        name: string;
        email: string;
    } | null;
    auditable_type: string | null;
    auditable_id: string | null;
    ip_address: string | null;
    created_at: string;
};

export type PaginatedReportAuditTrails = {
    data: ReportAuditItem[];
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

export type AuditReportFilters = {
    date_from: string;
    date_to: string;
    event?: string;
    user_id?: string | null;
    search?: string | null;
    sort?: string;
    direction?: string;
};

export type AvailableReportEvent = {
    value: string;
    label: string;
};

/* @end-chisel-reporting */
