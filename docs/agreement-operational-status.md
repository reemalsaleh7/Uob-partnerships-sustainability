# Agreement signing and operational status

## Purpose

Approval and operation are separate. President approval leaves an Agreement in
`APPROVED`. A creator or system administrator then uploads the executed file,
records the actual signing/effective/expiry dates and final signatories, and
finalizes an immutable signing record.

## State rules

| Current state | Condition | Result |
| --- | --- | --- |
| `APPROVED` | No finalized signing record | Not operational |
| `APPROVED` | Signing finalized; effective date is in the future | Scheduled, still `APPROVED` |
| `APPROVED` | Signing finalized; effective date is today or earlier | `ACTIVE` |
| `ACTIVE` | Expiry date is before the processing date | `EXPIRED` |
| `TERMINATED` | Any date | Unchanged |

An Agreement remains active through its stated expiry date and becomes expired
on the following day. Status synchronization is idempotent and writes at most
one activation and one expiry event per Agreement.

## Authorization and integrity

- The user needs `MANAGE_AGREEMENT_OPERATIONS` and must be the Agreement
  creator. A system administrator is the controlled exception.
- The Agreement must be `APPROVED` and must not already have a signing record.
- The signed file must be a secure Agreement document whose type is
  `SIGNED_AGREEMENT`.
- The file path remains private. Its SHA-256 checksum is verified again before
  finalization.
- At least one UOB and one linked-partner signatory are required.
- The signatory list is frozen as JSON in the immutable signing record.
- The referenced signed document cannot be deleted after finalization.

## Daily synchronization

The CLI command is a dry run unless `--commit` is supplied:

```powershell
& "C:\xampp\php\php.exe" `
  .\scripts\sync_agreement_operational_statuses.php

& "C:\xampp\php\php.exe" `
  .\scripts\sync_agreement_operational_statuses.php --commit
```

Production should schedule the commit command once each day after midnight in
the deployment timezone. An optional `--as-of=YYYY-MM-DD` argument supports
controlled testing and recovery.

## Persistence

- `agreement_signing_records`: one immutable final signing record per Agreement.
- `agreement_status_events`: append-only activation and expiry history.
- `agreements.activated_at`: first activation timestamp.
- `agreements.expired_at`: first expiry-processing timestamp.
- `audit_logs`: signing creation and each Agreement status update.

The original approved Agreement versions are not rewritten. Renewal and
amendment successors follow the same signing and activation process independently.
