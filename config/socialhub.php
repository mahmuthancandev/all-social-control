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

];
