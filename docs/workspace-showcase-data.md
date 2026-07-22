# Workspace showcase data and active-Agreement discovery

## Purpose

This phase makes the role-based workspace useful in a demonstration environment without changing the approved Agreement workflow. It adds clearly identified `DEMO-*` records, realistic annual reporting evidence, and a direct path from an active Agreement to the existing Initiative request form.

The migration is intended for development, demonstration, and user-acceptance environments. The records are not real University partnerships and the PDF evidence states this explicitly.

## Agreement views

Agreement creators receive four distinct scopes:

- **Active Agreements**: every active University Agreement visible to the user.
- **My active Agreements**: active Agreements whose `created_by` value is the signed-in user.
- **My Agreements**: every Agreement created by the user, regardless of status.
- **All visible**: the complete set allowed by the existing record-visibility policy.

Each row identifies whether it was **Created by you** or is a **University Agreement**. Initiative creators see active Agreements by default and receive a **Use for Initiative** action.

## Faculty and Initiative integration

The migration grants `VIEW_AGREEMENT` to the existing **Initiative Creator** role. This does not grant Agreement creation, editing, submission, review, document upload, operational, lifecycle, or performance-report permissions.

When Faculty selects **Use for Initiative**:

1. The existing secure one-time workspace handoff is used.
2. The selected Agreement ID is passed to the legacy Initiative request page.
3. The page re-queries PostgreSQL and accepts only an `ACTIVE` Agreement.
4. The Agreement title, reference, partner, and objectives are shown as context.
5. The Agreement reference is stored inside the existing Initiative description field, preserving the teammate-owned CSV structure and review workflow.

## Showcase records

The idempotent migration creates:

- Six Agreements: five `ACTIVE` and one future `APPROVED` Agreement.
- Six external partner records.
- SDG mappings, outcome metrics, executive programmes, and version-one snapshots.
- Five annual reporting periods: two accepted, one submitted, one returned, and one draft.
- Outcome results and programme-progress updates.
- Four protected PDF annual reports with recorded file size and SHA-256 checksum.
- `VIEW_AGREEMENT` access for Initiative creators.

The Dean development account owns three active showcase Agreements, making the difference between institution-wide active Agreements and the Dean's active portfolio immediately visible.

## Secure report evidence

The PDF files are copied to the existing private Agreement-document store. Their database rows use `document_type = 'ANNUAL_REPORT'`, valid storage keys, file sizes, and SHA-256 checksums. The annual-report screen now provides a protected download action through the authenticated document endpoint.

## Idempotency and removal

Rerunning the migration updates only records identified by `DEMO-*` Agreement codes and fixed showcase storage keys. It does not delete or rewrite ordinary Agreements.

If the showcase data must be removed, delete the `DEMO-*` Agreements in a development database; the related partner records can then be removed separately if they are no longer referenced. Do not remove the files or database rows manually while the report records still reference them.
