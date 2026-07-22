# Agreement performance monitoring

## Purpose

Agreement approval and activation establish what the University and partner
authorized. Performance reporting records what happened afterward without
editing the approved Agreement, its immutable versions, or the finalized
signing record.

The module provides annual reporting periods, secure report evidence,
period-specific outcome results, executive-program progress, submission and
review history, deadlines, and a management dashboard.

## Reporting cycle

1. `scripts/generate_agreement_reporting_periods.php` finds `ACTIVE` or
   `EXPIRED` Agreements whose `annual_report_required` flag is true.
2. The generator uses the immutable signing record's effective and expiry
   dates when available, then falls back to the Agreement dates for approved
   legacy imports.
3. It opens only the current reporting cycle and sets its deadline to 30 days
   after the period end. A unique database constraint makes reruns idempotent.
4. The Agreement owner or system administrator prepares a draft. Baseline
   Agreement metrics and proposed executive programs are copied into the new
   period so targets remain traceable.
5. Submission requires an executive summary, achievements, actual values for
   every baseline metric, and a secure `ANNUAL_REPORT` document whose SHA-256
   checksum still matches.
6. A user with `REVIEW_AGREEMENT_REPORTS` accepts the report or returns it with
   mandatory comments. The preparer cannot review their own submission.
7. Returned reports become editable and may be resubmitted. Accepted reports
   are immutable through the API and contribute to dashboard outcome totals.

## Storage model

| Table | Responsibility |
| --- | --- |
| `agreement_performance_reports` | Period, deadline, narrative, secure evidence reference, state, submitter, and reviewer decision. |
| `agreement_performance_metric_results` | Period-specific planned/actual values; the original Agreement metric remains unchanged. |
| `agreement_executive_program_updates` | Program status, completion, achievements, outputs, challenges, and next steps for the period. |
| `agreement_performance_report_events` | Append-only submit, accept, and return history. |

All multi-table saves, submissions, decisions, generation writes, audit rows,
and status events share a database transaction.

## Authorization

| Permission | Default role | Scope |
| --- | --- | --- |
| `MANAGE_AGREEMENT_REPORTS` | Agreement Creator, System Administrator | The creator may manage only reports belonging to Agreements they created; administrators may manage all. |
| `REVIEW_AGREEMENT_REPORTS` | Agreement Approver, System Administrator | Review submitted reports. Drafts owned by other users remain private. |
| `VIEW_AGREEMENT_DASHBOARD` | Agreement Approver, System Administrator | View aggregate management reporting. |

Browser visibility is not authorization. Every list, detail, update, submit,
review, and dashboard endpoint performs server-side permission and ownership
checks.

## Dashboard rules

- Operational Agreement counts come from current Agreement status.
- Reporting compliance is filtered by reporting year.
- A report is overdue only while `DRAFT` or `RETURNED` and past its deadline.
- Submitted reports are shown in the management queue but are not counted as
  accepted outcomes.
- Metric and executive-program aggregates use `ACCEPTED` reports only.
- Private narratives, documents, and storage keys are never exposed through
  the aggregate endpoint.

## Scheduled generation

Preview without writes:

```powershell
& "C:\xampp\php\php.exe" `
  .\scripts\generate_agreement_reporting_periods.php
```

Commit due periods:

```powershell
& "C:\xampp\php\php.exe" `
  .\scripts\generate_agreement_reporting_periods.php --commit
```

Task Scheduler may run the commit command daily. Repeated runs do not create a
second row for the same Agreement and period.

## Deliberate boundaries

- This phase does not change Agreement approval or lifecycle-request routes.
- It does not update the original `agreement_metrics.actual_value` column;
  periodic results are preserved separately.
- It does not expose reporting evidence publicly.
- It does not modify Initiative files or create Initiative reporting records.
