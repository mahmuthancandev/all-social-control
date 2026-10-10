<?php

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

    // FFmpeg (ücretsiz) yolu. Boşsa PATH içinde aranır.
    'ffmpeg_path' => env('FFMPEG_PATH'),
    'ffprobe_path' => env('FFPROBE_PATH'),

];
