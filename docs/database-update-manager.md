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
4. **Create a new versioned database migration** — creates a correctly named
   migration file for a developer who is introducing a new database change.

The manager:

- Finds the standard XAMPP PHP and PostgreSQL 17 paths automatically.
- Prompts for the PostgreSQL password once.
- Never drops or recreates the database.
- Checks actual tables, columns, enum values, permissions, and workflow stages;
  it does not guess from filenames.
- Creates `schema_migrations` and adopts features that were installed before
  migration tracking existed.
- Automatically discovers every new required `.sql` file in
  `uob-agreements/data/sql/migrations`; no migration list needs to be edited.
- Records SHA-256 checksums for future checks.
- Rejects a migration file that was edited after another database recorded it.
- Installs each newly discovered migration and its tracking record in one
  PostgreSQL transaction, so a failed migration is rolled back.
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

## Team workflow for every new database change

The developer making the change:

1. Pulls the current branch.
2. Runs `database-manager.cmd` and chooses option `4`, or runs
   `new-database-migration.cmd`.
3. Enters a short name such as `add_notification_preferences`.
4. Edits the generated file, removes `UOB_MIGRATION_TODO`, and adds the
   forward-only PostgreSQL statements.
5. Runs option `1` to confirm the file is reported as pending.
6. Runs option `2` to apply it locally and test the application.
7. Commits the migration together with the PHP or frontend code that depends
   on it.

Every teammate then only needs:

```powershell
git pull
.\database-manager.cmd
```

They choose option `1` to see pending migrations or option `2` to back up the
database and install them. The manager scans the migration folder
automatically, applies pending files in filename order, and records each
checksum in `schema_migrations`.

## Migration rules

- Never edit, rename, or delete an applied migration. Add another migration to
  correct or extend it.
- Generated filenames use
  `YYYYMMDD_HHMMSS_short_description.sql`, which gives the team a deterministic
  installation order.
- Do not add `BEGIN`, `COMMIT`, or `ROLLBACK` to automatically discovered
  migrations. The manager owns the transaction.
- Keep migrations forward-only and safe for databases that already contain
  data.
- Required schema/reference-data changes belong in `migrations`.
- Local accounts and presentation records belong in `seed`; they must not be
  added as required migrations.
- When a change also affects fresh deployments, update the corresponding
  modular schema file and consolidated schema artifact in the same commit.
- A checksum error means the checked-out migration differs from the one already
  applied. Restore the original file and create a new migration.

Example notification migration:

```sql
ALTER TABLE notifications
    ADD COLUMN IF NOT EXISTS recipient_email VARCHAR(255);

CREATE INDEX IF NOT EXISTS idx_notifications_recipient_email
    ON notifications (recipient_email);
```

The SQL above is only an example; the notification developer should use the
project's actual notification tables and constraints.
