-- Development/showcase data only. This migration is idempotent and uses
-- DEMO-* Agreement codes so it cannot collide with ordinary records.

-- Windows psql may inherit WIN1252 from the console. Force UTF-8 before the
-- Arabic showcase titles are parsed by PostgreSQL.
SET client_encoding = 'UTF8';

BEGIN;

-- Faculty and Department Heads may discover active University Agreements and
-- use one as the partnership context for an Initiative request.
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r
JOIN permissions p ON p.permission_code = 'VIEW_AGREEMENT'
WHERE r.role_name = 'Initiative Creator'
ON CONFLICT DO NOTHING;

INSERT INTO partners (
    organization_name, partner_type, country, city, website, email, is_active
)
SELECT *
FROM (VALUES
    ('Bahrain Digital Innovation Hub', 'Innovation Hub', 'Bahrain', 'Manama', 'https://example.test/digital-innovation', 'partnerships@digital-innovation.example', TRUE),
    ('Gulf Centre for Public Health', 'Research Centre', 'Bahrain', 'Manama', 'https://example.test/public-health', 'collaboration@public-health.example', TRUE),
    ('Regional Academic Exchange Network', 'Academic Network', 'Bahrain', 'Manama', 'https://example.test/exchange', 'mobility@exchange.example', TRUE),
    ('Arabian Renewable Energy Institute', 'Research Institute', 'Bahrain', 'Sakhir', 'https://example.test/renewable-energy', 'research@renewable-energy.example', TRUE),
    ('Bahrain Cloud Skills Academy', 'Training Provider', 'Bahrain', 'Manama', 'https://example.test/cloud-skills', 'programmes@cloud-skills.example', TRUE),
    ('Coastal and Marine Research Centre', 'Research Centre', 'Bahrain', 'Muharraq', 'https://example.test/marine-research', 'projects@marine-research.example', TRUE)
) AS v(organization_name, partner_type, country, city, website, email, is_active)
WHERE NOT EXISTS (
    SELECT 1
    FROM partners p
    WHERE p.organization_name = v.organization_name
);

WITH showcase (
    agreement_code, title, title_ar, agreement_type, description,
    geographic_scope, start_date, end_date, effective_date, signing_date,
    owner_email, responsible_unit_code, expected_value, objectives,
    focus_areas, collaboration_areas, implementation_methods,
    financial_commitments, financial_amount, training_programs,
    training_description, monitoring_plan, legal_binding_status,
    status, activated_at, created_at
) AS (
    VALUES
        (
            'DEMO-AI-2024',
            'Applied AI Talent and Research Partnership',
            'شراكة المواهب والبحوث في الذكاء الاصطناعي التطبيقي',
            'STRATEGIC_PARTNERSHIP',
            'A three-year collaboration connecting University research, student projects, specialist mentoring, and applied artificial-intelligence challenges.',
            'LOCAL', '2024-09-01', '2027-08-31', '2024-09-01', '2024-08-22',
            'dev.dean@uob.test', 'CIT',
            'Industry-facing research experience, graduate employability, and reusable AI prototypes.',
            'Establish a shared applied-AI lab; supervise student projects; publish joint applied research.',
            'Artificial intelligence, data science, responsible innovation',
            'Research, student placements, faculty exchange, professional seminars',
            'Joint steering group, semester delivery plan, quarterly outcome review',
            TRUE, 48000.00, TRUE,
            'Four specialist seminars and two project-based learning cycles each year.',
            'Quarterly programme review and an accepted annual performance report.',
            'MIXED', 'ACTIVE', '2024-09-01 09:00:00', '2024-08-01 09:00:00'
        ),
        (
            'DEMO-HEALTH-2023',
            'Digital Health Innovation and Training Agreement',
            'اتفاقية الابتكار والتدريب في الصحة الرقمية',
            'COOPERATION_AGREEMENT',
            'A multidisciplinary digital-health programme covering privacy-aware analytics, capstone supervision, and innovation challenges.',
            'LOCAL', '2023-01-01', '2027-12-31', '2023-01-01', '2022-12-12',
            'dev.vp@uob.test', 'UOB',
            'Cross-college learning, research translation, and stronger digital-health capability.',
            'Train students and faculty; deliver joint pilots; create a governed health-data collaboration model.',
            'Digital health, analytics, privacy, multidisciplinary education',
            'Training, capstone projects, research pilots, public engagement',
            'Annual programme calendar, ethics review, partner-led workshops',
            TRUE, 62000.00, TRUE,
            'Digital-health bootcamp and clinical analytics workshops.',
            'Semester checkpoints with annual outcomes and ethics-compliance review.',
            'BINDING', 'ACTIVE', '2023-01-01 09:00:00', '2022-11-10 09:00:00'
        ),
        (
            'DEMO-EXCHANGE-2022',
            'Student and Faculty Exchange Programme',
            'برنامج تبادل الطلبة وأعضاء هيئة التدريس',
            'MEMORANDUM_OF_UNDERSTANDING',
            'An academic-mobility framework supporting semester exchanges, visiting faculty, and joint learning activities.',
            'INTERNATIONAL', '2022-09-01', '2027-08-31', '2022-09-01', '2022-07-20',
            'dev.dean@uob.test', 'CIT',
            'International learning opportunities and sustained academic collaboration.',
            'Increase student mobility; support faculty exchange; align credit-recognition processes.',
            'Student mobility, faculty development, internationalisation',
            'Semester exchange, visiting faculty week, joint seminars',
            'Annual mobility plan, nomination timetable, academic advising',
            FALSE, NULL, TRUE,
            'Pre-departure preparation, advising, and visiting-faculty activities.',
            'Mobility totals and participant feedback reviewed each reporting period.',
            'NON_BINDING', 'ACTIVE', '2022-09-01 09:00:00', '2022-06-15 09:00:00'
        ),
        (
            'DEMO-ENERGY-2025',
            'Renewable Energy Research Collaboration',
            'تعاون بحثي في الطاقة المتجددة',
            'RESEARCH_AGREEMENT',
            'A research collaboration for solar-performance measurement, shared datasets, student research exchanges, and joint publications.',
            'LOCAL', '2025-07-01', '2028-06-30', '2025-07-01', '2025-06-18',
            'dev.president@uob.test', 'UOB',
            'Locally relevant renewable-energy evidence and research capacity.',
            'Create a shared measurement dataset; publish joint research; train student researchers.',
            'Renewable energy, climate resilience, engineering research',
            'Field research, publications, student and faculty exchange',
            'Shared technical protocol, research work packages, annual review',
            TRUE, 95000.00, TRUE,
            'Research-method workshops and field instrumentation training.',
            'Quarterly technical review plus annual metric and programme reporting.',
            'BINDING', 'ACTIVE', '2025-07-01 09:00:00', '2025-05-01 09:00:00'
        ),
        (
            'DEMO-CLOUD-2025',
            'Industry-Ready Cloud Skills Programme',
            'برنامج مهارات الحوسبة السحابية للجاهزية المهنية',
            'TRAINING_AGREEMENT',
            'A skills partnership delivering cloud laboratories, certification preparation, faculty enablement, and employer-facing student projects.',
            'LOCAL', '2025-01-01', '2027-12-31', '2025-01-01', '2024-12-10',
            'dev.dean@uob.test', 'CIT',
            'Improved graduate readiness and modern cloud-teaching capability.',
            'Train students; enable faculty; deliver industry-assessed capstone projects.',
            'Cloud computing, cybersecurity, employability',
            'Training laboratories, certification preparation, capstone review',
            'Cohort-based delivery with semester outcome checkpoints',
            TRUE, 35000.00, TRUE,
            'Two student cohorts and one faculty enablement programme each year.',
            'Cohort results, certification attempts, and project outcomes reported annually.',
            'MIXED', 'ACTIVE', '2025-01-01 09:00:00', '2024-11-15 09:00:00'
        ),
        (
            'DEMO-MARINE-2026',
            'Marine Data and Coastal Resilience Partnership',
            'شراكة البيانات البحرية والمرونة الساحلية',
            'RESEARCH_AGREEMENT',
            'An approved partnership preparing shared marine-data collection, student fieldwork, and coastal-resilience research.',
            'LOCAL', '2026-09-01', '2029-08-31', '2026-09-01', NULL,
            'dev.vp@uob.test', 'UOB',
            'Shared marine datasets, field learning, and evidence for coastal planning.',
            'Coordinate data collection; engage students; publish annual coastal-resilience findings.',
            'Marine science, data systems, climate adaptation',
            'Fieldwork, data exchange, student research, stakeholder workshops',
            'Joint technical committee and annual research programme',
            TRUE, 72000.00, TRUE,
            'Field safety, marine sensing, and data-quality training.',
            'Programme opens after signing and reports annually after activation.',
            'BINDING', 'APPROVED', NULL, '2026-06-20 09:00:00'
        )
)
INSERT INTO agreements (
    agreement_code, title, title_ar, agreement_type, description,
    geographic_scope, start_date, end_date, effective_date, signing_date,
    created_by, responsible_unit_id, need_justification, expected_value,
    objectives, focus_areas, collaboration_areas, implementation_methods,
    financial_commitments, financial_amount, financial_currency,
    financial_description, human_resources_commitments,
    human_resources_description, training_programs,
    training_programs_description, annual_report_required, monitoring_plan,
    confidentiality_terms, intellectual_property_terms, compliance_terms,
    legal_binding_status, status, activated_at, created_at, updated_at
)
SELECT
    s.agreement_code, s.title, s.title_ar, s.agreement_type, s.description,
    s.geographic_scope, s.start_date::date, s.end_date::date,
    s.effective_date::date, s.signing_date::date,
    u.user_id, ou.unit_id,
    'Showcase Agreement demonstrating the complete partnership lifecycle.',
    s.expected_value, s.objectives, s.focus_areas, s.collaboration_areas,
    s.implementation_methods, s.financial_commitments, s.financial_amount,
    'BHD',
    CASE WHEN s.financial_commitments
        THEN 'Budget covers agreed programme delivery, shared resources, and evaluation.'
        ELSE NULL END,
    TRUE,
    'Academic and administrative staff contribute through the agreed programme plan.',
    s.training_programs, s.training_description, TRUE, s.monitoring_plan,
    'Confidential information is handled under University policy and applicable law.',
    'Background intellectual property remains with its owner; joint outputs follow the approved project plan.',
    'Activities must comply with University policy, applicable law, ethics, privacy, and safety requirements.',
    s.legal_binding_status, s.status::agreement_status,
    s.activated_at::timestamp, s.created_at::timestamp, NOW()
FROM showcase s
JOIN users u ON u.email = s.owner_email
JOIN organizational_units ou ON ou.code = s.responsible_unit_code
ON CONFLICT (agreement_code) WHERE agreement_code IS NOT NULL DO UPDATE
SET title = EXCLUDED.title,
    title_ar = EXCLUDED.title_ar,
    description = EXCLUDED.description,
    agreement_type = EXCLUDED.agreement_type,
    geographic_scope = EXCLUDED.geographic_scope,
    start_date = EXCLUDED.start_date,
    end_date = EXCLUDED.end_date,
    effective_date = EXCLUDED.effective_date,
    signing_date = EXCLUDED.signing_date,
    created_by = EXCLUDED.created_by,
    responsible_unit_id = EXCLUDED.responsible_unit_id,
    expected_value = EXCLUDED.expected_value,
    objectives = EXCLUDED.objectives,
    focus_areas = EXCLUDED.focus_areas,
    collaboration_areas = EXCLUDED.collaboration_areas,
    implementation_methods = EXCLUDED.implementation_methods,
    financial_commitments = EXCLUDED.financial_commitments,
    financial_amount = EXCLUDED.financial_amount,
    training_programs = EXCLUDED.training_programs,
    training_programs_description = EXCLUDED.training_programs_description,
    annual_report_required = EXCLUDED.annual_report_required,
    monitoring_plan = EXCLUDED.monitoring_plan,
    legal_binding_status = EXCLUDED.legal_binding_status,
    status = EXCLUDED.status,
    activated_at = EXCLUDED.activated_at,
    updated_at = NOW();

WITH links(agreement_code, partner_name) AS (
    VALUES
        ('DEMO-AI-2024', 'Bahrain Digital Innovation Hub'),
        ('DEMO-HEALTH-2023', 'Gulf Centre for Public Health'),
        ('DEMO-EXCHANGE-2022', 'Regional Academic Exchange Network'),
        ('DEMO-ENERGY-2025', 'Arabian Renewable Energy Institute'),
        ('DEMO-CLOUD-2025', 'Bahrain Cloud Skills Academy'),
        ('DEMO-MARINE-2026', 'Coastal and Marine Research Centre')
)
INSERT INTO agreement_partners (agreement_id, partner_id)
SELECT a.agreement_id, p.partner_id
FROM links l
JOIN agreements a ON a.agreement_code = l.agreement_code
JOIN partners p ON p.organization_name = l.partner_name
ON CONFLICT DO NOTHING;

WITH values_to_add(agreement_code, sdg_number) AS (
    VALUES
        ('DEMO-AI-2024', 4), ('DEMO-AI-2024', 8), ('DEMO-AI-2024', 9),
        ('DEMO-HEALTH-2023', 3), ('DEMO-HEALTH-2023', 4), ('DEMO-HEALTH-2023', 9),
        ('DEMO-EXCHANGE-2022', 4), ('DEMO-EXCHANGE-2022', 10), ('DEMO-EXCHANGE-2022', 17),
        ('DEMO-ENERGY-2025', 7), ('DEMO-ENERGY-2025', 9), ('DEMO-ENERGY-2025', 13),
        ('DEMO-CLOUD-2025', 4), ('DEMO-CLOUD-2025', 8), ('DEMO-CLOUD-2025', 9),
        ('DEMO-MARINE-2026', 13), ('DEMO-MARINE-2026', 14), ('DEMO-MARINE-2026', 17)
)
INSERT INTO agreement_sdgs (agreement_id, sdg_number)
SELECT a.agreement_id, v.sdg_number
FROM values_to_add v
JOIN agreements a ON a.agreement_code = v.agreement_code
ON CONFLICT DO NOTHING;

WITH metric_values(agreement_code, metric_code, planned_value, notes) AS (
    VALUES
        ('DEMO-AI-2024', 'STUDENTS_EXCHANGED', 40, 'Students in placements or applied research projects'),
        ('DEMO-AI-2024', 'FACULTY_EXCHANGED', 8, 'Faculty mentors and specialist exchanges'),
        ('DEMO-AI-2024', 'JOINT_PROGRAMS', 2, 'Applied AI Lab and Industry AI Challenge'),
        ('DEMO-HEALTH-2023', 'STUDENTS_EXCHANGED', 60, 'Students trained in digital-health delivery'),
        ('DEMO-HEALTH-2023', 'FACULTY_EXCHANGED', 10, 'Multidisciplinary faculty engagement'),
        ('DEMO-HEALTH-2023', 'JOINT_PROGRAMS', 2, 'Bootcamp and analytics pilot'),
        ('DEMO-EXCHANGE-2022', 'STUDENTS_EXCHANGED', 30, 'Annual student mobility target'),
        ('DEMO-EXCHANGE-2022', 'FACULTY_EXCHANGED', 5, 'Annual teaching exchanges'),
        ('DEMO-EXCHANGE-2022', 'JOINT_PROGRAMS', 1, 'Visiting Faculty Week'),
        ('DEMO-ENERGY-2025', 'STUDENTS_EXCHANGED', 18, 'Student research placements'),
        ('DEMO-ENERGY-2025', 'FACULTY_EXCHANGED', 6, 'Researcher exchanges'),
        ('DEMO-ENERGY-2025', 'JOINT_PROGRAMS', 2, 'Measurement campaign and publication stream'),
        ('DEMO-CLOUD-2025', 'STUDENTS_EXCHANGED', 80, 'Students completing the skills programme'),
        ('DEMO-CLOUD-2025', 'FACULTY_EXCHANGED', 12, 'Faculty enablement participants'),
        ('DEMO-CLOUD-2025', 'JOINT_PROGRAMS', 3, 'Two cohorts and one capstone challenge'),
        ('DEMO-MARINE-2026', 'STUDENTS_EXCHANGED', 24, 'Planned field research participation'),
        ('DEMO-MARINE-2026', 'FACULTY_EXCHANGED', 8, 'Planned research exchanges'),
        ('DEMO-MARINE-2026', 'JOINT_PROGRAMS', 2, 'Field programme and stakeholder workshop')
)
INSERT INTO agreement_metrics (
    agreement_id, metric_code, planned_value, actual_value, notes
)
SELECT a.agreement_id, m.metric_code, m.planned_value, NULL, m.notes
FROM metric_values m
JOIN agreements a ON a.agreement_code = m.agreement_code
ON CONFLICT (agreement_id, metric_code) DO UPDATE
SET planned_value = EXCLUDED.planned_value,
    notes = EXCLUDED.notes;

WITH programme_values(
    agreement_code, title, description, expected_outputs,
    start_date, end_date, responsible_entity, display_order
) AS (
    VALUES
        ('DEMO-AI-2024', 'Applied AI Lab', 'Shared student and faculty research environment.', 'Two applied prototypes and mentored student projects.', '2024-09-01', '2027-08-31', 'College of Information Technology', 1),
        ('DEMO-AI-2024', 'Industry AI Challenge', 'Annual partner-defined challenge.', 'Validated prototypes and an employer showcase.', '2025-01-01', '2027-06-30', 'Department of Computer Science', 2),
        ('DEMO-HEALTH-2023', 'Digital Health Bootcamp', 'Privacy-aware digital-health skills programme.', 'Four workshops and a final showcase.', '2025-01-01', '2026-12-31', 'University of Bahrain', 1),
        ('DEMO-HEALTH-2023', 'Clinical Analytics Pilot', 'Supervised multidisciplinary analytics pilot.', 'Validated prototype and governance checklist.', '2025-02-01', '2026-12-31', 'Gulf Centre for Public Health', 2),
        ('DEMO-EXCHANGE-2022', 'Semester Mobility', 'Incoming and outgoing semester exchanges.', 'Completed placements and participant evaluations.', '2022-09-01', '2027-08-31', 'College of Information Technology', 1),
        ('DEMO-EXCHANGE-2022', 'Visiting Faculty Week', 'Short teaching and research exchanges.', 'Guest lectures and collaborative planning.', '2025-09-01', '2026-06-30', 'College of Information Technology', 2),
        ('DEMO-ENERGY-2025', 'Solar Measurement Campaign', 'Shared solar-performance field measurement.', 'Quality-assured baseline dataset.', '2025-07-01', '2026-06-30', 'Arabian Renewable Energy Institute', 1),
        ('DEMO-ENERGY-2025', 'Joint Research Publication', 'Collaborative analysis and publication stream.', 'Peer-reviewed paper submission.', '2025-09-01', '2027-06-30', 'University of Bahrain', 2),
        ('DEMO-CLOUD-2025', 'Cloud Skills Cohorts', 'Two annual student learning cohorts.', 'Lab completion and certification readiness.', '2025-01-01', '2027-12-31', 'Department of Computer Science', 1),
        ('DEMO-CLOUD-2025', 'Industry Capstone Review', 'Employer review of cloud capstone projects.', 'Assessed projects and employability feedback.', '2025-09-01', '2027-06-30', 'Bahrain Cloud Skills Academy', 2),
        ('DEMO-MARINE-2026', 'Marine Field Data Programme', 'Planned shared coastal data collection.', 'Open research dataset and student field experience.', '2026-09-01', '2029-06-30', 'Coastal and Marine Research Centre', 1)
)
INSERT INTO agreement_executive_programs (
    agreement_id, title, description, objectives, expected_outputs,
    start_date, end_date, responsible_entity, applicant_name, display_order
)
SELECT
    a.agreement_id, p.title, p.description,
    'Deliver the Agreement objectives through measurable scheduled work.',
    p.expected_outputs, p.start_date::date, p.end_date::date,
    p.responsible_entity, 'UOB Partnership Steering Group', p.display_order
FROM programme_values p
JOIN agreements a ON a.agreement_code = p.agreement_code
WHERE NOT EXISTS (
    SELECT 1
    FROM agreement_executive_programs ep
    WHERE ep.agreement_id = a.agreement_id
      AND ep.title = p.title
);

INSERT INTO agreement_versions (
    agreement_id, version_number, document_path, change_summary,
    agreement_snapshot, created_by, created_at
)
SELECT
    a.agreement_id, 1, NULL, 'Initial approved showcase record',
    to_jsonb(a) || jsonb_build_object(
        'partner_ids', COALESCE((
            SELECT jsonb_agg(ap.partner_id ORDER BY ap.partner_id)
            FROM agreement_partners ap
            WHERE ap.agreement_id = a.agreement_id
        ), '[]'::jsonb)
    ),
    a.created_by, a.created_at
FROM agreements a
WHERE a.agreement_code LIKE 'DEMO-%'
ON CONFLICT (agreement_id, version_number) DO NOTHING;

WITH document_values(
    agreement_code, file_name, storage_key, file_size_bytes, checksum
) AS (
    VALUES
        ('DEMO-AI-2024', 'DEMO-AI-2024-Annual-Performance-Report.pdf', '2026/07/97e7382b1545d8c4f0b089f90b6bc099ed8b265aadd90a728cb280ee1daeff0c.pdf', 46443, '4cbe1c26c84522985d4615452a884cbe757a45525d091e2885194dde88969794'),
        ('DEMO-HEALTH-2023', 'DEMO-HEALTH-2023-Annual-Performance-Report.pdf', '2026/07/9bf85666d8ed2fb5d98ba71e0e7ed4798ff03bd1fdd451737558998d3a2173d9.pdf', 46502, 'c699b685ff40606b48548ca5226af1d218b991dc67148f01e465f0b2b0cb2268'),
        ('DEMO-ENERGY-2025', 'DEMO-ENERGY-2025-Annual-Performance-Report.pdf', '2026/07/9540fbdc4a918489723a512a8a7c7ed35425de002aa5cd550ff9213fb09ea0ce.pdf', 46426, 'cce38a97712fcabe1f37f93b577f53a4bf0542283cfc925064a7f7ac8c76252d'),
        ('DEMO-EXCHANGE-2022', 'DEMO-EXCHANGE-2022-Annual-Performance-Report.pdf', '2026/07/c750a6f650f2f9fa180d6d957b0c7e988703da723ffe5eece725271cd66d4cda.pdf', 46387, 'abcded9125af97211d37f86cfe20825e818f48de2002cca6f07f3554f43413c0')
)
INSERT INTO agreement_documents (
    agreement_id, agreement_version_id, file_name, file_path, storage_key,
    mime_type, file_size_bytes, sha256_checksum, document_type,
    uploaded_by, uploaded_at
)
SELECT
    a.agreement_id, NULL, d.file_name, NULL, d.storage_key,
    'application/pdf', d.file_size_bytes, d.checksum,
    'ANNUAL_REPORT', a.created_by, '2026-07-08 10:00:00'
FROM document_values d
JOIN agreements a ON a.agreement_code = d.agreement_code
WHERE NOT EXISTS (
    SELECT 1
    FROM agreement_documents ad
    WHERE ad.storage_key = d.storage_key
);

WITH report_values(
    agreement_code, period_start, period_end, due_date, status,
    executive_summary, achievements, challenges, corrective_actions,
    next_period_plan, submitter_email, reviewer_email,
    submitted_at, reviewed_at, reviewer_comments
) AS (
    VALUES
        (
            'DEMO-AI-2024', '2025-07-01', '2026-06-30', '2026-07-30', 'ACCEPTED',
            'The first reporting year established a joint applied-AI lab, delivered industry-led learning, and expanded supervised student research. All headline outcomes reached at least 85 percent of target.',
            'Launched the Applied AI Lab; placed 38 students; delivered two prototypes and four specialist seminars.',
            'GPU procurement took six weeks longer than planned.',
            'A shared equipment calendar and earlier procurement review were introduced.',
            'Expand the challenge to cybersecurity analytics and add an external research showcase.',
            'dev.dean@uob.test', 'dev.vp@uob.test',
            '2026-07-08 10:00:00', '2026-07-10 11:30:00',
            'Accepted. Results are supported by the submitted evidence and programme records.'
        ),
        (
            'DEMO-HEALTH-2023', '2025-01-01', '2025-12-31', '2026-01-30', 'ACCEPTED',
            'The partnership delivered clinical-data workshops, multidisciplinary capstone supervision, and a pilot digital-health challenge.',
            'Trained 64 students; engaged 11 faculty members; completed one cross-college pilot.',
            'Dataset access required an additional ethics-review cycle.',
            'A standard data-access checklist and early ethics consultation were adopted.',
            'Complete the second pilot and extend the programme to public-health analytics.',
            'dev.vp@uob.test', 'dev.president@uob.test',
            '2026-01-22 09:00:00', '2026-01-26 13:00:00',
            'Accepted with the next-period ethics actions recorded.'
        ),
        (
            'DEMO-ENERGY-2025', '2025-07-01', '2026-06-30', '2026-07-30', 'SUBMITTED',
            'The collaboration completed its first measurement campaign and established a shared research dataset.',
            'Installed the monitoring kit; completed a paper draft; hosted three research exchanges.',
            'Summer fieldwork was rescheduled because of equipment heat-tolerance limits.',
            'Morning collection windows and remote sensor-health monitoring are planned.',
            'Publish the baseline dataset and begin the second research work package.',
            'dev.president@uob.test', NULL,
            '2026-07-18 10:15:00', NULL, NULL
        ),
        (
            'DEMO-EXCHANGE-2022', '2025-09-01', '2026-06-30', '2026-07-15', 'RETURNED',
            'Exchange activity resumed strongly, but outbound student participation remained below target.',
            'Completed two faculty teaching exchanges; supported 22 placements; introduced pre-departure advising.',
            'Late visa decisions reduced outbound participation.',
            'Recruitment and nomination will open eight weeks earlier with reserve lists.',
            'Confirm the early-nomination calendar and report visa-risk mitigation.',
            'dev.dean@uob.test', 'dev.vp@uob.test',
            '2026-07-12 09:30:00', '2026-07-16 14:00:00',
            'Return: clarify the outbound recruitment plan and attach the final mobility evidence.'
        ),
        (
            'DEMO-CLOUD-2025', '2025-07-01', '2026-06-30', '2026-07-30', 'DRAFT',
            'The cloud-skills programme completed two delivery cohorts and is preparing its first annual evidence package.',
            'Student laboratories and the employer-reviewed capstone cycle were completed.',
            'Certification scheduling remains in progress.',
            'Confirm the examination calendar and consolidate cohort evidence.',
            'Add an advanced cloud-security pathway in the next delivery cycle.',
            NULL, NULL, NULL, NULL, NULL
        )
)
INSERT INTO agreement_performance_reports (
    agreement_id, period_start, period_end, due_date, status,
    executive_summary, achievements, challenges, corrective_actions,
    next_period_plan, report_document_id, created_by,
    submitted_by, submitted_at, reviewed_by, reviewed_at,
    reviewer_comments, created_at, updated_at
)
SELECT
    a.agreement_id, r.period_start::date, r.period_end::date,
    r.due_date::date, r.status,
    r.executive_summary, r.achievements, r.challenges,
    r.corrective_actions, r.next_period_plan,
    ad.document_id, a.created_by,
    su.user_id, r.submitted_at::timestamp,
    ru.user_id, r.reviewed_at::timestamp, r.reviewer_comments,
    LEAST(COALESCE(r.submitted_at::timestamp, NOW()), NOW()), NOW()
FROM report_values r
JOIN agreements a ON a.agreement_code = r.agreement_code
LEFT JOIN users su ON su.email = r.submitter_email
LEFT JOIN users ru ON ru.email = r.reviewer_email
LEFT JOIN agreement_documents ad
  ON ad.agreement_id = a.agreement_id
 AND ad.document_type = 'ANNUAL_REPORT'
 AND ad.storage_key = CASE r.agreement_code
      WHEN 'DEMO-AI-2024' THEN '2026/07/97e7382b1545d8c4f0b089f90b6bc099ed8b265aadd90a728cb280ee1daeff0c.pdf'
      WHEN 'DEMO-HEALTH-2023' THEN '2026/07/9bf85666d8ed2fb5d98ba71e0e7ed4798ff03bd1fdd451737558998d3a2173d9.pdf'
      WHEN 'DEMO-ENERGY-2025' THEN '2026/07/9540fbdc4a918489723a512a8a7c7ed35425de002aa5cd550ff9213fb09ea0ce.pdf'
      WHEN 'DEMO-EXCHANGE-2022' THEN '2026/07/c750a6f650f2f9fa180d6d957b0c7e988703da723ffe5eece725271cd66d4cda.pdf'
      ELSE NULL
    END
ON CONFLICT (agreement_id, period_start, period_end) DO UPDATE
SET due_date = EXCLUDED.due_date,
    status = EXCLUDED.status,
    executive_summary = EXCLUDED.executive_summary,
    achievements = EXCLUDED.achievements,
    challenges = EXCLUDED.challenges,
    corrective_actions = EXCLUDED.corrective_actions,
    next_period_plan = EXCLUDED.next_period_plan,
    report_document_id = EXCLUDED.report_document_id,
    submitted_by = EXCLUDED.submitted_by,
    submitted_at = EXCLUDED.submitted_at,
    reviewed_by = EXCLUDED.reviewed_by,
    reviewed_at = EXCLUDED.reviewed_at,
    reviewer_comments = EXCLUDED.reviewer_comments,
    updated_at = NOW();

WITH result_values(
    agreement_code, metric_code, metric_label, planned_value,
    actual_value, unit, notes, display_order
) AS (
    VALUES
        ('DEMO-AI-2024', 'STUDENTS_EXCHANGED', 'Students exchanged / placed', 40, 38, 'PEOPLE', 'Project placements and supervised research participants.', 1),
        ('DEMO-AI-2024', 'FACULTY_EXCHANGED', 'Faculty exchanged', 8, 7, 'PEOPLE', 'Faculty mentors and specialist exchanges.', 2),
        ('DEMO-AI-2024', 'JOINT_PROGRAMS', 'Joint programmes', 2, 2, 'PROGRAMMES', 'Applied AI Lab and Industry AI Challenge.', 3),
        ('DEMO-HEALTH-2023', 'STUDENTS_EXCHANGED', 'Students trained', 60, 64, 'PEOPLE', 'Bootcamp and workshop participants.', 1),
        ('DEMO-HEALTH-2023', 'FACULTY_EXCHANGED', 'Faculty engaged', 10, 11, 'PEOPLE', 'Computing and health faculty.', 2),
        ('DEMO-HEALTH-2023', 'JOINT_PROGRAMS', 'Joint programmes', 2, 1, 'PROGRAMMES', 'One pilot completed; second approved.', 3),
        ('DEMO-ENERGY-2025', 'STUDENTS_EXCHANGED', 'Student researchers', 18, 15, 'PEOPLE', 'Field and analysis participation.', 1),
        ('DEMO-ENERGY-2025', 'FACULTY_EXCHANGED', 'Faculty researchers', 6, 6, 'PEOPLE', 'Research exchanges completed.', 2),
        ('DEMO-ENERGY-2025', 'JOINT_PROGRAMS', 'Joint programmes', 2, 1, 'PROGRAMMES', 'Measurement campaign complete; publication stream active.', 3),
        ('DEMO-EXCHANGE-2022', 'STUDENTS_EXCHANGED', 'Students exchanged', 30, 22, 'PEOPLE', 'Outbound participation below target.', 1),
        ('DEMO-EXCHANGE-2022', 'FACULTY_EXCHANGED', 'Faculty exchanged', 5, 4, 'PEOPLE', 'Two teaching exchanges involving four faculty.', 2),
        ('DEMO-EXCHANGE-2022', 'JOINT_PROGRAMS', 'Joint programmes', 1, 1, 'PROGRAMMES', 'Visiting Faculty Week delivered.', 3),
        ('DEMO-CLOUD-2025', 'STUDENTS_EXCHANGED', 'Students trained', 80, 72, 'PEOPLE', 'Draft result pending final evidence.', 1),
        ('DEMO-CLOUD-2025', 'FACULTY_EXCHANGED', 'Faculty enabled', 12, 10, 'PEOPLE', 'Draft result pending final evidence.', 2),
        ('DEMO-CLOUD-2025', 'JOINT_PROGRAMS', 'Joint programmes', 3, 2, 'PROGRAMMES', 'Two cohorts delivered; capstone review in progress.', 3)
)
INSERT INTO agreement_performance_metric_results (
    performance_report_id, agreement_metric_id, metric_code, metric_label,
    planned_value, actual_value, unit, notes, display_order
)
SELECT
    pr.performance_report_id, am.agreement_metric_id,
    rv.metric_code, rv.metric_label, rv.planned_value, rv.actual_value,
    rv.unit, rv.notes, rv.display_order
FROM result_values rv
JOIN agreements a ON a.agreement_code = rv.agreement_code
JOIN agreement_performance_reports pr
  ON pr.agreement_id = a.agreement_id
 AND EXTRACT(YEAR FROM pr.period_end) = 2026
JOIN agreement_metrics am
  ON am.agreement_id = a.agreement_id
 AND am.metric_code = rv.metric_code
ON CONFLICT (performance_report_id, metric_code) DO UPDATE
SET metric_label = EXCLUDED.metric_label,
    planned_value = EXCLUDED.planned_value,
    actual_value = EXCLUDED.actual_value,
    unit = EXCLUDED.unit,
    notes = EXCLUDED.notes,
    display_order = EXCLUDED.display_order;

WITH update_values(
    agreement_code, programme_title, progress_status, completion_percent,
    achievements, outputs_delivered, challenges, next_steps
) AS (
    VALUES
        ('DEMO-AI-2024', 'Applied AI Lab', 'COMPLETED', 100, 'Laboratory launched and operating.', 'Two research streams and supervised projects.', 'Procurement delay resolved.', 'Expand access to additional departments.'),
        ('DEMO-AI-2024', 'Industry AI Challenge', 'ON_TRACK', 85, 'Six teams completed validation.', 'Prototype showcase completed.', 'One team withdrew late.', 'Open the next challenge earlier.'),
        ('DEMO-HEALTH-2023', 'Digital Health Bootcamp', 'COMPLETED', 100, 'Four workshops delivered.', 'Sixty-four students trained.', NULL, 'Refresh privacy and analytics content.'),
        ('DEMO-HEALTH-2023', 'Clinical Analytics Pilot', 'ON_TRACK', 75, 'Prototype validated.', 'Governance checklist and pilot findings.', 'Ethics review extended the schedule.', 'Complete the second pilot.'),
        ('DEMO-ENERGY-2025', 'Solar Measurement Campaign', 'COMPLETED', 100, 'Baseline campaign completed.', 'Quality-assured research dataset.', 'Heat affected the field schedule.', 'Publish and extend the dataset.'),
        ('DEMO-ENERGY-2025', 'Joint Research Publication', 'ON_TRACK', 70, 'First manuscript drafted.', 'Partner-review manuscript.', 'Additional validation requested.', 'Submit to the selected journal.'),
        ('DEMO-EXCHANGE-2022', 'Semester Mobility', 'AT_RISK', 72, 'Twenty-two placements completed.', 'Participant records and advising logs.', 'Visa delays reduced outbound mobility.', 'Open nomination and reserve lists earlier.'),
        ('DEMO-EXCHANGE-2022', 'Visiting Faculty Week', 'COMPLETED', 100, 'Two teaching exchanges completed.', 'Guest lectures and planning outputs.', NULL, 'Schedule the next faculty week.'),
        ('DEMO-CLOUD-2025', 'Cloud Skills Cohorts', 'ON_TRACK', 90, 'Two student cohorts delivered.', 'Laboratory completion records.', 'Certification dates are pending.', 'Complete certification evidence.'),
        ('DEMO-CLOUD-2025', 'Industry Capstone Review', 'ON_TRACK', 75, 'Employer review cycle started.', 'Initial project feedback.', 'Two projects require revision.', 'Complete final employer assessment.')
)
INSERT INTO agreement_executive_program_updates (
    performance_report_id, executive_program_id, program_title,
    progress_status, completion_percent, achievements, outputs_delivered,
    challenges, next_steps, display_order
)
SELECT
    pr.performance_report_id, ep.executive_program_id, uv.programme_title,
    uv.progress_status, uv.completion_percent, uv.achievements,
    uv.outputs_delivered, uv.challenges, uv.next_steps, ep.display_order
FROM update_values uv
JOIN agreements a ON a.agreement_code = uv.agreement_code
JOIN agreement_performance_reports pr
  ON pr.agreement_id = a.agreement_id
 AND EXTRACT(YEAR FROM pr.period_end) = 2026
JOIN agreement_executive_programs ep
  ON ep.agreement_id = a.agreement_id
 AND ep.title = uv.programme_title
ON CONFLICT (performance_report_id, executive_program_id) DO UPDATE
SET program_title = EXCLUDED.program_title,
    progress_status = EXCLUDED.progress_status,
    completion_percent = EXCLUDED.completion_percent,
    achievements = EXCLUDED.achievements,
    outputs_delivered = EXCLUDED.outputs_delivered,
    challenges = EXCLUDED.challenges,
    next_steps = EXCLUDED.next_steps,
    display_order = EXCLUDED.display_order;

INSERT INTO agreement_performance_report_events (
    performance_report_id, from_status, to_status, comments,
    performed_by, created_at
)
SELECT
    pr.performance_report_id,
    CASE pr.status
        WHEN 'ACCEPTED' THEN 'SUBMITTED'
        WHEN 'RETURNED' THEN 'SUBMITTED'
        ELSE 'DRAFT'
    END,
    pr.status,
    pr.reviewer_comments,
    COALESCE(pr.reviewed_by, pr.submitted_by, pr.created_by),
    COALESCE(pr.reviewed_at, pr.submitted_at, pr.created_at)
FROM agreement_performance_reports pr
JOIN agreements a ON a.agreement_id = pr.agreement_id
WHERE a.agreement_code LIKE 'DEMO-%'
  AND pr.status <> 'DRAFT'
  AND NOT EXISTS (
      SELECT 1
      FROM agreement_performance_report_events e
      WHERE e.performance_report_id = pr.performance_report_id
        AND e.to_status = pr.status
  );

COMMIT;
