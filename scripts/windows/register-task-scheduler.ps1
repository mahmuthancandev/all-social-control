# SocialHub - Windows Görev Zamanlayıcı kaydı (isteğe bağlı)
# start.ps1 açık değilken de Laravel scheduler'ın her dakika çalışmasını sağlar.
# Kullanıcı oturumu açıkken çalışır; bilgisayar kapalıyken çalışmaz.
# Kaldırmak için: powershell -ExecutionPolicy Bypass -File scripts\windows\register-task-scheduler.ps1 -Remove
param([switch]$Remove)
$ErrorActionPreference = 'Stop'
$TaskName = 'SocialHub Scheduler'
$Root = Resolve-Path (Join-Path $PSScriptRoot '..\..')

if ($Remove) {
    Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false -ErrorAction SilentlyContinue
    Write-Host "'$TaskName' görevi kaldırıldı." -ForegroundColor Green
    exit 0
}

$php = (Get-Command php).Source
$action = New-ScheduledTaskAction -Execute $php -Argument 'artisan schedule:run' -WorkingDirectory $Root
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 1)
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 10)

Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger -Settings $settings -Description 'SocialHub Laravel scheduler (php artisan schedule:run)' -Force | Out-Null
Write-Host "'$TaskName' görevi kaydedildi (her dakika çalışır)." -ForegroundColor Green
