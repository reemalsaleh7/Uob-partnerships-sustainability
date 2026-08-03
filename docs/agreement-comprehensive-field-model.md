# Comprehensive Agreement field model

## Purpose

This model consolidates the four official University of Bahrain cooperation forms, the retired internal Agreement form, and both legacy Agreement CSV schemas. It keeps the Agreement record, partner profile, workflow history, repeating contacts/programs, outcome metrics, and later lifecycle requests separate so approvals and immutable versions remain reliable.

## Source forms reviewed

1. Cooperation Project Request Form (`نموذج طلب إبرام مشروع تعاون`).
2. Proposed Executive Program for a Cooperation Project (`نموذج برنامج تنفيذي مقترح لمشروع تعاون`).
3. Memorandum of Understanding template (`نموذج مذكرات التفاهم`).
4. Amendment / Renewal / Termination Request Form (`نموذج طلب تعديل أو تجديد أو إنهاء مشروع تعاون`).
5. Legacy internal add/edit Agreement form.
6. `agreements.csv` and `agreementsold.csv`.

## Agreement fields

| Field or group | Database destination | Source | Rule |
| --- | --- | --- | --- |
| Agreement code | `agreements.agreement_code` | Both CSV files | Nullable unique legacy/import reference. New records continue to receive a generated public reference when no stored code exists. |
| English/general title | `agreements.title` | All sources | Required. |
| Arabic title | `agreements.title_ar` | Bilingual forms | Required by the guided form and before submission; used by the Arabic public page. |
| Cooperation type | `agreements.agreement_type` | Request form, legacy form, CSV | Cooperation Framework, MOU, Cooperation Agreement, Research Agreement, or Other. |
| Partner scope | `agreements.geographic_scope` | Request form | `LOCAL` or `INTERNATIONAL`; derived by the server from selected partner countries rather than entered separately. All-Bahrain partners produce `LOCAL`; any non-Bahrain partner produces `INTERNATIONAL`. |
| Partner organization | `agreement_partners` | Forms and CSV | Exactly one partner is allowed for a current Agreement. Partner type, country, website, brief profile, city, logo, and coordinates stay in `partners`. Website is mandatory for new and selected partner records. New and edited profiles use Public/government, Private, Academic, or Non-profit as the four controlled organization types. |
| Summary/profile | `agreements.description` | Request form and CSV | Required before submission; approved summary may be public. |
| Start/end dates | `agreements.start_date`, `end_date` | Request, lifecycle, legacy form, CSV | Both required before submission; end cannot precede start. They remain separate values but either visible date box opens one shared duration calendar and updates both values. Programme and renewal durations use the same interaction. |
| Signing/effective dates | `agreements.signing_date`, `effective_date` | Final signing | Not collected on the creation form or required before submission. They are recorded after approval through the final-signing operation, where they control scheduled activation and operational status. |
| Fixed and renewal terms | `auto_renew`, `fixed_term_months`, `renewal_term_months`, `non_renewal_notice_months` | MOU and CSV | A non-automatically-renewing Agreement requires its fixed term in months. An automatically renewing Agreement instead records each renewal term and the non-renewal notice period. |
| Termination notice | `termination_notice_months` | MOU / legacy data | Retained for historical compatibility but no longer collected on the creation form. A proposal to terminate an Agreement must use the separate termination lifecycle request. |
| Responsible unit | `responsible_unit_id` or creator active unit | Request form and legacy owner entity | Applicant identity/unit are trusted system data, not arbitrary client values. |
| Need and justification | `need_justification` | Request form | Required before submission. |
| Objectives | `objectives` | Request, executive program, old CSV | Required before submission. |
| Expected University value/impact | `expected_value` | Request and renewal forms | Required before submission. |
| Focus areas | `focus_areas` | New CSV | Public/reporting categories such as research or training. |
| Fields of cooperation | `collaboration_areas` | MOU Article 1 | Optional extracted text. The creator reviews or corrects it when the uploaded DOCX exposes a distinct Article 1. |
| Implementation methods | `implementation_methods` | MOU Article 2 | Optional extracted text. Article-aware extraction keeps it separate from Article 1. |
| Financial commitment | financial columns on `agreements` | Request form, MOU, lifecycle form | Boolean, amount, ISO currency, and description. Finance review remains a workflow decision. |
| Human-resource commitment | HR columns on `agreements` | Request form | Boolean plus conditional description. |
| Training programs | training columns on `agreements` | Request form | Boolean plus conditional description. |
| Ranking alignment | `agreement_rankings` | Historical request form and CSV | Legacy/import compatibility only. QS World, THE Impact, and UI GreenMetric are no longer offered by the guided form. |
| SDG alignment | `agreement_sdgs` | Both CSV files | Normalized SDG numbers 1–17. |
| Monitoring and annual report | `annual_report_required`, `monitoring_plan` | MOU Article 5 | Annual report defaults to required. |
| Confidentiality | `confidentiality_terms` | MOU Article 6 | Stores agreed terms or approved deviations. |
| Intellectual property | `intellectual_property_terms` | MOU Article 8 | Stores approved IP treatment. |
| Legal/regulatory compliance | `compliance_terms` | MOU Article 7 | Preserves national, regional, and international rights and obligations. |
| Relationship disclaimer | `relationship_disclaimer` | MOU Article 8 | Records that the MOU does not itself create a partnership, joint venture, employment, or franchise. |
| Legal effect | `legal_binding_status` | MOU Article 9 / legacy data | Retained for existing records and imports but not selected by Agreement creators. Every new request must provide the MOU/governance document and passes through mandatory Legal Office review. |
| Amendment terms | `amendment_terms` | MOU Article 10 | Stores the agreed written-amendment mechanism. |
| Dispute resolution | `dispute_resolution_terms` | MOU Article 11 | Stores the agreed settlement mechanism. |
| Other terms | `other_terms` | MOU Article 1/2 catch-all | Stores approved additional fields or implementation methods agreed in writing. |
| Public signing/news URL | `signing_link` | New CSV | Legacy/import field only. It is no longer collected by the guided form because each reusable partner profile already provides its website. |
| Legacy source ID | `source_record_id` | New CSV | Import traceability only; not accepted from the normal browser form. |

## Repeating child records

| Record | Table | Fields covered |
| --- | --- | --- |
| Coordinators and signatories | `agreement_contacts` | UOB/partner party, coordinator/signatory role, name, job title, email, and phone. All four fields are mandatory for the UOB coordinator, partner coordinator, UOB signatory, and partner signatory. |
| Executive programs | `agreement_executive_programs` | One or more programs, each with title, responsible implementing entity, description, objectives, outputs/outcomes, and dates. Applicant name is not requested again because the Agreement creator is already trusted system data. |
| Outcome metrics | `agreement_metrics` | Planned value, actual value, and notes for students exchanged, students trained, faculty exchanged, and joint programs. Every displayed metric field is mandatory. |

## Partner-owned fields

These values describe the organization and are not duplicated in every Agreement:

- Organization name and type.
- Country, city, address, website, brief organization profile, email, and phone.
- Logo URL.
- Latitude and longitude used by the public partnership map.

The comprehensive migration adds city, logo, latitude, and longitude to
`partners`; the guided-form migration adds the reusable brief profile. The
Agreement form searches the active University partner directory and displays
the selected organization as a removable card with its country, website, and
profile. Each Agreement has exactly one partner, and selecting another directory
result replaces the previous selection. A creator may add a missing partner or
explicitly edit an existing selected profile. Both actions are validated and
audited because a directory profile is shared by future Agreements. A matching
existing name/country is selected instead of duplicated. Partner country and one
of the four current organization types are required. Legacy type labels are
normalized to Public/government, Private, Academic, or Non-profit at the
repository boundary. The server derives local or international scope, which is
shown beside the selected partner rather than as a separate user decision.
Directory results remain hidden until the creator types a search. When a
partner is missing, the add-partner panel can query a public Wikidata record and
suggest type, country, official website, and brief profile. Only empty fields
are populated, the user must review the result, and nothing is saved until the
normal audited partner action is used. A follow-up migration normalizes legacy
types and adds reviewed or clearly marked provisional profiles to partners
already linked to Agreements.
Before accepting the selection, the form checks Agreement history for that
partner. A draft, returned, under-review, approved, or active Agreement blocks a
second Agreement. Approved/active matches direct the creator to a preselected
amendment lifecycle request; the creator's own unfinished draft links back to
that draft. An expired Agreement does not block creation: the form shows a
notice, links to the historical record, and can copy its reusable content into
empty fields while leaving new dates and existing user input untouched. The
same uniqueness rule is enforced by the Agreement service so it cannot be
bypassed with a direct API request. Create/update transactions also take a
partner-scoped PostgreSQL advisory lock and repeat the uniqueness check, closing
the race where two requests try to create a current Agreement simultaneously.

## Guided form behavior

- Ten sections render as accessible disclosure panels. Only the first opens
  initially; required sections advance when complete, while Open all and
  Collapse all remain available.
- The former percentage bar is a clickable ten-step timeline. Each step shows
  complete, current, or needs-attention state and opens and scrolls to its
  corresponding form section. No step begins complete. Optional sections such
  as Resources, SDGs, and Supporting media become complete after the creator
  explicitly opens and reviews them. Required incomplete sections turn red
  only after they are visited, started, or included in a failed validation.
- Collapsed required sections retain a visible Complete, Not started, or Needs
  attention status. The first invalid section reopens on save. Field feedback
  uses a single invalid state and does not also paint valid fields green.
- Project duration displays separate start and end fields with only “to”
  between them. Clicking either field opens the same range calendar and updates
  both values. Each executive programme uses the same one-calendar range
  behavior while persisting its start and end values separately.
- Project start means planned activity delivery. Signing and effective dates
  are deliberately deferred until the approved instrument is finalized; the
  effective date recorded there controls scheduled activation.
- Automatic-renewal fields appear only when enabled. Non-renewal notice prevents
  the next automatic term. When automatic renewal is disabled, a fixed Agreement
  term in months is required and can be prefilled from the selected date range.
  Early termination is not initiated on this form; it requires the dedicated
  termination lifecycle request.
- University-ranking controls have been removed. Historical QS World, THE
  Impact, and UI GreenMetric values remain readable for compatibility but are
  not modified by guided-form saves.
- Every SDG displays its official short title and an explanatory hover/focus
  hint.
- A governance/MOU DOCX file is mandatory for a new Agreement. Choosing it
  immediately starts automatic language-aware extraction. Article 1 fields of
  cooperation, Article 2 implementation methods, governance clauses,
  coordinators, and signatories are copied only into empty fields and remain
  subject to creator review. Numbered-article parsing prevents Article 2 from
  being grouped into Article 1. The clause text fields are optional; the DOCX
  itself and the complete coordinator/signatory records remain mandatory.
  Submission and resubmission are rejected by the server if no
  `GOVERNANCE_CLAUSES` document is attached.
- Legal effect is not a creator-entered choice. The mandatory MOU file and
  mandatory Legal Office workflow step provide the legal-review boundary.
- Executive-programme suggestions cover the programme title, responsible
  implementing entity, description, objectives, expected outputs, start date,
  and end date. The responsible entity is selected from a dropdown containing
  the creator's University context, the selected partner, and a joint option.
  Applicant name is not repeated. At least one complete programme is required, and
  creators can add or remove further programme cards. A local preview shows
  every suggestion, populated programme fields are never overwritten, the
  suggestions apply to empty fields across all programme cards, and applying
  them does not move focus to the beginning of the form.
- Governance files and optional JPG, PNG, WebP, or MP4 supporting media are
  queued until the Agreement draft/version has been saved, then use the existing
  private document store, checksum, version link, access controls, and audit
  trail. Supporting media is presented as its own final section, separate from
  planned outcomes.

## System-derived workflow fields

These fields from the paper form or CSV are already generated from trusted application state:

- Submission date and applicant identity: authenticated user and server timestamp.
- Organizational unit: the authenticated creator's active position/unit, or the stored responsible unit.
- Draft/review/approved/active/rejected status: Agreement workflow and status enum.
- Administrative approval status: completed workflow, not a second manually entered status.
- VP/Legal/Finance/President notes: workflow history and step comments.
- Submitted-by email: authenticated user record.
- Created/updated timestamps: database timestamps.

## Amendment, renewal, and termination

The official lifecycle form does not become a set of editable columns on the approved Agreement. It is represented by `agreement_lifecycle_requests`, linked to the original Agreement:

- Request type: renewal, amendment, or termination.
- Justification, implemented initiatives/activities, and achieved University value.
- Proposed renewal dates and financial commitment.
- Amendment type, reason, and terms to amend.
- Termination reason, proposed date, and whether prior initiatives exist.
- Requester, status, and timestamps.

The existing `agreement_relationships`, `agreement_actions`, workflow engine, versions, and audit records remain the authoritative approval/history layer. Lifecycle request API screens are a later slice; the schema is included now so those official fields are not lost or forced into the base create form.

## Validation and versioning

- Draft creation remains backward compatible with the former four-field API.
- Formal submission requires an Arabic name; exactly one partner with a complete country; server-derived geographic scope; project start/end dates; description; need/justification; objectives; expected value; four complete coordinator/signatory records; at least one complete executive programme; every planned-outcome field; and an attached governance/MOU DOCX document. Signing and effective dates are captured later during final signing. Extracted Article 1/2 and other clause text remains optional.
- Commitment descriptions become required only when their corresponding flag is enabled.
- Every save snapshots scalar and repeating child data in `agreement_versions.agreement_snapshot`.
- Reviewers and the public catalogue receive only the fields allowed by their existing record-visibility or publication rules.

## Historical import boundary

The enriched 41-row `agreements.csv` dataset is imported by the controlled CLI
workflow documented in `agreement-legacy-csv-import.md`. Each imported record
receives provenance, a source hash, an immutable version, and an audit entry.
The archived `agreementsold.csv` remains excluded pending manual data-quality
review; using its schema during field design did not make its uncertain records
safe to publish.
