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
$documentStorage = agreementFormSource(
    'services/DocumentStorageService.php'
);
$migration = agreementFormSource(
    'uob-agreements/data/sql/migrations/20260728_153000_agreement_form_partner_profiles.sql'
);

agreementFormAssert(
    str_contains($form, 'Type of cooperative project')
        && str_contains($form, 'data-partner-search')
        && str_contains($form, 'data-selected-partners')
        && str_contains($form, 'data-show-new-partner'),
    'The searchable multi-partner experience is incomplete'
);
agreementFormAssert(
    !str_contains($form, 'Use Ctrl')
        && !str_contains($form, 'size="5"'),
    'The inaccessible legacy multi-select instructions remain'
);
agreementFormAssert(
    str_contains($form, 'data-date-range')
        && str_contains($form, 'data-start-target="start_date"')
        && str_contains($form, 'data-start-target="program_start_date"'),
    'Project and programme range calendars are missing'
);
agreementFormAssert(
    !str_contains($form, 'THE Impact Rankings')
        && !str_contains($form, 'UI GreenMetric'),
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
        && str_contains($form, 'data-document-type="MEDIA"'),
    'Clause extraction or queued media uploads are missing'
);
agreementFormAssert(
    str_contains($form, 'data-form-section')
        && str_contains($form, 'data-expand-all')
        && str_contains($form, 'data-collapse-all')
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
        && str_contains($migration, 'ADD COLUMN IF NOT EXISTS profile'),
    'Partner profile storage and controlled creation are incomplete'
);
agreementFormAssert(
    str_contains($agreementService, 'validatePartnerSelection')
        && str_contains(
            $agreementService,
            'Every international partner must have a country'
        ),
    'International partner countries are not enforced by the API'
);
agreementFormAssert(
    str_contains($agreementRoutes, '/agreement-document-extraction')
        && str_contains($documentStorage, "'mp4'")
        && str_contains($documentStorage, "'webp'"),
    'Clause extraction routing or secure media validation is incomplete'
);

echo "Agreement form experience smoke test passed.\n";
