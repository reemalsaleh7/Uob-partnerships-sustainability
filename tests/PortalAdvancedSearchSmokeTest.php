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
        && str_contains($agreementScript, "creator: (agreement)")
        && str_contains($agreementScript, "origin: (agreement)")
        && str_contains($agreementScript, "updated: 'updated_at'"),
    'Agreement search is missing partner, creator, origin, or updated-date fields'
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
