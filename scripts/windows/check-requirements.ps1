# SocialHub - Windows gereksinim kontrolü
# Kullanım: powershell -ExecutionPolicy Bypass -File scripts\windows\check-requirements.ps1
$ErrorActionPreference = 'Continue'

$script:Failed = $false

function Write-Ok($msg)   { Write-Host "  [OK]   $msg" -ForegroundColor Green }
function Write-Bad($msg)  { Write-Host "  [HATA] $msg" -ForegroundColor Red; $script:Failed = $true }
function Write-Warn2($msg){ Write-Host "  [UYARI] $msg" -ForegroundColor Yellow }

Write-Host "`nSocialHub gereksinim kontrolü`n" -ForegroundColor Cyan

# PHP >= 8.3
$php = Get-Command php -ErrorAction SilentlyContinue
if (-not $php) {
    Write-Bad "PHP bulunamadı. Ücretsiz kurulum: https://windows.php.net/download (8.3+ NTS x64) veya https://php.new"
} else {
    $phpVersion = (& php -r "echo PHP_VERSION;")
    if ([version]($phpVersion -replace '[^0-9.].*$','') -ge [version]'8.3.0') { Write-Ok "PHP $phpVersion" }
    else { Write-Bad "PHP $phpVersion bulundu, en az 8.3 gerekli." }

    $mods = (& php -m) -join ' '
    foreach ($ext in @('pdo_sqlite','sqlite3','mbstring','openssl','fileinfo','curl','zip','intl')) {
        if ($mods -match "(?i)\b$ext\b") { Write-Ok "PHP eklentisi: $ext" }
        else { Write-Bad "PHP eklentisi eksik: $ext  (php.ini içinde ';extension=$ext' satırının başındaki ';' işaretini kaldırın)" }
    }
}

# Composer
if (Get-Command composer -ErrorAction SilentlyContinue) {
    Write-Ok ((& composer --version --no-ansi 2>$null) | Select-Object -First 1)
} else {
    Write-Bad "Composer bulunamadı. Ücretsiz kurulum: https://getcomposer.org/Composer-Setup.exe"
}

# Node.js >= 20 LTS
if (Get-Command node -ErrorAction SilentlyContinue) {
    $nodeVersion = (& node -v).TrimStart('v')
    if ([int]($nodeVersion.Split('.')[0]) -ge 20) { Write-Ok "Node.js v$nodeVersion" }
    else { Write-Bad "Node.js v$nodeVersion bulundu, en az 20 LTS gerekli: https://nodejs.org" }
} else {
    Write-Bad "Node.js bulunamadı. Ücretsiz LTS kurulum: https://nodejs.org"
}

if (Get-Command npm -ErrorAction SilentlyContinue) { Write-Ok "npm $(& npm -v)" } else { Write-Bad "npm bulunamadı (Node.js ile birlikte gelir)." }

# Git (zorunlu değil ama önerilir)
if (Get-Command git -ErrorAction SilentlyContinue) { Write-Ok (& git --version) }
else { Write-Warn2 "Git bulunamadı (önerilir): https://git-scm.com/download/win" }

# FFmpeg (ileriki aşamalarda medya işleme için, isteğe bağlı)
if (Get-Command ffmpeg -ErrorAction SilentlyContinue) { Write-Ok "FFmpeg bulundu" }
else { Write-Warn2 "FFmpeg bulunamadı (Aşama 4+ medya işleme için isteğe bağlı): https://www.gyan.dev/ffmpeg/builds/" }

Write-Host ""
if ($script:Failed) {
    Write-Host "Eksik gereksinimler var. Yukarıdaki HATA satırlarını giderip tekrar deneyin." -ForegroundColor Red
    exit 1
}
Write-Host "Tüm zorunlu gereksinimler karşılanıyor." -ForegroundColor Green
exit 0
