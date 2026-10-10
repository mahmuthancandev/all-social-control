# SocialHub

Oyun ve uygulama geliştiren ekipler için **tamamen ücretsiz**, Windows üzerinde yerel çalışan sosyal medya yönetim paneli.
YouTube / YouTube Shorts, Instagram / Reels, TikTok ve X (Twitter) hesaplarını tek merkezden yönetmek için geliştirilmektedir.

> **Durum:** Aşama 1 tamamlandı (kurulum, giriş sistemi, kullanıcı rolleri, kullanıcı yönetimi).
> Diğer modüller menüde **pasif** ve aşama numarasıyla görünür; çalışıyormuş gibi gösterilmez.

## Teknolojiler (hepsi ücretsiz / açık kaynak)

| Katman | Teknoloji |
| --- | --- |
| Backend | PHP 8.3+, Laravel 13, Laravel Queue (database driver), Laravel Scheduler |
| Frontend | React 19, TypeScript, Inertia.js v3, Tailwind CSS v4, Lucide Icons, Recharts |
| Veritabanı | SQLite (MariaDB'ye geçişe uygun standart migration'lar) |
| Araçlar | Vite, Composer, Node.js LTS, Git, Windows Görev Zamanlayıcı |

Redis, Docker, ücretli hosting, ücretli API veya kredi kartı gerektiren hiçbir servis kullanılmaz.

## Windows'ta kurulum

### 1. Gereksinimler (ücretsiz)

| Program | Sürüm | İndirme |
| --- | --- | --- |
| PHP | 8.3+ (NTS x64) | https://windows.php.net/download veya https://php.new |
| Composer | 2.x | https://getcomposer.org/Composer-Setup.exe |
| Node.js | 20+ LTS | https://nodejs.org |
| Git | güncel | https://git-scm.com/download/win |
| FFmpeg (isteğe bağlı, Aşama 4+) | güncel | https://www.gyan.dev/ffmpeg/builds/ |

`php.ini` içinde şu eklentiler açık olmalı (satır başındaki `;` kaldırılır):
`pdo_sqlite`, `sqlite3`, `mbstring`, `openssl`, `fileinfo`, `curl`, `zip`, `intl`.

Gereksinimleri kontrol etmek için **`check.bat`** dosyasına çift tıklayın veya:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\windows\check-requirements.ps1
```

### 2. Projeyi indirin

```powershell
git clone https://github.com/mahmuthancandev/all-social-control.git SocialHub
cd SocialHub
```

### 3. Tek tıkla kurulum

**`install.bat`** dosyasına çift tıklayın. Script sırasıyla:

1. Sistem gereksinimlerini kontrol eder
2. PHP / Composer / Node sürümlerini doğrular
3. Composer bağımlılıklarını kurar
4. `.env` dosyasını hazırlar ve uygulama anahtarı üretir
5. SQLite veritabanını oluşturur (`database\database.sqlite`)
6. Migration'ları çalıştırır
7. Demo hesapları oluşturur
8. Frontend'i kurar ve derler
9. Önbellekleri temizler
10. Panel adresini gösterir ve isterseniz paneli başlatır

Elle kurulum tercih ederseniz:

```powershell
composer install
copy .env.example .env
php artisan key:generate
type nul > database\database.sqlite
php artisan migrate --seed
npm install
npm run build
```

(veya tek komut: `composer run setup`)

### 4. Başlatma / durdurma

- **`start.bat`** → üç süreci küçültülmüş pencerelerde başlatır ve tarayıcıyı açar:
  - Web sunucusu: `php artisan serve --host=127.0.0.1 --port=8000`
  - Kuyruk işçisi: `php artisan queue:work --tries=3 --backoff=30`
  - Zamanlayıcı: `php artisan schedule:work`
- **`stop.bat`** → bu pencereleri kapatır.

Panel adresi: **http://127.0.0.1:8000**

İsteğe bağlı olarak, `start.bat` açık değilken de zamanlayıcının her dakika çalışması için Windows Görev Zamanlayıcı kaydı:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\windows\register-task-scheduler.ps1          # kaydet
powershell -ExecutionPolicy Bypass -File scripts\windows\register-task-scheduler.ps1 -Remove  # kaldır
```

> ⚠️ Zamanlanmış işler yalnızca bilgisayar açık ve oturum açıkken çalışır. Bilgisayar kapalıyken zamanlanmış paylaşım **garanti edilmez**.
>
> ⚠️ Geliştirme sunucusu yalnızca `127.0.0.1` adresini dinler; **internete açmayın**. Çok kullanıcılı uzaktan erişim için ileride ayrı bir dağıtım mimarisi hazırlanacaktır.

### Geliştirme modu (canlı yenileme)

```powershell
npm run dev                # Vite (ayrı pencere)
php artisan serve --host=127.0.0.1
```

## Demo hesaplar

| Rol | E-posta | Şifre |
| --- | --- | --- |
| Yönetici (ADMIN) | `admin@socialhub.local` | `Admin123!` |
| İzleyici (VIEWER) | `viewer@socialhub.local` | `Viewer123!` |

İlk girişten sonra sağ üstteki menüden **Şifre Değiştir** ile şifreleri değiştirin.
Giriş ekranındaki demo kutusu `.env` içinde `SOCIALHUB_SHOW_DEMO_CREDENTIALS=false` yapılarak gizlenir.

## Roller

| Yetki | ADMIN | VIEWER |
| --- | :---: | :---: |
| Dashboard / istatistik / rapor görüntüleme | ✅ | ✅ |
| Kendi şifresini değiştirme | ✅ | ✅ |
| Kullanıcı ekleme / düzenleme / silme | ✅ | ❌ |
| Sosyal medya hesabı ekleme/silme, paylaşım, ayarlar (sonraki aşamalar) | ✅ | ❌ |

Yetki kontrolü sunucu tarafında `can:admin` Gate'i ile yapılır; arayüzde menünün gizlenmesi yalnızca kolaylık içindir.

## Güvenlik (Aşama 1'de uygulananlar)

- Şifreler `bcrypt` ile hash'lenir (`hashed` cast); asla düz metin saklanmaz.
- CSRF koruması (Laravel + Inertia XSRF-TOKEN çerezi).
- Oturum yenileme (session fixation koruması), çıkışta oturum ve token geçersizleştirme.
- Oturum verisi şifreli (`SESSION_ENCRYPT=true`), veritabanında saklanır.
- Giriş denemesi sınırlama: e-posta+IP başına 5 hatalı deneme / dakika, ayrıca rota başına 20 istek / dakika.
- Pasif hâle getirilen kullanıcı açık oturumundan da anında çıkarılır.
- Son aktif yönetici silinemez / rolü düşürülemez / pasifleştirilemez; yönetici kendini silemez.
- Denetim kaydı (`audit_logs`): giriş, çıkış, şifre değişikliği, kullanıcı işlemleri. `password`, `token`, `secret` vb. alanlar loglara **asla** yazılmaz (`App\Support\Audit::scrub`).
- Güvenlik başlıkları: `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`.
- Paylaşılan Inertia verisinde şifre hash'i veya remember token bulunmaz (testle doğrulanır).

## Testler

```powershell
php artisan test        # PHPUnit (giriş, roller, yetki, şifre, denetim kaydı)
npm run typecheck       # TypeScript tip kontrolü
vendor\bin\pint --test  # PHP kod stili
```

## Proje yapısı (Aşama 1)

```
app/
  Enums/UserRole.php                     ADMIN / VIEWER rolleri
  Http/Controllers/Auth/LoginController   Giriş / çıkış
  Http/Controllers/Admin/UserController   Kullanıcı yönetimi (yalnızca ADMIN)
  Http/Controllers/Settings/PasswordController
  Http/Controllers/DashboardController
  Http/Middleware/                       Inertia, aktif kullanıcı, güvenlik başlıkları
  Http/Requests/                         Laravel validation sınıfları
  Models/User.php, Models/AuditLog.php
  Support/Audit.php                      Hassas veri maskeleyen denetim kaydı
config/socialhub.php                     Demo modu ayarları
database/migrations, database/seeders    Şema ve demo hesaplar
lang/tr/                                 Türkçe doğrulama mesajları
resources/js/
  app.tsx                                Inertia + React giriş noktası
  layouts/AppLayout.tsx                  Sidebar + üst menü
  components/ui/                         Button, Form, Card, Badge, Modal, Toast, EmptyState, Skeleton
  pages/                                 auth/Login, Dashboard, users/Index, settings/Password, Error
  lib/navigation.ts                      13 ana menü (hazır olmayanlar pasif)
scripts/windows/                         Kurulum, başlatma, durdurma, görev zamanlayıcı
install.bat, start.bat, stop.bat, check.bat
```

## Yol haritası

| Aşama | Kapsam | Durum |
| --- | --- | --- |
| 1 | Laravel + React kurulumu, SQLite, giriş sistemi, roller | ✅ Tamamlandı |
| 2 | Dashboard (metrik kartları, grafikler, DEMO etiketli veriler) | ⏳ |
| 3 | Oyun/uygulama projeleri, sosyal medya hesap yönetimi | ⏳ |
| 4 | İçerik oluşturma, medya kütüphanesi, taslaklar, takvim | ⏳ |
| 5 | `SocialPlatformInterface` + YouTube / Instagram / TikTok / X servisleri, OAuth (state + PKCE), şifreli token saklama | ⏳ |
| 6 | Resmi ve ücretsiz API izinleriyle yayınlama; yoksa manuel yayın akışı | ⏳ |
| 7 | İstatistikler, karşılaştırmalar, senkronizasyon, CSV dışa aktarma | ⏳ |
| 8 | Yorumlar, bildirimler, otomasyonlar | ⏳ |
| 9 | Ek testler, güvenlik denetimi, kurulum scriptlerinin son hâli | ⏳ |

Platform API'lerinin izin, kota, ücret ve uygulama onayı gereksinimleri Aşama 5'te güncel resmi belgelerden doğrulanacaktır.
Ücretli API gerektiren hiçbir işlem otomatik yapılmayacak; bu durumlarda panel içeriği hazırlayıp manuel yayın akışı sunacaktır.

## Lisans

MIT
