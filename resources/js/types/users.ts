export type UserListItem = {
    id: string;
    name: string;
    email: string;
    is_active: boolean;
    email_verified_at: string | null;
    roles: string[];
    created_at: string;
    updated_at: string;
};

export type PaginatedUsers = {
    data: UserListItem[];
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

export type UserFilters = {
    search?: string;
    status?: string;
};
