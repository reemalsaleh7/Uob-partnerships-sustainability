# Fatema Initiative SQL Integration

Repository reviewed:
`reemalsaleh7/Uob-partnerships-sustainability`

Branch reviewed:
`Fatema`

Input:
`university_partnerships (1).sql` (MariaDB/phpMyAdmin dump)

## What this package updates

- Adds the missing Initiative business fields to PostgreSQL.
- Adds `REVISION_REQUIRED` to Initiative status.
- Adds immutable `initiative_snapshot JSONB` version history.
- Adds Initiative participants.
- Adds Initiative documents/attachments.
- Adds relation notes to the many-to-many Agreement link.
- Keeps the repository's shared workflow engine.
- Does not introduce the legacy MariaDB approval tables.

## Apply to an existing Fatema database

Run the dated migration after the existing repository migrations:

```bash
psql -U postgres -d your_database   -f uob-agreements/data/sql/migrations/20260728_integrate_fatema_initiative_schema.sql
```

## Files to replace/add in the Fatema branch

Replace:
- `uob-agreements/data/sql/types.sql`
- `uob-agreements/data/sql/tables/initiatives.sql`
- `uob-agreements/data/sql/tables/initiative_versions.sql`
- `uob-agreements/data/sql/tables/initiative_agreements.sql`

Add:
- `uob-agreements/data/sql/tables/initiative_participants.sql`
- `uob-agreements/data/sql/tables/initiative_documents.sql`
- `uob-agreements/data/sql/migrations/20260728_integrate_fatema_initiative_schema.sql`
- `uob-agreements/data/sql/all_sql_initiative_extension.sql`

## Important all_sql.sql note

The branch's `all_sql.sql` is a generated consolidated file.
Use the updated modular table files as the source of truth, then
regenerate `all_sql.sql` using the repository's normal SQL build
process. Until it is regenerated, the supplied
`all_sql_initiative_extension.sql` can be executed immediately after
the existing `all_sql.sql`.

## Verification queries

```sql
\d initiatives
\d initiative_versions
\d initiative_participants
\d initiative_documents
\d initiative_agreements

SELECT enumlabel
FROM pg_enum
JOIN pg_type ON pg_type.oid = pg_enum.enumtypid
WHERE typname = 'initiative_status'
ORDER BY enumsortorder;
```

## Application-layer impact

The Initiative repository/service should:
- write `description` from the form's summary field;
- create version 1 in the same transaction as Initiative creation;
- include a complete JSONB snapshot for every version;
- link Agreements only through `initiative_agreements`;
- store participants in `initiative_participants`;
- store attachment metadata in `initiative_documents`;
- use the existing workflow instance/history tables.


## Initiative approval workflow added

Apply this migration after the schema integration migration:

```bash
psql -U postgres -d your_database \
  -f uob-agreements/data/sql/migrations/20260728_add_initiative_approval_workflow.sql
```

It creates/rebuilds the active template:

```text
Creator
-> Department Head
-> Dean
-> Vice President
-> President
```

The Department Head and Dean template steps intentionally contain a
position but no fixed unit. The Initiative approval service must
resolve those units from the creator's Department and parent College.

The VP and President steps reference the active `VP` and `PRES`
organizational units.

Self-approval behavior remains service logic:

- Faculty creator: Department Head -> Dean -> VP -> President.
- Department Head creator: skip Department Head.
- Dean creator: skip Department Head and Dean.

For development accounts, run:

```bash
psql -U postgres -d your_database \
  -f uob-agreements/data/sql/seed/seed_dev_initiative_approvals_extension.sql
```

Then run `VERIFY_INITIATIVE_APPROVALS.sql`.


## Student Initiative creator added

New route:

```text
Student
-> Department Head of the student's specialization
-> Dean of the parent College
-> Vice President
-> President
```

Apply after the Initiative schema and approval migrations:

```bash
psql -U postgres -d your_database \
  -f uob-agreements/data/sql/migrations/20260728_add_student_initiative_creator.sql
```

For the development Student account:

```bash
psql -U postgres -d your_database \
  -f uob-agreements/data/sql/seed/seed_dev_student_initiative_extension.sql
```

The Student's specialization is stored in:

```text
student_profiles.department_id
```

The backend must resolve the College from the Department's
`parent_unit_id`. The frontend must not submit a trusted Department,
College, reviewer, or actor ID.
