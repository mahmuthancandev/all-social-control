# SocialHub - Windows gereksinim kontrolü
# Kullanım: check.bat   veya   check.bat "C:\yol\php.exe"
param([string]$PhpPath)
$ErrorActionPreference = 'Continue'
. (Join-Path $PSScriptRoot 'common.ps1')
if ($PhpPath) { $env:SOCIALHUB_PHP = $PhpPath }

$script:Failed = $false
function Write-Ok($msg)    { Write-Host "  [OK]   $msg" -ForegroundColor Green }
function Write-Bad($msg)   { Write-Host "  [HATA] $msg" -ForegroundColor Red; $script:Failed = $true }
function Write-Warn2($msg) { Write-Host "  [UYARI] $msg" -ForegroundColor Yellow }

Write-Host "`nSocialHub gereksinim kontrolü`n" -ForegroundColor Cyan

# PHP >= 8.3 (PATH'te olmasa da yaygın klasörlerde aranır)
$php = Find-Php
if (-not $php) {
    Write-Bad 'PHP bulunamadı (PATH, XAMPP, Laragon, WAMP, Herd, C:\php vb. arandı).'
    Show-PhpHelp
} elseif (-not $php.Usable) {
    Write-Bad "PHP $($php.Version) bulundu ($($php.Path)), en az 8.3 gerekli."
    Show-PhpHelp
} else {
    Use-Php $php
    Write-Ok "PHP $($php.Version)  ($($php.Path))"

    $mods = (& $php.Path -m) -join ' '
    $ini = (& $php.Path -r "echo php_ini_loaded_file() ?: 'yok';")
    foreach ($ext in @('pdo_sqlite','sqlite3','mbstring','openssl','fileinfo','curl','zip','intl')) {
        if ($mods -match "(?i)\b$ext\b") { Write-Ok "PHP eklentisi: $ext" }
        else { Write-Bad "PHP eklentisi eksik: $ext  ->  php.ini ($ini) içinde ';extension=$ext' satırının başındaki ';' işaretini kaldırın" }
    }
    if ($ini -eq 'yok') {
        Write-Warn2 "php.ini yok. $(Split-Path -Parent $php.Path) klasöründe 'php.ini-development' dosyasını 'php.ini' adıyla kopyalayıp eklentileri açın (extension_dir = ""ext"" satırını da açın)."
    }
}

# Composer (yoksa kurulum sırasında otomatik indirilir)
$composer = Find-Composer
if ($composer) { Write-Ok "Composer ($($composer.Path))" }
else { Write-Warn2 'Composer bulunamadı. install.bat ücretsiz composer.phar dosyasını otomatik indirecek.' }

# Node.js >= 20 LTS
if (Get-Command node -ErrorAction SilentlyContinue) {
    $nodeVersion = (& node -v).TrimStart('v')
    if ([int]($nodeVersion.Split('.')[0]) -ge 20) { Write-Ok "Node.js v$nodeVersion" }
    else { Write-Bad "Node.js v$nodeVersion bulundu, en az 20 LTS gerekli: https://nodejs.org" }
} else {
    Write-Bad 'Node.js bulunamadı. Ücretsiz LTS kurulum: https://nodejs.org'
}
if (Get-Command npm -ErrorAction SilentlyContinue) { Write-Ok "npm $(& npm -v)" } else { Write-Bad 'npm bulunamadı (Node.js ile birlikte gelir).' }

if (Get-Command git -ErrorAction SilentlyContinue) { Write-Ok (& git --version) }
else { Write-Warn2 'Git bulunamadı (önerilir): https://git-scm.com/download/win' }

if (Get-Command ffmpeg -ErrorAction SilentlyContinue) { Write-Ok 'FFmpeg bulundu' }
else { Write-Warn2 'FFmpeg bulunamadı (isteğe bağlı; video kapağı/süresi için): https://www.gyan.dev/ffmpeg/builds/' }

Write-Host ''
if ($script:Failed) {
    Write-Host 'Eksik gereksinimler var. Yukarıdaki HATA satırlarını giderip tekrar deneyin.' -ForegroundColor Red
    exit 1
}
Write-Host 'Tüm zorunlu gereksinimler karşılanıyor.' -ForegroundColor Green
exit 0
