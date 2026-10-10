import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/Button';

const messages: Record<number, { title: string; description: string }> = {
    403: { title: 'Erişim yetkiniz yok', description: 'Bu sayfayı görüntülemek veya bu işlemi yapmak için yönetici yetkisi gerekiyor.' },
    404: { title: 'Sayfa bulunamadı', description: 'Aradığınız sayfa taşınmış veya hiç var olmamış olabilir.' },
    429: { title: 'Çok fazla istek', description: 'Kısa sürede çok fazla istek gönderildi. Lütfen biraz bekleyip tekrar deneyin.' },
    500: { title: 'Sunucu hatası', description: 'Beklenmeyen bir hata oluştu. Ayrıntılar storage/logs klasörüne kaydedildi.' },
    503: { title: 'Bakım modu', description: 'Panel şu anda bakımda. Lütfen daha sonra tekrar deneyin.' },
};

export default function Error({ status }: { status: number }) {
    const message = messages[status] ?? messages[500];

    return (
        <div className="flex min-h-screen items-center justify-center bg-surface-950 px-4">
            <Head title={message.title} />
            <div className="max-w-md animate-slide-up text-center">
                <p className="bg-gradient-to-br from-brand-300 to-fuchsia-400 bg-clip-text text-7xl font-bold text-transparent">{status}</p>
                <h1 className="mt-4 text-xl font-semibold text-white">{message.title}</h1>
                <p className="mt-2 text-sm text-slate-400">{message.description}</p>
                <Link href="/dashboard" className="mt-8 inline-block">
                    <Button>Dashboard'a dön</Button>
                </Link>
            </div>
        </div>
    );
}
