# SocialHub - start.ps1 ile açılan pencereleri kapatır
$stopped = 0
Get-Process cmd -ErrorAction SilentlyContinue | Where-Object { $_.MainWindowTitle -like '*SocialHub - *' } | ForEach-Object {
    & taskkill /PID $_.Id /T /F | Out-Null
    $stopped++
}
if ($stopped -gt 0) { Write-Host "SocialHub durduruldu ($stopped süreç)." -ForegroundColor Green }
else { Write-Host 'Çalışan SocialHub süreci bulunamadı.' -ForegroundColor Yellow }
