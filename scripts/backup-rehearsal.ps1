[CmdletBinding()]
param(
    [string] $BackupDirectory,
    [ValidateRange(1, 365)] [int] $Retain = 7
)

$ErrorActionPreference = 'Stop'
$root = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
if (!$BackupDirectory) { $BackupDirectory = Join-Path $root 'artifacts/rehearsal-backups' }
$project = 'vydflow-prod-rehearsal'
$compose = @('--project-directory', $root, '--project-name', $project, '--env-file', (Join-Path $root '.env.prod-rehearsal.local'), '-f', (Join-Path $root 'compose.prod-rehearsal.yaml'))
$backupRoot = [IO.Path]::GetFullPath($BackupDirectory)
[IO.Directory]::CreateDirectory($backupRoot) | Out-Null
$operations = Join-Path $root 'artifacts/rehearsal-operations'
[IO.Directory]::CreateDirectory($operations) | Out-Null
$lock = [IO.File]::Open((Join-Path $operations 'backup.lock'), 'OpenOrCreate', 'ReadWrite', 'None')

function Invoke-RehearsalCompose {
    & docker compose @compose @args
    if ($LASTEXITCODE -ne 0) { throw "Docker Compose a échoué (code $LASTEXITCODE), opération $($args[0])." }
}

try {
    $running = @(Invoke-RehearsalCompose ps --status running --services)
    if ('database' -notin $running) { throw 'Démarre la base de répétition avant de sauvegarder.' }
    $database = Invoke-RehearsalCompose exec -T database psql -X -U vydflow -d vydflow -Atc 'SELECT current_database();'
    if ($database -ne 'vydflow') { throw 'Base de répétition inattendue.' }
    $snapshotId = [DateTime]::UtcNow.ToString('yyyyMMddTHHmmssZ') + '-' + [Guid]::NewGuid().ToString('N').Substring(0, 8)
    $stage = Join-Path $backupRoot ".partial-$snapshotId"
    $destination = Join-Path $backupRoot "rehearsal-$snapshotId"
    $dump = "/tmp/vydflow-$snapshotId.dump"
    $writers = @('ingress', 'publications', 'background', 'video-edits', 'retention', 'web') | Where-Object { $_ -in $running }
    [IO.Directory]::CreateDirectory($stage) | Out-Null

    try {
        if ($writers) { Invoke-RehearsalCompose stop @writers }
        Invoke-RehearsalCompose exec -T database sh -ec 'umask 077; pg_dump -U vydflow -d vydflow -Fc --no-owner --no-privileges -f $1; pg_restore --list $1 >/dev/null' backup $dump
        Invoke-RehearsalCompose cp "database:$dump" (Join-Path $stage 'database.dump')
        Invoke-RehearsalCompose run --rm --no-deps -T -v "${stage}:/backup" web sh -ec 'umask 077; tar -C /app/var/storage -cf /backup/media.tar .; tar -tf /backup/media.tar >/dev/null'
    } finally {
        try { Invoke-RehearsalCompose exec -T database rm -f -- $dump }
        finally { if ($writers) { Invoke-RehearsalCompose start @writers } }
    }

    $files = foreach ($name in 'database.dump', 'media.tar') {
        $file = Get-Item -LiteralPath (Join-Path $stage $name)
        if ($file.Length -le 0) { throw "Sauvegarde vide : $name" }
        @{ name = $name; bytes = $file.Length; sha256 = (Get-FileHash -LiteralPath $file.FullName).Hash }
    }
    $manifestPath = Join-Path $stage 'manifest.json'
    $manifestJson = @{ project = $project; version = 1; id = $snapshotId; createdUtc = [DateTime]::UtcNow.ToString('o'); files = @($files) } | ConvertTo-Json -Depth 4
    [IO.File]::WriteAllText($manifestPath, $manifestJson)
    [IO.Directory]::Move($stage, $destination)
    Write-Output "Sauvegarde vérifiée : $destination"

    $candidates = Get-ChildItem -LiteralPath $backupRoot -Directory |
        Where-Object { $_.Name -cmatch '^rehearsal-\d{8}T\d{6}Z-[a-f0-9]{8}$' -and -not ($_.Attributes -band [IO.FileAttributes]::ReparsePoint) } |
        Sort-Object Name -Descending
    $complete = foreach ($candidate in $candidates) {
        $entries = @(Get-ChildItem -LiteralPath $candidate.FullName -Force)
        if ($entries.Count -ne 3 -or ($entries | Where-Object { $_.PSIsContainer -or $_.Name -cnotin @('database.dump', 'media.tar', 'manifest.json') -or ($_.Attributes -band [IO.FileAttributes]::ReparsePoint) })) { continue }
        try { $manifest = Get-Content -Raw -LiteralPath (Join-Path $candidate.FullName 'manifest.json') | ConvertFrom-Json }
        catch { continue }
        if ($manifest.project -cne $project -or $manifest.version -ne 1 -or $candidate.Name -cne "rehearsal-$($manifest.id)") { continue }
        [pscustomobject]@{ directory = $candidate; files = $entries }
    }
    foreach ($old in ($complete | Select-Object -Skip $Retain)) {
        $resolved = (Resolve-Path -LiteralPath $old.directory.FullName).ProviderPath
        if ([IO.Path]::GetDirectoryName($resolved) -ne (Get-Item -LiteralPath $backupRoot).FullName) { throw 'Rétention hors du dossier de sauvegarde.' }
        foreach ($entry in $old.files) { Remove-Item -LiteralPath $entry.FullName }
        [IO.Directory]::Delete($resolved)
        Write-Output "Ancienne sauvegarde retirée : $($old.directory.Name)"
    }
} finally {
    $lock.Dispose()
}
