[CmdletBinding()]
param(
    [ValidateRange(1, 100)] [int] $StorageWarningPercent = 80,
    [ValidateRange(1, 1440)] [int] $ReadyWarningMinutes = 10,
    [ValidateRange(1, 1440)] [int] $DeliveredWarningMinutes = 15,
    [ValidateRange(1, 720)] [int] $BackupWarningHours = 36,
    [string] $BackupDirectory
)

$ErrorActionPreference = 'Stop'
$root = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
if (!$BackupDirectory) { $BackupDirectory = Join-Path $root 'artifacts/rehearsal-backups' }
$compose = @('--project-directory', $root, '--project-name', 'vydflow-prod-rehearsal', '--env-file', (Join-Path $root '.env.prod-rehearsal.local'), '-f', (Join-Path $root 'compose.prod-rehearsal.yaml'))
$operations = Join-Path $root 'artifacts/rehearsal-operations'
[IO.Directory]::CreateDirectory($operations) | Out-Null
$alerts = [Collections.Generic.List[string]]::new()
$status = [ordered]@{ checkedUtc = [DateTime]::UtcNow.ToString('o'); state = 'ok'; http = $null; storage = @(); queues = @(); backupUtc = $null; alerts = @() }

try {
    $lock = [IO.File]::Open((Join-Path $operations 'backup.lock'), 'OpenOrCreate', 'ReadWrite', 'None')
    $lock.Dispose()
} catch [IO.IOException] {
    if (32 -ne ($_.Exception.HResult -band 0xffff)) { throw }
    $status.state = 'maintenance'
}

if ($status.state -ne 'maintenance') {
    try {
        $running = @(& docker compose @compose ps --status running --services)
        if ($LASTEXITCODE -ne 0) { throw 'État Docker Compose indisponible.' }
        foreach ($service in 'database', 'web', 'ingress', 'publications', 'background', 'video-edits') {
            if ($service -notin $running) { $alerts.Add("Service arrêté : $service") }
        }

        if ('ingress' -in $running) {
            $binding = & docker compose @compose port ingress 80
            if ($LASTEXITCODE -ne 0 -or $binding -notmatch '^127\.0\.0\.1:(\d+)$') {
                $alerts.Add('Adresse de répétition indisponible.')
            } else {
                $httpCode = & curl.exe --silent --max-time 5 --output NUL --write-out '%{http_code}' "http://$binding/"
                if ($LASTEXITCODE -eq 0 -and $httpCode -match '^\d{3}$') { $status.http = [int]$httpCode }
                if ($status.http -ne 200) { $alerts.Add('Accueil HTTP 200 indisponible.') }
            }
        }

        if ('web' -in $running) {
            $status.storage = @(& docker compose @compose exec -T -e "STORAGE_WARNING_PERCENT=$StorageWarningPercent" web sh docker/production/check-storage.sh)
            if ($LASTEXITCODE -eq 1) { $alerts.Add("Le volume des médias a atteint le seuil de $StorageWarningPercent %.") }
            elseif ($LASTEXITCODE -ne 0) { $alerts.Add("Contrôle d’espace disque indisponible.") }
        }

        if ('database' -in $running) {
            $query = @"
BEGIN READ ONLY;
WITH clock AS (SELECT NOW() AT TIME ZONE 'UTC' AS value)
SELECT queue_name,
    count(*) FILTER (WHERE delivered_at IS NULL AND available_at > value),
    count(*) FILTER (WHERE delivered_at IS NULL AND available_at <= value),
    count(*) FILTER (WHERE delivered_at IS NULL AND available_at <= value - INTERVAL '$ReadyWarningMinutes minutes'),
    count(*) FILTER (WHERE delivered_at IS NOT NULL),
    count(*) FILTER (WHERE delivered_at <= value - INTERVAL '$DeliveredWarningMinutes minutes')
FROM messenger_messages CROSS JOIN clock GROUP BY queue_name ORDER BY queue_name;
COMMIT;
"@
            $rows = @(& docker compose @compose exec -T database psql -X -q -At -U vydflow -d vydflow -v ON_ERROR_STOP=1 -c $query)
            if ($LASTEXITCODE -ne 0) { $alerts.Add('Contrôle des files indisponible.') }
            else {
                $status.queues = @(foreach ($row in $rows) {
                    $queue, $future, $ready, $late, $delivered, $stale = $row -split '\|'
                    if ($queue -eq 'publication_failures') { $alerts.Add("File d’échecs : $([int]$future + [int]$ready + [int]$delivered) message(s).") }
                    elseif ([int]$late -gt 0 -or [int]$stale -gt 0) { $alerts.Add("File $queue : $late attente(s) ancienne(s), $stale traitement(s) ancien(s).") }
                    @{ name = $queue; future = [int]$future; ready = [int]$ready; late = [int]$late; delivered = [int]$delivered; stale = [int]$stale }
                })
            }
        }
    } catch { $alerts.Add($_.Exception.Message) }
}

$backupRoot = [IO.Path]::GetFullPath($BackupDirectory)
$latestBackup = $null
if (Test-Path -LiteralPath $backupRoot -PathType Container) {
    $backups = Get-ChildItem -LiteralPath $backupRoot -Directory |
        Where-Object { $_.Name -cmatch '^rehearsal-\d{8}T\d{6}Z-[a-f0-9]{8}$' -and -not ($_.Attributes -band [IO.FileAttributes]::ReparsePoint) } |
        Sort-Object Name -Descending
    foreach ($backup in $backups) {
        $manifestPath = Join-Path $backup.FullName 'manifest.json'
        if (-not (Test-Path -LiteralPath $manifestPath -PathType Leaf)) { continue }
        try {
            $manifest = Get-Content -Raw -LiteralPath $manifestPath | ConvertFrom-Json
            if ($manifest.project -ne 'vydflow-prod-rehearsal' -or $manifest.version -ne 1 -or $backup.Name -ne "rehearsal-$($manifest.id)") { continue }
            if (-not (Test-Path -LiteralPath (Join-Path $backup.FullName 'database.dump') -PathType Leaf) -or -not (Test-Path -LiteralPath (Join-Path $backup.FullName 'media.tar') -PathType Leaf)) { continue }
            $latestBackup = [DateTimeOffset]$manifest.createdUtc
            $status.backupUtc = $latestBackup.UtcDateTime.ToString('o')
            break
        } catch { continue }
    }
}
if ($status.state -ne 'maintenance' -and (!$latestBackup -or $latestBackup -lt [DateTimeOffset]::UtcNow.AddHours(-$BackupWarningHours))) {
    $alerts.Add("Aucune sauvegarde complète au cours des $BackupWarningHours dernières heures.")
}

$status.alerts = @($alerts.ToArray())
if ($alerts.Count) { $status.state = 'alert' }
$temporary = Join-Path $operations ('status-' + [Guid]::NewGuid().ToString('N') + '.tmp')
$json = $status | ConvertTo-Json -Depth 5
[IO.File]::WriteAllText($temporary, $json)
$statusPath = Join-Path $operations 'status.json'
if ([IO.File]::Exists($statusPath)) { [IO.File]::Replace($temporary, $statusPath, [NullString]::Value) }
else { [IO.File]::Move($temporary, $statusPath) }
if ($alerts.Count) {
    $alertLine = $status | ConvertTo-Json -Depth 5 -Compress
    [IO.File]::AppendAllText((Join-Path $operations 'alerts.jsonl'), $alertLine + [Environment]::NewLine)
}
Write-Output $json
if ($alerts.Count) { exit 1 }
