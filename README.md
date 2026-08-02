# Agreement detail and form-style update

Target branch: `agreements`

Target baseline: `b889b69c874daab8b20b62a64df8a9a1f9084e18`

## Included changes

- Redesigns the Agreement detail page using the approved card-based layout.
- Keeps workflow, comments, documents, lifecycle requests, performance monitoring, signing status, version history, exports, and review-return navigation.
- Shows partner type, country/city, website, submitter identity, status, and record origin.
- Preserves the Administrative correction feature introduced on 2 August.
- Restores the redesigned create/edit form, including guided sections, searchable partner selection, date-range controls, partner lookup, document extraction, programme guidance, and responsive styling.
- Restores the Overview priority-card CSS so `Act now`, `Track`, and `Report` labels, counts, and descriptions no longer run together.
- Adds cache-busting asset versions and static acceptance assertions.

No database migration is required for this package.

## Form-style regression investigation

The regression was introduced by commit:

`b889b69c874daab8b20b62a64df8a9a1f9084e18` — `add Administrative correction`

Commit time: 2 August 2026, 13:33 Bahrain time.

That commit correctly added Administrative correction backend behavior, but it also unintentionally replaced these newer workspace files with older copies:

- `uob-agreements/workspace/agreement-form.php`
- `uob-agreements/workspace/assets/js/agreement-form.js`
- `uob-agreements/workspace/assets/css/workspace.css`
- `uob-agreements/workspace/includes/layout.php`

The form regression was substantial: approximately 3,694 lines were removed across those four files. The missing stylesheet rules also caused the Overview priority text to run together.

The modern form originated in commit `5e14e09` (`Redesign Agreement form experience`) and was refined by `46ea231`, `35e9af1`, `69fcd09`, and `db94cc7` before the rollback.

## Apply the update

Make sure the current branch is `agreements` and the working tree is clean.

Option A: extract this ZIP at the repository root and allow these nine files to be replaced.

Option B: apply the included patch from the repository root:

```powershell
git apply --check .\agreements-branch-agreement-detail-update.patch
git apply .\agreements-branch-agreement-detail-update.patch
```

Then run:

```powershell
& "C:\xampp\php\php.exe" .\tests\WorkspaceExperienceSmokeTest.php

& "C:\xampp\php\php.exe" `
  .\scripts\run_agreement_acceptance_suite.php --quick
```

Finally inspect and commit:

```powershell
git diff --check
git status --short
git diff --stat
```

