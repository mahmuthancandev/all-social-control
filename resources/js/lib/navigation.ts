import {
    BarChart3,
    Bell,
    CalendarDays,
    FileText,
    FolderKanban,
    GitCompareArrows,
    Images,
    LayoutDashboard,
    MessageSquare,
    PenSquare,
    Settings,
    Share2,
    Users,
    type LucideIcon,
} from 'lucide-react';

export interface NavItem {
    label: string;
    icon: LucideIcon;
    href: string;
    adminOnly?: boolean;
    count?: 'notifications' | 'comments';
}

export const navigation: NavItem[] = [
    { label: 'Dashboard', icon: LayoutDashboard, href: '/dashboard' },
    { label: 'Projelerim', icon: FolderKanban, href: '/projects' },
    { label: 'Sosyal Medya Hesapları', icon: Share2, href: '/accounts' },
    { label: 'İçerik Oluştur', icon: PenSquare, href: '/posts/create', adminOnly: true },
    { label: 'Medya Kütüphanesi', icon: Images, href: '/media' },
    { label: 'İçerik Takvimi', icon: CalendarDays, href: '/calendar' },
    { label: 'Gönderiler', icon: FileText, href: '/posts' },
    { label: 'Yorumlar', icon: MessageSquare, href: '/comments', count: 'comments' },
    { label: 'İstatistikler', icon: BarChart3, href: '/analytics' },
    { label: 'Karşılaştırmalar', icon: GitCompareArrows, href: '/compare' },
    { label: 'Bildirimler', icon: Bell, href: '/notifications', count: 'notifications' },
    { label: 'Kullanıcı Yönetimi', icon: Users, href: '/admin/users', adminOnly: true },
    { label: 'Ayarlar', icon: Settings, href: '/settings', adminOnly: true },
];
