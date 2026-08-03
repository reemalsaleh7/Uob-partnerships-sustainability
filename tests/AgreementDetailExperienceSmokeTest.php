<?php

declare(strict_types=1);

function agreementDetailAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function agreementDetailSource(string $relativePath): string
{
    $source = file_get_contents(dirname(__DIR__) . '/' . $relativePath);
    agreementDetailAssert(
        $source !== false,
        "Could not read {$relativePath}"
    );
    return $source;
}

$page = agreementDetailSource('uob-agreements/workspace/agreement.php');
$detailJavascript = agreementDetailSource(
    'uob-agreements/workspace/assets/js/agreement-detail.js'
);
$documentJavascript = agreementDetailSource(
    'uob-agreements/workspace/assets/js/agreement-documents.js'
);
$operationJavascript = agreementDetailSource(
    'uob-agreements/workspace/assets/js/agreement-operations.js'
);
$layout = agreementDetailSource(
    'uob-agreements/workspace/includes/layout.php'
);
$operationService = agreementDetailSource(
    'services/AgreementOperationService.php'
);
$apiClient = agreementDetailSource(
    'uob-agreements/workspace/assets/js/api-client.js'
);
$repository = agreementDetailSource('repositories/AgreementRepository.php');
$controller = agreementDetailSource('controllers/AgreementController.php');
$routes = agreementDetailSource('routes/agreements.php');

agreementDetailAssert(
    !str_contains($page, 'data-field="effective_date"')
        && !str_contains($page, 'data-field="signing_date"')
        && !str_contains($page, 'data-signing-field="effective_date"')
        && !str_contains($page, 'data-signing-field="signing_date"')
        && !str_contains($page, 'data-final-effective-date')
        && !str_contains($page, 'data-final-signing-date')
        && !str_contains($page, 'data-field="termination_notice_months"')
        && !str_contains($page, 'data-field="signing_link"'),
    'Fields removed from the Agreement form are still exposed on Agreement details'
);

agreementDetailAssert(
    !str_contains($operationJavascript, 'elements.signingDate')
        && !str_contains($operationJavascript, 'elements.effectiveDate')
        && !str_contains($operationJavascript, 'data-final-signing-date')
        && !str_contains($operationJavascript, 'data-final-effective-date')
        && !str_contains($operationJavascript, 'signing_date:')
        && !str_contains($operationJavascript, 'effective_date:')
        && str_contains($operationService, "new DateTimeImmutable('today')")
        && str_contains($operationService, '$agreement[\'start_date\']')
        && !str_contains($operationService, '$input[\'signing_date\']')
        && !str_contains($operationService, '$input[\'effective_date\']')
        && str_contains($layout, 'workspaceVersionedAsset')
        && str_contains($layout, 'filemtime($absolutePath)'),
    'Signing finalization still reads removed date controls or can serve stale JavaScript'
);

agreementDetailAssert(
    substr_count($layout, "'apiClientScript' => 'assets/js/api-client.js'") === 1
        && substr_count(
            $layout,
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js'
        ) === 1
        && !str_contains($layout, 'api-client.js?v=20260722-showcase-data')
        && !str_contains($layout, 'api-client.js?v=20260802-agreement-detail-v2')
        && str_contains($layout, 'workspaceVersionedAsset($asset)')
        && str_contains(
            $detailJavascript,
            "typeof AgreementApi.lifecycleRequestsForAgreement === 'function'"
        )
        && str_contains(
            $detailJavascript,
            "typeof AgreementApi.lifecycleRequests === 'function'"
        ),
    'Shared scripts can be duplicated, cached under stale keys, or break lifecycle history compatibility'
);

require_once dirname(__DIR__) . '/services/AgreementOperationService.php';
$operationServiceInstance = (new ReflectionClass(AgreementOperationService::class))
    ->newInstanceWithoutConstructor();
$validateSigningInput = Closure::bind(
    function (array $agreement, array $input): array {
        return $this->validateSigningInput($agreement, 7, $input);
    },
    $operationServiceInstance,
    AgreementOperationService::class
);
$derivedSigningData = $validateSigningInput(
    ['start_date' => '2099-01-01', 'partner_ids' => [42]],
    [
        'signing_date' => '1900-01-01',
        'effective_date' => '1900-01-01',
        'expiry_date' => '2099-12-31',
        'signed_document_id' => 8,
        'signatories' => [
            [
                'party_type' => 'UOB',
                'full_name' => 'UOB Representative',
                'job_title' => 'Authorized Signatory',
                'organization_name' => 'University of Bahrain',
            ],
            [
                'party_type' => 'PARTNER',
                'partner_id' => 42,
                'full_name' => 'Partner Representative',
                'job_title' => 'Authorized Signatory',
                'organization_name' => 'Partner Organization',
            ],
        ],
    ]
);
agreementDetailAssert(
    $derivedSigningData['signing_date'] === (new DateTimeImmutable('today'))->format('Y-m-d')
        && $derivedSigningData['effective_date'] === '2099-01-01',
    'Signing/effective dates were not derived securely by the server'
);

agreementDetailAssert(
    substr_count($page, ' data-mou-preview>') === 1
        && str_contains($page, 'agreement-mou-card')
        && !str_contains($page, '>MOU clauses<')
        && strpos($page, 'data-mou-preview') < strpos($page, 'agreementDocumentsPanel')
        && str_contains($page, 'data-mou-text-preview')
        && str_contains($documentJavascript, 'latestMouDocument')
        && str_contains($documentJavascript, 'documents.forEach((document)')
        && str_contains($documentJavascript, 'renderMouPreview(')
        && str_contains($documentJavascript, 'AgreementApi.previewDocument')
        && str_contains($apiClient, 'previewDocument(id)')
        && str_contains($controller, 'previewStoredDocx')
        && str_contains($routes, '/preview$#'),
    'The protected MOU preview flow is incomplete'
);

agreementDetailAssert(
    str_contains($page, 'data-lifecycle-history-section')
        && !str_contains(
            $page,
            'class="workspace-card mt-4 d-none" aria-labelledby="lifecycle-history-title"'
        )
        && str_contains($page, 'data-lifecycle-history-empty')
        && str_contains($page, 'data-lifecycle-history-table')
        && substr_count($page, 'data-lifecycle-request') >= 2
        && str_contains($detailJavascript, 'renderLifecycleHistory')
        && str_contains($detailJavascript, '.catch((error) => ({ error }))')
        && str_contains($apiClient, 'lifecycleRequestsForAgreement'),
    'Past lifecycle requests are not integrated into Agreement details'
);

foreach (
    [
        'p.address',
        'p.profile',
        'p.website',
        'p.email',
        'p.phone',
        'p.logo_url',
        'p.latitude',
        'p.longitude',
        'p.is_active',
        'partner_contacts',
    ] as $requiredPartnerSource
) {
    agreementDetailAssert(
        str_contains($repository, $requiredPartnerSource),
        "Complete partner data is missing {$requiredPartnerSource}"
    );
}

foreach (
    [
        "addDetail('Type', partner.partner_type)",
        "addDetail('Country', partner.country)",
        "addDetail('City', partner.city)",
        "addDetail('Address', partner.address)",
        "addDetail('Brief profile', partner.profile)",
        'partner.logo_url',
        'partner.contacts',
    ] as $requiredPartnerRendering
) {
    agreementDetailAssert(
        str_contains($detailJavascript, $requiredPartnerRendering),
        "Complete partner rendering is missing {$requiredPartnerRendering}"
    );
}

agreementDetailAssert(
    class_exists(ZipArchive::class),
    'The PHP Zip extension is required for the MOU preview smoke test'
);

$temporaryBase = tempnam(sys_get_temp_dir(), 'uob-mou-preview-');
agreementDetailAssert(
    is_string($temporaryBase) && $temporaryBase !== '',
    'Could not allocate the temporary MOU preview file'
);
@unlink($temporaryBase);
$temporaryDocx = $temporaryBase . '.docx';
$archive = new ZipArchive();
agreementDetailAssert(
    $archive->open($temporaryDocx, ZipArchive::CREATE) === true,
    'Could not create the temporary MOU preview file'
);
$archive->addFromString(
    '[Content_Types].xml',
    '<?xml version="1.0" encoding="UTF-8"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '</Types>'
);
$archive->addFromString(
    'word/document.xml',
    '<?xml version="1.0" encoding="UTF-8"?>'
        . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:body><w:p><w:r><w:t>مذكرة تفاهم بين جامعة البحرين والطرف الثاني</w:t>'
        . '</w:r></w:p></w:body></w:document>'
);
$archive->close();

try {
    require_once dirname(__DIR__)
        . '/services/AgreementClauseExtractionService.php';
    $preview = (new AgreementClauseExtractionService())
        ->previewStoredDocx($temporaryDocx);
    agreementDetailAssert(
        ($preview['language'] ?? null) === 'ar'
            && ($preview['direction'] ?? null) === 'rtl'
            && str_contains(
                (string) ($preview['text'] ?? ''),
                'مذكرة تفاهم'
            ),
        'The stored Arabic DOCX was not rendered as a readable RTL preview'
    );
} finally {
    @unlink($temporaryDocx);
}

echo "Agreement detail experience smoke test passed.\n";
