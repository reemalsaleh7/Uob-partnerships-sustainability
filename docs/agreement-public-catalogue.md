# PostgreSQL-backed public Agreement catalogue

## Purpose

The public Agreement catalogue now reads from the same PostgreSQL `agreements`
table used by the authenticated workspace. This removes the split where the
approval workflow completed in PostgreSQL but the public catalogue continued
to depend on manually maintained CSV rows.

## Publication rule

Only `APPROVED` and `ACTIVE` Agreements are public. `DRAFT`,
`REVISION_REQUIRED`, `UNDER_REVIEW`, `REJECTED`, `EXPIRED`, and `TERMINATED`
are excluded by the repository query itself. The PHP page never receives those
records, so search, filters, HTML, and direct catalogue links cannot reveal
them.

## Public field allow-list

The query returns only the generated or preserved public reference; bilingual
Agreement title, type, description, dates, renewal flag, objectives, expected
value, focus areas, SDG alignment, approved outcome summaries, and public
status; partner name, type, country, city, website, and optional coordinates;
creator organizational unit; approval date; and ordering timestamps.

It does not return creator identity, email, reviewer identity, workflow
comments, version snapshots, audit records, document metadata, or file storage
information.

## Stable public reference

The catalogue derives a deterministic reference from the database primary key:

```text
UOB-AGR-000123
```

This is a display and public URL identifier. The workspace and API continue to
use numeric `agreement_id` values with their existing authorization checks.

## Compatibility boundary

`readAgreements(true)` is the compatibility boundary used by the public home,
catalogue, details, and map endpoint. It reads the PostgreSQL
`PublicAgreementRepository` first and maps its public allow-list into the
legacy presentation keys still used by those pages.

The CSV reader is a temporary availability fallback for an installation where
the application database cannot be opened. No CSV page can create, approve, or
publish an Agreement, and PostgreSQL remains the authoritative source whenever
it is available.

After Initiative relationships move to `initiative_agreements`, this detail
compatibility lookup and the legacy Agreement CSV can be retired.

## Failure behavior

If PostgreSQL is unavailable, the portal logs the server-side error and uses
only rows marked approved in the existing compatibility CSV. This keeps the
public site usable during a database outage while preserving the CSV approval
filter. The fallback should be removed after every Initiative relationship has
moved to PostgreSQL.

## Public map

The map endpoint uses the same `readAgreements(true)` result, so a newly
`APPROVED` or `ACTIVE` Agreement appears in the catalogue and on the map
together. Exact partner latitude/longitude values are used when available.
Older partner rows without coordinates use a deterministic country centre,
with known locations from the historical catalogue preferred over the built-in
country centre. Records are never omitted merely because precise coordinates
have not yet been curated.

## Acceptance checks

1. Complete a workflow through President approval.
2. Confirm the Agreement appears publicly with a `UOB-AGR-######` reference.
3. Confirm its detail page opens without authentication.
4. Confirm draft, in-review, returned, and rejected Agreements stay absent.
5. Confirm identities, comments, versions, audits, and private documents do not
   appear in page source or public output.
6. Open an existing Initiative with a legacy approved Agreement link and
   confirm the compatibility detail remains readable.
7. Confirm an active Agreement whose partner has a country but no coordinates
   appears on the partnership map at the country centre.

No database migration is required for this phase.

## Historical PostgreSQL population

The later controlled import phase populates this publication query with the 41
approved `agreements.csv` records. They are stored as `ACTIVE` Agreements and
therefore pass the same public allow-list as newly approved records. Import
provenance, raw payloads, source hashes, warnings, audit rows, and versions are
not selected publicly. When no current organizational-unit row matches a
legacy owner label, the public owner display uses that preserved source label.

See `agreement-legacy-csv-import.md` for deduplication, dry-run, and rollback
rules.
