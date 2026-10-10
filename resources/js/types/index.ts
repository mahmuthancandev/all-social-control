export type UserRole = 'admin' | 'viewer';
export type PlatformKey = 'youtube' | 'instagram' | 'tiktok' | 'x';

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    role_label: string;
    is_admin: boolean;
}

export interface SharedProps {
    app: { name: string; timezone: string; demo_mode: boolean };
    auth: { user: AuthUser | null };
    counts: { notifications: number; comments: number } | null;
    flash: { success: string | null; error: string | null };
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

export interface Account {
    id: number;
    platform: PlatformKey;
    platform_label: string;
    platform_color: string;
    follower_label: string;
    handle: string;
    display_name: string;
    avatar_url: string | null;
    profile_url: string;
    status: string;
    status_label: string;
    connection_type: 'oauth' | 'api_key' | 'manual' | 'demo';
    connection_label: string;
    followers_count: number | null;
    posts_count: number | null;
    last_synced_at: string | null;
    updated_at: string | null;
    last_error: string | null;
    is_demo: boolean;
}

export interface CapabilityItem {
    key: string;
    label: string;
    enabled: boolean;
    reason: string | null;
}

export interface Series {
    key: string;
    label: string;
    color: string;
    points: Array<number | null>;
}

export interface SeriesData {
    dates: string[];
    series: Series[];
}

export interface Kpis {
    followers: number | null;
    followers_known_accounts: number;
    followers_total_accounts: number;
    followers_by_platform: Array<{ platform: PlatformKey; label: string; follower_label: string; value: number | null; accounts: number }>;
    followers_change_day: number | null;
    followers_change_week: number | null;
    followers_change_range: number | null;
    engagement_rate: number | null;
    views: number | null;
    views_previous: number | null;
    reach: number | null;
    reach_previous: number | null;
    impressions: number | null;
    likes: number | null;
    likes_previous: number | null;
    comments: number | null;
    comments_previous: number | null;
    shares: number | null;
    shares_previous: number | null;
}

export interface ExternalPostSummary {
    id: number;
    external_id: string;
    title: string | null;
    url: string | null;
    thumbnail_url: string | null;
    content_type: string | null;
    content_type_label: string | null;
    published_at: string | null;
    views: number | null;
    likes: number | null;
    comments: number | null;
    shares: number | null;
    engagement_rate: number | null;
    is_demo: boolean;
    account: Account | null;
}

export interface ComparisonRow {
    key: string;
    label: string;
    color: string;
    follower_label?: string;
    accounts: number;
    followers: number | null;
    followers_change: number | null;
    views: number | null;
    likes: number | null;
    comments: number | null;
    shares: number | null;
    engagement_rate: number | null;
    posts_published: number;
    avg_views_per_post: number | null;
    is_demo?: boolean;
    id?: number;
}

export interface MediaItem {
    id: number;
    project_id: number | null;
    folder: string | null;
    original_name: string;
    mime_type: string;
    extension: string;
    type: 'video' | 'image';
    size_bytes: number;
    width: number | null;
    height: number | null;
    duration_seconds: number | null;
    url: string;
    download_url: string;
    thumbnail_url: string | null;
    is_demo: boolean;
    created_at: string | null;
}

export interface TargetSummary {
    id: number;
    post_id: number;
    title: string | null;
    platform: PlatformKey;
    platform_label: string;
    content_type_label?: string;
    account: string;
    status: string;
    status_label: string;
    publish_mode?: string;
    scheduled_at: string | null;
    error_message?: string | null;
    is_demo: boolean;
}

export interface ProjectRef {
    id: number;
    name: string;
    color?: string;
    is_demo?: boolean;
}
