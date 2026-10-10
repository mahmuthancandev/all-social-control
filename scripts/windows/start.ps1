# SocialHub - yerel başlatma
# Üç süreç ayrı pencerelerde açılır:
#   1) Web sunucusu   (yalnızca 127.0.0.1 - internete açık değildir)
#   2) Queue worker    (yayınlama/senkronizasyon işleri, retry + backoff)
#   3) Scheduler       (zamanlanmış görevler; bilgisayar açık olduğu sürece çalışır)
param(
    [int]$Port = 8000
)
$ErrorActionPreference = 'Stop'
$Root = Resolve-Path (Join-Path $PSScriptRoot '..\..')
Set-Location $Root

if (-not (Test-Path '.env')) {
    Write-Host '.env bulunamadı. Önce install.bat çalıştırın.' -ForegroundColor Red
    exit 1
}

if (-not (Test-Path 'public\build\manifest.json')) {
    Write-Host 'Frontend derlenmemiş, derleniyor...' -ForegroundColor Yellow
    & npm run build
}

$procs = @(
    @{ Title = 'SocialHub - Web';       Cmd = "php artisan serve --host=127.0.0.1 --port=$Port" },
    @{ Title = 'SocialHub - Queue';     Cmd = 'php artisan queue:work --queue=default --tries=3 --backoff=30 --sleep=3 --timeout=900' },
    @{ Title = 'SocialHub - Scheduler'; Cmd = 'php artisan schedule:work' }
)

foreach ($p in $procs) {
    Start-Process -FilePath 'cmd.exe' -ArgumentList '/k', "title $($p.Title) && cd /d `"$Root`" && $($p.Cmd)" -WindowStyle Minimized
}

Start-Sleep -Seconds 2
$url = "http://127.0.0.1:$Port"
Write-Host "`nSocialHub çalışıyor: $url" -ForegroundColor Green
Write-Host 'Durdurmak için açılan "SocialHub - ..." pencerelerini kapatın veya stop.bat çalıştırın.'
Write-Host 'Not: Bilgisayar kapalıyken veya bu pencereler kapalıyken zamanlanmış paylaşımlar çalışmaz.' -ForegroundColor Yellow
Start-Process $url
