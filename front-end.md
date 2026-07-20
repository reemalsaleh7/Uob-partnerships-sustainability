# Agreement frontend — Phase 1

This package adds a protected internal Agreement workspace without changing the existing public CSV-driven `uob-agreements/agreements.php` page.

## Included

- Real API login using `POST /api/index.php/login`.
- Session verification through `GET /api/index.php/me`.
- Permission check for `VIEW_AGREEMENT`.
- Agreement register loaded from `GET /api/index.php/agreements`.
- Client-side title/type/status/ID filtering.
- Agreement detail loaded from `GET /api/index.php/agreements/{id}`.
- Version history loaded from `GET /api/index.php/agreements/{id}/versions`.
- Logout using `POST /api/index.php/logout`.
- Responsive UOB-styled Bootstrap layout.
- DOM-safe rendering with `textContent` rather than HTML interpolation.

Create/edit, submit, and workflow inbox buttons are intentionally deferred to the next slices. Buttons are displayed only when the user has the related permission and remain disabled until those screens are implemented.

## Install

Copy the included `uob-agreements/workspace` directory into the repository at the same path:

```text
Uob-partnerships-sustainability/
└── uob-agreements/
    └── workspace/
```

No existing file is replaced in this phase.

## Open locally

```text
http://localhost/Uob-partnerships-sustainability/uob-agreements/workspace/login.php
```

Development account example:

```text
dev.dean@uob.test
UobDev2026!
```

The fixture password is development-only.

## Validate

From the repository root in PowerShell:

```powershell
Get-ChildItem `
  ".\uob-agreements\workspace" `
  -Recurse `
  -Filter "*.php" |
ForEach-Object {
  & "C:\xampp\php\php.exe" -l $_.FullName
}

git diff --check
git status --short
```

Then open the workspace and verify:

1. An invalid login displays the API error.
2. Dean login succeeds.
3. The Agreement register loads PostgreSQL records rather than the CSV data.
4. Search and status filters work.
5. Opening Agreement 69 shows its details and versions.
6. Signing out returns to the workspace login page.
7. Opening `agreements.php` after logout redirects to the workspace login page.

Expected new files appear only under:

```text
uob-agreements/workspace/
```

