# Agreement legacy-page replacement

## Purpose

The authenticated Agreement workspace is now the canonical internal interface for creating, reviewing, revising, approving, rejecting, and managing Agreement documents. This phase retires the CSV-based internal Agreement administration paths. The subsequent public-catalogue phase changes the catalogue to PostgreSQL while leaving the Initiative module unchanged.

## Canonical internal routes

| Purpose | Canonical route |
| --- | --- |
| Agreement register | `uob-agreements/workspace/agreements.php` |
| Create Agreement | `uob-agreements/workspace/agreement-form.php` |
| Agreement details and versions | `uob-agreements/workspace/agreement.php?id={id}` |
| Workflow inbox | `uob-agreements/workspace/workflow-inbox.php` |
| Role-specific reviews | The assignment-scoped workspace review pages |

All canonical routes authenticate against the PostgreSQL-backed API. The API derives the actor from the authenticated tab session and applies Agreement visibility, permission, ownership, and active-assignment checks.

## Retired internal routes

The following legacy paths no longer render or mutate CSV Agreement data while the rollout switch is enabled:

| Legacy route | Redirect destination |
| --- | --- |
| `uob-agreements/admin/add-agreement.php` | `uob-agreements/workspace/agreement-form.php` |
| `uob-agreements/admin/review-agreements.php` | `uob-agreements/workspace/agreements.php` |
| `uob-agreements/admin/agreements.php` | `uob-agreements/workspace/agreements.php` |
| `uob-agreements/admin/edit-agreement.php` | `uob-agreements/workspace/agreements.php` |

The redirects use HTTP `302` plus no-cache headers. A user without a valid Agreement workspace session is then sent to `workspace/login.php`, with the intended workspace page retained as the safe return target.

Legacy Agreement codes are not guessed or translated to PostgreSQL numeric IDs. An old edit bookmark therefore opens the protected Agreement register rather than risking access to the wrong record.

## Updated navigation

- The public-site administrator dropdown opens the new create form and Agreement register.
- The legacy administrator dashboard opens the Agreement register and Workflow inbox.
- The legacy administrator top bar opens the new register.
- The email-only legacy login now lands administrators on the general legacy dashboard instead of the retired Agreement review page.

## Intentionally retained

This phase does **not** remove or replace:

- The approved legacy Agreement detail compatibility lookup used by existing Initiative links.
- Initiative and SDG data ownership or relationships.
- Initiative creation, review, administration, CSV files, or Initiative workflows.
- The legacy Agreement source code below the redirect guard, until production acceptance is complete.

The public catalogue is now PostgreSQL-backed and uses the core fields available in the Agreement model. Optional legacy map/SDG/publication metadata remains outside this phase. Existing approved legacy detail links remain readable until the Initiative relationship migration is complete.

## Rollout switch and rollback

The transition is controlled by this setting in `uob-agreements/includes/config.php`:

```php
define('AGREEMENT_WORKSPACE_REPLACES_LEGACY_ADMIN', true);
```

Set it to `false` only as a temporary rollback during acceptance testing. The old add/review pages and legacy navigation then become reachable again. The compatibility-only edit alias remains redirected because there was no safe legacy-code-to-database-ID mapping. Do not leave both systems writable in normal operation, because they use different data stores and would diverge.

Once production acceptance and the Initiative relationship migration are complete, the unreachable legacy Agreement management code, compatibility detail lookup, and temporary switch can be removed in a later cleanup commit.

## Acceptance checks

1. Open each retired internal route and confirm it redirects to the matching workspace route.
2. Repeat while signed out of the workspace and confirm the workspace login appears.
3. Sign in and confirm the safe return opens the originally requested register or create form.
4. Open the legacy administrator dashboard and confirm its Agreement actions open the workspace.
5. Confirm the public Agreement catalogue and a public Agreement detail still render normally.
6. Confirm Initiative administration remains unchanged.
7. Create a draft in the workspace and confirm no row is written to `agreements.csv`.
8. Complete a representative Agreement approval and confirm the PostgreSQL workflow remains authoritative.
