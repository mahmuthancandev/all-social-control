# SocialHub

Oyun ve uygulama geliştiren ekipler için **tamamen ücretsiz**, Windows üzerinde yerel çalışan sosyal medya yönetim paneli.
YouTube / Shorts, Instagram / Reels, TikTok ve X (Twitter) hesaplarını tek merkezden yönetir.

- 💸 **Sıfır maliyet:** Ücretli API, hosting, veritabanı, tema veya servis yok. X API ücretli olduğu için varsayılan olarak kapalıdır.
- 🔐 **Şifre yok:** Sosyal medya şifreleri asla istenmez; bağlantı yalnızca resmi OAuth ile yapılır, tokenlar şifreli saklanır.
- 🧭 **Dürüst arayüz:** Bir özellik API izniyle gerçekten çalışmıyorsa çalışıyormuş gibi gösterilmez; nedeni yazılır ve **Paylaşım Asistanı** sunulur.
- 🧪 **Demo modu:** API anahtarı olmadan tüm panel DEMO etiketli verilerle denenebilir; demo veriler gerçek verilerden tamamen ayrıdır ve tek tıkla silinir.

---

## İçindekiler

1. [Özellikler](#özellikler)
2. [Kurulum (Windows)](#kurulum-windows)
3. [Başlatma, zamanlayıcı ve kuyruk](#başlatma-zamanlayıcı-ve-kuyruk)
4. [Platform bağlantıları ve ücretsiz API stratejisi](#platform-bağlantıları-ve-ücretsiz-api-stratejisi)
5. [Paylaşım Asistanı (manuel yayın akışı)](#paylaşım-asistanı-manuel-yayın-akışı)
6. [Roller](#roller)
7. [Güvenlik](#güvenlik)
8. [Mimari](#mimari)
9. [Testler ve doğrulama durumu](#testler-ve-doğrulama-durumu)
10. [Sorun giderme](#sorun-giderme)

---

## Özellikler

| Modül | Neler var |
| --- | --- |
| **Dashboard** | Toplam takipçi + abone, günlük/haftalık değişim, görüntülenme, beğeni, yorum, etkileşim oranı, platform bazlı takipçi, 7/30/90 gün grafikleri, en başarılı video ve gönderiler, yaklaşan planlı paylaşımlar, başarısız/manuel bekleyen yayınlar, bildirimler, son senkronizasyon, proje/platform/demo filtresi |
| **Projelerim** | Oyun/uygulama projeleri (ad, logo, açıklama, kategori, renk), bağlı hesaplar, proje bazlı takipçi değişimi, görüntülenme, etkileşim, platform karşılaştırması, en iyi içerikler, proje takvimi |
| **Sosyal Medya Hesapları** | Sınırsız hesap (10+), OAuth ile bağlama/yenileme, YouTube için API anahtarıyla herkese açık veri, manuel hesap, proje atama, bağlantı durumu, hesap bazlı **yetenek listesi** (izinlere göre açık/kapalı + nedeni), manuel günlük metrik girişi, **CSV içe aktarma**, oEmbed ile **gönderi linki ekleme**, senkronizasyon geçmişi |
| **İçerik Oluştur** | Tek ekrandan çok platform: video/görsel yükleme veya kütüphaneden seçim, başlık, açıklama, hashtag, platform+hesap seçimi, **platforma özel** içerik türü/metin/hashtag/medya/kapak/gizlilik, karakter ve medya kuralı doğrulaması, önizleme, taslak / planla / şimdi yayınla |
| **Medya Kütüphanesi** | Yerel diskte saklama, klasör ve proje filtresi, önizleme (video oynatıcı), boyut, kullanılmayan medya tespiti, aynı medyanın birden çok gönderide kullanımı, aynı dosyanın tekrar yüklenmesinin engellenmesi (SHA-256), FFmpeg varsa süre/çözünürlük/kapak |
| **İçerik Takvimi** | Aylık / haftalık / günlük görünüm, sürükle-bırak ile tarih değiştirme, taslaklar, durum renkleri + ikonları, proje/platform/durum filtresi, Europe/Istanbul saat dilimi |
| **Gönderiler** | Liste + filtre, gönderi detayı, hedef başına durum, yayın geçmişi (her deneme), yeniden dene, iptal, yeniden planla, kopyala, **Paylaşım Asistanı** |
| **Yorumlar** | API destekli platformlarda yorum listeleme, okunmamış filtresi, gönderiye göre filtre, cevap verme (spam koruması: dakikada en fazla 10), desteklenmeyen platformlar için resmi yorum sayfası bağlantısı |
| **İstatistikler** | Günlük/haftalık/aylık gruplama, metrik seçimi, platform/hesap/proje bazlı trendler, takipçi artışı/azalışı, hesap ve gönderi performans tabloları, **geçmiş veriye dayalı en iyi paylaşım saatleri** ısı haritası, **CSV dışa aktarma** (Excel TR uyumlu) |
| **Karşılaştırmalar** | Platform karşılaştırması ve oyun/uygulama karşılaştırması (görüntülenme, etkileşim, takipçi değişimi, ortalama görüntülenme) |
| **Bildirimler** | Panel içi, kullanıcı başına okundu/okunmadı: yayınlandı, yayın başarısız, manuel paylaşım gerekli, bağlantı koptu, yeniden bağlanmalı, API kotası doldu, takipçi kaybı, **kurallar**: izlenme eşiği, takipçi hedefi, zamanlanmış paylaşım hatırlatması |
| **Kullanıcı Yönetimi** | ADMIN / VIEWER, kullanıcı ekle/düzenle/sil, pasifleştirme, son giriş |
| **Ayarlar** | Platform uygulama bilgileri (gizliler şifreli), yönlendirme (redirect) adresleri, YouTube kota takibi, X API (ücretli) uyarılı açma, sistem durumu (zamanlayıcı, kuyruk, FFmpeg, php.ini), demo verileri oluştur/sil |

**Metrik kuralı:** API izni olmayan veya ölçülmemiş metrikler veritabanında **NULL** saklanır ve arayüzde “—” (veri yok) olarak gösterilir; asla 0'a çevrilmez. YouTube “abone”, diğer platformlar “takipçi” olarak ayrı etiketlenir; Instagram erişimi (reach) ile X gösterimi (impressions) gibi farklı metrikler karıştırılmaz.

---

## Kurulum (Windows)

### 1. Ücretsiz gereksinimler

| Program | Sürüm | İndirme |
| --- | --- | --- |
| PHP | 8.3+ (NTS x64) | https://windows.php.net/download veya https://php.new |
| Composer | 2.x | https://getcomposer.org/Composer-Setup.exe |
| Node.js | 20+ LTS | https://nodejs.org |
| Git | güncel | https://git-scm.com/download/win |
| FFmpeg (isteğe bağlı) | güncel | https://www.gyan.dev/ffmpeg/builds/ |

`php.ini` içinde şu eklentileri açın (satır başındaki `;` işaretini kaldırın):
`pdo_sqlite`, `sqlite3`, `mbstring`, `openssl`, `fileinfo`, `curl`, `zip`, `intl`.

Kontrol: **`check.bat`** (veya `powershell -ExecutionPolicy Bypass -File scripts\windows\check-requirements.ps1`).

### 2. İndirin ve kurun

```powershell
git clone https://github.com/mahmuthancandev/all-social-control.git SocialHub
cd SocialHub
install.bat
```

`install.bat` sırasıyla: gereksinimleri kontrol eder → sürümleri doğrular → Composer bağımlılıklarını kurar → `.env` hazırlar ve anahtar üretir → SQLite veritabanını oluşturur → migration çalıştırır → demo hesapları ve DEMO verileri oluşturur → frontend'i derler → depolama bağlantısını kurar → panel adresini gösterir ve isterseniz başlatır.

Elle kurulum: `composer run setup` veya adım adım `composer install`, `copy .env.example .env`, `php artisan key:generate`, `type nul > database\database.sqlite`, `php artisan migrate --seed`, `php artisan storage:link`, `npm install`, `npm run build`.

### 3. Demo hesaplar

| Rol | E-posta | Şifre |
| --- | --- | --- |
| Yönetici (ADMIN) | `admin@socialhub.local` | `Admin123!` |
| İzleyici (VIEWER) | `viewer@socialhub.local` | `Viewer123!` |

İlk girişten sonra sağ üst menü → **Şifre Değiştir**. Giriş ekranındaki demo kutusu `.env` içinde `SOCIALHUB_SHOW_DEMO_CREDENTIALS=false` ile gizlenir.
Demo sosyal medya verileri **Ayarlar → Demo veriler → Demo verileri sil** ile tek tıkla kaldırılır; gerçek verileriniz etkilenmez.

---

## Başlatma, zamanlayıcı ve kuyruk

**`start.bat`** üç süreci ayrı (küçültülmüş) pencerelerde başlatır ve tarayıcıyı açar:

| Pencere | Komut | Görev |
| --- | --- | --- |
| SocialHub - Web | `php -S 127.0.0.1:8000` (public/) | Panel. Yalnızca bu bilgisayardan erişilir. Büyük video yüklemeleri için `upload_max_filesize=2048M` ile başlar. |
| SocialHub - Queue | `php artisan queue:work --tries=3 --timeout=3700` | Yayınlama ve senkronizasyon işleri (veritabanı kuyruğu, Redis gerekmez). Retry: 1 dk → 5 dk → 15 dk. |
| SocialHub - Scheduler | `php artisan schedule:work` | Her dakika planlı paylaşımlar, 5 dakikada bir bildirim kuralları, saatlik senkronizasyon, günlük token yenileme. |

**`stop.bat`** bu pencereleri kapatır. Panel: **http://127.0.0.1:8000**

`start.bat` açık değilken de zamanlayıcının çalışması için (isteğe bağlı) Windows Görev Zamanlayıcı:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\windows\register-task-scheduler.ps1          # kaydet
powershell -ExecutionPolicy Bypass -File scripts\windows\register-task-scheduler.ps1 -Remove  # kaldır
```

> ⚠️ **Bilgisayar kapalıyken veya uyku modundayken hiçbir zamanlanmış paylaşım ve senkronizasyon çalışmaz.** Panel bunu her planlama ekranında belirtir. Kaçırılan planlı paylaşımlar bilgisayar açılıp zamanlayıcı çalıştığında işlenir.
>
> ⚠️ Yerel sunucuyu **internete açmayın**. Çok kullanıcılı uzaktan erişim için ayrı bir dağıtım mimarisi gerekir (ücretli hosting zorunlu değildir).

Geliştirme modu: `npm run dev` + yukarıdaki web komutu.

---

## Platform bağlantıları ve ücretsiz API stratejisi

Her platform ayrı bir servis sınıfıdır (`app/Services/Platforms/Drivers`) ve ortak `SocialPlatformInterface`'i uygular. Her hesabın yetenekleri (`canReadFollowers`, `canReadAnalytics`, `canListPosts`, `canPublishVideo`, `canPublishImage`, `canPublishText`, `canReadComments`, `canReplyComments`) **bağlantıda gerçekten verilen OAuth izinlerinden** hesaplanır ve Hesaplar sayfasında nedenleriyle gösterilir.

Uygulama bilgileri **Ayarlar** sayfasından girilir (gizli değerler APP_KEY ile şifrelenir). Her platformun **yönlendirme adresi** Ayarlar'da kopyalanabilir şekilde gösterilir; geliştirici panelinize birebir girin.

### Güncel durum (Ekim 2026 itibarıyla araştırıldı)

| Platform | Ücretsiz ve resmi yol | Otomatik yayın | Önemli kısıt |
| --- | --- | --- | --- |
| **YouTube** | Data API v3 + Analytics API (Google Cloud, kredi kartsız) | ✅ OAuth `youtube.upload` ile | Denetimden (audit) geçmemiş projelerden API ile yüklenen videolar Google tarafından **“özel” (private)** kısıtlanır. Günlük kota 10.000 birim; yükleme maliyeti kaynaklarda 100 ile 1600 birim arasında farklı geçiyor — panel temkinli 1600 sayar, Ayarlar'dan değiştirilebilir; gerçek değeri Cloud Console kota sayfasından doğrulayın. Yalnızca herkese açık istatistik için **API anahtarı** yeterli (OAuth gerekmez). |
| **Instagram** | Instagram API with Instagram Login (`graph.instagram.com`) | ⚠️ Koşullu | Yalnızca Profesyonel hesaplar. Meta, medyayı **herkese açık bir URL'den kendisi indirir**; yerel diskteki dosyalara erişemez. Bu nedenle “herkese açık medya URL tabanı” ayarlanmadıkça otomatik yayın kapalıdır ve Paylaşım Asistanı kullanılır. Geliştirme modunda yalnızca test kullanıcıları bağlanabilir; genel kullanım için Meta uygulama incelemesi gerekir. |
| **TikTok** | Login Kit + Display API + Content Posting API | ⚠️ Taslak olarak | Varsayılan **gelen kutusu (inbox)** modunda video, `video.upload` izniyle TikTok uygulamanıza **taslak** olarak gönderilir; yayını uygulamada siz tamamlarsınız (panel bunu “yayınlandı” saymaz). Doğrudan yayın (`video.publish`) için uygulama denetimi gerekir; **denetlenmemiş uygulamaların gönderileri yalnızca “özel” (SELF_ONLY)** görünür. Kapsamlar TikTok incelemesine tabidir. Yorumlar için herkese açık resmi API yoktur. |
| **X (Twitter)** | — | ❌ Varsayılan kapalı | X, **6 Şubat 2026'dan itibaren yeni geliştiricilere ücretsiz katman sunmuyor** (kullandıkça öde / pay-per-use). Sıfır maliyet kuralı gereği X yayını **resmi ve ücretsiz `x.com/intent/post` sayfası** üzerinden Paylaşım Asistanı ile yapılır; metrikler manuel/CSV girilir. API kodu hazırdır ve Ayarlar'dan **ücreti kabul ederek** açılabilir. |

Kaynaklar (doğrulama için):
[YouTube videos.insert](https://developers.google.com/youtube/v3/docs/videos/insert) ·
[Instagram içerik yayınlama](https://developers.facebook.com/docs/instagram-platform/content-publishing) ·
[TikTok Content Posting – Direct Post](https://developers.tiktok.com/doc/content-posting-api-reference-direct-post) ·
[TikTok Content Posting – Başlangıç](https://developers.tiktok.com/docs/en/content-posting-api-get-started) ·
[X API fiyatlandırması](https://docs.x.com/x-api/getting-started/pricing) ·
[X'in kullandıkça öde geçişi (haber)](https://roboin.io/article/en/2026/02/08/x-transitions-api-to-pay-per-use-model-ending-free-plan/)

> Platform kuralları sık değişir. Bir özelliği açmadan önce ilgili resmi belgeyi kontrol edin. Panel, izni olmayan hiçbir özelliği çalışıyormuş gibi göstermez.

### API olmadan da veri toplama (ücretsiz ve kurallara uygun)

- **YouTube API anahtarı:** Hesap eklerken “Herkese açık API anahtarı” seçin; abone, video ve yorum verileri OAuth olmadan okunur.
- **CSV içe aktarma:** YouTube Studio, Instagram, TikTok veya X Analytics'ten indirdiğiniz günlük raporları yükleyin. Türkçe/İngilizce başlıklar (`tarih/date`, `takipçi/followers/abone`, `görüntüleme/views`, `beğeni/likes`, `yorum/comments`, `paylaşım/shares`, `erişim/reach`, `gösterim/impressions`), `,` `;` veya sekme ayracı, `12.345` / `1,5K` biçimleri tanınır.
- **Manuel metrik girişi:** Hesap sayfasında “Günlük metrik gir”.
- **Gönderi linki ekleme:** YouTube, TikTok ve X'in resmi oEmbed servisleriyle başlık/kapak alınır (Instagram oEmbed uygulama tokenı gerektirdiği için yalnızca link saklanır).

**Neden tarayıcı otomasyonu / kazıma yok?** Instagram, TikTok ve X kullanım şartları; bot ile giriş, otomatik paylaşım ve izinsiz veri çekmeyi yasaklar. Bu yöntemler hesap kısıtlaması/kapatılması riski taşır, sosyal medya şifrelerinin saklanmasını gerektirir ve sık bozulur. SocialHub bunların yerine resmi API'leri ve Paylaşım Asistanı'nı kullanır.

---

## Paylaşım Asistanı (manuel yayın akışı)

API izni olmayan hedefler (ör. X, herkese açık URL'si olmayan Instagram, manuel hesaplar) için gönderi sayfasında:

1. **Açıklama + hashtag** ve **başlığı** tek tıkla panoya kopyalayın,
2. **Video/görsel ve kapağı** indirin,
3. Platformun **resmi paylaşım sayfasını** açın (YouTube Studio, TikTok Studio, Instagram, `x.com/intent/post` — X için metin önceden doldurulur),
4. Paylaştıktan sonra **“Manuel yayınlandı”** olarak işaretleyin (isterseniz gönderi linkiyle; link istatistik takibine eklenir).

Manuel hedefler, siz işaretleyene kadar asla “yayınlandı” görünmez. Durumlar ayrıdır: `Yayınlandı (API)` ≠ `Manuel yayınlandı` ≠ `Manuel paylaşım bekliyor`.

---

## Roller

| Yetki | ADMIN | VIEWER |
| --- | :---: | :---: |
| Dashboard, projeler, hesaplar, gönderiler, takvim, medya, yorumlar, istatistikler, karşılaştırmalar (görüntüleme) | ✅ | ✅ |
| CSV dışa aktarma, kendi bildirimleri ve şifresi | ✅ | ✅ |
| İçerik oluşturma, yayınlama, planlama, manuel işaretleme | ✅ | ❌ |
| Hesap ekleme/silme, OAuth bağlantısı, senkronizasyon, metrik/CSV girişi | ✅ | ❌ |
| Medya yükleme/silme, proje yönetimi, yorum cevaplama, bildirim kuralları | ✅ | ❌ |
| Kullanıcı yönetimi, Ayarlar, demo veri yönetimi | ✅ | ❌ |

Tüm yetki kontrolleri sunucu tarafında `can:admin` Gate'i ile yapılır; menünün gizlenmesi yalnızca kolaylıktır.

---

## Güvenlik

- Panel şifreleri `bcrypt`; sosyal medya şifreleri **hiç istenmez**.
- OAuth: tek kullanımlık, 10 dakika geçerli **state** (zamanlamaya dayanıklı `hash_equals`), destekleyen platformlarda **PKCE (S256)**.
- OAuth tokenları ve gizli ayarlar **APP_KEY ile şifreli** (`encrypted` cast); frontend'e, sayfa verisine ve loglara gönderilmez (testle doğrulanır). Denetim kaydı `password/token/secret` alanlarını maskeler.
- CSRF koruması, oturum yenileme, şifreli oturum (`SESSION_ENCRYPT=true`), pasif kullanıcının oturumunun anında sonlandırılması.
- Rate limiting: giriş (e-posta+IP başına 5 hata/dk), OAuth, yükleme, yayınlama, senkronizasyon, CSV dışa aktarma ve yorum cevapları (spam önleme).
- Medya: içerikten (finfo) MIME doğrulama + uzantı beyaz listesi (SVG/PHP vb. reddedilir), boyut sınırı, rastgele dosya adı, web kökü dışında saklama, yalnızca giriş yapmış kullanıcıya `nosniff` + kısıtlı CSP ile sunum.
- CSV dışa aktarmada formül enjeksiyonu (`=`, `+`, `-`, `@`) etkisizleştirilir.
- Tekrar yayın koruması: her hedefin benzersiz `idempotency_key`'i, atomik “Kuyrukta → Yayınlanıyor” sahiplenmesi ve yayınlanmış hedeflerin kilitlenmesi.
- Güvenlik başlıkları: `X-Frame-Options: DENY`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`.
- Sunucu yalnızca `127.0.0.1` dinler.

---

## Mimari

```
app/
  Enums/                    Platform, ContentType, Capability, TargetStatus, PostStatus, AccountStatus, UserRole
  Services/Platforms/
    Contracts/              SocialPlatformInterface, ProvidesDailyAnalytics
    Drivers/                YouTubeService, InstagramService, TikTokService, XService, DemoPlatformService
    Data/                   TokenSet, ProfileData, RemotePost, RemoteComment, PublishResult, CapabilitySet
    PlatformManager.php     Hesaba uygun sürücü (demo hesaplar asla gerçek API'ye gitmez)
    QuotaTracker.php        Günlük kota sayacı (YouTube)
    LinkPreviewService.php  Resmi oEmbed
  Services/Publishing/      PublishingService (kuyruk, manuel akış, tekrar koruması), TargetValidator
  Services/Sync/            AccountSyncService (profil, günlük ölçüm, gönderiler, yorumlar, hata/kota yönetimi)
  Services/Analytics/       AnalyticsService (KPI, zaman serisi, karşılaştırma, en iyi saatler — DB bağımsız)
  Services/Import/          MetricsCsvImporter
  Services/Media/           MediaService (yükleme, SHA-256 tekrar tespiti, FFmpeg)
  Services/Demo/            DemoDataGenerator (üret / günlük ilerlet / temizle)
  Jobs/                     PublishTargetJob (3 deneme, 1/5/15 dk backoff, benzersiz), SyncAccountJob
  Console/Commands/         socialhub:dispatch-scheduled | sync-accounts | check-notifications | refresh-tokens
resources/js/pages/         Dashboard, projects, accounts, posts (Composer, Show), media, calendar, comments,
                            analytics, compare, notifications, settings, users, auth
scripts/windows/            check-requirements, install, start, stop, register-task-scheduler
```

**Veritabanı tabloları:** `users`, `projects`, `social_accounts`, `account_credentials`, `project_social_accounts`, `posts`, `post_media`, `post_platform_targets`, `media_files`, `publication_jobs`, `account_metric_snapshots`, `external_posts`, `post_metric_snapshots`, `comments`, `notification_rules`, `notifications`, `sync_logs`, `api_quota_usages`, `settings`, `audit_logs` (+ Laravel `jobs`, `failed_jobs`, `sessions`, `cache`).
Tüm demo kayıtlar `is_demo` ile işaretlidir.

**MariaDB'ye geçiş:** Migration'lar standart Laravel şema yapıcısıyla yazılmıştır, istatistik gruplaması PHP tarafında yapılır ve tarih alanları `Y-m-d` olarak saklanır (`App\Casts\DateOnly`). `.env` içinde `DB_CONNECTION=mariadb` ve bağlantı bilgilerini girip `php artisan migrate` çalıştırmanız yeterlidir.

**Grafik renkleri:** Platform renkleri (YouTube `#d95926`, Instagram `#9085e9`, TikTok `#199e70`, X `#3987e5`) koyu yüzeyde renk körlüğü ayrımı ve kontrast açısından doğrulanmış sırayla seçilmiştir; renk tek başına bilgi taşımaz (lejant, etiket, ikon her zaman vardır).

---

## Testler ve doğrulama durumu

```powershell
php artisan test        # 81 test, 359 doğrulama
npm run typecheck       # TypeScript
vendor\bin\pint --test  # PHP kod stili
```

**Otomatik testlerin kapsadıkları:** giriş, hız sınırı, pasif kullanıcı, ADMIN/VIEWER yetkileri (tüm sayfalar ve değiştirme işlemleri), şifre değiştirme, OAuth state/PKCE/tek kullanım, tokenların şifreli saklanması ve sızmaması, platform yeteneklerinin izinlere göre hesaplanması, X'in varsayılan kapalı olması, Instagram'ın herkese açık URL koşulu, TikTok'ta yorum iddia edilmemesi, YouTube API anahtarıyla senkronizasyon (sahte HTTP), iptal edilmiş token → yeniden bağlanma bildirimi, kota koruması, yayın kuyruğu, tekrar yayın engeli, planlı yayınların zamanında işlenmesi, başarısızlık bildirimi, retry/backoff, içerik oluşturma ve platform kuralları, medya yükleme güvenliği (gizlenmiş PHP, SVG), CSV içe/dışa aktarma, NULL ≠ 0 kuralı, yorum cevabı ve spam sınırı, bildirim kurallarının bir kez tetiklenmesi, demo verilerin ayrı silinmesi, ayarların şifrelenmesi.

**Uçtan uca tarayıcı testi (Chromium):** tüm sayfalar admin ve izleyici olarak açıldı (konsol hatası yok), medya yükleme, çok platformlu içerik oluşturma, kuyruk işçisiyle demo yayın, X için Paylaşım Asistanı ve manuel işaretleme denendi.

**Henüz test EDİLMEYENLER (açıkça):**
- Gerçek YouTube / Instagram / TikTok / X hesaplarına **canlı** bağlantı ve yayın. Servisler resmi belgelere göre yazıldı ve sahte HTTP yanıtlarıyla test edildi; gerçek uygulama kimlik bilgileriyle ilk denemede uç nokta veya izin farklılıkları çıkabilir.
- Windows PowerShell/BAT betikleri bu geliştirme ortamında (Linux) **çalıştırılmadı**; UTF-8 BOM ve CRLF ile hazırlandı.
- Instagram ve TikTok'un yerel `http://127.0.0.1` yönlendirme adresini kabul edip etmediği (https isteyebilirler; Ayarlar'daki “OAuth yönlendirme adresi tabanı” bunun için vardır).

---

## Sorun giderme

| Sorun | Çözüm |
| --- | --- |
| `PHP bulunamadı` ama PHP kurulu | Betik PATH'e ek olarak XAMPP, Laragon, WAMP, Herd, php.new, Scoop, Chocolatey ve `C:\php` klasörlerini arar. Yine bulamazsa yolu verin: `install.bat "C:\xampp\php\php.exe"` (bulunan yol `.php-path` dosyasına kaydedilir; `start.bat` de onu kullanır). |
| PHP 8.2 veya eski bulundu | Laravel 13 için PHP **8.3+** gerekir. XAMPP/Laragon'da 8.3+ sürümü seçin veya PowerShell'de: `Set-ExecutionPolicy Bypass -Scope Process -Force; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.4'))` |
| `Composer bulunamadı` | Gerekmez: `install.bat` resmi `composer.phar` dosyasını getcomposer.org'dan indirir ve SHA-256 ile doğrular (`scripts\windows\bin`). |
| `could not find driver` | `php.ini`'de `extension=pdo_sqlite` ve `extension=sqlite3` satırlarını açın. |
| Video yüklenmiyor (boyut) | Paneli `start.bat` ile başlatın (2 GB sınırla açar) veya `php.ini`'de `upload_max_filesize` / `post_max_size` değerlerini artırın. |
| Planlı paylaşım çalışmadı | Bilgisayar açık mıydı? `start.bat` pencereleri açık mı? Ayarlar → Sistem durumu → Zamanlayıcı son çalışma zamanı. |
| Hesap “Yeniden bağlanmalı” | Hesap sayfasında **Bağlantıyı yenile**. Token süresi dolmuş veya izin iptal edilmiş. |
| YouTube videosu “özel” yüklendi | Google denetiminden geçmemiş projelerin beklenen davranışı; YouTube Studio'dan görünürlüğü değiştirin veya Google denetimine başvurun. |
| Video kapakları oluşmuyor | FFmpeg'i kurun ve PATH'e ekleyin veya `.env`'de `FFMPEG_PATH`/`FFPROBE_PATH` verin. |
| Türkçe karakterler bozuk (PowerShell) | `chcp 65001` çalıştırın; `.bat` dosyaları bunu otomatik yapar. |

## Lisans

MIT
