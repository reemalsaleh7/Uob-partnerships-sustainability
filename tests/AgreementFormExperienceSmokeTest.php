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
$styles = agreementFormSource(
    'uob-agreements/workspace/assets/css/workspace.css'
);
$partnerRepository = agreementFormSource(
    'repositories/PartnerRepository.php'
);
$agreementService = agreementFormSource('services/AgreementService.php');
$partnerRoutes = agreementFormSource('routes/partners.php');
$agreementRoutes = agreementFormSource('routes/agreements.php');
$clauseExtraction = agreementFormSource(
    'services/AgreementClauseExtractionService.php'
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

agreementFormAssert(
    str_contains($form, 'Type of cooperative project')
        && str_contains($form, 'data-partner-search')
        && str_contains($form, 'data-selected-partners')
        && str_contains($form, 'data-show-new-partner')
        && str_contains($form, 'data-edit-partner') === false
        && str_contains($javascript, 'dataset.editPartner'),
    'The searchable multi-partner experience is incomplete'
);
agreementFormAssert(
    !str_contains($form, '<label for="geographic_scope"')
        && str_contains($form, 'data-derived-partner-scope')
        && str_contains($javascript, 'derivedPartnerScope')
        && str_contains($agreementService, 'withDerivedPartnerScope'),
    'Partner scope must be derived from selected partner countries'
);
agreementFormAssert(
    str_contains($form, 'PUBLIC_GOVERNMENT')
        && str_contains($form, 'PRIVATE')
        && str_contains($form, 'ACADEMIC')
        && str_contains($form, 'NON_PROFIT')
        && str_contains($partnerRoutes, "\$method === 'PATCH'")
        && str_contains($javascript, 'AgreementApi.updatePartner'),
    'Controlled partner types or audited existing-partner editing is incomplete'
);
agreementFormAssert(
    !str_contains($form, 'Use Ctrl')
        && !str_contains($form, 'size="5"'),
    'The inaccessible legacy multi-select instructions remain'
);
agreementFormAssert(
    str_contains($form, 'data-date-range')
        && str_contains($form, 'data-start-target="start_date"')
        && str_contains($form, 'data-start-target="program_start_date"')
        && str_contains($form, 'id="start_date" name="start_date" type="date"')
        && str_contains($form, 'id="end_date" name="end_date" type="date"'),
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
    str_contains($form, 'data-form-section')
        && str_contains($form, 'data-expand-all')
        && str_contains($form, 'data-collapse-all')
        && substr_count($form, 'data-section-number="') === 1
        && str_contains($form, "        10,\n        'media'")
        && str_contains($javascript, 'maybeAdvanceSection')
        && str_contains($styles, '.agreement-section-toggle'),
    'The guided collapsible section workflow is incomplete'
);
agreementFormAssert(
    str_contains($javascript, 'program_applicant_name')
        && str_contains($javascript, 'refreshProgramSuggestions')
        && str_contains($javascript, 'selectedPartnersHaveRequiredCountries'),
    'Applicant autofill, suggestions, or international-country validation is missing'
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
    str_contains($agreementService, 'validatePartnerSelection')
        && str_contains(
            $agreementService,
            'Every selected partner must have a country'
        ),
    'Partner countries are not enforced by the API'
);
agreementFormAssert(
    str_contains($agreementRoutes, '/agreement-document-extraction')
        && str_contains($documentStorage, "'mp4'")
        && str_contains($documentStorage, "'webp'")
        && str_contains($documentRepository, 'hasDocumentType')
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
    preg_match(
        '/^[ \t]*(BEGIN|START[ \t]+TRANSACTION|COMMIT|ROLLBACK)[ \t]*;/mi',
        $fixedTermMigration
    ) === 0,
    'The fixed-term migration must rely on the database manager transaction'
);

echo "Agreement form experience smoke test passed.\n";
