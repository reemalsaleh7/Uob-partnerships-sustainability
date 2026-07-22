# Agreement field annotations and change review

## User experience

Every displayed Agreement field has a **Comment on this field** action. A user
can first select part of the field text to create a text-level anchor, or use
the action without a selection to comment on the complete field. The Agreement
page highlights fields that have open comments and provides a central comment
list with **Show field**, **Resolve**, and author-only **Delete** actions.

Comments have two visibility levels:

- `SHARED`: visible to authenticated users who are currently authorized to
  view the Agreement.
- `PRIVATE`: a personal note returned only to its author. It is not returned to
  another office member or through the administrator UI/API.

Each anchor points to `agreement_version_id`, not only the live Agreement. This
keeps the original context stable after later edits.

## Change awareness

`agreement_user_views` records the last immutable version each user viewed.
When the page opens after a newer version exists, the API compares the saved
version through the latest version, reports only changed fields, and attaches
the reason from the version in which each value changed. Status-only versions,
such as resubmission, do not replace the substantive revision reason.

The edit form now requires the creator to answer **What changed, and why?**.
That value becomes `agreement_versions.change_summary`. The detail page shows:

- version range;
- revision reason;
- old and new value for every changed field;
- a visible highlight on each affected field; and
- manual **View changes** actions in version history.

## Ownership and office visibility

Agreement ownership remains personal: `agreements.created_by` identifies one
original creator. Two users in the same office therefore do **not** own the
same draft Agreements and cannot edit or submit one another's drafts.

Office review work is shared differently. Workflow steps route to an
organizational unit and create active assignments for every eligible office
member. Two authorized users in the same VP, Legal, Finance, or President
office will normally see the same active office-review item. The first valid
decision completes the step and deactivates all of its assignments; history
records the specific acting user.

This separation is deliberate:

- **My Agreements** means `created_by = current user`.
- **Office review inbox** means an active workflow assignment for the user's
  office membership and approval permission.
- **Active University Agreements** are institution-visible records and are not
  owned by every viewer.

## Security boundaries

- Agreement access is checked before annotation access.
- Private content is filtered in SQL using the authenticated user ID.
- A guessed private annotation ID returns not found to non-authors.
- Private comment text is not duplicated into audit JSON.
- Comment anchors use an allow-list of supported Agreement fields.
- Selected text must exist in the current immutable snapshot.
- Comment size is limited to 4,000 characters.
- Revision reasons are required and limited to 1,000 characters.
