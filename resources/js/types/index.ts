export type UserRole = 'admin' | 'viewer';

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    role_label: string;
    is_admin: boolean;
}

export interface SharedProps {
    app: {
        name: string;
        timezone: string;
        demo_mode: boolean;
    };
    auth: {
        user: AuthUser | null;
    };
    flash: {
        success: string | null;
        error: string | null;
    };
    errors: Record<string, string>;
    [key: string]: unknown;
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}
