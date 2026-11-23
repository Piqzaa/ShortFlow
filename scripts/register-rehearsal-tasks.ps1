[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$taskNames = @('VydFlow - sauvegarde repetition', 'VydFlow - surveillance repetition')
$scriptNames = @('backup-rehearsal.ps1', 'check-rehearsal.ps1')
$exe = Join-Path $env:SystemRoot 'System32/WindowsPowerShell/v1.0/powershell.exe'
$principal = New-ScheduledTaskPrincipal -UserId ([Security.Principal.WindowsIdentity]::GetCurrent().Name) -LogonType Interactive -RunLevel Limited
$triggers = @(
    (New-ScheduledTaskTrigger -Daily -At '03:15'),
    (New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(5) -RepetitionInterval (New-TimeSpan -Minutes 5))
)
$settings = @(
    (New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -StartWhenAvailable -ExecutionTimeLimit (New-TimeSpan -Hours 5)),
    (New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 2))
)

for ($index = 0; $index -lt $taskNames.Length; $index++) {
    $taskName = $taskNames[$index]
    $script = Join-Path $PSScriptRoot $scriptNames[$index]
    $arguments = '-NoLogo -NoProfile -NonInteractive -WindowStyle Hidden -File "' + $script + '"'
    $existing = Get-ScheduledTask -TaskName $taskName -TaskPath '\' -ErrorAction SilentlyContinue
    if ($existing -and ($existing.Actions.Count -ne 1 -or $existing.Actions[0].Execute -ne $exe -or $existing.Actions[0].Arguments -ne $arguments)) {
        throw "Une autre tâche utilise déjà le nom $taskName."
    }
    $action = New-ScheduledTaskAction -Execute $exe -Argument $arguments
    Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $triggers[$index] -Settings $settings[$index] -Principal $principal -Force | Out-Null
    Get-ScheduledTask -TaskName $taskName -TaskPath '\' | Select-Object TaskName, State
}
