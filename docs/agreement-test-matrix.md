# Agreement regression matrix

Run these scenarios against the `seed_dev.sql` users after restarting Apache.

| Scenario | Actor | Expected result |
| --- | --- | --- |
| Create an agreement with a fixture partner | Faculty | `201/200`; agreement, partner link, version 1 snapshot, and `INSERT` audit record exist. |
| Omit title, type, description, or partner | Faculty | `422` with validation errors and no persisted rows. |
| Use a nonexistent partner ID | Faculty | Database rejection and full transaction rollback. |
| Update title and partner | Faculty | Version number increments, snapshot preserves the prior version, and an `UPDATE` audit record exists. |
| Read version 1 after update | Faculty | Returns version 1's original `agreement_snapshot`. |
| Submit | Faculty | Status becomes `UNDER_REVIEW`, a new snapshot/version is created, and an `UPDATE` audit record exists. |
| Add and remove document metadata | Faculty | Metadata row is created then removed; `INSERT` and `DELETE` audit records exist. |
| Delete agreement | Faculty | Agreement, partner links, documents, and versions are removed; `DELETE` audit remains. |
| Attempt delete | Approver | `403` because Agreement Approver lacks `DELETE_AGREEMENT`. |
| Request without a session | Any | `401`. |
| Malformed JSON or invalid IDs | Any | `4xx` response; no database changes. |

## Transaction rollback checks

For create, update, submit, document deletion, and agreement deletion, temporarily force the final audit write to fail in a local test branch. Confirm that the preceding data change is absent after the request. Remove the forced failure immediately after each check.
