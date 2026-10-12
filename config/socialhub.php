<?php

use App\Support\Settings;

return [

    /*
    | Demo modu: Gerçek API anahtarları bağlanana kadar panel demo verilerle çalışır.
    | Demo veriler arayüzde her zaman "DEMO" etiketiyle gösterilir.
    */
    'demo_mode' => (bool) env('SOCIALHUB_DEMO_MODE', true),

    /*
    | Giriş ekranında demo hesap bilgilerini göster (yalnızca yerel kullanım için).
    */
    'show_demo_credentials' => (bool) env('SOCIALHUB_SHOW_DEMO_CREDENTIALS', env('APP_ENV') === 'local'),

    /*
    | Medya sınırları. php.ini (upload_max_filesize, post_max_size) da en az bu kadar olmalı;
    | start.bat bu değerleri otomatik ayarlar.
    */
    'media' => [
        'max_video_mb' => (int) env('SOCIALHUB_MAX_VIDEO_MB', 2048),
        'max_image_mb' => (int) env('SOCIALHUB_MAX_IMAGE_MB', 20),
    ],

    /*
    | Sunucu (VPS) modu: Panel 7/24 açık bir Linux sunucuda, https alan adıyla çalışır.
    | Açıkken "bilgisayar kapalıyken çalışmaz" uyarıları gizlenir ve APP_URL https ise
    | Instagram medyası süreli imzalı adreslerle Meta'ya sunulabilir.
    */
    'server_mode' => (bool) env('SOCIALHUB_SERVER_MODE', false),

    // Instagram'ın medyayı indirmesi için üretilen imzalı adresin geçerlilik süresi (dakika).
    'public_media_ttl_minutes' => (int) env('SOCIALHUB_PUBLIC_MEDIA_TTL', 180),

    /*
    | .env üzerinden verilen platform ayarları (SOCIALHUB_<AYAR_ADI>). Panel ayarı önceliklidir.
    | config:cache sonrasında env() uygulama kodunda boş döndüğü için burada okunur.
    */
    'settings_env' => array_combine(
        array_keys(Settings::DEFINITIONS),
        array_map(fn (string $key) => env('SOCIALHUB_'.strtoupper($key)), array_keys(Settings::DEFINITIONS)),
    ),

    // FFmpeg (ücretsiz) yolu. Boşsa PATH içinde aranır.
    'ffmpeg_path' => env('FFMPEG_PATH'),
    'ffprobe_path' => env('FFPROBE_PATH'),

];
