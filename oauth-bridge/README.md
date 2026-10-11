# SocialHub OAuth köprüsü (ücretsiz, GitHub Pages)

Instagram ve TikTok, OAuth yönlendirme adresi için **https** ister; yerel panel ise `http://127.0.0.1:8000` üzerinde çalışır.
Bu klasördeki statik sayfa, platformun gönderdiği `code` ve `state` parametrelerini tarayıcınız üzerinden yerel panele iletir.
Sunucunuz internete açılmaz; sayfa hiçbir şey saklamaz. (`code` tek kullanımlıktır ve App Secret olmadan işe yaramaz.)

Kurulum:
1. GitHub'da **herkese açık (Public)** yeni bir depo oluşturun, ör. `socialhub-oauth`.
2. Bu klasördeki `index.html` ve `404.html` dosyalarını deponun köküne yükleyin.
3. Depo → Settings → Pages → Source: *Deploy from a branch*, Branch: `main` / `(root)` → Save.
4. 1-2 dakika sonra adres hazır: `https://KULLANICIADI.github.io/socialhub-oauth`
5. SocialHub → Ayarlar → Gelişmiş → **Instagram/TikTok için https yönlendirme tabanı** alanına bu adresi girin.
