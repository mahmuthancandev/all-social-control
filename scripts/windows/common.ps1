# SocialHub - ortak yardımcılar (PHP ve Composer'ı bulma)
# Diğer betikler bu dosyayı ". (Join-Path $PSScriptRoot 'common.ps1')" ile yükler.

$script:Root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$script:PhpPathFile = Join-Path $script:Root '.php-path'
$script:ComposerPhar = Join-Path $script:Root 'scripts\windows\bin\composer.phar'

function Get-PhpVersion([string]$exe) {
    try {
        $v = & $exe -r "echo PHP_VERSION;" 2>$null
        if ($LASTEXITCODE -eq 0 -and $v) { return [string]$v }
    } catch { }
    return $null
}

function Test-PhpUsable([string]$exe) {
    if (-not $exe -or -not (Test-Path -LiteralPath $exe -PathType Leaf)) { return $false }
    $v = Get-PhpVersion $exe
    if (-not $v) { return $false }
    return ([version]($v -replace '[^0-9.].*$', '') -ge [version]'8.3.0')
}

# PHP'yi sırasıyla arar: SOCIALHUB_PHP ortam değişkeni, kayıtlı .php-path, PATH,
# ardından XAMPP / Laragon / WAMP / Herd / php.new / Scoop / Chocolatey / C:\php klasörleri.
# Birden fazla bulunursa 8.3+ olan en yüksek sürüm seçilir.
function Find-Php {
    $candidates = New-Object System.Collections.Generic.List[string]

    if ($env:SOCIALHUB_PHP) { $candidates.Add($env:SOCIALHUB_PHP) }
    if (Test-Path -LiteralPath $script:PhpPathFile) { $candidates.Add(([string](Get-Content -LiteralPath $script:PhpPathFile -Raw)).Trim().TrimStart([char]0xFEFF)) }

    Get-Command php -All -ErrorAction SilentlyContinue | ForEach-Object { if ($_.Source) { $candidates.Add($_.Source) } }

    $userHome = $env:USERPROFILE
    $patterns = @(
        "$userHome\.config\herd-lite\bin\php.exe",
        "$userHome\.config\herd\bin\php*.exe",
        "$userHome\scoop\apps\php*\current\php.exe",
        'C:\xampp\php\php.exe',
        'D:\xampp\php\php.exe',
        'C:\laragon\bin\php\*\php.exe',
        'D:\laragon\bin\php\*\php.exe',
        'C:\wamp64\bin\php\*\php.exe',
        'C:\wamp\bin\php\*\php.exe',
        'C:\php\php.exe',
        'C:\php*\php.exe',
        'C:\tools\php*\php.exe',
        'C:\Program Files\PHP\*\php.exe',
        'C:\Program Files\php*\php.exe',
        'C:\ProgramData\chocolatey\bin\php.exe'
    )
    foreach ($p in $patterns) {
        try { Get-ChildItem -Path $p -ErrorAction SilentlyContinue | ForEach-Object { $candidates.Add($_.FullName) } } catch { }
    }

    $best = $null
    $bestVersion = [version]'0.0.0'
    $seen = @{}
    foreach ($c in $candidates) {
        if (-not $c -or $seen.ContainsKey($c.ToLower())) { continue }
        $seen[$c.ToLower()] = $true
        if (-not (Test-Path -LiteralPath $c -PathType Leaf)) { continue }
        $v = Get-PhpVersion $c
        if (-not $v) { continue }
        $ver = [version]($v -replace '[^0-9.].*$', '')
        if ($ver -gt $bestVersion) { $best = $c; $bestVersion = $ver }
    }

    if ($best) {
        return [pscustomobject]@{ Path = $best; Version = $bestVersion; Usable = ($bestVersion -ge [version]'8.3.0') }
    }
    return $null
}

# Bulunan PHP klasörünü bu oturumun PATH'ine ekler ve yolu .php-path dosyasına kaydeder.
function Use-Php($php) {
    $dir = Split-Path -Parent $php.Path
    if (-not (($env:Path -split ';') -contains $dir)) { $env:Path = "$dir;$env:Path" }
    Set-Content -LiteralPath $script:PhpPathFile -Value $php.Path -Encoding UTF8
}

# Composer: PATH'teki composer veya projeye indirilen composer.phar.
function Find-Composer {
    $cmd = Get-Command composer -ErrorAction SilentlyContinue
    if ($cmd) { return @{ Kind = 'global'; Path = $cmd.Source } }
    $herdComposer = "$env:USERPROFILE\.config\herd-lite\bin\composer.bat"
    if (Test-Path -LiteralPath $herdComposer) { return @{ Kind = 'global'; Path = $herdComposer } }
    if (Test-Path -LiteralPath $script:ComposerPhar) { return @{ Kind = 'phar'; Path = $script:ComposerPhar } }
    return $null
}

# Composer yoksa resmi sitesinden ücretsiz composer.phar indirir ve SHA-256 ile doğrular.
function Install-ComposerPhar {
    $dir = Split-Path -Parent $script:ComposerPhar
    New-Item -ItemType Directory -Force -Path $dir | Out-Null
    [Net.ServicePointManager]::SecurityProtocol = [Net.ServicePointManager]::SecurityProtocol -bor [Net.SecurityProtocolType]::Tls12
    $base = if ($env:SOCIALHUB_COMPOSER_BASE) { $env:SOCIALHUB_COMPOSER_BASE } else { 'https://getcomposer.org/download/latest-stable' }
    Write-Host '  composer.phar indiriliyor (getcomposer.org)...'
    $shaFile = "$($script:ComposerPhar).sha256sum"
    Invoke-WebRequest -UseBasicParsing -Uri "$base/composer.phar" -OutFile $script:ComposerPhar
    Invoke-WebRequest -UseBasicParsing -Uri "$base/composer.phar.sha256sum" -OutFile $shaFile
    $expected = ([string](Get-Content -LiteralPath $shaFile -Raw)).Trim().Split(' ')[0].ToLower()
    Remove-Item -LiteralPath $shaFile -Force
    $actual = (Get-FileHash -LiteralPath $script:ComposerPhar -Algorithm SHA256).Hash.ToLower()
    if (-not $expected -or $expected -ne $actual) {
        Remove-Item -LiteralPath $script:ComposerPhar -Force
        throw 'composer.phar doğrulanamadı (SHA-256 uyuşmadı). Composer-Setup.exe ile elle kurun.'
    }
    Write-Host '  composer.phar indirildi ve doğrulandı.' -ForegroundColor Green
    return @{ Kind = 'phar'; Path = $script:ComposerPhar }
}

function Invoke-Composer($composer, [string[]]$argList) {
    if ($composer.Kind -eq 'phar') { & php $composer.Path @argList } else { & $composer.Path @argList }
    if ($LASTEXITCODE -ne 0) { throw "Composer komutu başarısız oldu: composer $($argList -join ' ')" }
}

function Show-PhpHelp {
    Write-Host ''
    Write-Host '  PHP bulunamadı veya sürümü 8.3''ten eski.' -ForegroundColor Yellow
    Write-Host '  Çözüm seçenekleri:'
    Write-Host '   a) PHP''nin yolunu verin:  install.bat "C:\yol\php\php.exe"'
    Write-Host '   b) PHP 8.3+ kurun (ücretsiz): https://windows.php.net/download  veya PowerShell''de:'
    Write-Host '      Set-ExecutionPolicy Bypass -Scope Process -Force; iex ((New-Object System.Net.WebClient).DownloadString(''https://php.new/install/windows/8.4''))'
    Write-Host '   c) XAMPP/Laragon kullanıyorsanız 8.3+ sürümünü seçin.'
    Write-Host ''
}
