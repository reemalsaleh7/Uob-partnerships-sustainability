<?php

declare(strict_types=1);

function portalSearchAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function portalSearchSource(string $relativePath): string
{
    $source = file_get_contents(
        dirname(__DIR__) . '/' . ltrim($relativePath, '/')
    );
    portalSearchAssert($source !== false, "Could not read {$relativePath}");
    return $source;
}

$search = portalSearchSource(
    'uob-agreements/workspace/assets/js/advanced-search.js'
);
$agreementPage = portalSearchSource(
    'uob-agreements/workspace/agreements.php'
);
$agreementScript = portalSearchSource(
    'uob-agreements/workspace/assets/js/agreements.js'
);
$agreementDetailScript = portalSearchSource(
    'uob-agreements/workspace/assets/js/agreement-detail.js'
);
$apiClient = portalSearchSource(
    'uob-agreements/workspace/assets/js/api-client.js'
);
$workspaceLayout = portalSearchSource(
    'uob-agreements/workspace/includes/layout.php'
);
$lifecyclePage = portalSearchSource(
    'uob-agreements/workspace/lifecycle-requests.php'
);
$lifecycleScript = portalSearchSource(
    'uob-agreements/workspace/assets/js/lifecycle-requests.js'
);
$reportPage = portalSearchSource(
    'uob-agreements/workspace/performance-reports.php'
);
$reportScript = portalSearchSource(
    'uob-agreements/workspace/assets/js/performance-reports.js'
);
$agreementRepository = portalSearchSource(
    'repositories/AgreementRepository.php'
);
$lifecycleRepository = portalSearchSource(
    'repositories/AgreementLifecycleRepository.php'
);

foreach ([$agreementPage, $lifecyclePage, $reportPage] as $page) {
    portalSearchAssert(
        str_contains($page, 'assets/js/advanced-search.js'),
        'A searchable portal register is missing the shared query parser'
    );
    portalSearchAssert(
        str_contains($page, 'Advanced search')
            && str_contains($page, 'Clear filters'),
        'A searchable portal register is missing its advanced filter controls'
    );
    portalSearchAssert(
        str_contains($page, 'data-advanced-search-toggle')
            && str_contains($page, 'data-advanced-search-panel')
            && str_contains($page, 'data-search-rule-builder')
            && str_contains($page, 'Add condition')
            && str_contains($page, 'no search commands required'),
        'Search does not use progressive disclosure and a plain-language rule builder'
    );
}

portalSearchAssert(
    str_contains($search, 'function tokenize')
        && str_contains($search, "token.startsWith('-')")
        && str_contains($search, "rawValue.split('|')")
        && str_contains($search, 'const comparison = token.match'),
    'Advanced query syntax does not support phrases, exclusions, OR, and comparisons'
);
portalSearchAssert(
    str_contains($search, 'function matchesRules')
        && str_contains($search, 'function createFilterController')
        && str_contains($search, "mode === 'any'")
        && str_contains($search, 'data-rule-field')
        && str_contains($search, 'data-advanced-filter-count'),
    'The accessible visual query builder or all/any condition matching is missing'
);

foreach ([$agreementScript, $lifecycleScript, $reportScript] as $script) {
    portalSearchAssert(
        str_contains($script, 'UobAdvancedSearch.matches')
            && str_contains($script, 'UobAdvancedSearch.inDateRange'),
        'A portal register is not using both structured query and date filtering'
    );
    portalSearchAssert(
        str_contains($script, 'UobAdvancedSearch.createFilterController')
            && str_contains($script, 'advancedFilters.matchesRules')
            && str_contains($script, 'advancedFilters.clearRules'),
        'A portal register is not connected to the visual search-rule builder'
    );
}

portalSearchAssert(
    str_contains($agreementScript, "partner: (agreement)")
        && str_contains($agreementScript, "description: 'description'")
        && str_contains($agreementScript, "objectives: 'objectives'")
        && str_contains($agreementScript, "impact: 'expected_value'")
        && str_contains($agreementScript, "contact_email: (agreement)")
        && str_contains($agreementScript, "programme_outputs: (agreement)")
        && str_contains($agreementScript, "metric_planned: (agreement)"),
    'Agreement search is missing fields that are present in the Agreement form'
);
portalSearchAssert(
    str_contains($agreementPage, 'agreement-title')
        && str_contains($agreementPage, 'agreement-title-ar')
        && str_contains($agreementPage, 'agreement-type')
        && str_contains($agreementPage, 'agreement-partner')
        && str_contains($agreementPage, 'agreement-partner-type')
        && str_contains($agreementPage, 'agreement-partner-country')
        && str_contains($agreementPage, 'agreement-description')
        && str_contains($agreementPage, 'agreement-objectives')
        && str_contains($agreementPage, 'agreement-start-from')
        && str_contains($agreementPage, 'agreement-end-to')
        && str_contains($agreementPage, 'agreement-fixed-term-min')
        && str_contains($agreementPage, 'agreement-renewal-term-max')
        && str_contains($agreementPage, 'agreement-financial')
        && str_contains($agreementPage, 'agreement-financial-min')
        && str_contains($agreementPage, 'agreement-human-resources')
        && str_contains($agreementPage, 'agreement-training')
        && str_contains($agreementPage, 'agreement-annual-report')
        && str_contains($agreementPage, 'agreement-auto-renew')
        && str_contains($agreementPage, 'agreement-sdg'),
    'The Agreement register is missing form-aligned precision filters'
);
portalSearchAssert(
    // Match actual form controls, not similarly named workflow/view attributes
    // such as data-agreement-scope on the register's navigation buttons.
    !str_contains($agreementPage, 'id="agreement-status"')
        && !str_contains($agreementPage, 'id="agreement-code"')
        && !str_contains($agreementPage, 'id="agreement-origin"')
        && !str_contains($agreementPage, 'id="agreement-creator"')
        && !str_contains($agreementPage, 'id="agreement-unit"')
        && !str_contains($agreementPage, 'id="agreement-scope"')
        && !str_contains($agreementPage, 'id="agreement-updated-from"')
        && !str_contains($agreementPage, 'id="agreement-legal-status"')
        && !str_contains($agreementScript, "{ key: 'status'")
        && !str_contains($agreementScript, "{ key: 'code'")
        && !str_contains($agreementScript, "{ key: 'origin'")
        && !str_contains($agreementScript, "{ key: 'creator'")
        && !str_contains($agreementScript, "{ key: 'unit'")
        && !str_contains($agreementScript, "{ key: 'scope'")
        && !str_contains($agreementScript, "{ key: 'effective'")
        && !str_contains($agreementScript, "{ key: 'signing'")
        && !str_contains($agreementScript, "{ key: 'updated'"),
    'Retired or system-only fields remain exposed as Agreement form filters'
);
portalSearchAssert(
    str_contains($agreementPage, 'agreement-register-wrap')
        && str_contains($agreementPage, 'agreement-register-table')
        && str_contains($agreementPage, 'Project type')
        && str_contains($agreementPage, 'Project period')
        && !str_contains($agreementPage, '<th scope="col">Relationship</th>')
        && !str_contains($agreementPage, '<th scope="col">Status</th>')
        && !str_contains($agreementPage, '<th scope="col">Updated</th>')
        && !str_contains($agreementScript, 'createStatusBadge')
        && !str_contains($agreementScript, 'createRecordOriginBadge')
        && !str_contains($agreementScript, 'agreement-owner-badge')
        && str_contains($agreementScript, 'agreement-primary-cell')
        && str_contains($agreementScript, 'agreement-register-title'),
    'The Agreement register does not use the approved redesign style or still exposes removed fields'
);
portalSearchAssert(
    str_contains($agreementScript, 'booleanFilterMatches')
        && str_contains($agreementScript, 'numberRangeMatches')
        && str_contains($agreementScript, 'state.startFrom')
        && str_contains($agreementScript, 'state.endTo')
        && str_contains($agreementScript, 'state.description')
        && str_contains($agreementScript, "financial_amount: 'financial_amount'"),
    'The new Agreement precision fields are not connected to search behavior'
);
portalSearchAssert(
    str_contains($search, "type === 'select'")
        && str_contains($search, "type === 'boolean'")
        && str_contains($search, "type === 'number'")
        && str_contains($search, "type === 'date'")
        && str_contains($search, 'createRuleValueControl')
        && str_contains($agreementScript, "type: 'boolean'")
        && str_contains($agreementScript, "type: 'select'")
        && str_contains($agreementScript, "type: 'number'")
        && str_contains($agreementScript, "type: 'date'"),
    'The precise search builder does not preserve form field types'
);
portalSearchAssert(
    str_contains($agreementRepository, 'AS partner_country')
        && str_contains($agreementRepository, 'AS sdgs')
        && str_contains($agreementRepository, 'AS contacts')
        && str_contains($agreementRepository, 'AS executive_programs')
        && str_contains($agreementRepository, 'AS metrics')
        && str_contains($agreementRepository, "'fixed_term_months'"),
    'The Agreement register API is missing searchable current-form data'
);
portalSearchAssert(
    str_contains($apiClient, 'function createRecordOriginBadge')
        && str_contains($apiClient, 'createRecordOriginBadge,')
        && str_contains(
            $agreementDetailScript,
            'AgreementApi.createRecordOriginBadge'
        ),
    'The shared Agreement API does not expose the record-origin badge helper'
);
portalSearchAssert(
    str_contains($workspaceLayout, 'function workspaceVersionedAsset')
        && str_contains($workspaceLayout, 'filemtime($absolutePath)')
        && str_contains(
            $workspaceLayout,
            "workspaceVersionedAsset('assets/css/workspace.css')"
        ),
    'Workspace assets are missing automatic cache-version URLs'
);
portalSearchAssert(
    str_contains($lifecycleScript, "termination: (request)")
        && str_contains($lifecycleScript, "requester: (request)"),
    'Lifecycle request search is missing request-specific fields'
);
portalSearchAssert(
    str_contains($reportScript, "reviewer: (report)")
        && str_contains($reportScript, "overdue: (report)"),
    'Performance report search is missing reviewer or overdue fields'
);

portalSearchAssert(
    str_contains($agreementRepository, 'AS creator_name')
        && str_contains($agreementRepository, 'a.created_by = :creator_user_id')
        && str_contains($agreementRepository, 'wsa.user_id = :reviewer_user_id'),
    'Agreement search metadata or authorization-sensitive visibility is missing'
);
portalSearchAssert(
    str_contains($lifecycleRepository, 'AS requester_name')
        && str_contains($lifecycleRepository, 'lr.requested_by = :requester_user_id')
        && str_contains($lifecycleRepository, 'wsa.user_id = :reviewer_user_id'),
    'Lifecycle search metadata or authorization-sensitive visibility is missing'
);

echo "Portal advanced search smoke test passed.\n";
