
# Agreement regression matrix

Run these scenarios against the development fixtures after applying all migrations and restarting Apache. Service smoke tests execute inside transactions and roll back temporary records. HTTP lifecycle tests persist only the explicitly created development record.

## Development actors

| Actor            | Email                      | Primary responsibility                                                              |
| ---------------- | -------------------------- | ----------------------------------------------------------------------------------- |
| Dean             | `dev.dean@uob.test`      | Create, update, submit, redraft, and resubmit Agreements.                           |
| Vice President   | `dev.vp@uob.test`        | Initial review, Finance selection, mediation, routing, final review, and rejection. |
| Legal reviewer   | `dev.legal@uob.test`     | Legal approval or change request.                                                   |
| Finance reviewer | `dev.finance@uob.test`   | Finance approval or change request when required.                                   |
| President        | `dev.president@uob.test` | Final approval, change request, or rejection.                                       |

Development-only password: `UobDev2026!`.

## CRUD, versions, documents, and audit

| ID   | Scenario                                         | Actor              | Expected result                                                                                                  |
| ---- | ------------------------------------------------ | ------------------ | ---------------------------------------------------------------------------------------------------------------- |
| A-01 | Create an Agreement with a valid fixture partner | Dean               | Agreement begins as`DRAFT`; partner link, version 1 snapshot, and `INSERT` audit record exist.               |
| A-02 | Omit title, type, description, or partner        | Dean               | `422`; no Agreement, version, partner link, or audit record persists.                                          |
| A-03 | Use a nonexistent partner ID                     | Dean               | Database rejection and full transaction rollback.                                                                |
| A-04 | Update title, description, or partner            | Dean               | Agreement updates; version number increments; immutable snapshot and`UPDATE` audit record exist.               |
| A-05 | Read an earlier version after updating           | Authorized viewer  | Original historical`agreement_snapshot` remains unchanged.                                                     |
| A-06 | Submit a`DRAFT` Agreement                      | Eligible creator   | Agreement becomes`UNDER_REVIEW`; submission version and audit record exist; workflow begins at `VP_INITIAL`. |
| A-07 | Submit a non-`DRAFT` Agreement                 | Any creator        | `409`/`422`; no second active workflow is created.                                                           |
| A-08 | Upload a valid PDF, DOC, or DOCX                 | Authorized creator | A random private storage key, MIME type, byte size, SHA-256, version link, and `INSERT` audit record exist.      |
| A-08A | Upload an executable, renamed file, or file over 10 MB | Authorized actor | `422`; no metadata, audit row, or private stored file remains.                                                   |
| A-08B | Download a stored document                       | Authorized viewer  | File is streamed as an attachment after a fresh record-level authorization check; storage path is not disclosed. |
| A-08C | Delete the actor's own manageable document       | Uploader           | Metadata is deleted, private file is removed, and a `DELETE` audit row exists.                                   |
| A-08D | Delete another user's or locked document         | Other/previous actor | `403`; metadata and private file remain unchanged.                                                               |
| A-09 | Delete a permitted Agreement                     | Authorized user    | Agreement and dependent partner/version/document rows are removed; deletion audit remains.                       |
| A-10 | Attempt deletion without permission              | Approver           | `403`; no rows change.                                                                                         |

## Agreement record visibility

| ID   | Scenario                                                   | Actor                | Expected result                                                                                 |
| ---- | ---------------------------------------------------------- | -------------------- | ----------------------------------------------------------------------------------------------- |
| V-01 | Open the Agreement register while another user owns a draft | VP                   | The other user's `DRAFT` Agreement is absent.                                                   |
| V-02 | Change the detail URL to another user's draft ID             | VP                   | `404 Agreement not found`; details and versions are not disclosed.                              |
| V-03 | Open the creator's own draft                                 | Dean                 | The draft, version history, and document metadata remain visible.                               |
| V-04 | Open an Agreement assigned for Initial VP review             | Assigned VP          | The `UNDER_REVIEW` Agreement is visible while the VP assignment is active.                       |
| V-05 | Route to parallel Legal and Finance reviews                  | Legal and Finance    | Both assigned specialists can view the Agreement while their respective steps are active.       |
| V-06 | Complete an assigned review                                  | Former reviewer      | The still-`UNDER_REVIEW` Agreement is no longer visible unless a new active assignment exists.   |
| V-07 | Complete final President approval                            | Any permitted viewer | The `APPROVED` Agreement is visible in the register and by direct URL.                            |
| V-08 | Inspect all Agreement states                                 | System administrator | Every Agreement remains visible for administration and support.                                 |
| V-09 | Load a Dean draft, sign out, then sign in as VP in the same browser | VP              | A fresh authorized response is fetched; the Dean's cached register, details, versions, and documents are not reused. |
| V-10 | Inspect document list response                               | Authorized viewer  | Original name and safe metadata appear; `storage_key`, `file_path`, and absolute path are absent.                    |
| V-11 | Finish a reviewer assignment then reuse its download URL     | Former reviewer    | `404`; the file is not streamed after Agreement visibility ends.                                                     |

## Workflow creation and hierarchy

| ID   | Scenario                                                    | Actor              | Expected result                                                                                                                 |
| ---- | ----------------------------------------------------------- | ------------------ | ------------------------------------------------------------------------------------------------------------------------------- |
| W-01 | Start workflow as Dean                                      | Dean               | Allowed; creator step is`APPROVED`; Initial VP is `IN_PROGRESS`.                                                            |
| W-02 | Start workflow as VP Office member                          | VP                 | Allowed.                                                                                                                        |
| W-03 | Start workflow as President Office member                   | President          | Allowed.                                                                                                                        |
| W-04 | Start workflow as Faculty, Legal, or Finance                | Unauthorized actor | Rejected because the actor is not an eligible Agreement initiator.                                                              |
| W-05 | Resolve active office approvers                             | System             | VP, Legal, Finance, and President users resolve from active positions, roles, and`APPROVE_AGREEMENT`.                         |
| W-06 | Attempt to start a second active workflow for one Agreement | Creator            | Rejected; the original active workflow remains unchanged.                                                                       |
| W-07 | Inspect new workflow steps                                  | System             | Six ordered keys exist:`CREATOR`, `VP_INITIAL`, `LEGAL_REVIEW`, `FINANCE_REVIEW`, `VP_FINAL`, `PRESIDENT_APPROVAL`. |

## Initial VP and specialist reviews

| ID   | Scenario                                        | Actor          | Expected result                                                                                              |
| ---- | ----------------------------------------------- | -------------- | ------------------------------------------------------------------------------------------------------------ |
| W-08 | Initial VP requests Legal and Finance           | VP             | VP step becomes`APPROVED`; both specialist steps become `IN_PROGRESS`; both offices receive assignments. |
| W-09 | Initial VP requests Legal only                  | VP             | Legal becomes`IN_PROGRESS`; Finance becomes `SKIPPED`; Finance receives no active assignment.            |
| W-10 | Unassigned user attempts Initial VP decision    | Other approver | Rejected; workflow state remains unchanged.                                                                  |
| W-11 | Finance approves before Legal                   | Finance        | Finance becomes`APPROVED`; Final VP remains `PENDING`.                                                   |
| W-12 | Legal approves after Finance                    | Legal          | Legal and Finance are both approved; Final VP becomes`IN_PROGRESS` exactly once.                           |
| W-13 | Legal approves when Finance was skipped         | Legal          | Final VP activates immediately.                                                                              |
| W-14 | Complete Finance when Finance was not requested | Finance        | Rejected; Finance remains`SKIPPED`.                                                                        |
| W-15 | Repeat a completed specialist approval          | Specialist     | Rejected; duplicate history and assignments are not created.                                                 |
| W-15A | Open an assigned Finance task in the workspace  | Finance        | Dedicated Finance review page loads the Agreement, assignment, and latest version.                           |
| W-15B | Approve Finance through the workspace           | Finance        | Finance task leaves the inbox; Final VP waits for Legal or activates when every required specialist is done. |
| W-15C | Request Finance changes through the workspace   | Finance        | A nonblank reason is required and the workflow routes to VP mediation.                                       |

## Final approval

| ID   | Scenario                                     | Actor     | Expected result                                                                                                              |
| ---- | -------------------------------------------- | --------- | ---------------------------------------------------------------------------------------------------------------------------- |
| W-16 | Final VP approves after required specialists | VP        | Final VP becomes`APPROVED`; President becomes `IN_PROGRESS`; President Office receives an assignment.                    |
| W-17 | Final VP acts before specialists finish      | VP        | Rejected because Final VP is not active.                                                                                     |
| W-18 | President approves                           | President | President step becomes`APPROVED`; workflow becomes `COMPLETED`; Agreement becomes `APPROVED`; `completed_at` is set. |
| W-19 | Repeat President approval                    | President | Rejected because the workflow is no longer active.                                                                           |
| W-20 | Inspect President inbox after completion     | President | Completed assignment is absent.                                                                                              |

## Specialist and President change requests

| ID   | Scenario                                       | Actor      | Expected result                                                                                          |
| ---- | ---------------------------------------------- | ---------- | -------------------------------------------------------------------------------------------------------- |
| R-01 | Legal requests changes while Finance is active | Legal      | Legal becomes`CHANGES_REQUESTED`; Finance pauses at `PENDING`; VP mediation becomes active.          |
| R-02 | Finance requests changes                       | Finance    | Finance becomes`CHANGES_REQUESTED`; other active specialist work pauses; VP mediation activates.       |
| R-03 | President requests changes                     | President  | President becomes`CHANGES_REQUESTED`; previous Final VP decision is cleared; VP mediation reactivates. |
| R-04 | Submit a blank change-request reason           | Reviewer   | `422`; no workflow state changes.                                                                      |
| R-05 | Unassigned user requests changes               | Other user | Rejected; no assignment or history changes.                                                              |
| R-06 | Inspect change-request history                 | System     | Ordered`CHANGES_REQUESTED` and `ROUTED_TO_VP` records exist with actor and reason.                   |

## VP mediation and routing

| ID   | Scenario                                      | Actor          | Expected result                                                                                                                 |
| ---- | --------------------------------------------- | -------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| R-07 | Route to creator                              | VP             | Creator becomes`IN_PROGRESS`; Agreement becomes `REVISION_REQUIRED`; current version is stored as `redraft_base_version`. |
| R-08 | Route to Legal                                | VP             | Legal is reset and becomes`IN_PROGRESS`; only Legal receives the new active specialist assignment.                            |
| R-09 | Route to Finance                              | VP             | Finance becomes required and`IN_PROGRESS`; only Finance receives the new active specialist assignment.                        |
| R-10 | Reject during mediation                       | VP             | VP mediation step, workflow, and Agreement become`REJECTED`; `completed_at` is recorded.                                    |
| R-11 | Select an unsupported routing destination     | VP             | `422`; no workflow state changes.                                                                                             |
| R-12 | Route when no change request awaits mediation | VP             | Rejected; normal Final VP approval state cannot be misused as mediation.                                                        |
| R-13 | Unassigned user attempts VP routing           | Other approver | Rejected.                                                                                                                       |
| R-14 | Inspect routing history                       | System         | Exactly one corresponding`ROUTED_TO_CREATOR`, `ROUTED_TO_LEGAL`, `ROUTED_TO_FINANCE`, or `REJECTED` action exists.      |

## Creator redraft and resubmission

| ID   | Scenario                                    | Actor            | Expected result                                                                                                      |
| ---- | ------------------------------------------- | ---------------- | -------------------------------------------------------------------------------------------------------------------- |
| R-15 | Resubmit without creating a newer version   | Creator          | Rejected; creator step stays active; baseline remains stored.                                                        |
| R-16 | Update Agreement after creator routing      | Creator          | A newer immutable version is created with change summary and snapshot.                                               |
| R-17 | Resubmit with a newer version               | Original creator | Review cycle increments; creator becomes`APPROVED`; Agreement returns to `UNDER_REVIEW`; Initial VP reactivates. |
| R-18 | Inspect downstream steps after resubmission | System           | Legal, Finance, Final VP, and President reset to`PENDING`; prior history remains.                                  |
| R-19 | Inspect instance after resubmission         | System           | `finance_review_required` and `redraft_base_version` are `NULL`; current step is 2.                            |
| R-20 | Non-creator attempts resubmission           | Other user       | Rejected even if the user has`SUBMIT_AGREEMENT`.                                                                   |
| R-21 | Inspect resubmission history                | System           | `RESUBMITTED` contains the new version and review-cycle number.                                                    |
| R-21A | Open returned Agreement in workspace       | Original creator | `REVISION_REQUIRED` record shows Revise and Resubmit actions; edit form loads existing content.                   |
| R-21B | Try to edit an Agreement under review      | Original creator | UI hides Edit and backend rejects a direct `PUT`; no new version is created.                                       |
| R-21C | Resubmit through Agreement endpoint        | Original creator | Active workflow is resolved by Agreement ID and the same protected new-cycle transition is applied.                |

## Direct VP decisions

| ID   | Scenario                                   | Actor | Expected result                                                                                              |
| ---- | ------------------------------------------ | ----- | ------------------------------------------------------------------------------------------------------------ |
| R-22 | Initial VP returns directly to creator     | VP    | Initial VP becomes`CHANGES_REQUESTED`; creator redraft activates; Agreement becomes `REVISION_REQUIRED`. |
| R-23 | Initial VP rejects                         | VP    | Initial VP, workflow, and Agreement become`REJECTED`.                                                      |
| R-24 | Final VP returns directly to creator       | VP    | Final VP becomes`CHANGES_REQUESTED`; creator redraft activates; version baseline is recorded.              |
| R-25 | Final VP rejects                           | VP    | Final VP, workflow, and Agreement become`REJECTED`.                                                        |
| R-26 | Direct VP decision on a non-active VP step | VP    | Rejected; no state changes.                                                                                  |

## President rejection

| ID   | Scenario                                   | Actor     | Expected result                                                                                       |
| ---- | ------------------------------------------ | --------- | ----------------------------------------------------------------------------------------------------- |
| R-27 | President rejects an active final decision | President | President step, workflow, and Agreement become`REJECTED`; completion time and history are recorded. |
| R-28 | President rejection without a reason       | President | `422`; workflow remains active.                                                                     |
| R-29 | Repeat President rejection                 | President | Rejected because workflow is terminal.                                                                |
| R-30 | Inspect President inbox after rejection    | President | Rejected assignment is absent.                                                                        |

## Assignment and review-cycle integrity

| ID   | Scenario                                        | Expected result                                                                 |
| ---- | ----------------------------------------------- | ------------------------------------------------------------------------------- |
| I-01 | Reactivate a previously completed step          | Prior decision fields and comments are cleared; step receives a new start time. |
| I-02 | Reactivate a prior assignee                     | Earlier assignment remains inactive; exactly one new active assignment exists.  |
| I-03 | Pause a workflow for VP mediation               | All obsolete active assignments are deactivated.                                |
| I-04 | Increment review cycle                          | Value increases by one and stays positive.                                      |
| I-05 | Parallel specialists finish near-simultaneously | Instance/step locks prevent duplicate Final VP activation.                      |
| I-06 | Query inbox                                     | Only active assignments on active workflow steps are returned.                  |
| I-07 | Query ordinary Final VP inbox task              | `task_mode` is `REVIEW`; Legal/Finance results are included.                 |
| I-08 | Query VP task after a specialist change request | `task_mode` is `VP_MEDIATION`; source step and recorded reason are included. |
| I-09 | Query President inbox task                      | Legal, Finance, and approved Final VP status/comments are included.          |

## HTTP routing, sessions, and permissions

| ID   | Scenario                                            | Expected result                                                                                             |
| ---- | --------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| H-01 | Request workflow inbox without a session            | `401 Unauthorized`.                                                                                       |
| H-02 | Call a valid workflow endpoint without a session    | `401 Unauthorized`, proving the dispatcher reached authentication middleware.                             |
| H-03 | Call an invalid workflow route                      | `404` with `Approval route not found`.                                                                  |
| H-04 | Log in with valid development credentials           | `200`; session cookie, identity, roles, permissions, and position are returned.                           |
| H-05 | Call`/me` with saved cookie                       | Authenticated user data is returned.                                                                        |
| H-06 | Use a valid session without the required permission | `403 Forbidden`.                                                                                          |
| H-07 | Run the complete HTTP approval workflow             | Agreement ends`APPROVED`; workflow ends `COMPLETED`; all six steps and ordered history records persist. |
| H-08 | Send malformed JSON                                 | `422` or controlled validation response; no data changes.                                                 |

## Final VP and mediation frontend checks

| ID    | Scenario                                      | Expected result                                                                                  |
| ----- | --------------------------------------------- | ------------------------------------------------------------------------------------------------ |
| UI-01 | Open an assigned ordinary `VP_FINAL` task   | Final VP screen shows Agreement, latest version, Legal result, and Finance result/requirement.   |
| UI-02 | Approve an ordinary Final VP task             | Task leaves VP inbox and `PRESIDENT_APPROVAL` appears in the President inbox.                  |
| UI-03 | Return from ordinary Final VP                 | Reason is required; Agreement becomes `REVISION_REQUIRED` and creator receives redraft work.   |
| UI-04 | Reject from ordinary Final VP                 | Confirmation and reason are required; Agreement and workflow become terminally `REJECTED`.     |
| UI-05 | Open `VP_FINAL` after a specialist request  | Screen displays **VP mediation**, the requesting stage, and its recorded reason.                 |
| UI-06 | Mediate to creator, Legal, or Finance          | Selected destination receives the next controlled task and the VP task leaves the inbox.        |
| UI-07 | Reject during mediation                       | Workflow ends and no active assignment remains.                                                  |
| UI-08 | Open another user's VP task by changing IDs   | Page refuses the action because no matching active inbox assignment belongs to the signed-in VP. |

## President frontend checks

| ID    | Scenario                                      | Expected result                                                                                         |
| ----- | --------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| UI-09 | Open an assigned President task               | Agreement, latest version, Legal, Finance, and Final VP outcomes appear.                                |
| UI-10 | Approve as President                          | Confirmation is required; workflow becomes `COMPLETED`, Agreement becomes `APPROVED`, and task closes. |
| UI-11 | Request changes as President                  | Reason is required; President task closes and VP receives mediation with the recorded reason.           |
| UI-12 | Reject as President                           | Confirmation and reason are required; Agreement and workflow become terminally `REJECTED`.             |
| UI-13 | Open another user's President task by IDs     | Page refuses access because the signed-in user does not own that active assignment.                     |
| UI-14 | Repeat a completed President decision         | No active assignment remains; direct page access and duplicate backend decision are rejected.           |

## Document frontend checks

| ID    | Scenario                                             | Expected result                                                                                       |
| ----- | ---------------------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| UI-15 | Open an editable draft as its creator                | Upload form is visible; document list loads.                                                          |
| UI-16 | Open the same draft as VP by changing its ID         | Agreement and documents return `404`; no metadata is shown.                                          |
| UI-17 | Upload a valid file                                  | File appears with type, linked version, uploader, date, size, Download, and permitted Delete action. |
| UI-18 | Submit the draft and reopen it as creator            | Documents remain downloadable, but creator upload/delete controls are hidden during review.          |
| UI-19 | Open an active Legal, Finance, VP, or President task | Existing documents load and the assigned reviewer can upload a review document.                      |
| UI-20 | Complete that reviewer task                          | Former reviewer can no longer reopen or download the still-private in-review Agreement documents.    |
| UI-21 | Open an approved Agreement                           | Authorized viewers can download documents; upload/delete controls remain locked.                     |

## Verified automated tests

| Test file                                           | Coverage                                                                        |
| --------------------------------------------------- | ------------------------------------------------------------------------------- |
| `tests/WorkflowRepositorySmokeTest.php`           | Template existence, order, mandatory Legal, optional Finance, and office codes. |
| `tests/HierarchyResolverSmokeTest.php`            | Office approvers and eligible Agreement creators.                               |
| `tests/InitialVpDecisionSmokeTest.php`            | Initial VP with Finance.                                                        |
| `tests/InitialVpNoFinanceSmokeTest.php`           | Initial VP without Finance.                                                     |
| `tests/SpecialistReviewSmokeTest.php`             | Parallel specialist completion and Legal-only completion.                       |
| `tests/FinalVpReviewSmokeTest.php`                | Final VP inbox context and activation of President.                             |
| `tests/PresidentApprovalSmokeTest.php`            | Workflow completion and Agreement approval.                                     |
| `tests/ReturnWorkflowRepositorySmokeTest.php`     | Step reset, assignments, and review-cycle support.                              |
| `tests/AgreementChangeRequestSmokeTest.php`       | Legal/President requests and assignment-scoped VP mediation context.            |
| `tests/VpRoutingDecisionSmokeTest.php`            | Creator, Legal, Finance, and rejection routing.                                 |
| `tests/AgreementRedraftResubmissionSmokeTest.php` | Version enforcement and cycle-2 resubmission.                                   |
| `tests/VpDirectDecisionSmokeTest.php`             | Initial and Final VP direct return/rejection.                                   |
| `tests/PresidentRejectionSmokeTest.php`           | Terminal President rejection.                                                   |
| `tests/AgreementDocumentAuthorizationSmokeTest.php` | Creator/reviewer document visibility and active-assignment upload authorization. |

## Transaction rollback checks

For every multi-write operation, force a controlled failure at the final repository call in a temporary local test branch. Confirm that preceding status, assignment, history, version, and audit changes are absent after rollback. Remove the forced failure immediately after each test.

Operations requiring rollback verification include:

- Agreement create, update, submit, and delete.
- Document metadata create/delete and physical-file cleanup on failed database writes.
- Workflow creation.
- Initial VP activation of specialist steps.
- Specialist completion and Final VP activation.
- Final VP activation of President.
- President approval or rejection.
- Change request and VP mediation activation.
- VP routing.
- Creator redraft resubmission.
- Direct VP return or rejection.
