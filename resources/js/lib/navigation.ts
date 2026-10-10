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
    /** Hazır olan sayfaların adresi. Henüz geliştirilmemiş modüllerde null. */
    href: string | null;
    /** Modülün planlandığı geliştirme aşaması (henüz hazır değilse). */
    stage?: number;
    adminOnly?: boolean;
}

export const navigation: NavItem[] = [
    { label: 'Dashboard', icon: LayoutDashboard, href: '/dashboard' },
    { label: 'Projelerim', icon: FolderKanban, href: null, stage: 3 },
    { label: 'Sosyal Medya Hesapları', icon: Share2, href: null, stage: 3 },
    { label: 'İçerik Oluştur', icon: PenSquare, href: null, stage: 4, adminOnly: true },
    { label: 'Medya Kütüphanesi', icon: Images, href: null, stage: 4 },
    { label: 'İçerik Takvimi', icon: CalendarDays, href: null, stage: 4 },
    { label: 'Gönderiler', icon: FileText, href: null, stage: 4 },
    { label: 'Yorumlar', icon: MessageSquare, href: null, stage: 8 },
    { label: 'İstatistikler', icon: BarChart3, href: null, stage: 7 },
    { label: 'Karşılaştırmalar', icon: GitCompareArrows, href: null, stage: 7 },
    { label: 'Bildirimler', icon: Bell, href: null, stage: 8 },
    { label: 'Kullanıcı Yönetimi', icon: Users, href: '/admin/users', adminOnly: true },
    { label: 'Ayarlar', icon: Settings, href: null, stage: 5, adminOnly: true },
];
