# Agreement user-acceptance testing and release plan

## Purpose

This plan turns the passing automated Agreement acceptance suite into a
controlled business acceptance cycle. It verifies that real users can complete
the Agreement journeys through the browser, that each role sees only its own
authorized work, and that the release can be approved with traceable evidence.

The accompanying `agreement-uat-tracker.xlsx` is the execution record. Testers
must record an actual result, status, tester, date, and evidence reference for
every assigned case. Defects are recorded on the workbook's **Defects** sheet
and linked back to the affected UAT case.

## Scope

Included:

- Authentication, tab-session isolation, and role-based access.
- Agreement creation, validation, version history, documents, and submission.
- Initial VP, Legal, optional Finance, Final VP, and President decisions.
- Change requests, VP mediation, creator redraft, resubmission, and rejection.
- Public catalogue visibility and private-data exclusion.
- Renewal, amendment, and termination requests and successor lineage.
- Final signing, scheduled/immediate activation, and expiry processing.
- Annual performance reporting, review, deadlines, and management aggregates.
- Deployment readiness, backup, rollback, evidence, and release sign-off.

Explicitly excluded:

- Initiative creation, approval, data, routes, and screens.
- Notification delivery, reminders, escalation, email, SMS, or messaging.
- Removal of the temporary legacy Agreement compatibility boundary. It remains
  until the Initiative relationship migration is complete.

## Entry criteria

UAT may begin only when all of the following are true:

1. The full Agreement acceptance suite reports **28 passed and 0 failed** on
   the exact Git commit selected for UAT.
2. `AgreementReleaseReadinessSmokeTest.php` passes.
3. A dedicated UAT database and private-document directory are available.
4. The UAT database is backed up before test execution.
5. VP, Legal, Finance, and President test users each have an active position
   and the required approval/rejection permissions.
6. The Agreement creator, approvers, administrator, and business witnesses are
   assigned in the workbook.
7. Browser cache is cleared with `Ctrl+Shift+R` after deployment.

Development fixtures may be loaded only into an isolated development or UAT
database. Never run `seed_dev.sql` against production.

## Test roles

| Role | UAT responsibility |
| --- | --- |
| Agreement creator / Dean | Create, update, upload, submit, redraft, sign, and report. |
| Vice President | Initial routing, optional Finance selection, mediation, and final recommendation. |
| Legal reviewer | Legal approval or reasoned request for changes. |
| Finance reviewer | Financial approval or reasoned request for changes when selected. |
| President Office | Final approval, change request, or rejection. |
| System administrator | Readiness, support, controlled recovery, and cross-role verification. |
| Agreement process owner | Witness expected business behavior and approve the result. |
| Technical lead | Verify evidence, defects, deployment readiness, and rollback readiness. |

The same person must not prepare and approve the same performance report. UAT
should use separate browser profiles or isolated tabs with the application's
tab-session header so identities cannot leak between roles.

## Execution waves

### Wave 1: access and smoke

Run login, session isolation, Agreement register visibility, retired-route
redirects, and public catalogue checks. Stop if there is any cross-user data
exposure or authentication bypass.

### Wave 2: primary approval journey

Create one complete Agreement, upload evidence, submit it, include both Legal
and Finance, complete both specialist reviews, obtain Final VP recommendation,
and obtain President approval. Capture the Agreement ID and public reference.

### Wave 3: correction and terminal paths

Use separate Agreements to test Legal or President change requests, VP
mediation, creator redraft and versioned resubmission, direct rejection, and
duplicate-decision prevention. Do not reuse the successful Wave 2 record for a
terminal rejection test.

### Wave 4: lifecycle and operation

On an approved/active Agreement, test renewal, amendment, and termination as
separate records. Confirm successors are created only for approved renewal and
amendment requests. Finalize a signing record and verify immediate or scheduled
activation and date-driven expiry.

### Wave 5: performance and management

Generate a reporting period, prepare and submit a complete report with secure
evidence, return it with comments, resubmit, accept it using a different user,
and verify that only accepted outcomes contribute to the dashboard.

### Wave 6: security and release rehearsal

Verify document authorization after reviewer handoff, direct-ID protections,
API request boundaries, no private fields in public responses, backup and
restore availability, and the deployment/rollback runbook.

## Evidence requirements

Each UAT case must reference evidence that contains:

- Test case ID and date/time.
- Git commit tested.
- Actor/role used, without recording a password.
- Agreement, workflow, lifecycle request, or report ID where applicable.
- Screenshot, exported console output, or database verification query result.
- Actual result and final status.

Store evidence in a restricted team location. Never place database passwords,
session cookies, private documents, storage keys, or personal information in
screenshots or the Git repository.

## Defect severity

| Severity | Definition | Release treatment |
| --- | --- | --- |
| Critical | Data loss/corruption, authorization bypass, private-data exposure, or core workflow cannot complete. | Stop testing; release is blocked. |
| High | Major required journey fails with no safe workaround. | Release is blocked. |
| Medium | Required behavior is impaired but a safe documented workaround exists. | Fix or obtain written deferral approval. |
| Low | Cosmetic or minor usability issue with no workflow, security, or data-integrity impact. | May be deferred with owner and target date. |

Every fix requires the failed case to be retested and the full automated
Agreement suite to pass again on the new commit.

## Exit criteria

The release may be signed off only when:

1. Every UAT case marked P0 or P1 has passed.
2. No Critical or High defect remains open.
3. Any deferred Medium or Low defect has an owner, target date, workaround,
   and written business approval.
4. The full automated suite still reports 28 passed and 0 failed on the release
   candidate commit.
5. The release checklist is complete, including backup and rollback rehearsal.
6. Agreement process, VP, Legal, Finance, President Office, and technical
   representatives have recorded their decisions in the workbook.
7. The final release decision is **Approved** or **Approved with conditions**;
   all conditions are explicitly recorded.

## Change control during UAT

- Freeze Agreement feature work when UAT starts.
- Fix only documented defects on a dedicated release branch.
- Re-run the affected case and the full automated suite after every fix.
- Record the new commit in the workbook and invalidate evidence from an older
  commit when the changed behavior affects that case.
- Do not merge Initiative or notification changes into the Agreement release
  candidate unless the project owner starts a separate integration cycle.

## Final records

Keep the completed workbook, automated-suite output, release commit/tag,
database backup identifier, deployment log, post-release verification, and
signed release decision together as the permanent Agreement release record.

