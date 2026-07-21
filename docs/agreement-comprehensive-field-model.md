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
| Arabic title | `agreements.title_ar` | Bilingual forms | Optional while drafting; used by the Arabic public page when present. |
| Cooperation type | `agreements.agreement_type` | Request form, legacy form, CSV | Cooperation Framework, MOU, Cooperation Agreement, Research Agreement, or Other. |
| Partner scope | `agreements.geographic_scope` | Request form | `LOCAL` or `INTERNATIONAL`. |
| Partner organizations | `agreement_partners` | Forms and CSV | Multiple partners are supported. Partner type, country, website, city, logo, and coordinates stay in `partners`. |
| Summary/profile | `agreements.description` | Request form and CSV | Required before submission; approved summary may be public. |
| Start/end dates | `agreements.start_date`, `end_date` | Request, lifecycle, legacy form, CSV | Both required before submission; end cannot precede start. |
| Signing/effective dates | `agreements.signing_date`, `effective_date` | MOU | Optional until known. |
| Renewal controls | `auto_renew`, `renewal_term_months`, `non_renewal_notice_months` | MOU and CSV | Stores whether renewal is automatic and its term/notice period. |
| Termination notice | `termination_notice_months` | MOU | Defaults to six months, matching the template. |
| Responsible unit | `responsible_unit_id` or creator active unit | Request form and legacy owner entity | Applicant identity/unit are trusted system data, not arbitrary client values. |
| Need and justification | `need_justification` | Request form | Required before submission. |
| Objectives | `objectives` | Request, executive program, old CSV | Required before submission. |
| Expected University value/impact | `expected_value` | Request and renewal forms | Required before submission. |
| Focus areas | `focus_areas` | New CSV | Public/reporting categories such as research or training. |
| Fields of cooperation | `collaboration_areas` | MOU Article 1 | Required before submission. |
| Implementation methods | `implementation_methods` | MOU Article 2 | Required before submission. |
| Financial commitment | financial columns on `agreements` | Request form, MOU, lifecycle form | Boolean, amount, ISO currency, and description. Finance review remains a workflow decision. |
| Human-resource commitment | HR columns on `agreements` | Request form | Boolean plus conditional description. |
| Training programs | training columns on `agreements` | Request form | Boolean plus conditional description. |
| Ranking alignment | `agreement_rankings` | Request form and CSV | `QS_WORLD`, `THE_IMPACT`, `UI_GREENMETRIC`. |
| SDG alignment | `agreement_sdgs` | Both CSV files | Normalized SDG numbers 1–17. |
| Monitoring and annual report | `annual_report_required`, `monitoring_plan` | MOU Article 5 | Annual report defaults to required. |
| Confidentiality | `confidentiality_terms` | MOU Article 6 | Stores agreed terms or approved deviations. |
| Intellectual property | `intellectual_property_terms` | MOU Article 8 | Stores approved IP treatment. |
| Legal/regulatory compliance | `compliance_terms` | MOU Article 7 | Preserves national, regional, and international rights and obligations. |
| Relationship disclaimer | `relationship_disclaimer` | MOU Article 8 | Records that the MOU does not itself create a partnership, joint venture, employment, or franchise. |
| Legal effect | `legal_binding_status` | MOU Article 9 | `NON_BINDING`, `BINDING`, or `MIXED`. |
| Amendment terms | `amendment_terms` | MOU Article 10 | Stores the agreed written-amendment mechanism. |
| Dispute resolution | `dispute_resolution_terms` | MOU Article 11 | Stores the agreed settlement mechanism. |
| Other terms | `other_terms` | MOU Article 1/2 catch-all | Stores approved additional fields or implementation methods agreed in writing. |
| Public signing/news URL | `signing_link` | New CSV | May be published only after approval. |
| Legacy source ID | `source_record_id` | New CSV | Import traceability only; not accepted from the normal browser form. |

## Repeating child records

| Record | Table | Fields covered |
| --- | --- | --- |
| Coordinators and signatories | `agreement_contacts` | UOB/partner party, coordinator/signatory role, name, job title, email, phone, optional partner association. Covers MOU Article 4, applicant/signature rows, and partner contacts. |
| Executive programs | `agreement_executive_programs` | Program title, implementing entity, description, objectives, outputs/outcomes, dates, and applicant. Covers the complete proposed executive-program form. |
| Outcome metrics | `agreement_metrics` | Planned value, actual value, and notes for students exchanged, faculty exchanged, and joint programs. Covers the corresponding CSV reporting fields. |

## Partner-owned fields

These values describe the organization and are not duplicated in every Agreement:

- Organization name and type.
- Country, city, address, website, email, and phone.
- Logo URL.
- Latitude and longitude used by the public partnership map.

The comprehensive migration adds city, logo, latitude, and longitude to `partners`. The Agreement form displays active partner data but does not silently rewrite the shared partner profile.

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
- Formal submission now requires partners, geographic scope, duration, description, need/justification, objectives, expected value, collaboration areas, and implementation methods.
- Commitment descriptions become required only when their corresponding flag is enabled.
- Every save snapshots scalar and repeating child data in `agreement_versions.agreement_snapshot`.
- Reviewers and the public catalogue receive only the fields allowed by their existing record-visibility or publication rules.
