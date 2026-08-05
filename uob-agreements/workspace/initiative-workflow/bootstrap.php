<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/layout.php';

$view = strtolower(trim((string) ($_GET['view'] ?? 'list')));
if ($view === 'legacy') {
    $view = 'existing';
}

$allowedViews = ['list', 'form', 'detail', 'notifications', 'convert', 'existing'];

if (!in_array($view, $allowedViews, true)) {
    http_response_code(404);
    $view = 'list';
}

$titles = [
    'list' => 'Initiative requests',
    'form' => 'Initiative request form',
    'detail' => 'Initiative request',
    'notifications' => 'Initiative notifications',
    'convert' => 'Create Final Initiative',
    'existing' => 'Register Existing Initiative',
];

workspaceHeader($titles[$view], 'initiatives');

echo '<link href="initiative-workflow/assets/module.css?v=20260804-final-conditional-v2" rel="stylesheet">';

// INITIATIVE_REVISION_AUDIENCE_ASSETS_V1
echo '<link href="initiative-workflow/assets/revision-audience.css?v=20260805-audience2" rel="stylesheet">';

require __DIR__ . '/views.php';

$initiativeScripts = [
    'initiative-workflow/assets/module.js?v=20260805-audience-runtime2',
    // INITIATIVE_REVISION_AUDIENCE_ASSETS_V1
    'initiative-workflow/assets/revision-audience.js?v=20260805-audience-runtime2',
];

if ($view === 'form') {
    $mapsConfig = require dirname(__DIR__)
        . '/config/google-maps.php';
    $mapsApiKey = trim((string) ($mapsConfig['api_key'] ?? ''));
    $workspaceLanguage = workspaceResolveLanguage();

    $locationSearchConfig = json_encode(
        [
            'apiKeyConfigured' => $mapsApiKey !== '',
            'language' => $workspaceLanguage,
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    );

    echo '<script>window.InitiativeLocationSearchConfig = '
        . $locationSearchConfig
        . ';</script>';

    $initiativeScripts[] =
        'initiative-workflow/assets/location-search.js'
        . '?v=20260802-phase17q';

    if ($mapsApiKey !== '') {
        $mapsQuery = http_build_query(
            [
                'key' => $mapsApiKey,
                'loading' => 'async',
                'callback' => 'initiativeLocationSearchReady',
                'v' => 'weekly',
                'libraries' => 'places',
                'language' => $workspaceLanguage,
                'region' => 'BH',
                'auth_referrer_policy' => 'origin',
            ],
            '',
            '&',
            PHP_QUERY_RFC3986
        );

        $initiativeScripts[] =
            'https://maps.googleapis.com/maps/api/js?'
            . $mapsQuery;
    }
}

workspaceFooter($initiativeScripts);
