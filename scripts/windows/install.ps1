# SocialHub - Windows ilk kurulum
# Kullanım: kök klasörde install.bat dosyasına çift tıklayın
#           veya: powershell -ExecutionPolicy Bypass -File scripts\windows\install.ps1
$ErrorActionPreference = 'Stop'
$Root = Resolve-Path (Join-Path $PSScriptRoot '..\..')
Set-Location $Root

function Step($n, $msg) { Write-Host "`n[$n/10] $msg" -ForegroundColor Cyan }
function Run($exe, [string[]]$argList) {
    & $exe @argList
    if ($LASTEXITCODE -ne 0) { throw "Komut başarısız oldu: $exe $($argList -join ' ')" }
}

try {
    Step 1 'Sistem gereksinimleri kontrol ediliyor'
    & powershell -NoProfile -ExecutionPolicy Bypass -File (Join-Path $PSScriptRoot 'check-requirements.ps1')
    if ($LASTEXITCODE -ne 0) { throw 'Gereksinimler karşılanmıyor.' }

    Step 2 'PHP / Composer / Node sürümleri doğrulandı'

    Step 3 'Composer bağımlılıkları kuruluyor'
    Run 'composer' @('install', '--no-interaction', '--prefer-dist')

    Step 4 '.env dosyası hazırlanıyor'
    if (-not (Test-Path '.env')) {
        Copy-Item '.env.example' '.env'
        Run 'php' @('artisan', 'key:generate', '--ansi')
    } else {
        Write-Host '  .env zaten var, korunuyor.'
        if (-not (Select-String -Path '.env' -Pattern '^APP_KEY=.+' -Quiet)) { Run 'php' @('artisan', 'key:generate', '--ansi') }
    }

    Step 5 'SQLite veritabanı oluşturuluyor'
    $db = Join-Path $Root 'database\database.sqlite'
    if (-not (Test-Path $db)) { New-Item -ItemType File -Path $db | Out-Null; Write-Host "  Oluşturuldu: $db" }
    else { Write-Host '  Veritabanı zaten var, korunuyor.' }

    Step 6 'Migration çalıştırılıyor'
    Run 'php' @('artisan', 'migrate', '--force')

    Step 7 'Demo hesaplar oluşturuluyor'
    Run 'php' @('artisan', 'db:seed', '--force')

    Step 8 'Frontend bağımlılıkları kuruluyor ve derleniyor'
    Run 'npm' @('install')
    Run 'npm' @('run', 'build')

    Step 9 'Depolama bağlantısı ve önbellek'
    try { & php artisan storage:link *> $null } catch { }
    Run 'php' @('artisan', 'optimize:clear')

    Step 10 'Kurulum tamamlandı'
    Write-Host ''
    Write-Host '  Panel adresi : http://127.0.0.1:8000' -ForegroundColor Green
    Write-Host '  Yönetici     : admin@socialhub.local  /  Admin123!' -ForegroundColor Yellow
    Write-Host '  İzleyici     : viewer@socialhub.local /  Viewer123!' -ForegroundColor Yellow
    Write-Host '  (Demo şifreleri ilk girişten sonra değiştirin.)'
    Write-Host ''
    Write-Host '  Başlatmak için: start.bat' -ForegroundColor Cyan
    Write-Host ''

    $answer = Read-Host 'Paneli şimdi başlatmak ister misiniz? (E/h)'
    if ($answer -eq '' -or $answer -match '^[eEyY]') {
        & powershell -NoProfile -ExecutionPolicy Bypass -File (Join-Path $PSScriptRoot 'start.ps1')
    }
}
catch {
    Write-Host "`nKURULUM BAŞARISIZ: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host 'Ayrıntılar için yukarıdaki çıktıyı ve storage\logs\laravel.log dosyasını inceleyin.'
    exit 1
}
