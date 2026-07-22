# Agreement release runbook

## Release boundary

This runbook releases the completed Agreement module. It does not deploy or
approve changes owned by the Initiative or notification teams. This UAT and
release-preparation phase adds no database migration and no production runtime
change.

Use three distinct environments where possible: development, UAT/staging, and
production. The complete automated test suite runs on development/UAT. On
production, run only read-only readiness and focused browser smoke checks.

## 1. Freeze and identify the candidate

From the repository root:

```powershell
git status --short --branch
git rev-parse HEAD
git log -1 --format="%H%n%ad%n%s" --date=iso-strict
```

Record the commit in `agreement-uat-tracker.xlsx`. The working tree must be
clean except for explicitly documented local files. Confirm that local secrets,
private uploads, database backups, and test evidence are not staged:

```powershell
git check-ignore -v config/database.local.php
git diff --cached --name-only
```

Do not continue if `config/database.local.php`, `storage/private`, a `.backup`
file, or UAT evidence appears in the staged list.

## 2. Capture environment evidence

```powershell
& "C:\xampp\php\php.exe" --version
& "C:\xampp\php\php.exe" --ini
& "C:\xampp\php\php.exe" -m |
  Select-String -Pattern "pdo_pgsql|pgsql|fileinfo|zip"
& "C:\Program Files\PostgreSQL\17\bin\psql.exe" --version
```

Record PHP, PostgreSQL, Apache/XAMPP, operating system, browser, database name,
timezone, and Git commit in the workbook. The application timezone and the
scheduled-task timezone must agree for activation, expiry, and report periods.

## 3. Back up the database and private documents

Choose an explicit protected backup directory. The examples below use
`C:\UOB-Release-Backups`; create and secure it before the release window.

```powershell
$Stamp = Get-Date -Format "yyyyMMdd-HHmmss"
$BackupDir = "C:\UOB-Release-Backups\agreement-$Stamp"
New-Item -ItemType Directory -Path $BackupDir | Out-Null

& "C:\Program Files\PostgreSQL\17\bin\pg_dump.exe" `
  -U postgres `
  -d UOB_Partnership_and_Initiative `
  -F c `
  -f "$BackupDir\database.backup"

Copy-Item `
  ".\storage\private" `
  "$BackupDir\private" `
  -Recurse
```

Verify the database archive is readable without restoring it:

```powershell
& "C:\Program Files\PostgreSQL\17\bin\pg_restore.exe" `
  --list `
  "$BackupDir\database.backup" |
  Select-Object -First 20
```

Record the backup path, size, creation time, responsible person, and verification
result. A backup is not accepted until a restore has been rehearsed on a
separate disposable database during UAT.

## 4. UAT release gate

On the UAT database, run:

```powershell
& "C:\xampp\php\php.exe" `
  .\tests\AgreementReleaseReadinessSmokeTest.php

& "C:\xampp\php\php.exe" `
  .\scripts\run_agreement_acceptance_suite.php
```

Expected automated result:

```text
Tests: 28
Passed: 28
Failed: 0
All Agreement acceptance tests passed.
```

Then complete every required row in `agreement-uat-tracker.xlsx`. A passing
automated suite does not replace browser UAT or business sign-off.

## 5. Migration history for a new environment

An existing environment should apply only migrations it has not already
recorded and tested. Do not blindly replay files on production. For a new
environment built from the base deployment, the Agreement migrations were
tested in this dependency order:

1. `20260716_agreement_version_snapshots.sql`
2. `20260716_create_audit_logs.sql`
3. `20260719_agreement_workflow_foundation.sql`
4. `20260719_add_review_offices.sql`
5. `20260719_add_workflow_step_timestamps.sql`
6. `20260719_add_routed_to_vp_action.sql`
7. `20260719_add_auth_tracking_columns.sql`
8. `20260719_add_agreement_return_workflow.sql`
9. `20260720_add_redraft_version_baseline.sql`
10. `20260721_comprehensive_agreement_fields.sql`
11. `20260721_secure_agreement_documents.sql`
12. `20260721_legacy_agreement_import_tracking.sql`
13. `20260721_agreement_lifecycle_workflow.sql`
14. `20260721_secure_lifecycle_request_documents.sql`
15. `20260721_lifecycle_successor_agreements.sql`
16. `20260721_agreement_operational_status.sql`
17. `20260721_agreement_performance_monitoring.sql`
18. `20260721_agreement_integration_hardening.sql`

Apply each with `-v ON_ERROR_STOP=1`, inspect the expected `COMMIT`, and stop on
the first error. The controlled 41-row legacy import is a separate data
operation, not a schema migration. Preserve its dry-run, source-hash, and
rollback procedure from `docs/agreement-legacy-csv-import.md`.

## 6. Production deployment window

1. Confirm signed UAT approval and the exact release commit/tag.
2. Pause scheduled Agreement status synchronization and reporting-period
   generation.
3. Put the site in the project's approved maintenance mode or stop Apache for
   the short file/database transition.
4. Take and verify fresh database and private-document backups.
5. Deploy the exact signed-off Git commit; do not deploy a dirty worktree.
6. Preserve production `config/database.local.php` or environment variables;
   never replace them with the example file.
7. Apply only approved pending migrations in the tested dependency order.
8. Confirm private storage exists and remains writable by the Apache/PHP user.
9. Restart Apache and perform a hard refresh.

## 7. Production verification

Run the read-only readiness check:

```powershell
& "C:\xampp\php\php.exe" `
  .\tests\AgreementReleaseReadinessSmokeTest.php
```

Do not run development seeds, legacy import commits, or the full transaction
test suite on production.

Perform focused browser checks with designated production test accounts:

1. Login and logout work; the Agreement register loads.
2. A creator can create and delete a disposable draft if policy permits.
3. VP, Legal, Finance, and President inboxes load only authorized tasks.
4. One approved/active public Agreement resolves through its public reference.
5. A private Agreement document cannot be opened without authorization.
6. Lifecycle, signing, reporting, and dashboard pages load for authorized roles.
7. Existing Initiative pages still behave as before.

If a disposable draft is prohibited, restrict production smoke testing to
read-only pages and use the verified UAT evidence for mutations.

## 8. Scheduled jobs

After verification, resume the two Agreement jobs with the same PHP executable,
project path, database configuration, and timezone used during UAT:

```powershell
& "C:\xampp\php\php.exe" `
  .\scripts\sync_agreement_operational_statuses.php --commit

& "C:\xampp\php\php.exe" `
  .\scripts\generate_agreement_reporting_periods.php --commit
```

Run each once manually, inspect the output, and then enable its daily Task
Scheduler entry. Notification jobs are owned and released separately by the
notification teammate.

## 9. Rollback triggers

Start rollback immediately for any of the following:

- Authentication or authorization bypass.
- Private-data or document exposure.
- Agreement, workflow, version, signing, lifecycle, or report corruption.
- Core approval journey cannot progress and there is no safe workaround.
- Migration failure or database consistency/readiness failure.
- Public catalogue exposes a non-public Agreement.

## 10. Rollback procedure

1. Stop Apache or enable maintenance mode.
2. Disable both Agreement scheduled jobs.
3. Preserve the failed deployment logs and database for investigation.
4. Restore the last known-good code tag/commit.
5. Restore the database backup into a controlled replacement database or follow
   the database administrator's approved restore procedure.
6. Restore the matching private-document directory. Database metadata and
   private files must come from the same backup point.
7. Restore the production database configuration without placing credentials
   in Git.
8. Restart Apache.
9. Run the read-only readiness check and focused read-only browser checks.
10. Record the rollback decision, time, operator, reason, data recovery point,
    and verification result in the release record.

Do not attempt to reverse additive migrations manually during an incident.
Restore the verified backup so code, schema, records, and private files remain
consistent.

## 11. Closure

After a stable observation period, record the production verification result,
enable normal support ownership, and archive:

- Completed UAT workbook and sign-offs.
- Release commit/tag and deployment timestamp.
- Automated test output and readiness output.
- Backup verification and restore-rehearsal evidence.
- Migration/deployment logs.
- Post-release checks and any accepted deferred defects.

