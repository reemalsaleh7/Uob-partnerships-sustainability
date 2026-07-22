# Controlled historical Agreement import

## Scope

This phase imports the 41 enriched, administratively approved records in
`uob-agreements/data/agreements.csv` into the normalized PostgreSQL Agreement
model. It does not import `agreementsold.csv`.

The older file remains an archive and field-discovery source because its 25
rows contain synthetic or internally inconsistent title/partner combinations,
three proposed records, and mostly empty summary/outcome data. Importing it
automatically would publish uncertain records. It can be handled later only
after a human data-quality review.

## Safety model

The command is dry-run by default. `--commit` is explicit and uses one database
transaction plus a PostgreSQL advisory lock, so the batch is all-or-nothing and
two imports cannot run concurrently.

The importer also verifies the canonical dataset hash and exact 41-row count.
Line-ending or column-order differences do not matter, but any changed field,
added row, or removed row is refused until the source is reviewed and the
expected dataset identity is intentionally updated.

Before writing, every row is checked against:

1. `agreement_legacy_imports` by source filename, row number, source ID, and
   SHA-256 row hash.
2. The unique Agreement code.
3. `agreements.source_record_id`.
4. A normalized title–partner–start-date fingerprint.
5. Duplicate identities inside the CSV itself.
6. Existing partners by normalized organization name and country.

An exact prior import is skipped. A changed source row or an untracked match is
reported as a conflict; the importer never updates or adopts it automatically.
Any conflict or invalid row blocks the entire commit.

## Field mapping

| CSV field | PostgreSQL destination |
| --- | --- |
| `agreement_code` | `agreements.agreement_code` |
| `source_record_id` | `agreements.source_record_id` and import history |
| `agreement_name` | `agreements.title`; also `title_ar` when Arabic text is present |
| `agreement_type` | `agreements.agreement_type` |
| `agreement_summary` | `agreements.description` |
| `country` | `partners.country`; also derives `geographic_scope` |
| `start_date`, `end_date` | Agreement duration |
| `auto_renew` | `agreements.auto_renew` |
| `owner_entity` | Existing `responsible_unit_id` when resolvable; always retained in immutable import history |
| `focus_area` | `agreements.focus_areas` |
| Valid signing URL | `agreements.signing_link` |
| Partner name/type/city/site/logo/coordinates | `partners` and `agreement_partners` |
| `sdgs` | `agreement_sdgs` |
| QS/GreenMetric support | `agreement_rankings` |
| Student/faculty/joint-program text | `agreement_metrics.notes` |

Two semicolon-delimited multi-partner rows become separate partner records.
Shared coordinates are not guessed between partners. The entire raw CSV row is
stored as JSONB in import history, so no source text is lost.

## Status and workflow treatment

All 41 source rows are marked active and administratively approved, so they are
imported as `ACTIVE` and become visible through the existing public publication
boundary. The six rows whose recorded end dates have passed receive report
warnings; source status is preserved rather than silently changed.

Imported Agreements receive:

- the selected administrator as `created_by`;
- version 1 with a complete immutable snapshot;
- an `INSERT` audit entry naming the CSV row;
- one provenance record with source payload, hash, warnings, and batch ID.

They do not receive workflow instances or approval assignments. Creating a new
workflow would falsely imply that historical decisions were performed in the
new system.

## Commands

Apply the tracking migration first, then preview:

```powershell
& "C:\xampp\php\php.exe" .\scripts\import_legacy_agreements.php `
  --dry-run `
  --creator-email=dev.admin@uob.test `
  --report="$env:TEMP\agreement-import-dry-run.json"
```

The first clean preview should report 41 ready rows, zero conflicts, and zero
invalid rows. Commit only after reviewing that report:

```powershell
& "C:\xampp\php\php.exe" .\scripts\import_legacy_agreements.php `
  --commit `
  --creator-email=dev.admin@uob.test `
  --report="$env:TEMP\agreement-import-commit.json"
```

Run the dry-run command again. It should report all 41 rows as skipped and zero
ready rows, proving idempotency.

## Rollback

The import does not include an automatic delete command. If live review finds a
problem, restore the database backup taken immediately before import. This is
safer than deleting Agreements after public IDs, partner links, versions, and
audit records may have been observed by other users.
