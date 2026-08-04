<?php

declare(strict_types=1);

function agreementFormAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function agreementFormSource(string $relativePath): string
{
    $path = dirname(__DIR__) . '/' . ltrim($relativePath, '/');
    $source = file_get_contents($path);
    agreementFormAssert(
        $source !== false,
        "Could not read {$relativePath}"
    );

    return $source;
}

$form = agreementFormSource(
    'uob-agreements/workspace/agreement-form.php'
);
$javascript = agreementFormSource(
    'uob-agreements/workspace/assets/js/agreement-form.js'
);
$apiClient = agreementFormSource(
    'uob-agreements/workspace/assets/js/api-client.js'
);
$lifecycleJavascript = agreementFormSource(
    'uob-agreements/workspace/assets/js/lifecycle-form.js'
);
$styles = agreementFormSource(
    'uob-agreements/workspace/assets/css/workspace.css'
);
$formStyles = agreementFormSource(
    'uob-agreements/workspace/assets/css/agreement-form.css'
);
$partnerRepository = agreementFormSource(
    'repositories/PartnerRepository.php'
);
$agreementService = agreementFormSource('services/AgreementService.php');
$agreementValidator = agreementFormSource('validators/AgreementValidator.php');
$partnerRoutes = agreementFormSource('routes/partners.php');
$agreementRoutes = agreementFormSource('routes/agreements.php');
$apiBoundary = agreementFormSource('api/index.php');
$agreementController = agreementFormSource(
    'controllers/AgreementController.php'
);
$agreementRepository = agreementFormSource(
    'repositories/AgreementRepository.php'
);
$clauseExtraction = agreementFormSource(
    'services/AgreementClauseExtractionService.php'
);
$partnerLookup = agreementFormSource(
    'services/PartnerLookupService.php'
);
$documentRepository = agreementFormSource(
    'repositories/AgreementDocumentRepository.php'
);
$documentStorage = agreementFormSource(
    'services/DocumentStorageService.php'
);
$migration = agreementFormSource(
    'uob-agreements/data/sql/migrations/20260728_153000_agreement_form_partner_profiles.sql'
);
$fixedTermMigration = agreementFormSource(
    'uob-agreements/data/sql/migrations/20260728_170000_agreement_fixed_term_months.sql'
);
$trainedStudentsMigration = agreementFormSource(
    'uob-agreements/data/sql/migrations/20260728_183000_add_trained_students_metric.sql'
);
$partnerEnrichmentMigration = agreementFormSource(
    'uob-agreements/data/sql/migrations/20260729_103000_enrich_existing_partner_profiles.sql'
);

agreementFormAssert(
    str_contains($form, 'Type of cooperative project')
        && str_contains($form, 'data-partner-search')
        && str_contains($form, 'data-selected-partners')
        && str_contains($form, 'data-show-new-partner')
        && str_contains($form, 'data-partner-results hidden')
        && str_contains($form, 'data-edit-partner') === false
        && str_contains($javascript, 'dataset.editPartner'),
    'The searchable single-partner experience is incomplete'
);
agreementFormAssert(
    str_contains($apiBoundary, "str_starts_with(\$requestPath, '/partners')")
        && str_contains($javascript, 'partnerSearchOpen')
        && str_contains($javascript, "addEventListener('focus'")
        && str_contains($form, 'role="combobox"')
        && str_contains($form, 'aria-controls="partner-search-results"'),
    'Partner directory search is not routed or exposed accessibly'
);
agreementFormAssert(
    !str_contains($form, '<label for="geographic_scope"')
        && str_contains($form, 'data-derived-partner-scope')
        && str_contains($javascript, 'derivedPartnerScope')
        && str_contains($agreementService, 'withDerivedPartnerScope')
        && str_contains($agreementService, 'findActiveByIds')
        && str_contains($agreementService, "? 'LOCAL'")
        && str_contains($agreementService, ": 'INTERNATIONAL'"),
    'Partner scope must be derived from selected partner countries'
);
agreementFormAssert(
    str_contains($form, 'PUBLIC_GOVERNMENT')
        && str_contains($form, 'PRIVATE')
        && str_contains($form, 'ACADEMIC')
        && str_contains($form, 'NON_PROFIT')
        && str_contains($partnerRoutes, "\$method === 'PATCH'")
        && str_contains($javascript, 'AgreementApi.updatePartner')
        && str_contains(
            $partnerRepository,
            'CAST(:exclude_partner_id_empty AS BIGINT)'
        ),
    'Controlled partner types or audited existing-partner editing is incomplete'
);
agreementFormAssert(
    str_contains($form, 'data-lookup-partner')
        && str_contains($apiClient, 'lookupPartner(name)')
        && str_contains($partnerRoutes, '/partners/lookup')
        && str_contains($partnerLookup, "'wbsearchentities'")
        && str_contains($partnerLookup, "'wbgetentities'")
        && str_contains($partnerLookup, "'P856'")
        && str_contains($partnerLookup, "'P17'"),
    'The reviewable public partner lookup is incomplete'
);
agreementFormAssert(
    !str_contains($form, 'Use Ctrl')
        && !str_contains($form, 'size="5"'),
    'The inaccessible legacy multi-select instructions remain'
);
agreementFormAssert(
    str_contains($form, 'data-date-range')
        && str_contains($form, 'data-start-target="start_date"')
        && str_contains($form, 'data-range-trigger-start="start_date"')
        && str_contains($form, 'data-range-trigger-end="end_date"')
        && str_contains($form, 'class="date-range-separator">to</span>')
        && str_contains($javascript, "input._flatpickr?.open()"),
    'Project and programme range calendars are missing'
);
agreementFormAssert(
    !str_contains($form, 'THE Impact Rankings')
        && !str_contains($form, 'UI GreenMetric')
        && !str_contains($form, 'QS World University Rankings'),
    'Removed ranking choices are still present'
);
agreementFormAssert(
    substr_count($form, 'data-bs-toggle="tooltip"') === 1
        && str_contains($form, 'foreach ($sdgs as $number')
        && substr_count($form, "=> ['") >= 17
        && str_contains($form, 'No Poverty')
        && str_contains($form, 'Partnerships for the Goals'),
    'All 17 titled SDG explanations are required'
);
agreementFormAssert(
    str_contains($form, 'data-extract-clauses')
        && str_contains($form, 'data-document-type="GOVERNANCE_CLAUSES"')
        && str_contains($form, 'accept=".docx" required')
        && str_contains($form, 'data-document-type="MEDIA"'),
    'Clause extraction or queued media uploads are missing'
);
agreementFormAssert(
    str_contains($clauseExtraction, "'collaboration_areas'")
        && str_contains($clauseExtraction, "'implementation_methods'")
        && str_contains($clauseExtraction, "'contacts'")
        && str_contains($javascript, "(result.contacts || [])")
        && str_contains(
            $javascript,
            "document.getElementById('governance_document')"
        ),
    'Automatic Article 1/2, coordinator, and signatory extraction is incomplete'
);
agreementFormAssert(
    str_contains($clauseExtraction, 'numberedArticleSection')
        && str_contains($clauseExtraction, 'articleNumber')
        && str_contains($clauseExtraction, 'extractDocxContent')
        && str_contains($clauseExtraction, 'extractContactsFromTables')
        && str_contains($clauseExtraction, 'isPlausiblePersonName')
        && str_contains($javascript, 'extractionSuggested'),
    'Article-aware or table-aware extraction safeguards are incomplete'
);
agreementFormAssert(
    str_contains($form, 'data-form-section')
        && str_contains($form, 'agreement-request-hero')
        && str_contains($form, 'agreement-form-shell')
        && str_contains($form, 'agreement-form-step-panel')
        && str_contains($form, 'data-previous-section')
        && str_contains($form, 'data-next-section')
        && substr_count($form, 'data-section-number="') === 1
        && str_contains($form, "        10,\n        'media'")
        && str_contains($javascript, 'maybeAdvanceSection')
        && str_contains($javascript, 'showSection')
        && str_contains($javascript, 'moveSection')
        && str_contains($styles, '.agreement-section-toggle')
        && str_contains($formStyles, '.agreement-request-hero')
        && str_contains($formStyles, '.agreement-form-shell')
        && str_contains($formStyles, '.agreement-step.is-current')
        && str_contains($form, 'data-step-timeline')
        && str_contains($form, 'data-step-target=')
        && str_contains($javascript, 'is-current')
        && str_contains($javascript, 'visitedSections')
        && str_contains($javascript, 'sectionNeedsAttention')
        && str_contains($javascript, 'setSectionOpen(section, !isOpen)')
        && !str_contains(
            $javascript,
            'setSectionOpen(candidate, candidate === section)'
        )
        && str_contains($formStyles, '.agreement-step:last-child::before')
        && str_contains($styles, '.agreement-form-section.needs-attention .agreement-section-number')
        && str_contains($styles, '.partner-results[hidden]'),
    'The guided collapsible section workflow is incomplete'
);
agreementFormAssert(
    !str_contains($form, 'Programme applicant name')
        && !str_contains($javascript, 'applicant_name')
        && str_contains($form, 'data-program-responsible-entity')
        && str_contains($javascript, 'refreshProgramSuggestions')
        && str_contains($javascript, 'selectedPartnersHaveRequiredCountries'),
    'Programme applicant removal, suggestions, responsible-entity choices, or international-country validation is incomplete'
);
agreementFormAssert(
    str_contains($partnerRepository, 'profile')
        && str_contains($partnerRepository, 'findActiveByIds')
        && str_contains($partnerRoutes, "\$method === 'POST'")
        && str_contains($partnerRepository, 'public function update')
        && str_contains($migration, 'ADD COLUMN IF NOT EXISTS profile'),
    'Partner profile storage and controlled creation are incomplete'
);
agreementFormAssert(
    str_contains($partnerEnrichmentMigration, "THEN 'ACADEMIC'")
        && str_contains($partnerEnrichmentMigration, "THEN 'NON_PROFIT'")
        && str_contains($partnerEnrichmentMigration, 'SET profile = CASE')
        && preg_match(
            '/^[ \t]*(BEGIN|START[ \t]+TRANSACTION|COMMIT|ROLLBACK)[ \t]*;/mi',
            $partnerEnrichmentMigration
        ) === 0,
    'Existing partner types and profiles are not enriched safely'
);
agreementFormAssert(
    str_contains($agreementService, 'validatePartnerSelection')
        && str_contains(
            $agreementService,
            'Every selected partner must have a country'
        ),
    'Partner countries are not enforced by the API'
);
agreementFormAssert(
    str_contains($form, 'name="partner_id"')
        && !str_contains($form, 'name="partner_ids[]" multiple')
        && str_contains(
            $agreementValidator,
            'Exactly one partner is required'
        )
        && str_contains(
            $agreementService,
            'Exactly one partner organization must be selected'
        ),
    'Exactly-one-partner enforcement is incomplete'
);
agreementFormAssert(
    str_contains($form, 'data-partner-agreement-context')
        && str_contains($partnerRoutes, 'agreement-context')
        && str_contains($apiClient, 'partnerAgreementContext')
        && str_contains($javascript, 'Request amendment')
        && str_contains($javascript, 'Use previous work')
        && str_contains(
            $agreementService,
            'validatePartnerAgreementUniqueness'
        )
        && str_contains($lifecycleJavascript, 'requestedType'),
    'Partner Agreement uniqueness, amendment routing, or expired-Agreement reuse is incomplete'
);
agreementFormAssert(
    str_contains($form, 'data-program-list')
        && str_contains($form, 'data-add-program')
        && str_contains($form, 'data-remove-program')
        && str_contains($javascript, 'function addProgram')
        && str_contains($javascript, 'function validatePrograms')
        && str_contains(
            $agreementValidator,
            'At least one complete executive programme is required'
        ),
    'One-or-more executive-programme enforcement is incomplete'
);
agreementFormAssert(
    str_contains(
        $form,
        "'title_ar', 'اسم مشروع التعاون (العربية) *'"
    )
        && !str_contains($form, "'Signing date *'")
        && !str_contains($form, "'Effective date *'")
        && !str_contains(
            $agreementValidator,
            'Signing date is required before submission'
        )
        && !str_contains(
            $agreementValidator,
            'Effective date is required before submission'
        )
        && !str_contains(
            $form,
            "'Fields of cooperation / MOU Article 1 *'"
        )
        && !str_contains(
            $form,
            "'Implementation methods / MOU Article 2 *'"
        )
        && substr_count($form, "'Full name *'") === 1
        && str_contains($form, "'Planned number *'")
        && str_contains($form, "'Actual number *'")
        && str_contains($form, "'Notes *'")
        && str_contains(
            $agreementValidator,
            'Every planned-outcome field is required before submission'
        ),
    'The revised mandatory and optional field rules are incomplete'
);
agreementFormAssert(
    str_contains($form, "'TRAINED_STUDENTS' => 'Trained students'")
        && str_contains($trainedStudentsMigration, "'TRAINED_STUDENTS'")
        && preg_match(
            '/^[ \t]*(BEGIN|START[ \t]+TRANSACTION|COMMIT|ROLLBACK)[ \t]*;/mi',
            $trainedStudentsMigration
        ) === 0,
    'The trained-students planned outcome is incomplete'
);
agreementFormAssert(
    str_contains($agreementRoutes, '/agreement-document-extraction')
        && str_contains(
            $agreementRoutes,
            '$controller->extractDocumentClauses()'
        )
        && str_contains(
            $agreementController,
            'AgreementClauseExtractionService'
        )
        && str_contains(
            $agreementController,
            'public function extractDocumentClauses'
        )
        && str_contains(
            $agreementController,
            "PermissionMiddleware::requireAny"
        )
        && str_contains($documentStorage, "'mp4'")
        && str_contains($documentStorage, "'webp'")
        && str_contains(
            $documentStorage,
            'normalizedAllowedExtensions'
        )
        && str_contains($documentRepository, 'hasDocumentType')
        && str_contains($documentRepository, 'storage_key IS NOT NULL')
        && str_contains($agreementService, "'GOVERNANCE_CLAUSES'")
        && str_contains($agreementService, "'MEDIA'")
        && str_contains(
            $agreementService,
            "'GOVERNANCE_CLAUSES' => ['docx']"
        )
        && str_contains(
            $agreementService,
            "'MEDIA' => ['jpg', 'jpeg', 'png', 'webp', 'mp4']"
        )
        && str_contains(
            $agreementService,
            'Upload the governance / MOU clauses DOCX file before submission'
        ),
    'Clause extraction routing or secure media validation is incomplete'
);
agreementFormAssert(
    !str_contains($form, 'Public signing / news link')
        && !str_contains($form, 'Termination notice (months)')
        && !str_contains($form, 'Legal effect')
        && str_contains($form, 'Agreement term (months) *')
        && str_contains($javascript, 'fixed_term_months')
        && str_contains($fixedTermMigration, 'fixed_term_months')
        && str_contains($form, "'media',\n        'Supporting media'")
        && str_contains($form, 'data-program-suggestion-list')
        && str_contains($javascript, 'programSuggestionFeedback')
        && !str_contains(
            $javascript,
            "showFeedback('Suggestions were applied"
        ),
    'Lifecycle boundaries, the media section, or local programme feedback are incorrect'
);
agreementFormAssert(
    str_contains(
        $agreementController,
        "'signing_date', 'fixed_term_months', 'renewal_term_months'"
    )
        && str_contains($agreementRepository, "'fixed_term_months'")
        && str_contains(
            $agreementService,
            'restoreMissingFixedTermMonths'
        ),
    'Fixed Agreement terms are not preserved or recovered before submission'
);
agreementFormAssert(
    preg_match(
        '/^[ \t]*(BEGIN|START[ \t]+TRANSACTION|COMMIT|ROLLBACK)[ \t]*;/mi',
        $fixedTermMigration
    ) === 0,
    'The fixed-term migration must rely on the database manager transaction'
);

require_once dirname(__DIR__)
    . '/services/AgreementClauseExtractionService.php';
$extractor = new AgreementClauseExtractionService();
$classifier = new ReflectionMethod(
    AgreementClauseExtractionService::class,
    'classifyClauses'
);
$classifier->setAccessible(true);
$paragraphs = [
    'Article 1 - Fields of Cooperation',
    'Research, teaching, and student exchange.',
    'Article 2 - Implementation Methods',
    'Annual plans, named coordinators, and joint review.',
    'Article 3 - Duration',
    'This instrument remains in force for three years.',
];
$classified = $classifier->invoke(
    $extractor,
    implode("\n\n", $paragraphs),
    $paragraphs
);
agreementFormAssert(
    str_contains(
        (string) ($classified['collaboration_areas'] ?? ''),
        'Research, teaching'
    )
        && !str_contains(
            (string) ($classified['collaboration_areas'] ?? ''),
            'Implementation Methods'
        )
        && str_contains(
            (string) ($classified['implementation_methods'] ?? ''),
            'Annual plans'
        ),
    'Article 1 and Article 2 extraction are grouped incorrectly'
);

$contactExtractor = new ReflectionMethod(
    AgreementClauseExtractionService::class,
    'extractContacts'
);
$contactExtractor->setAccessible(true);
$contacts = $contactExtractor->invoke($extractor, [
    'University of Bahrain Coordinator',
    'Name: Dr Aisha Ahmed',
    'Job title: Director of Partnerships',
    'Email: aisha@uob.example',
    'Phone: +973 1743 8000',
    'Partner Coordinator',
    'Name: Mr Daniel Smith; Position: International Office Manager; Email: daniel@partner.example; Mobile No.: +44 20 7946 0958',
    'For and on behalf of the University of Bahrain',
    'Full name: Prof Mariam Ali',
    'Capacity: President',
    'For and on behalf of the partner organization',
    'Full name: Dr John Lee',
    'Designation: Chief Executive Officer',
]);
$contactsByRole = [];
foreach ($contacts as $contact) {
    $contactsByRole[
        ($contact['party_type'] ?? '')
        . ':'
        . ($contact['contact_role'] ?? '')
    ] = $contact;
}
agreementFormAssert(
    ($contactsByRole['UOB:COORDINATOR']['full_name'] ?? '')
        === 'Dr Aisha Ahmed'
        && ($contactsByRole['UOB:COORDINATOR']['job_title'] ?? '')
            === 'Director of Partnerships'
        && ($contactsByRole['PARTNER:COORDINATOR']['full_name'] ?? '')
            === 'Mr Daniel Smith'
        && ($contactsByRole['PARTNER:COORDINATOR']['job_title'] ?? '')
            === 'International Office Manager'
        && ($contactsByRole['UOB:COORDINATOR']['email'] ?? '')
            === 'aisha@uob.example'
        && ($contactsByRole['UOB:COORDINATOR']['phone'] ?? '')
            === '+973 1743 8000'
        && ($contactsByRole['PARTNER:COORDINATOR']['email'] ?? '')
            === 'daniel@partner.example'
        && ($contactsByRole['PARTNER:COORDINATOR']['phone'] ?? '')
            === '+44 20 7946 0958'
        && ($contactsByRole['UOB:SIGNATORY']['full_name'] ?? '')
            === 'Prof Mariam Ali'
        && ($contactsByRole['UOB:SIGNATORY']['job_title'] ?? '')
            === 'President'
        && ($contactsByRole['PARTNER:SIGNATORY']['full_name'] ?? '')
            === 'Dr John Lee'
        && ($contactsByRole['PARTNER:SIGNATORY']['job_title'] ?? '')
            === 'Chief Executive Officer',
    'Coordinator and signatory names or job titles are assigned incorrectly'
);

$arabicContacts = $contactExtractor->invoke(
    $extractor,
    [
        'المادة (4) نقاط الاتصال',
        'يعين كل طرف منسق يمثله لأغراض متابعة تنفيذ مواد هذه المذكرة، وذلك وفق الآتي',
        'ويجوز لكل من الطرفين -بعد إخطار الطرف الآخر- تغيير المنسق الواردة بياناته أعلاه متى ارتأى ذلك.',
        'المادة (13) النفاذ',
        'تدخل هذه المذكرة حيز التنفيذ من تاريخ إتمام التوقيع عليها مِن قبل الطرفين، وتظل سارية المفعول لمدة ثلاث سنوات وتُجدد تلقائياً.',
    ],
    [
        [
            [
                'جامعة البحرين (الطرف الأول)',
                'اسم الجهة (الطرف الثاني)',
            ],
            [
                "الاسم: الدكتورة مريم علي\nالمسمى الوظيفي: مديرة الشراكات\nعنوان البريد الإلكتروني: mariam@uob.example\nرقم الهاتف: +973 1743 8000",
                "المسمى الوظيفي: أستاذ جاسم\nعنوان البريد الإلكتروني: jasem@partner.example\nرقم الهاتف: +973 1700 1234",
            ],
        ],
    ]
);
$arabicContactsByParty = [];
foreach ($arabicContacts as $contact) {
    $arabicContactsByParty[$contact['party_type'] ?? ''] = $contact;
}
agreementFormAssert(
    count($arabicContacts) === 2
        && ($arabicContactsByParty['UOB']['full_name'] ?? '')
            === 'الدكتورة مريم علي'
        && ($arabicContactsByParty['UOB']['job_title'] ?? '')
            === 'مديرة الشراكات'
        && ($arabicContactsByParty['UOB']['email'] ?? '')
            === 'mariam@uob.example'
        && ($arabicContactsByParty['UOB']['phone'] ?? '')
            === '+973 1743 8000'
        && ($arabicContactsByParty['PARTNER']['full_name'] ?? '')
            === 'أستاذ جاسم'
        && ($arabicContactsByParty['PARTNER']['job_title'] ?? '') === ''
        && ($arabicContactsByParty['PARTNER']['email'] ?? '')
            === 'jasem@partner.example'
        && ($arabicContactsByParty['PARTNER']['phone'] ?? '')
            === '+973 1700 1234',
    'Arabic two-party coordinator table extraction is incorrect'
);

$clauseOnlyContacts = $contactExtractor->invoke($extractor, [
    'يعين كل طرف منسق يمثله لأغراض متابعة تنفيذ مواد هذه المذكرة، وذلك وفق الآتي',
    'ويجوز لكل من الطرفين -بعد إخطار الطرف الآخر- تغيير المنسق الواردة بياناته أعلاه متى ارتأى ذلك.',
    'تدخل هذه المذكرة حيز التنفيذ من تاريخ إتمام التوقيع عليها مِن قبل الطرفين، وتظل سارية المفعول لمدة ثلاث سنوات.',
]);
agreementFormAssert(
    $clauseOnlyContacts === [],
    'Arabic legal clauses must never be returned as contact names or titles'
);
agreementFormAssert(
    str_contains(
        $javascript,
        "state.validationAttempted && !input.checkValidity()"
    )
        && !str_contains($javascript, "dataset.touched = 'true'"),
    'Fields must not show red validation before a save attempt'
);

echo "Agreement form experience smoke test passed.\n";
