# Agreement Integration Hardening and Release Acceptance

## Scope

This phase hardens the complete Agreement module after workflow, documents,
lifecycle requests, successors, signing, operational status, historical import,
public publication, and performance monitoring were combined. It does not
modify the Initiative or notification modules.

## Security changes

- Database credentials are no longer stored in tracked source. Runtime settings
  come from `config/database.local.php` or `UOB_DB_*` environment variables.
- `config/database.local.php` and private uploaded files are ignored by Git.
- PostgreSQL native prepared statements are enforced.
- Workspace session IDs use PHP strict mode. Authentication regenerates the
  session ID, idle sessions expire after 30 minutes, and every authenticated
  session ends after 12 hours.
- Deactivating a user invalidates their next protected API request immediately.
- Five failed logins create a 15-minute lock. A later successful login resets
  the counter and lock timestamp.
- Every state-changing API request requires `X-UOB-Tab-Session`; the workspace
  client already sends this header.
- JSON payloads must be objects using `application/json`, parse correctly, and
  remain at or below 1 MB. Upload endpoints retain their separate 10 MB secure
  document limit. All Agreement mutation controllers use this shared boundary,
  including creator resubmission.
- API responses add restrictive browser headers and an `X-Request-Id`. An
  unexpected server error exposes only that identifier, while the full error is
  written to the PHP error log.

## Permission repair

The hardening migration makes development and production roles converge:

| Role | Agreement permissions |
| --- | --- |
| Agreement Creator | Create, edit, submit, view, delete eligible drafts; finalize eligible signing; manage owned performance reports |
| Agreement Approver | View; approve/reject assigned work; review reports; view aggregate dashboard |
| System Administrator | All Agreement permissions above |

Record ownership, workflow assignment, office assignment, status, and service
authorization checks still apply after role permission checks.

## Configuration

Copy `config/database.local.example.php` to `config/database.local.php`, then
set the local PostgreSQL password. Never commit the local file.

Production may instead provide:

- `UOB_DB_HOST`
- `UOB_DB_PORT`
- `UOB_DB_NAME`
- `UOB_DB_USER`
- `UOB_DB_PASSWORD`

## Acceptance commands

Run the fast suite after ordinary Agreement changes:

```powershell
& "C:\xampp\php\php.exe" `
  .\scripts\run_agreement_acceptance_suite.php --quick
```

Run the full suite before merging or releasing:

```powershell
& "C:\xampp\php\php.exe" `
  .\scripts\run_agreement_acceptance_suite.php
```

Each database-mutating smoke test manages its own transaction and rolls back.
The historical import verification is read-only and expects the controlled
41-row import to have completed.

## Release gate

A release is ready only when:

1. The hardening migration has completed.
2. PHP extensions `pdo_pgsql`, `fileinfo`, and `zip` are enabled.
3. The quick and full acceptance suites both report zero failures.
4. The browser checks in `docs/agreement-test-matrix.md` pass for creator, VP,
   Legal, Finance, President, report reviewer, and administrator accounts.
5. VP, Legal, Finance, and President offices each have at least one active user
   with both Agreement approval and rejection permissions.
6. Private storage is writable by Apache and remains unreachable by direct URL.
7. No `config/database.local.php` or uploaded document is staged by Git.
