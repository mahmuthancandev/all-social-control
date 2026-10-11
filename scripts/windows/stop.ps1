# SocialHub - start.ps1 ile başlatılan tüm süreçleri kapatır.
# Pencere başlığına değil süreç komut satırına bakar; bu yüzden Windows Terminal'de
# açılan veya başlığı değişen pencereler de bulunur.
. (Join-Path $PSScriptRoot 'common.ps1')
$root = $script:Root
$stopped = 0

$all = Get-CimInstance Win32_Process -ErrorAction SilentlyContinue
# start.ps1'in açtığı cmd pencereleri; /T ile içlerindeki php süreçleri de kapanır.
# Başka projelerin PHP süreçlerine dokunulmaz.
$targets = $all | Where-Object { $_.Name -eq 'cmd.exe' -and ([string]$_.CommandLine) -like '*title SocialHub - *' }

foreach ($p in $targets) {
    & taskkill /PID $p.ProcessId /T /F 2>$null | Out-Null
    $stopped++
}

# start.ps1'in kaydettiği PID'ler (varsa)
$pidFile = Join-Path $root 'storage\app\socialhub-pids.txt'
if (Test-Path -LiteralPath $pidFile) {
    Get-Content -LiteralPath $pidFile | ForEach-Object {
        if ($_ -match '^\d+$' -and (Get-Process -Id $_ -ErrorAction SilentlyContinue)) {
            & taskkill /PID $_ /T /F 2>$null | Out-Null
            $stopped++
        }
    }
    Remove-Item -LiteralPath $pidFile -Force -ErrorAction SilentlyContinue
}

if ($stopped -gt 0) { Write-Host "SocialHub durduruldu ($stopped süreç kapatıldı)." -ForegroundColor Green }
else { Write-Host 'Çalışan SocialHub süreci bulunamadı.' -ForegroundColor Yellow }
