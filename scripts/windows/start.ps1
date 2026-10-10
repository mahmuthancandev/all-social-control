# SocialHub - yerel başlatma
# Üç süreç ayrı pencerelerde açılır:
#   1) Web sunucusu   (yalnızca 127.0.0.1 - internete açık değildir)
#   2) Queue worker    (yayınlama/senkronizasyon işleri, retry + backoff)
#   3) Scheduler       (zamanlanmış görevler; bilgisayar açık olduğu sürece çalışır)
param(
    [int]$Port = 8000
)
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'common.ps1')
$Root = $script:Root
Set-Location $Root

# PHP PATH'te olmasa da bulunur; açılan pencereler bu PATH'i devralır.
$php = Find-Php
if (-not $php -or -not $php.Usable) { Show-PhpHelp; exit 1 }
Use-Php $php

if (-not (Test-Path '.env')) {
    Write-Host '.env bulunamadı. Önce install.bat çalıştırın.' -ForegroundColor Red
    exit 1
}

if (-not (Test-Path 'public\build\manifest.json')) {
    Write-Host 'Frontend derlenmemiş, derleniyor...' -ForegroundColor Yellow
    & npm run build
}

# Web sunucusu: PHP'nin yerleşik sunucusu, yalnızca 127.0.0.1 (internete kapalı).
# Büyük video yüklemeleri için php.ini sınırları burada yükseltilir.
$phpFlags = '-d upload_max_filesize=2048M -d post_max_size=2100M -d memory_limit=512M -d max_execution_time=0'
$webCmd = "cd /d `"$Root\public`" && php $phpFlags -S 127.0.0.1:$Port ..\vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php"

$procs = @(
    @{ Title = 'SocialHub - Web';       Cmd = $webCmd },
    @{ Title = 'SocialHub - Queue';     Cmd = 'php artisan queue:work --queue=default --tries=3 --backoff=30 --sleep=3 --timeout=3700' },
    @{ Title = 'SocialHub - Scheduler'; Cmd = 'php artisan schedule:work' }
)

foreach ($p in $procs) {
    Start-Process -FilePath 'cmd.exe' -ArgumentList '/k', "chcp 65001 >nul && title $($p.Title) && cd /d `"$Root`" && $($p.Cmd)" -WindowStyle Minimized
}

Start-Sleep -Seconds 2
$url = "http://127.0.0.1:$Port"
Write-Host "`nSocialHub çalışıyor: $url" -ForegroundColor Green
Write-Host 'Durdurmak için açılan "SocialHub - ..." pencerelerini kapatın veya stop.bat çalıştırın.'
Write-Host 'Not: Bilgisayar kapalıyken veya bu pencereler kapalıyken zamanlanmış paylaşımlar çalışmaz.' -ForegroundColor Yellow
Start-Process $url
