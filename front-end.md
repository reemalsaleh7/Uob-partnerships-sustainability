# Agreement frontend — complete workflow and PostgreSQL public catalogue

This package provides the protected internal Agreement workspace, makes it the canonical Agreement administration interface, and publishes only approved or active PostgreSQL Agreements to `uob-agreements/agreements.php`.

The draft editor is now a comprehensive nine-section form combining the official cooperation request, MOU, executive-program, legacy form, and CSV reporting fields. It captures multiple partners; bilingual titles; duration and renewal; need, objectives, value, focus and cooperation fields; commitments; rankings and SDGs; monitoring and legal clauses; coordinators and signatories; an executive program; outcome metrics; and a public signing link. The Agreement detail screen renders the complete record, while applicant, organizational, submission, approval, and status data remain system-derived.

The authenticated workspace also includes dedicated lifecycle-request pages.
Approved and active Agreements can start a renewal, amendment, or termination
draft. Conditional forms expose only the official fields for that request type,
the request detail page shows immutable versions and resubmission actions, and a
single assignment-protected review screen adapts to Initial VP, Legal, Finance,
Final VP/mediation, and President stages.

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
- Multiple active partner selection loaded from `GET /api/index.php/partners`.
- Create Agreement draft using `POST /api/index.php/agreements`.
- Edit a draft or returned Agreement using `PUT /api/index.php/agreements/{id}`.
- Submit a draft into the approval workflow using `POST /api/index.php/agreements/{id}/submit`.
- Save a returned Agreement as a new version and resubmit it using `POST /api/index.php/agreements/{id}/resubmit`.
- Workflow inbox loaded from `GET /api/index.php/workflow-inbox`.
- Initial VP review with required Legal routing and an optional Finance review.
- Initial VP return-to-creator and terminal rejection decisions.
- Legal review approval and documented change requests routed back to the VP.
- Finance review approval and documented change requests routed back to the VP when Finance is required.
- Final VP review with Legal/Finance outcome summary and routing to President.
- Final VP return-to-creator and terminal rejection decisions.
- VP mediation that displays the requesting stage and recorded reason, then routes to the creator, Legal, Finance, or rejection.
- President review with Legal, Finance, and Final VP outcome summaries.
- President approval that completes the workflow and publishes the Agreement as `APPROVED`.
- President change requests routed to VP mediation and terminal President rejection with a required reason.
- Secure Agreement document upload, listing, download, and controlled deletion on the Agreement record and every role-specific review screen.
- PDF/DOC/DOCX validation, 10 MB limit, server-generated private storage keys, SHA-256 checksums, and Agreement-version association.
- Logout using `POST /api/index.php/logout`.
- Non-cacheable authenticated API requests so data from one account cannot appear after switching users in the same browser.
- Responsive UOB-styled Bootstrap layout.
- DOM-safe rendering with `textContent` rather than HTML interpolation.
- Controlled replacement of legacy Agreement add/review routes with non-cacheable redirects to the authenticated workspace.
- A reversible rollout switch that keeps Initiative administration unchanged while retiring legacy internal Agreement pages.
- PostgreSQL-backed public catalogue restricted to `APPROVED` and `ACTIVE` Agreements.
- Stable `UOB-AGR-######` public references and an explicit non-sensitive field allow-list.
- Compatibility-only approved legacy detail lookup for Initiative links; legacy rows are not listed in the catalogue.
- Controlled migration of the 41 enriched legacy CSV Agreements into PostgreSQL with immutable versions, audit history, and source provenance.

Create, edit, submit, redraft, workflow, and document controls are displayed only when the authenticated user owns the applicable record or active workflow assignment and has the required permission. Physical files are served only through the authenticated API; private storage paths are never exposed to the browser.

## Install

Copy the supplied project files into the repository root while preserving their paths. The public-catalogue phase adds the public repository, adapter, public pages, regression test, and documentation:

```text
Uob-partnerships-sustainability/
├── repositories/
├── tests/
├── docs/
├── front-end.md
└── uob-agreements/
    ├── includes/
    ├── agreements.php
    └── agreement-details.php
```

Replace matching files. Do not delete the public Agreement catalogue, public detail page, or any Initiative files.

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

Before testing documents on an existing database, apply the idempotent migration:

```powershell
& "C:\Program Files\PostgreSQL\17\bin\psql.exe" `
  -U postgres `
  -d UOB_Partnership_and_Initiative `
  -f ".\uob-agreements\data\sql\migrations\20260721_secure_agreement_documents.sql"
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
18. Return an Agreement from Initial VP, sign in as its creator, and confirm `REVISION_REQUIRED` shows **Revise Agreement**.
19. Save the revision and confirm the version history gains a new immutable snapshot.
20. Resubmit the revision and confirm its status returns to `UNDER_REVIEW` and Initial VP receives the new-cycle task.
21. Sign in as `dev.legal@uob.test`, open the Legal task, and approve it or send a reasoned change request to the VP.
22. Route a new Agreement through Initial VP with Finance required, sign in as `dev.finance@uob.test`, and confirm the Finance task opens its dedicated review screen.
23. Approve Finance before Legal and confirm no Final VP task appears until Legal also approves.
24. Repeat with a Finance change request and confirm the active specialist work pauses while VP mediation receives the task.
25. Complete all required specialist reviews and confirm the VP inbox labels the next task **Final VP review**.
26. Open it and confirm the Agreement, latest version, Legal status, and Finance status/requirement appear.
27. Approve it and confirm the task leaves the VP inbox and appears in the President inbox.
28. In a separate test workflow, request Legal or Finance changes and confirm the VP inbox labels the task **VP mediation**.
29. Open mediation and confirm the requesting stage and recorded reason appear.
30. Route the request to the creator, Legal, or Finance and confirm only the selected destination receives the next task.
31. Complete Final VP review, sign in as `dev.president@uob.test`, and open the President approval from the Workflow inbox.
32. Confirm the President screen shows the Agreement, latest version, Legal result, Finance result or “Not required,” and Final VP comments.
33. Approve the Agreement and confirm the President task disappears, the workflow completes, and the Agreement status becomes `APPROVED`.
34. In a separate workflow, submit a required President change reason and confirm a **VP mediation** task appears with the President reason.
35. In another workflow, reject from President with a required reason and confirm the Agreement becomes terminally `REJECTED`.
36. Change the President review URL IDs or sign in as another approver and confirm the page refuses access without that exact active assignment.
37. As the Dean, upload a valid PDF to a draft and confirm it appears with its linked Agreement version and can be downloaded.
38. Try an unsupported or renamed file and a file over 10 MB; confirm the upload is rejected without a document row.
39. Submit the Agreement and confirm the Dean can download but cannot upload or delete during review.
40. Open the active reviewer screen and confirm only the assigned reviewer can upload a review document.
41. Complete the reviewer task and confirm the former reviewer can no longer reuse the document URL while the Agreement remains under review.
42. Approve the Agreement and confirm permitted viewers can download documents while upload/delete remain locked.
43. Open `admin/add-agreement.php` and confirm it redirects to `workspace/agreement-form.php`.
44. Open `admin/review-agreements.php`, `admin/agreements.php`, and `admin/edit-agreement.php`; confirm each reaches the protected Agreement register.
45. Repeat the legacy-route checks while signed out and confirm workspace login returns to the requested canonical page after authentication.
46. Open Agreement actions from the legacy administrator dashboard and header; confirm they use the workspace.
47. Complete a President approval and confirm the public catalogue lists the new PostgreSQL Agreement.
48. Confirm Initiative administration routes remain unchanged.
49. Confirm draft, in-review, returned, and rejected Agreements do not appear in the public catalogue.
50. Open a listed Agreement and confirm its public reference, title, type, partner, country, status, summary, and responsible unit render.
51. Confirm creator/reviewer identity, workflow comments, versions, audits, and private documents are absent from public output.
52. Open an existing Initiative with a legacy approved Agreement code and confirm its compatibility detail still opens without adding the row to the catalogue.
53. Apply the legacy-import tracking migration and run the importer in dry-run mode; confirm 41 ready rows and no conflicts.
54. Commit the import and confirm all 41 Agreement codes appear in the PostgreSQL-backed public catalogue.
55. Run the importer again in dry-run mode and confirm all 41 rows are skipped without creating duplicates.

The frontend files are under:

```text
uob-agreements/workspace/
```

The partner lookup also adds the corresponding controller, service, repository, and route in the existing backend layers.

The controlled replacement boundary is documented in `docs/agreement-legacy-replacement.md`. The publication boundary is documented in `docs/agreement-public-catalogue.md`.
The historical migration boundary is documented in `docs/agreement-legacy-csv-import.md`.
