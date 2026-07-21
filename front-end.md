# Agreement frontend — Phase 2

This package adds a protected internal Agreement workspace without changing the existing public CSV-driven `uob-agreements/agreements.php` page.

## Included

- Real API login using `POST /api/index.php/login`.
- Session verification through `GET /api/index.php/me`.
- Tab-isolated workspace sessions that remain stable when each tab refreshes.
- Permission check for `VIEW_AGREEMENT`.
- Agreement register loaded from `GET /api/index.php/agreements`.
- Backend-scoped Agreement visibility for creators, current reviewers, published Agreements, and system administrators.
- Client-side title/type/status/ID filtering.
- Agreement detail loaded from `GET /api/index.php/agreements/{id}`.
- Version history loaded from `GET /api/index.php/agreements/{id}/versions`.
- Active partner selection loaded from `GET /api/index.php/partners`.
- Create Agreement draft using `POST /api/index.php/agreements`.
- Edit a draft using `PUT /api/index.php/agreements/{id}`.
- Submit a draft into the approval workflow using `POST /api/index.php/agreements/{id}/submit`.
- Workflow inbox loaded from `GET /api/index.php/workflow-inbox`.
- Initial VP review with required Legal routing and an optional Finance review.
- Initial VP return-to-creator and terminal rejection decisions.
- Logout using `POST /api/index.php/logout`.
- Non-cacheable authenticated API requests so data from one account cannot appear after switching users in the same browser.
- Responsive UOB-styled Bootstrap layout.
- DOM-safe rendering with `textContent` rather than HTML interpolation.

The Legal, Finance, Final VP, President, creator redraft, and physical document upload screens remain deferred to the next slices. Create, edit, submit, and workflow controls are displayed only when the authenticated user owns the applicable record or active workflow assignment and has the required permission.

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
8. A Dean can create a draft using an active partner and is redirected to its detail page.
9. A draft can be edited and the version history gains a snapshot.
10. Submitting a draft changes its status to `UNDER_REVIEW` and hides the edit/submit actions.
11. The submitted Agreement appears in the VP user's Workflow inbox.
12. The Initial VP can open the review and send it to Legal with or without Finance review.
13. After a successful decision, the completed task is removed from the VP inbox.
14. A VP cannot see another user's unsubmitted draft in the Agreement register or by changing the Agreement ID in the detail URL.
15. The creator can still see the draft, and an assigned reviewer can see an `UNDER_REVIEW` Agreement while their task is active.
16. After the Dean loads a draft and signs out, the VP cannot see or directly open that draft from a cached response.
17. Sign in as the Dean and VP in two separate tabs, refresh both tabs, and confirm each tab retains its own user.

The frontend files are under:

```text
uob-agreements/workspace/
```

The partner lookup also adds the corresponding controller, service, repository, and route in the existing backend layers.
