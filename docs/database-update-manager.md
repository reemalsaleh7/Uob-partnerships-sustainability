# Database Update Manager

The database manager replaces manual migration lists for teammates who already
have `UOB_Partnership_and_Initiative`.

From the repository root, double-click `database-manager.cmd` or run:

```powershell
.\database-manager.cmd
```

The menu provides:

1. **Check only** — reports environment and database gaps without changing
   anything.
2. **Install missing required updates** — backs up the database, enables
   XAMPP's bundled `pdo_pgsql` extension when required, installs only failed
   database features, and verifies the result.
3. **Install updates plus local demo users and showcase data** — performs the
   required update and also installs the development accounts and `DEMO-*`
   Agreements. Use this only on a local development database.

The manager:

- Finds the standard XAMPP PHP and PostgreSQL 17 paths automatically.
- Prompts for the PostgreSQL password once.
- Never drops or recreates the database.
- Checks actual tables, columns, enum values, permissions, and workflow stages;
  it does not guess from filenames.
- Creates `schema_migrations` and adopts features that were installed before
  migration tracking existed.
- Records SHA-256 checksums for future checks.
- Creates a custom-format backup in
  `Documents\UOB-Database-Backups` before the first database change.
- Stops immediately when a required SQL file or core table is missing.

Command-line examples:

```powershell
# Read-only check
.\database-manager.cmd -CheckOnly

# Install required updates using the default database and postgres user
.\database-manager.cmd

# Prepare a complete local demonstration database
.\database-manager.cmd -IncludeDevelopmentData

# Use non-default connection settings
.\database-manager.cmd `
  -Database "UOB_Partnership_and_Initiative" `
  -DbUser postgres `
  -DatabaseHost localhost `
  -Port 5432
```

If the manager enables `pdo_pgsql`, restart Apache after it completes.

Do not select the development-data option for a production database.
