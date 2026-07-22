# Agreement lifecycle successor Agreements

## Outcome

Final President approval of a renewal or amendment creates a new approved
Agreement rather than editing the source. Termination continues to update only
the source status and action history.

## Transaction boundary

The President decision, workflow completion, request approval, successor,
relationship, version, and audits commit or roll back together. The request row
is locked before the decision is processed, and database uniqueness rules allow
only one successor per lifecycle request and one identical relationship edge.

## Successor content

The new Agreement copies all scalar fields and normalized collections from the
source: partners, SDGs, rankings, contacts/signatories, executive programs, and
outcome metrics. Its creator is the lifecycle requester and its initial status
is `APPROVED`, because the lifecycle request has already completed the full VP,
Legal, optional Finance, Final VP, and President route.

Renewals replace `start_date`, `end_date`, and `effective_date` with the approved
proposed period. Amendments do not attempt to parse free-text clauses into base
Agreement columns. The approved amendment request, its immutable versions, and
its private attachments remain the exact authority for those changes.

## Provenance and navigation

Successor version 1 embeds `lifecycle_provenance` with the request ID, request
type, source Agreement ID, final approver, justification, renewal dates, and
amendment details. The lifecycle request stores `successor_agreement_id`.
`agreement_relationships` stores the source-to-successor edge, and authenticated
Agreement detail pages show links in both directions.

## Migration

Apply `20260721_lifecycle_successor_agreements.sql` after the lifecycle workflow
and secure lifecycle-document migrations. It adds the successor foreign key and
the uniqueness constraints required for retry safety.
