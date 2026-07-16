# Agreement API

All endpoints require an authenticated session and the listed permission.

| Method | Endpoint | Permission | Purpose |
| --- | --- | --- | --- |
| GET | `/agreements` | `VIEW_AGREEMENT` | List agreements. |
| GET | `/agreements/{id}` | `VIEW_AGREEMENT` | Get an agreement. |
| POST | `/agreements` | `CREATE_AGREEMENT` | Create a draft agreement and version 1. |
| PUT | `/agreements/{id}` | `EDIT_AGREEMENT` | Update an agreement and create a snapshot version. |
| POST | `/agreements/{id}/submit` | `SUBMIT_AGREEMENT` | Move the agreement to `UNDER_REVIEW`. |
| DELETE | `/agreements/{id}` | `DELETE_AGREEMENT` | Delete the agreement. |
| GET | `/agreements/{id}/versions` | `VIEW_AGREEMENT` | List versions newest first. |
| GET | `/agreements/{id}/versions/{version}` | `VIEW_AGREEMENT` | Get one immutable agreement snapshot. |
| GET | `/agreements/{id}/documents` | `VIEW_AGREEMENT` | List document metadata. |
| POST | `/agreements/{id}/documents` | `CREATE_AGREEMENT` | Create document metadata. |
| DELETE | `/documents/{id}` | `DELETE_AGREEMENT` | Remove document metadata. |

## Create request

```json
{
  "title": "Research Collaboration MOU",
  "agreement_type": "MOU",
  "description": "Development test agreement",
  "partner_id": 1
}
```

The authenticated user is always used as `created_by` or `updated_by`; clients cannot supply those fields.

## Lifecycle and guarantees

- New agreements begin as `DRAFT`; submitting transitions them to `UNDER_REVIEW`.
- Every create, update, and submit writes a JSON `agreement_snapshot` to `agreement_versions`.
- Agreement creation, updates, submission, agreement deletion, and document metadata changes are transactional with their audit entries.
- Audit actions use the database enum values `INSERT`, `UPDATE`, and `DELETE`.
