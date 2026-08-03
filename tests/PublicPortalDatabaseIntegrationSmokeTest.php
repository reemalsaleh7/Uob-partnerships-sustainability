<?php

declare(strict_types=1);

function publicPortalAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function publicPortalSource(string $relativePath): string
{
    $path = dirname(__DIR__) . '/' . ltrim($relativePath, '/');
    $source = file_get_contents($path);
    publicPortalAssert($source !== false, "Could not read {$relativePath}");

    return $source;
}

$functions = publicPortalSource('uob-agreements/includes/functions.php');
$repository = publicPortalSource(
    'repositories/PublicAgreementRepository.php'
);
$home = publicPortalSource('uob-agreements/index.php');
$mapApi = publicPortalSource(
    'uob-agreements/partnership/agreements-api.php'
);
$map = publicPortalSource('uob-agreements/partnership/partners.php');
$header = publicPortalSource('uob-agreements/header.php');
$legacyLogin = publicPortalSource('uob-agreements/login.php');
$workspaceLogin = publicPortalSource(
    'uob-agreements/workspace/assets/js/login.js'
);

publicPortalAssert(
    str_contains($functions, 'new PublicAgreementRepository()')
        && str_contains($functions, "'_source' => 'database'")
        && str_contains($repository, "a.status IN ('APPROVED', 'ACTIVE')"),
    'The public Agreement catalogue is not backed by published database rows'
);
publicPortalAssert(
    str_contains($home, '$agreements = readAgreements(true);')
        && !str_contains(
            $home,
            "readCsvRows(__DIR__ . '/data/agreements.csv')"
        ),
    'The public home page bypasses the database-backed Agreement catalogue'
);
publicPortalAssert(
    str_contains($mapApi, 'readAgreements(true)')
        && str_contains($mapApi, 'publicMapCountryCentres')
        && str_contains($mapApi, "'Status'")
        && str_contains($map, 'agreement["Status"]'),
    'The partnership map does not include current database Agreements'
);
publicPortalAssert(
    str_contains($header, 'workspace/login.php')
        && str_contains($legacyLogin, 'workspace/login.php')
        && str_contains($workspaceLogin, "window.location.replace('index.php')"),
    'Public login does not use the real styled login and overview destination'
);

echo "Public portal database integration smoke test passed.\n";
