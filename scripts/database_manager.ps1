[CmdletBinding()]
param(
    [string]$Database = 'UOB_Partnership_and_Initiative',
    [string]$DbUser = 'postgres',
    [string]$DatabaseHost = 'localhost',
    [int]$Port = 5432,
    [string]$PsqlPath,
    [string]$PhpPath,
    [switch]$CheckOnly,
    [switch]$IncludeDevelopmentData,
    [switch]$SkipBackup
)

$ErrorActionPreference = 'Stop'
$script:RepoRoot = Split-Path -Parent $PSScriptRoot
$script:SqlRoot = Join-Path $script:RepoRoot 'uob-agreements\data\sql'
$script:MigrationRoot = Join-Path $script:SqlRoot 'migrations'
$script:Psql = $null
$script:Php = $null
$script:TrackingAvailable = $false
$script:PhpIniChanged = $false
$script:PhpReady = $false
$script:ApplicationConfigReady = $false
$script:OriginalPgPassword = $env:PGPASSWORD
$script:OptionalMigrationNames = @(
    '20260722_workspace_showcase_data.sql'
)

function Write-Result {
    param(
        [ValidateSet('OK', 'MISSING', 'FIXED', 'INFO', 'WARNING', 'ERROR')]
        [string]$State,
        [string]$Message
    )

    $color = switch ($State) {
        'OK' { 'Green' }
        'FIXED' { 'Green' }
        'MISSING' { 'Yellow' }
        'WARNING' { 'Yellow' }
        'ERROR' { 'Red' }
        default { 'Cyan' }
    }

    Write-Host ('[{0}] {1}' -f $State, $Message) -ForegroundColor $color
}

function Resolve-Executable {
    param(
        [string]$RequestedPath,
        [string]$CommandName,
        [string[]]$CommonPaths
    )

    if ($RequestedPath) {
        if (Test-Path -LiteralPath $RequestedPath -PathType Leaf) {
            return (Resolve-Path -LiteralPath $RequestedPath).Path
        }
        throw "Executable not found: $RequestedPath"
    }

    $command = Get-Command $CommandName -ErrorAction SilentlyContinue
    if ($command) {
        return $command.Source
    }

    foreach ($candidate in $CommonPaths) {
        if ($candidate -and (Test-Path -LiteralPath $candidate -PathType Leaf)) {
            return (Resolve-Path -LiteralPath $candidate).Path
        }
    }

    return $null
}

function Resolve-Tools {
    $postgresCandidates = @(
        'C:\Program Files\PostgreSQL\17\bin\psql.exe',
        'C:\Program Files\PostgreSQL\18\bin\psql.exe',
        'C:\Program Files\PostgreSQL\16\bin\psql.exe'
    )

    if (Test-Path -LiteralPath 'C:\Program Files\PostgreSQL') {
        $discovered = Get-ChildItem 'C:\Program Files\PostgreSQL' -Directory -ErrorAction SilentlyContinue |
            Sort-Object Name -Descending |
            ForEach-Object { Join-Path $_.FullName 'bin\psql.exe' }
        $postgresCandidates = @($discovered) + $postgresCandidates
    }

    $script:Psql = Resolve-Executable -RequestedPath $PsqlPath `
        -CommandName 'psql.exe' -CommonPaths $postgresCandidates

    $script:Php = Resolve-Executable -RequestedPath $PhpPath `
        -CommandName 'php.exe' -CommonPaths @('C:\xampp\php\php.exe')

    if (-not $script:Psql) {
        throw 'PostgreSQL psql.exe was not found. Install PostgreSQL 17 or pass -PsqlPath.'
    }
    Write-Result OK "PostgreSQL client: $script:Psql"

    if (-not $script:Php) {
        Write-Result MISSING 'PHP was not found. Install XAMPP or pass -PhpPath.'
        if (-not $CheckOnly) {
            throw 'PHP is required by the application and could not be repaired automatically.'
        }
    } else {
        Write-Result OK "PHP CLI: $script:Php"
    }
}

function Get-PhpModules {
    if (-not $script:Php) {
        return @()
    }

    $output = & $script:Php -m 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "Unable to inspect PHP modules:`n$($output -join [Environment]::NewLine)"
    }
    return @($output | ForEach-Object { $_.ToString().Trim().ToLowerInvariant() })
}

function Enable-PhpExtension {
    param(
        [string]$IniPath,
        [string]$ExtensionName
    )

    $content = Get-Content -LiteralPath $IniPath -Raw
    $escapedName = [regex]::Escape($ExtensionName)
    $pattern = "(?im)^[ \t]*;[ \t]*extension[ \t]*=[ \t]*(?:php_)?${escapedName}(?:\.dll)?[ \t]*$"
    $enabledPattern = "(?im)^[ \t]*extension[ \t]*=[ \t]*(?:php_)?${escapedName}(?:\.dll)?[ \t]*$"

    if ($content -match $enabledPattern) {
        return $false
    }

    if ($content -match $pattern) {
        $content = [regex]::Replace(
            $content,
            $pattern,
            "extension=$ExtensionName",
            1
        )
    } else {
        $content = $content.TrimEnd() + [Environment]::NewLine +
            "extension=$ExtensionName" + [Environment]::NewLine
    }

    $utf8WithoutBom = New-Object System.Text.UTF8Encoding($false)
    [IO.File]::WriteAllText($IniPath, $content, $utf8WithoutBom)
    return $true
}

function Test-AndRepairPhp {
    if (-not $script:Php) {
        return
    }

    $requiredExtensions = @(
        [pscustomobject]@{
            Module = 'pdo_pgsql'
            IniName = 'pdo_pgsql'
            DllName = 'php_pdo_pgsql.dll'
        },
        [pscustomobject]@{
            Module = 'fileinfo'
            IniName = 'fileinfo'
            DllName = 'php_fileinfo.dll'
        },
        [pscustomobject]@{
            Module = 'zip'
            IniName = 'zip'
            DllName = 'php_zip.dll'
        }
    )

    $modules = Get-PhpModules
    $missing = @(
        $requiredExtensions |
            Where-Object { $modules -notcontains $_.Module }
    )

    foreach ($extension in $requiredExtensions) {
        if ($modules -contains $extension.Module) {
            Write-Result OK "PHP extension $($extension.Module) is enabled."
        } else {
            Write-Result MISSING "PHP extension $($extension.Module) is disabled."
        }
    }

    if ($missing.Count -eq 0) {
        $script:PhpReady = $true
        return
    }

    if ($CheckOnly) {
        return
    }

    $iniOutput = & $script:Php -r 'echo php_ini_loaded_file();' 2>&1
    if ($LASTEXITCODE -ne 0 -or -not $iniOutput) {
        throw 'PHP did not report a loaded php.ini file.'
    }
    $iniPath = ($iniOutput | Select-Object -Last 1).ToString().Trim()
    if (-not (Test-Path -LiteralPath $iniPath -PathType Leaf)) {
        throw "Loaded php.ini was not found: $iniPath"
    }

    $phpDirectory = Split-Path -Parent $script:Php
    foreach ($extension in $missing) {
        $extensionDll = Join-Path $phpDirectory "ext\$($extension.DllName)"
        if (-not (Test-Path -LiteralPath $extensionDll -PathType Leaf)) {
            throw "XAMPP's $($extension.Module) extension is missing: $extensionDll"
        }
    }

    $backupPath = '{0}.uob-backup-{1}' -f $iniPath, (Get-Date -Format 'yyyyMMdd-HHmmss')
    Copy-Item -LiteralPath $iniPath -Destination $backupPath

    $changed = $false
    foreach ($extension in $missing) {
        if (
            Enable-PhpExtension `
                -IniPath $iniPath `
                -ExtensionName $extension.IniName
        ) {
            $changed = $true
        }
    }

    if (
        ($missing | Where-Object { $_.Module -eq 'pdo_pgsql' }) -and
        (Test-Path -LiteralPath (Join-Path $phpDirectory 'ext\php_pgsql.dll'))
    ) {
        if (Enable-PhpExtension -IniPath $iniPath -ExtensionName 'pgsql') {
            $changed = $true
        }
    }

    $modules = Get-PhpModules
    $stillMissing = @(
        $requiredExtensions |
            Where-Object { $modules -notcontains $_.Module }
    )
    if ($stillMissing.Count -gt 0) {
        $names = $stillMissing.Module -join ', '
        throw "$names is still unavailable. Restore $backupPath and inspect the PHP startup warnings."
    }

    $script:PhpReady = $true
    $script:PhpIniChanged = $changed
    Write-Result FIXED "Enabled required PHP extensions in $iniPath (backup: $backupPath)."
}

function Get-ConnectionArguments {
    return @(
        '-X',
        '-w',
        '-h', $DatabaseHost,
        '-p', $Port.ToString(),
        '-U', $DbUser,
        '-d', $Database,
        '-v', 'ON_ERROR_STOP=1'
    )
}

function Invoke-PsqlCapture {
    param([string]$Sql)

    $arguments = (Get-ConnectionArguments) + @('-q', '-A', '-t', '-c', $Sql)
    $previousErrorActionPreference = $ErrorActionPreference
    try {
        # PostgreSQL writes informational NOTICE messages to stderr. Windows
        # PowerShell must not turn those into terminating PowerShell errors;
        # psql's process exit code remains the source of truth.
        $ErrorActionPreference = 'Continue'
        $output = & $script:Psql @arguments 2>&1
        $psqlExitCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $previousErrorActionPreference
    }
    if ($psqlExitCode -ne 0) {
        throw ($output -join [Environment]::NewLine)
    }
    return @(
        $output |
            ForEach-Object { $_.ToString().Trim() } |
            Where-Object { $_ -ne '' -and $_ -notmatch '^(NOTICE|DETAIL|HINT):' }
    )
}

function Invoke-PsqlFile {
    param([string]$Path)

    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        throw "Required SQL file is missing: $Path"
    }

    $arguments = (Get-ConnectionArguments) + @('-f', $Path)
    $previousErrorActionPreference = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        $output = & $script:Psql @arguments 2>&1
        $psqlExitCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $previousErrorActionPreference
    }
    foreach ($line in $output) {
        Write-Host $line
    }
    if ($psqlExitCode -ne 0) {
        throw "SQL installation failed: $Path"
    }
}

function Invoke-TrackedMigrationFile {
    param(
        [string]$Path,
        [string]$Name,
        [string]$Checksum
    )

    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        throw "Migration file is missing: $Path"
    }

    $content = Get-Content -LiteralPath $Path -Raw
    if ($content -match '(?im)^[ \t]*(BEGIN|START[ \t]+TRANSACTION|COMMIT|ROLLBACK)[ \t]*;') {
        throw "Automatic migration $Name contains transaction control. Remove BEGIN/COMMIT/ROLLBACK; the database manager wraps the migration and its tracking record in one transaction."
    }
    if ($content -match 'UOB_MIGRATION_TODO') {
        throw "Automatic migration $Name still contains its TODO marker. Add the SQL change and remove UOB_MIGRATION_TODO before running it."
    }

    $safeName = Escape-SqlLiteral -Value $Name
    $safeChecksum = Escape-SqlLiteral -Value $Checksum
    $trackingSql = @"
INSERT INTO schema_migrations (
    migration_name, checksum_sha256, installation_method
)
VALUES ('$safeName', '$safeChecksum', 'applied');
"@

    $arguments = (Get-ConnectionArguments) + @(
        '--single-transaction',
        '-f', $Path,
        '-c', $trackingSql
    )
    $previousErrorActionPreference = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        $output = & $script:Psql @arguments 2>&1
        $psqlExitCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $previousErrorActionPreference
    }
    foreach ($line in $output) {
        Write-Host $line
    }
    if ($psqlExitCode -ne 0) {
        throw "Migration failed and was rolled back: $Name"
    }
}

function Connect-Database {
    $testArguments = (Get-ConnectionArguments) + @('-q', '-A', '-t', '-c', 'SELECT 1;')
    $previousErrorActionPreference = $ErrorActionPreference
    try {
        # Windows PowerShell can turn a native program's stderr into a
        # terminating ErrorRecord when ErrorActionPreference is Stop. A
        # password-required response is expected here and must be inspected
        # before the manager decides whether to prompt.
        $ErrorActionPreference = 'Continue'
        $output = & $script:Psql @testArguments 2>&1
        $connectionExitCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $previousErrorActionPreference
    }

    if ($connectionExitCode -eq 0) {
        Write-Result OK "Connected to $Database."
        return
    }

    $message = $output -join [Environment]::NewLine
    if ($message -notmatch 'password|authentication') {
        throw "Could not connect to PostgreSQL:`n$message"
    }

    $securePassword = Read-Host "PostgreSQL password for $DbUser" -AsSecureString
    $passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
    try {
        $env:PGPASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
    } finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer)
    }

    $previousErrorActionPreference = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        $output = & $script:Psql @testArguments 2>&1
        $connectionExitCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $previousErrorActionPreference
    }

    if ($connectionExitCode -ne 0) {
        throw "Could not connect to PostgreSQL:`n$($output -join [Environment]::NewLine)"
    }
    Write-Result OK "Connected to $Database."
}

function Invoke-ApplicationDatabaseProbe {
    $configPath = Join-Path $script:RepoRoot 'config\database.php'
    if (-not (Test-Path -LiteralPath $configPath -PathType Leaf)) {
        throw "Application database configuration is missing: $configPath"
    }

    $probeCode = @'
<?php

$path = getenv('UOB_CONFIG_PROBE_PATH');
require $path;
$database = Database::connect()->query('SELECT current_database()')->fetchColumn();
echo $database;
'@

    $probePath = [IO.Path]::Combine(
        [IO.Path]::GetTempPath(),
        ('uob-database-probe-{0}.php' -f [guid]::NewGuid().ToString('N'))
    )
    $utf8WithoutBom = New-Object System.Text.UTF8Encoding($false)
    [IO.File]::WriteAllText($probePath, $probeCode, $utf8WithoutBom)

    $previousErrorActionPreference = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        $output = & $script:Php $probePath 2>&1
        $probeExitCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $previousErrorActionPreference
        Remove-Item -LiteralPath $probePath -Force -ErrorAction SilentlyContinue
    }

    $lastLine = if ($output) {
        ($output | Select-Object -Last 1).ToString().Trim()
    } else {
        ''
    }

    return [pscustomobject]@{
        Success = (
            $probeExitCode -eq 0 -and
            ($lastLine -eq $Database)
        )
        Output = @($output)
    }
}

function Test-ApplicationDatabaseConfiguration {
    if (-not $script:Php -or -not $script:PhpReady) {
        return
    }

    $configPath = Join-Path $script:RepoRoot 'config\database.php'
    $examplePath = Join-Path $script:RepoRoot 'config\database.local.example.php'
    if (-not (Test-Path -LiteralPath $examplePath -PathType Leaf)) {
        throw "Safe local database configuration example is missing: $examplePath"
    }

    $environmentNames = @(
        'UOB_CONFIG_PROBE_PATH',
        'UOB_DB_HOST',
        'UOB_DB_PORT',
        'UOB_DB_NAME',
        'UOB_DB_USER',
        'UOB_DB_PASSWORD'
    )
    $originalValues = @{}
    foreach ($name in $environmentNames) {
        $originalValues[$name] = [Environment]::GetEnvironmentVariable(
            $name,
            'Process'
        )
    }

    try {
        [Environment]::SetEnvironmentVariable(
            'UOB_CONFIG_PROBE_PATH',
            $configPath,
            'Process'
        )

        $actualProbe = Invoke-ApplicationDatabaseProbe
        if ($actualProbe.Success) {
            $script:ApplicationConfigReady = $true
            Write-Result OK 'Application database configuration connected successfully.'
            return
        }

        # Prove that database.php honors the supported UOB_DB_* overrides. This
        # distinguishes a missing local secret from a hardcoded/broken loader.
        [Environment]::SetEnvironmentVariable(
            'UOB_DB_HOST',
            $DatabaseHost,
            'Process'
        )
        [Environment]::SetEnvironmentVariable(
            'UOB_DB_PORT',
            $Port.ToString(),
            'Process'
        )
        [Environment]::SetEnvironmentVariable(
            'UOB_DB_NAME',
            $Database,
            'Process'
        )
        [Environment]::SetEnvironmentVariable(
            'UOB_DB_USER',
            $DbUser,
            'Process'
        )
        if ($env:PGPASSWORD) {
            [Environment]::SetEnvironmentVariable(
                'UOB_DB_PASSWORD',
                $env:PGPASSWORD,
                'Process'
            )
        }

        $overrideProbe = Invoke-ApplicationDatabaseProbe
        if (-not $overrideProbe.Success) {
            throw 'config/database.php did not connect even with verified UOB_DB_* settings. Ensure it loads config/database.local.php and gives UOB_DB_* variables precedence.'
        }

        Write-Result MISSING 'Application database credentials are missing or incorrect. Copy config\database.local.example.php to config\database.local.php and enter this database connection.'
} finally {

        foreach ($name in $environmentNames) {
            [Environment]::SetEnvironmentVariable(
                $name,
                $originalValues[$name],
                'Process'
            )
        }
    }
}

function Test-Feature {
    param([string]$CheckSql)

    try {
        $output = Invoke-PsqlCapture -Sql $CheckSql
        if ($output.Count -eq 0) {
            return $false
        }
        return (($output | Select-Object -Last 1) -eq 't')
    } catch {
        return $false
    }
}

function Test-CoreSchema {
    $sql = @'
WITH required(name) AS (
    VALUES
        ('users'), ('roles'), ('permissions'), ('role_permissions'),
        ('user_roles'), ('organizational_units'), ('position_types'),
        ('positions'), ('user_positions'), ('partners'), ('agreements'),
        ('agreement_partners'), ('agreement_versions'),
        ('agreement_relationships'), ('agreement_actions'), ('initiatives'),
        ('initiative_agreements'), ('initiative_versions'),
        ('workflow_templates'), ('workflow_template_steps'),
        ('workflow_instances'), ('workflow_instance_steps'),
        ('workflow_history')
)
SELECT COALESCE(string_agg(name, ', ' ORDER BY name), '')
FROM required
WHERE to_regclass('public.' || name) IS NULL;
'@

    $output = Invoke-PsqlCapture -Sql $sql
    $missing = if ($output.Count -gt 0) { $output | Select-Object -Last 1 } else { '' }
    if ($missing) {
        throw "The database is missing core tables: $missing. This is not an incremental-update database; create a fresh database with deploy.sql."
    }
    Write-Result OK 'Core database tables are present.'
}

function Get-FileChecksum {
    param([string]$RelativePath)

    $path = Join-Path $script:SqlRoot $RelativePath
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) {
        throw "Required SQL file is missing: $path"
    }
    return (Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash.ToLowerInvariant()
}

function Test-TrackingTable {
    $output = Invoke-PsqlCapture -Sql "SELECT to_regclass('public.schema_migrations') IS NOT NULL;"
    $script:TrackingAvailable = ($output.Count -gt 0 -and ($output | Select-Object -Last 1) -eq 't')
}

function Ensure-TrackingTable {
    $sql = @'
CREATE TABLE IF NOT EXISTS schema_migrations (
    migration_name TEXT PRIMARY KEY,
    checksum_sha256 CHAR(64) NOT NULL,
    installation_method VARCHAR(20) NOT NULL
        CHECK (installation_method IN ('applied', 'baseline')),
    applied_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
'@
    [void](Invoke-PsqlCapture -Sql $sql)
    $script:TrackingAvailable = $true
}

function Escape-SqlLiteral {
    param([string]$Value)
    return $Value.Replace("'", "''")
}

function Get-TrackingRecord {
    param([string]$Name)

    if (-not $script:TrackingAvailable) {
        return $null
    }

    $escapedName = Escape-SqlLiteral -Value $Name
    $output = Invoke-PsqlCapture -Sql @"
SELECT checksum_sha256 || '|' || installation_method
FROM schema_migrations
WHERE migration_name = '$escapedName';
"@
    if ($output.Count -eq 0) {
        return $null
    }

    $parts = ($output | Select-Object -Last 1) -split '\|', 2
    $method = if ($parts.Count -gt 1) { $parts[1] } else { 'unknown' }
    return [pscustomobject]@{
        Checksum = $parts[0]
        Method = $method
    }
}

function Save-TrackingRecord {
    param(
        [string]$Name,
        [string]$Checksum,
        [ValidateSet('applied', 'baseline')]
        [string]$Method
    )

    $safeName = Escape-SqlLiteral -Value $Name
    $safeChecksum = Escape-SqlLiteral -Value $Checksum
    $safeMethod = Escape-SqlLiteral -Value $Method

    $sql = @"
INSERT INTO schema_migrations (
    migration_name, checksum_sha256, installation_method
)
VALUES ('$safeName', '$safeChecksum', '$safeMethod')
ON CONFLICT (migration_name) DO UPDATE
SET
    checksum_sha256 = EXCLUDED.checksum_sha256,
    installation_method = EXCLUDED.installation_method,
    applied_at = CURRENT_TIMESTAMP;
"@
    [void](Invoke-PsqlCapture -Sql $sql)
}

function Get-AutomaticMigrationSteps {
    param([object[]]$KnownSteps)

    if (-not (Test-Path -LiteralPath $script:MigrationRoot -PathType Container)) {
        throw "Migration directory is missing: $script:MigrationRoot"
    }

    $knownMigrationNames = @(
        $KnownSteps |
            Where-Object { $_.RelativePath -like 'migrations\*.sql' } |
            ForEach-Object { Split-Path -Leaf $_.RelativePath }
    )

    $steps = @()
    $files = Get-ChildItem -LiteralPath $script:MigrationRoot -File -Filter '*.sql' |
        Sort-Object Name

    foreach ($file in $files) {
        if ($script:OptionalMigrationNames -contains $file.Name) {
            continue
        }
        if ($knownMigrationNames -contains $file.Name) {
            continue
        }
        if ($file.Name -notmatch '^\d{8}_\d{6}_[a-z0-9][a-z0-9_]*\.sql$') {
            throw "New migration '$($file.Name)' has an unsafe name. Create it with new-database-migration.cmd so ordering is deterministic."
        }

        $steps += [pscustomobject]@{
            Name = $file.Name
            Description = "Pending migration: $($file.Name)"
            RelativePath = "migrations\$($file.Name)"
            CheckSql = $null
            AutoDiscovered = $true
        }
    }

    return $steps
}

function Test-MigrationRegistryConsistency {
    if (-not $script:TrackingAvailable) {
        return
    }

    $output = Invoke-PsqlCapture -Sql @'
SELECT migration_name
FROM schema_migrations
WHERE migration_name LIKE '%.sql'
ORDER BY migration_name;
'@

    $available = @(
        Get-ChildItem -LiteralPath $script:MigrationRoot -File -Filter '*.sql' |
            ForEach-Object { $_.Name }
    )
    $missingFiles = @($output | Where-Object { $available -notcontains $_ })
    if ($missingFiles.Count -gt 0) {
        throw "Applied migration file(s) are missing from this checkout: $($missingFiles -join ', '). Pull the complete branch before continuing."
    }
}

function New-DatabaseBackup {
    $pgDump = Join-Path (Split-Path -Parent $script:Psql) 'pg_dump.exe'
    if (-not (Test-Path -LiteralPath $pgDump -PathType Leaf)) {
        throw "pg_dump.exe was not found beside psql.exe: $pgDump"
    }

    $documents = [Environment]::GetFolderPath('MyDocuments')
    if (-not $documents) {
        $documents = $env:TEMP
    }
    $backupDirectory = Join-Path $documents 'UOB-Database-Backups'
    New-Item -ItemType Directory -Path $backupDirectory -Force | Out-Null

    $safeDatabaseName = $Database -replace '[^A-Za-z0-9_.-]', '_'
    $backupPath = Join-Path $backupDirectory (
        '{0}-before-update-{1}.backup' -f $safeDatabaseName, (Get-Date -Format 'yyyyMMdd-HHmmss')
    )

    $arguments = @(
        '-w',
        '-h', $DatabaseHost,
        '-p', $Port.ToString(),
        '-U', $DbUser,
        '-d', $Database,
        '-F', 'c',
        '-f', $backupPath
    )
    & $pgDump @arguments
    if ($LASTEXITCODE -ne 0) {
        throw 'Database backup failed. No updates were installed.'
    }
    Write-Result OK "Backup created: $backupPath"
}

function Get-DatabaseSteps {
    return @(
        [pscustomobject]@{
            Name = 'reference-data-baseline'
            Description = 'Required roles, permissions, and University units'
            RelativePath = 'maintenance\ensure_reference_data.sql'
            CheckSql = @"
SELECT
    (SELECT count(*) FROM roles
      WHERE role_name IN (
        'Agreement Creator', 'Agreement Approver', 'Initiative Creator',
        'Initiative Approver', 'System Administrator'
      )) = 5
    AND
    (SELECT count(*) FROM permissions
      WHERE permission_code IN (
        'CREATE_AGREEMENT', 'EDIT_AGREEMENT', 'SUBMIT_AGREEMENT',
        'VIEW_AGREEMENT', 'DELETE_AGREEMENT', 'APPROVE_AGREEMENT',
        'REJECT_AGREEMENT', 'MANAGE_AGREEMENT_OPERATIONS',
        'MANAGE_AGREEMENT_REPORTS', 'REVIEW_AGREEMENT_REPORTS',
        'VIEW_AGREEMENT_DASHBOARD', 'CREATE_INITIATIVE',
        'EDIT_INITIATIVE', 'APPROVE_INITIATIVE', 'REJECT_INITIATIVE',
        'VIEW_REPORTS', 'MANAGE_USERS'
      )) = 17
    AND
    (SELECT count(*) FROM organizational_units
      WHERE code IN ('UOB', 'PRES', 'VP', 'LEGAL', 'FIN', 'CIT', 'CS', 'IS')
        AND is_active) = 8;
"@
        },
        [pscustomobject]@{
            Name = 'core-agreement-documents'
            Description = 'Agreement document storage table'
            RelativePath = 'tables\agreement_documents.sql'
            CheckSql = "SELECT to_regclass('public.agreement_documents') IS NOT NULL;"
        },
        [pscustomobject]@{
            Name = '20260716_create_audit_logs.sql'
            Description = 'Audit log table and action type'
            RelativePath = 'migrations\20260716_create_audit_logs.sql'
            CheckSql = "SELECT to_regclass('public.audit_logs') IS NOT NULL AND EXISTS (SELECT 1 FROM pg_type WHERE typname = 'audit_action');"
        },
        [pscustomobject]@{
            Name = '20260716_agreement_version_snapshots.sql'
            Description = 'Immutable Agreement snapshots'
            RelativePath = 'migrations\20260716_agreement_version_snapshots.sql'
            CheckSql = "SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name='agreement_versions' AND column_name='agreement_snapshot' AND is_nullable='NO') AND NOT EXISTS (SELECT 1 FROM agreement_versions WHERE agreement_snapshot IS NULL);"
        },
        [pscustomobject]@{
            Name = '20260719_add_auth_tracking_columns.sql'
            Description = 'Authentication tracking columns'
            RelativePath = 'migrations\20260719_add_auth_tracking_columns.sql'
            CheckSql = "SELECT (SELECT count(*) FROM information_schema.columns WHERE table_schema='public' AND table_name='users' AND column_name IN ('last_login','failed_login_attempts','password_changed_at')) = 3;"
        },
        [pscustomobject]@{
            Name = '20260719_agreement_workflow_foundation.sql'
            Description = 'Six-stage Agreement workflow foundation'
            RelativePath = 'migrations\20260719_agreement_workflow_foundation.sql'
            CheckSql = "SELECT to_regclass('public.workflow_step_assignments') IS NOT NULL AND EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name='workflow_instances' AND column_name='finance_review_required') AND EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name='workflow_instance_steps' AND column_name='step_key') AND EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name='workflow_template_steps' AND column_name='step_key');"
        },
        [pscustomobject]@{
            Name = '20260719_add_workflow_step_timestamps.sql'
            Description = 'Workflow timing columns'
            RelativePath = 'migrations\20260719_add_workflow_step_timestamps.sql'
            CheckSql = "SELECT (SELECT count(*) FROM information_schema.columns WHERE table_schema='public' AND table_name='workflow_instance_steps' AND column_name IN ('started_at','completed_at')) = 2;"
        },
        [pscustomobject]@{
            Name = '20260719_add_review_offices.sql'
            Description = 'Legal and Finance review offices'
            RelativePath = 'migrations\20260719_add_review_offices.sql'
            CheckSql = "SELECT (SELECT count(*) FROM organizational_units WHERE code IN ('LEGAL','FIN') AND is_active) = 2;"
        },
        [pscustomobject]@{
            Name = '20260719_add_agreement_return_workflow.sql'
            Description = 'Return, redraft, and resubmission workflow'
            RelativePath = 'migrations\20260719_add_agreement_return_workflow.sql'
            CheckSql = "SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name='workflow_instances' AND column_name='review_cycle') AND (SELECT count(*) FROM pg_enum e JOIN pg_type t ON t.oid=e.enumtypid WHERE t.typname='workflow_action_type' AND e.enumlabel IN ('CHANGES_REQUESTED','ROUTED_TO_CREATOR','ROUTED_TO_LEGAL','ROUTED_TO_FINANCE','RESUBMITTED')) = 5;"
        },
        [pscustomobject]@{
            Name = '20260719_add_routed_to_vp_action.sql'
            Description = 'VP mediation workflow action'
            RelativePath = 'migrations\20260719_add_routed_to_vp_action.sql'
            CheckSql = "SELECT EXISTS (SELECT 1 FROM pg_enum e JOIN pg_type t ON t.oid=e.enumtypid WHERE t.typname='workflow_action_type' AND e.enumlabel='ROUTED_TO_VP');"
        },
        [pscustomobject]@{
            Name = '20260720_add_redraft_version_baseline.sql'
            Description = 'Redraft version baseline'
            RelativePath = 'migrations\20260720_add_redraft_version_baseline.sql'
            CheckSql = "SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name='workflow_instances' AND column_name='redraft_base_version');"
        },
        [pscustomobject]@{
            Name = '20260721_comprehensive_agreement_fields.sql'
            Description = 'Comprehensive Agreement fields and supporting tables'
            RelativePath = 'migrations\20260721_comprehensive_agreement_fields.sql'
            CheckSql = "SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name='agreements' AND column_name='agreement_code') AND EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name='partners' AND column_name='city') AND to_regclass('public.agreement_sdgs') IS NOT NULL AND to_regclass('public.agreement_rankings') IS NOT NULL AND to_regclass('public.agreement_contacts') IS NOT NULL AND to_regclass('public.agreement_executive_programs') IS NOT NULL AND to_regclass('public.agreement_metrics') IS NOT NULL AND to_regclass('public.agreement_lifecycle_requests') IS NOT NULL;"
        },
        [pscustomobject]@{
            Name = '20260721_secure_agreement_documents.sql'
            Description = 'Secure Agreement document metadata'
            RelativePath = 'migrations\20260721_secure_agreement_documents.sql'
            CheckSql = "SELECT (SELECT count(*) FROM information_schema.columns WHERE table_schema='public' AND table_name='agreement_documents' AND column_name IN ('agreement_version_id','storage_key','mime_type','file_size_bytes','sha256_checksum')) = 5;"
        },
        [pscustomobject]@{
            Name = '20260721_legacy_agreement_import_tracking.sql'
            Description = 'Legacy Agreement import tracking'
            RelativePath = 'migrations\20260721_legacy_agreement_import_tracking.sql'
            CheckSql = "SELECT to_regclass('public.agreement_legacy_imports') IS NOT NULL;"
        },
        [pscustomobject]@{
            Name = '20260721_agreement_lifecycle_workflow.sql'
            Description = 'Renewal, amendment, and termination workflow'
            RelativePath = 'migrations\20260721_agreement_lifecycle_workflow.sql'
            CheckSql = "SELECT to_regclass('public.agreement_lifecycle_request_versions') IS NOT NULL AND EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name='agreement_lifecycle_requests' AND column_name='workflow_instance_id');"
        },
        [pscustomobject]@{
            Name = '20260721_secure_lifecycle_request_documents.sql'
            Description = 'Secure lifecycle-request documents'
            RelativePath = 'migrations\20260721_secure_lifecycle_request_documents.sql'
            CheckSql = "SELECT to_regclass('public.agreement_lifecycle_request_documents') IS NOT NULL;"
        },
        [pscustomobject]@{
            Name = '20260721_lifecycle_successor_agreements.sql'
            Description = 'Traceable successor Agreements'
            RelativePath = 'migrations\20260721_lifecycle_successor_agreements.sql'
            CheckSql = "SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name='agreement_lifecycle_requests' AND column_name='successor_agreement_id') AND to_regclass('public.ux_lifecycle_successor_agreement') IS NOT NULL;"
        },
        [pscustomobject]@{
            Name = '20260721_agreement_operational_status.sql'
            Description = 'Signing and operational status records'
            RelativePath = 'migrations\20260721_agreement_operational_status.sql'
            CheckSql = "SELECT to_regclass('public.agreement_signing_records') IS NOT NULL AND to_regclass('public.agreement_status_events') IS NOT NULL AND (SELECT count(*) FROM information_schema.columns WHERE table_schema='public' AND table_name='agreements' AND column_name IN ('activated_at','expired_at')) = 2;"
        },
        [pscustomobject]@{
            Name = '20260721_agreement_performance_monitoring.sql'
            Description = 'Annual reports, metrics, and programme progress'
            RelativePath = 'migrations\20260721_agreement_performance_monitoring.sql'
            CheckSql = "SELECT to_regclass('public.agreement_performance_reports') IS NOT NULL AND to_regclass('public.agreement_performance_metric_results') IS NOT NULL AND to_regclass('public.agreement_executive_program_updates') IS NOT NULL AND to_regclass('public.agreement_performance_report_events') IS NOT NULL;"
        },
        [pscustomobject]@{
            Name = '20260721_agreement_integration_hardening.sql'
            Description = 'Authentication and permission hardening'
            RelativePath = 'migrations\20260721_agreement_integration_hardening.sql'
            CheckSql = "SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name='users' AND column_name='locked_until') AND (SELECT count(*) FROM permissions WHERE permission_code IN ('MANAGE_AGREEMENT_OPERATIONS','MANAGE_AGREEMENT_REPORTS','REVIEW_AGREEMENT_REPORTS','VIEW_AGREEMENT_DASHBOARD')) = 4;"
        },
        [pscustomobject]@{
            Name = '20260721_functional_workspace_redesign.sql'
            Description = 'Functional workspace and Initiative handoff'
            RelativePath = 'migrations\20260721_functional_workspace_redesign.sql'
            CheckSql = "SELECT to_regclass('public.workspace_legacy_handoffs') IS NOT NULL AND EXISTS (SELECT 1 FROM role_permissions rp JOIN roles r ON r.role_id=rp.role_id JOIN permissions p ON p.permission_id=rp.permission_id WHERE r.role_name='Initiative Creator' AND p.permission_code='VIEW_AGREEMENT');"
        },
        [pscustomobject]@{
            Name = '20260722_agreement_field_annotations.sql'
            Description = 'Field comments, private notes, and change views'
            RelativePath = 'migrations\20260722_agreement_field_annotations.sql'
            CheckSql = "SELECT to_regclass('public.agreement_annotations') IS NOT NULL AND to_regclass('public.agreement_user_views') IS NOT NULL;"
        },
        [pscustomobject]@{
            Name = 'workflow-template-seed'
            Description = 'Current Agreement and Initiative workflow templates'
            RelativePath = 'seed\workflows.sql'
            CheckSql = "SELECT (SELECT count(*) FROM workflow_template_steps s JOIN workflow_templates t ON t.workflow_template_id=s.workflow_template_id WHERE t.name='Agreement Approval' AND s.step_key IN ('CREATOR','VP_INITIAL','LEGAL_REVIEW','FINANCE_REVIEW','VP_FINAL','PRESIDENT_APPROVAL')) = 6 AND (SELECT count(*) FROM workflow_template_steps s JOIN workflow_templates t ON t.workflow_template_id=s.workflow_template_id WHERE t.name='Initiative Approval') = 5;"
        }
    )
}

function Get-OptionalDevelopmentSteps {
    return @(
        [pscustomobject]@{
            Name = 'development-seed'
            Description = 'Local development users, positions, and partners'
            RelativePath = 'seed\seed_dev.sql'
            CheckSql = @"
WITH expected_users(university_id, email) AS (
    VALUES
        ('DEV-ADMIN-001', 'dev.admin@uob.test'),
        ('DEV-PRES-001', 'dev.president@uob.test'),
        ('DEV-VP-001', 'dev.vp@uob.test'),
        ('DEV-DEAN-001', 'dev.dean@uob.test'),
        ('DEV-HEAD-001', 'dev.head@uob.test'),
        ('DEV-FAC-001', 'dev.faculty@uob.test'),
        ('DEV-LEGAL-001', 'dev.legal@uob.test'),
        ('DEV-FIN-001', 'dev.finance@uob.test')
),
expected_positions(email, position_name, unit_code) AS (
    VALUES
        ('dev.admin@uob.test', 'System Administrator', 'UOB'),
        ('dev.president@uob.test', 'President', 'PRES'),
        ('dev.vp@uob.test', 'Vice President', 'VP'),
        ('dev.dean@uob.test', 'Dean', 'CIT'),
        ('dev.head@uob.test', 'Department Head', 'CS'),
        ('dev.faculty@uob.test', 'Faculty Member', 'CS'),
        ('dev.legal@uob.test', 'Legal Reviewer', 'LEGAL'),
        ('dev.finance@uob.test', 'Finance Reviewer', 'FIN')
),
expected_roles(email, role_name) AS (
    VALUES
        ('dev.admin@uob.test', 'System Administrator'),
        ('dev.president@uob.test', 'Agreement Creator'),
        ('dev.president@uob.test', 'Agreement Approver'),
        ('dev.president@uob.test', 'Initiative Approver'),
        ('dev.vp@uob.test', 'Agreement Creator'),
        ('dev.vp@uob.test', 'Agreement Approver'),
        ('dev.vp@uob.test', 'Initiative Approver'),
        ('dev.dean@uob.test', 'Agreement Creator'),
        ('dev.dean@uob.test', 'Initiative Approver'),
        ('dev.legal@uob.test', 'Agreement Approver'),
        ('dev.finance@uob.test', 'Agreement Approver'),
        ('dev.head@uob.test', 'Initiative Creator'),
        ('dev.head@uob.test', 'Initiative Approver'),
        ('dev.faculty@uob.test', 'Initiative Creator')
),
expected_partners(organization_name) AS (
    VALUES
        ('Bahrain Institute of Technology'),
        ('Gulf Research Centre'),
        ('Future Skills Foundation')
)
SELECT
    NOT EXISTS (
        SELECT 1
        FROM expected_users expected
        LEFT JOIN users u
          ON u.university_id = expected.university_id
         AND u.email = expected.email
         AND u.is_active = TRUE
        WHERE u.user_id IS NULL
    )
    AND NOT EXISTS (
        SELECT 1
        FROM expected_positions expected
        LEFT JOIN users u ON u.email = expected.email
        LEFT JOIN positions p ON p.name = expected.position_name
        LEFT JOIN organizational_units ou ON ou.code = expected.unit_code
        LEFT JOIN user_positions up
          ON up.user_id = u.user_id
         AND up.position_id = p.position_id
         AND up.unit_id = ou.unit_id
         AND up.is_active = TRUE
         AND (up.end_date IS NULL OR up.end_date >= CURRENT_DATE)
        WHERE up.user_position_id IS NULL
    )
    AND NOT EXISTS (
        SELECT 1
        FROM expected_roles expected
        LEFT JOIN users u ON u.email = expected.email
        LEFT JOIN roles r ON r.role_name = expected.role_name
        LEFT JOIN user_roles ur
          ON ur.user_id = u.user_id
         AND ur.role_id = r.role_id
        WHERE ur.user_id IS NULL
    )
    AND NOT EXISTS (
        SELECT 1
        FROM expected_partners expected
        LEFT JOIN partners p
          ON p.organization_name = expected.organization_name
         AND p.is_active = TRUE
        WHERE p.partner_id IS NULL
    );
"@
        },
        [pscustomobject]@{
            Name = '20260722_workspace_showcase_data.sql'
            Description = 'Showcase Agreements and annual reports'
            RelativePath = 'migrations\20260722_workspace_showcase_data.sql'
            CheckSql = "SELECT (SELECT count(*) FROM agreements WHERE agreement_code LIKE 'DEMO-%') = 6;"
        }
    )
}

function Inspect-Steps {
    param([object[]]$Steps)

    $results = @()
    foreach ($step in $Steps) {
        $checksum = Get-FileChecksum -RelativePath $step.RelativePath
        $record = Get-TrackingRecord -Name $step.Name
        $isAutomatic = ($step.PSObject.Properties.Name -contains 'AutoDiscovered' -and $step.AutoDiscovered)
        $installed = if ($isAutomatic) {
            $null -ne $record
        } else {
            Test-Feature -CheckSql $step.CheckSql
        }
        $fileName = Split-Path -Leaf $step.RelativePath
        $isImmutableMigration = (
            $step.RelativePath -like 'migrations\*.sql' -and
            $script:OptionalMigrationNames -notcontains $fileName
        )
        $fileChanged = ($null -ne $record -and $record.Checksum -ne $checksum)
        $drifted = ($fileChanged -and $isImmutableMigration)

        $results += [pscustomobject]@{
            Step = $step
            Checksum = $checksum
            Installed = $installed
            Record = $record
            Drifted = $drifted
        }

        if ($drifted) {
            Write-Result ERROR "$($step.Name) was changed after it was recorded. Restore the file and add a new migration."
        } elseif ($fileChanged) {
            Write-Result WARNING "$($step.Description) uses repeatable setup SQL that changed after its previous run."
        } elseif ($installed) {
            if ($isAutomatic) {
                Write-Result OK "Applied migration: $($step.Name)"
            } elseif ($record) {
                Write-Result OK $step.Description
            } else {
                Write-Result OK "$($step.Description) (installed before tracking)"
            }
        } else {
            Write-Result MISSING $step.Description
        }
    }
    return $results
}

function Install-Steps {
    param([object[]]$Inspection)

    Ensure-TrackingTable
    foreach ($item in $Inspection) {
        $step = $item.Step
        if ($item.Drifted) {
            throw "Migration checksum mismatch: $($step.Name). Applied migrations are immutable; restore it and create a new migration."
        }
        if ($item.Installed) {
            if (-not $item.Record) {
                Save-TrackingRecord -Name $step.Name -Checksum $item.Checksum -Method baseline
            } elseif ($item.Record.Checksum -ne $item.Checksum) {
                # Immutable migrations with changed checksums are rejected
                # above. Reaching this branch means the file is repeatable
                # setup SQL, so record the version that was just verified.
                Save-TrackingRecord `
                    -Name $step.Name `
                    -Checksum $item.Checksum `
                    -Method $item.Record.Method
            }
            continue
        }

        Write-Host ""
        Write-Host "Installing: $($step.Description)" -ForegroundColor Cyan
        $path = Join-Path $script:SqlRoot $step.RelativePath
        $isAutomatic = ($step.PSObject.Properties.Name -contains 'AutoDiscovered' -and $step.AutoDiscovered)
        if ($isAutomatic) {
            Invoke-TrackedMigrationFile -Path $path -Name $step.Name -Checksum $item.Checksum
        } else {
            Invoke-PsqlFile -Path $path

            if (-not (Test-Feature -CheckSql $step.CheckSql)) {
                throw "Installation completed without satisfying the feature check: $($step.Description)"
            }

            Save-TrackingRecord -Name $step.Name -Checksum $item.Checksum -Method applied
        }
        Write-Result FIXED $step.Description
    }
}

function Show-FinalSummary {
    param([object[]]$Steps)

    Write-Host ""
    Write-Host 'Final verification' -ForegroundColor Cyan
    Write-Host '------------------'
    $failed = @()
    foreach ($step in $Steps) {
        $isAutomatic = ($step.PSObject.Properties.Name -contains 'AutoDiscovered' -and $step.AutoDiscovered)
        if ($isAutomatic) {
            $checksum = Get-FileChecksum -RelativePath $step.RelativePath
            $record = Get-TrackingRecord -Name $step.Name
            $valid = ($null -ne $record -and $record.Checksum -eq $checksum)
        } else {
            $valid = Test-Feature -CheckSql $step.CheckSql
        }

        if ($valid) {
            if ($isAutomatic) {
                Write-Result OK "Applied migration: $($step.Name)"
            } else {
                Write-Result OK $step.Description
            }
        } else {
            Write-Result MISSING $step.Description
            $failed += $step
        }
    }

    if ($failed.Count -gt 0) {
        throw "$($failed.Count) database feature(s) are still missing."
    }
}

function Invoke-QuickSuite {
    if (-not $script:Php -or -not $IncludeDevelopmentData) {
        return
    }

    $suite = Join-Path $script:RepoRoot 'scripts\run_agreement_acceptance_suite.php'
    if (-not (Test-Path -LiteralPath $suite -PathType Leaf)) {
        Write-Result WARNING 'Acceptance suite was not found; database verification still passed.'
        return
    }

    Write-Host ""
    Write-Host 'Running the quick Agreement acceptance suite...' -ForegroundColor Cyan
    & $script:Php $suite '--quick'
    if ($LASTEXITCODE -ne 0) {
        throw 'Database updates passed, but the quick Agreement acceptance suite failed.'
    }
}

try {
    $mode = if ($CheckOnly) {
        'check only'
    } elseif ($IncludeDevelopmentData) {
        'install + development data'
    } else {
        'install required updates'
    }

    Write-Host ""
    Write-Host 'UOB Database Manager' -ForegroundColor Cyan
    Write-Host '===================='
    Write-Host ("Mode: {0}" -f $mode)
    Write-Host ""

    Resolve-Tools
    Test-AndRepairPhp
    Connect-Database
    Test-ApplicationDatabaseConfiguration
    Test-CoreSchema
    Test-TrackingTable

    $legacySteps = @(Get-DatabaseSteps)
    $steps = @($legacySteps)
    $steps += @(Get-AutomaticMigrationSteps -KnownSteps $legacySteps)
    if ($IncludeDevelopmentData) {
        $steps += @(Get-OptionalDevelopmentSteps)
    }

    Write-Host ""
    Write-Host 'Checking database features...' -ForegroundColor Cyan
    $inspection = @(Inspect-Steps -Steps $steps)
    $drifted = @($inspection | Where-Object { $_.Drifted })
    $missing = @($inspection | Where-Object { -not $_.Installed })
    $untracked = @($inspection | Where-Object { $_.Installed -and -not $_.Record })

    if ($drifted.Count -gt 0) {
        throw "$($drifted.Count) applied migration file(s) changed after installation. Restore those files and express every new database change in a new migration."
    }

    Test-MigrationRegistryConsistency

    if ($CheckOnly) {
        Write-Host ""
        Write-Host 'Checking optional local development data...' -ForegroundColor Cyan
        $optionalInspection = @(Inspect-Steps -Steps @(Get-OptionalDevelopmentSteps))
        $optionalMissing = @($optionalInspection | Where-Object { -not $_.Installed })

        Write-Host ""
        if (
            $missing.Count -eq 0 -and
            $script:PhpReady -and
            $script:ApplicationConfigReady
        ) {
            Write-Result OK 'All required database features are installed.'
            if ($optionalMissing.Count -gt 0) {
                Write-Result INFO 'Optional development users/showcase data are not fully installed. Choose option 3 to add them.'
            } else {
                Write-Result OK 'Optional development users and showcase data are installed.'
            }
            exit 0
        }
        if ($missing.Count -gt 0) {
            Write-Result MISSING "$($missing.Count) database feature(s) need installation."
        }
        if (-not $script:PhpReady) {
            Write-Result MISSING 'One or more required PHP extensions need installation.'
        }
        if ($script:PhpReady -and -not $script:ApplicationConfigReady) {
            Write-Result MISSING 'The application database credentials need configuration.'
        }
        Write-Host 'Run database-manager.cmd and choose option 2 or 3 to install them.'
        exit 2
    }

    if ($script:PhpReady -and -not $script:ApplicationConfigReady) {
        throw 'Database updates were not started because the application database credentials are not configured. Copy config\database.local.example.php to config\database.local.php, set the password, and rerun the manager.'
    }

    if ($missing.Count -eq 0 -and $untracked.Count -eq 0) {
        Write-Host ""
        Write-Result OK 'The database is already current.'
    } else {
        if (-not $SkipBackup) {
            New-DatabaseBackup
        } else {
            Write-Result WARNING 'Backup was skipped by request.'
        }
        Install-Steps -Inspection $inspection
    }

    Show-FinalSummary -Steps $steps

    if (-not $IncludeDevelopmentData) {
        $activeUsers = Invoke-PsqlCapture -Sql 'SELECT count(*) FROM users WHERE is_active;'
        if ($activeUsers.Count -gt 0 -and ($activeUsers | Select-Object -Last 1) -eq '0') {
            Write-Result WARNING 'No active users exist. For a local demo database, rerun and choose option 3.'
        }
    }

    Invoke-QuickSuite

    Write-Host ""
    Write-Result OK 'Database setup is complete.'
    if ($script:PhpIniChanged) {
        Write-Result INFO 'Restart Apache once so the website loads the enabled PostgreSQL extension.'
    }
    exit 0
} catch {
    Write-Host ""
    Write-Host ('[ERROR] {0}' -f $_.Exception.Message) -ForegroundColor Red
    exit 1
} finally {
    if ($null -eq $script:OriginalPgPassword) {
        Remove-Item Env:PGPASSWORD -ErrorAction SilentlyContinue
    } else {
        $env:PGPASSWORD = $script:OriginalPgPassword
    }
}
