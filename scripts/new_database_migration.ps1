[CmdletBinding()]
param(
    [string]$Name
)

$ErrorActionPreference = 'Stop'
$repoRoot = Split-Path -Parent $PSScriptRoot
$migrationRoot = Join-Path $repoRoot 'uob-agreements\data\sql\migrations'

if (-not $Name) {
    $Name = Read-Host 'Short migration name (example: add_notification_preferences)'
}

$safeName = $Name.Trim().ToLowerInvariant()
$safeName = $safeName -replace '[^a-z0-9]+', '_'
$safeName = $safeName.Trim('_')

if (-not $safeName -or $safeName -notmatch '^[a-z0-9][a-z0-9_]*$') {
    throw 'Enter a short name containing at least one letter or number.'
}

if (-not (Test-Path -LiteralPath $migrationRoot -PathType Container)) {
    throw "Migration directory is missing: $migrationRoot"
}

$timestamp = Get-Date -Format 'yyyyMMdd_HHmmss'
$fileName = '{0}_{1}.sql' -f $timestamp, $safeName
$path = Join-Path $migrationRoot $fileName

if (Test-Path -LiteralPath $path) {
    throw "Migration already exists: $path. Wait one second and run the command again."
}

$createdAt = (Get-Date).ToString('yyyy-MM-dd HH:mm:ss zzz')
$content = @"
-- Migration: $safeName
-- Created: $createdAt
-- Forward-only: never edit this file after another database has applied it.
-- Transaction: do not add BEGIN, COMMIT, or ROLLBACK; the manager supplies it.

-- UOB_MIGRATION_TODO
-- Add the required PostgreSQL statements below, then remove the TODO line.
-- Prefer guarded operations where PostgreSQL supports them, for example:
-- ALTER TABLE example_table ADD COLUMN IF NOT EXISTS example_column TEXT;

"@

$utf8WithoutBom = New-Object System.Text.UTF8Encoding($false)
[IO.File]::WriteAllText($path, $content, $utf8WithoutBom)

Write-Host ''
Write-Host 'Migration created:' -ForegroundColor Green
Write-Host $path
Write-Host ''
Write-Host 'Next:' -ForegroundColor Cyan
Write-Host '1. Add the SQL change and remove UOB_MIGRATION_TODO.'
Write-Host '2. Test with database-manager.cmd option 1, then option 2.'
Write-Host '3. Commit this migration with the related application code.'
