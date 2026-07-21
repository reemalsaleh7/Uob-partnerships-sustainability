<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/LegacyAgreementCsvMapper.php';
require_once __DIR__ . '/../repositories/AgreementRepository.php';
require_once __DIR__ . '/../repositories/AgreementVersionRepository.php';
require_once __DIR__ . '/../repositories/AuditRepository.php';

const LEGACY_AGREEMENT_EXPECTED_ROWS = 41;
const LEGACY_AGREEMENT_EXPECTED_DATASET_SHA256 =
    'ae39999ed83aef0e98102e95458648309b2fc05898897fb0a5744d7821ce6421';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This importer may be run only from the command line.\n");
    exit(1);
}

$options = getopt('', ['dry-run', 'commit', 'creator-email:', 'report:', 'help']);
if (isset($options['help'])) {
    echo <<<TEXT
Usage:
  php scripts/import_legacy_agreements.php --dry-run [--creator-email=EMAIL] [--report=PATH]
  php scripts/import_legacy_agreements.php --commit  [--creator-email=EMAIL] [--report=PATH]

The command imports only uob-agreements/data/agreements.csv. Dry-run is the
default. Commit is all-or-nothing and refuses unresolved duplicates or invalid
rows. The archived agreementsold.csv is intentionally excluded.
TEXT;
    exit(0);
}

$commit = isset($options['commit']);
if ($commit && isset($options['dry-run'])) {
    fwrite(STDERR, "Choose either --dry-run or --commit, not both.\n");
    exit(1);
}

$sourcePath = __DIR__ . '/../uob-agreements/data/agreements.csv';
$sourceFile = basename($sourcePath);
$reportPath = isset($options['report']) ? trim((string) $options['report']) : null;
$creatorEmail = isset($options['creator-email']) ? trim((string) $options['creator-email']) : null;
$batchId = legacyImportUuid();
$report = [
    'mode' => $commit ? 'commit' : 'dry-run',
    'batch_id' => $batchId,
    'source_file' => $sourceFile,
    'source_sha256' => is_file($sourcePath) ? hash_file('sha256', $sourcePath) : null,
    'canonical_dataset_sha256' => null,
    'creator' => null,
    'counts' => [
        'total_rows' => 0,
        'ready' => 0,
        'skipped' => 0,
        'conflicts' => 0,
        'invalid' => 0,
        'warning_messages' => 0,
        'partners_ready_to_create' => 0,
        'agreements_imported' => 0,
        'partners_created' => 0,
    ],
    'rows' => [],
    'completed_at' => null,
];

try {
    [$headers, $sourceRows] = legacyImportReadCsv($sourcePath);
    $missingHeaders = array_values(array_diff(LegacyAgreementCsvMapper::REQUIRED_HEADERS, $headers));
    if ($missingHeaders !== []) {
        throw new RuntimeException('CSV is missing required headers: ' . implode(', ', $missingHeaders));
    }
    $canonicalHashes = array_map(
        static fn (array $sourceRow): string => LegacyAgreementCsvMapper::canonicalRowHash(
            $sourceRow['data']
        ),
        $sourceRows
    );
    $datasetHash = hash('sha256', implode("\n", $canonicalHashes));
    $report['canonical_dataset_sha256'] = $datasetHash;
    if (count($sourceRows) !== LEGACY_AGREEMENT_EXPECTED_ROWS
        || !hash_equals(LEGACY_AGREEMENT_EXPECTED_DATASET_SHA256, $datasetHash)) {
        throw new RuntimeException(
            'agreements.csv differs from the reviewed 41-row dataset; import was refused.'
        );
    }

    $db = Database::connect();
    $db->beginTransaction();
    $db->query("SELECT pg_advisory_xact_lock(hashtext('uob_legacy_agreement_import'))");
    legacyImportRequireTrackingTable($db);

    $creator = legacyImportResolveCreator($db, $creatorEmail);
    $report['creator'] = [
        'user_id' => (int) $creator['user_id'],
        'email' => $creator['email'],
    ];

    $tracking = legacyImportTracking($db, $sourceFile);
    $agreements = legacyImportExistingAgreements($db);
    $partners = legacyImportExistingPartners($db);
    $partnerPreflightMap = legacyImportPartnerMap($partners);
    $units = legacyImportUnits($db);
    $seenCodes = [];
    $seenFingerprints = [];
    $candidates = [];

    foreach ($sourceRows as $sourceRow) {
        $rowNumber = $sourceRow['row_number'];
        $raw = $sourceRow['data'];
        $mapped = LegacyAgreementCsvMapper::map($raw, $rowNumber);
        $agreement = $mapped['agreement'];
        $code = $agreement['agreement_code'];
        $sourceRecordId = $agreement['source_record_id'];
        $partnerNames = array_column($mapped['partners'], 'organization_name');
        $fingerprint = LegacyAgreementCsvMapper::fingerprint(
            $agreement['title'],
            $partnerNames,
            $agreement['start_date']
        );
        $sourceHash = LegacyAgreementCsvMapper::canonicalRowHash($raw);
        $errors = $mapped['errors'];
        $warnings = $mapped['warnings'];
        $action = 'ready';
        $existingAgreementId = null;

        $tracked = $tracking['rows'][$rowNumber] ?? $tracking['source_ids'][$sourceRecordId] ?? null;
        if ($tracked !== null) {
            $existingAgreementId = (int) $tracked['agreement_id'];
            if (hash_equals((string) $tracked['source_hash'], $sourceHash)) {
                $action = 'skipped';
            } else {
                $action = 'conflict';
                $errors[] = 'Previously imported source row has changed; automatic overwrite is forbidden.';
            }
        } elseif (isset($agreements['codes'][$code])) {
            $action = 'conflict';
            $existingAgreementId = (int) $agreements['codes'][$code]['agreement_id'];
            $errors[] = 'agreement_code already belongs to an untracked PostgreSQL Agreement.';
        } elseif ($sourceRecordId !== null && isset($agreements['source_ids'][$sourceRecordId])) {
            $action = 'conflict';
            $existingAgreementId = (int) $agreements['source_ids'][$sourceRecordId]['agreement_id'];
            $errors[] = 'source_record_id already belongs to an untracked PostgreSQL Agreement.';
        } elseif (isset($agreements['fingerprints'][$fingerprint])) {
            $action = 'conflict';
            $existingAgreementId = (int) $agreements['fingerprints'][$fingerprint]['agreement_id'];
            $errors[] = 'Normalized title, partners, and start date match an existing PostgreSQL Agreement.';
        } elseif (isset($seenCodes[$code])) {
            $action = 'invalid';
            $errors[] = 'Duplicate agreement_code appears inside the source CSV.';
        } elseif (isset($seenFingerprints[$fingerprint])) {
            $action = 'invalid';
            $errors[] = 'Duplicate normalized title, partners, and start date appear inside the source CSV.';
        } elseif ($errors !== []) {
            $action = 'invalid';
        }

        $unitId = legacyImportResolveUnit($units, $agreement['legacy_owner_entity']);
        if ($unitId === null && $agreement['legacy_owner_entity'] !== null
            && LegacyAgreementCsvMapper::fingerprint($agreement['legacy_owner_entity'], [], null)
                !== LegacyAgreementCsvMapper::fingerprint('Not specified', [], null)) {
            $warnings[] = 'Owner entity did not match an existing organizational unit; raw value remains in import history.';
        }

        if ($action === 'ready') {
            $pendingPartners = [];
            foreach ($mapped['partners'] as $partner) {
                $partnerKey = legacyImportPartnerKey(
                    $partner['organization_name'],
                    $partner['country']
                );
                if (isset($partnerPreflightMap[$partnerKey]) || isset($pendingPartners[$partnerKey])) {
                    continue;
                }
                if (legacyImportPartnersWithName(
                    $partnerPreflightMap + $pendingPartners,
                    $partner['organization_name']
                ) !== []) {
                    $action = 'conflict';
                    $errors[] = 'Partner name exists with a different country: '
                        . $partner['organization_name'];
                    break;
                }
                $pendingPartners[$partnerKey] = $partner + ['partner_id' => null];
            }
            if ($action === 'ready') {
                $partnerPreflightMap += $pendingPartners;
                $report['counts']['partners_ready_to_create'] += count($pendingPartners);
            }
        }

        $entry = [
            'row_number' => $rowNumber,
            'agreement_code' => $code,
            'source_record_id' => $sourceRecordId,
            'title' => $agreement['title'],
            'action' => $action,
            'existing_agreement_id' => $existingAgreementId,
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
        ];
        $report['rows'][] = $entry;
        $report['counts']['total_rows']++;
        $report['counts']['warning_messages'] += count($entry['warnings']);

        if ($action === 'ready') {
            $report['counts']['ready']++;
            $seenCodes[$code] = true;
            $seenFingerprints[$fingerprint] = true;
            $mapped['source_hash'] = $sourceHash;
            $mapped['raw'] = $raw;
            $mapped['responsible_unit_id'] = $unitId;
            $mapped['report_index'] = count($report['rows']) - 1;
            $candidates[] = $mapped;
        } elseif ($action === 'skipped') {
            $report['counts']['skipped']++;
        } elseif ($action === 'conflict') {
            $report['counts']['conflicts']++;
        } else {
            $report['counts']['invalid']++;
        }
    }

    if (!$commit) {
        $db->rollBack();
        $report['completed_at'] = date(DATE_ATOM);
        legacyImportWriteReport($report, $reportPath);
        exit(($report['counts']['conflicts'] + $report['counts']['invalid']) === 0 ? 0 : 2);
    }

    if ($report['counts']['conflicts'] > 0 || $report['counts']['invalid'] > 0) {
        $db->rollBack();
        $report['completed_at'] = date(DATE_ATOM);
        legacyImportWriteReport($report, $reportPath);
        fwrite(STDERR, "Import was not committed because the report contains conflicts or invalid rows.\n");
        exit(2);
    }

    $agreementRepository = new AgreementRepository();
    $versionRepository = new AgreementVersionRepository();
    $auditRepository = new AuditRepository();
    $partnerMap = legacyImportPartnerMap($partners);

    foreach ($candidates as $candidate) {
        $agreement = $candidate['agreement'];
        $agreementId = $agreementRepository->create([
            'title' => $agreement['title'],
            'title_ar' => $agreement['title_ar'],
            'agreement_type' => $agreement['agreement_type'],
            'description' => $agreement['description'],
            'geographic_scope' => $agreement['geographic_scope'],
            'start_date' => $agreement['start_date'],
            'end_date' => $agreement['end_date'],
            'auto_renew' => $agreement['auto_renew'],
            'responsible_unit_id' => $candidate['responsible_unit_id'],
            'focus_areas' => $agreement['focus_areas'],
            'signing_link' => $agreement['signing_link'],
            'created_by' => (int) $creator['user_id'],
            'status' => $agreement['status'],
        ]);

        $metadata = $db->prepare('UPDATE agreements SET
            agreement_code = :agreement_code,
            source_record_id = :source_record_id,
            created_at = COALESCE(CAST(:submitted_at AS TIMESTAMP), created_at)
            WHERE agreement_id = :agreement_id');
        $metadata->execute([
            'agreement_code' => $agreement['agreement_code'],
            'source_record_id' => $agreement['source_record_id'],
            'submitted_at' => $agreement['legacy_submitted_at'],
            'agreement_id' => $agreementId,
        ]);

        $partnerIds = [];
        foreach ($candidate['partners'] as $partner) {
            $key = legacyImportPartnerKey($partner['organization_name'], $partner['country']);
            if (isset($partnerMap[$key])) {
                $partnerIds[] = (int) $partnerMap[$key]['partner_id'];
                continue;
            }

            $sameName = legacyImportPartnersWithName($partnerMap, $partner['organization_name']);
            if ($sameName !== []) {
                throw new RuntimeException(
                    'Partner country conflict for ' . $partner['organization_name']
                    . '; no Agreement data was committed.'
                );
            }

            $insertPartner = $db->prepare('INSERT INTO partners (
                organization_name, partner_type, country, city, website,
                logo_url, latitude, longitude, is_active
            ) VALUES (
                :organization_name, :partner_type, :country, :city, :website,
                :logo_url, :latitude, :longitude, TRUE
            ) RETURNING partner_id');
            $insertPartner->execute($partner);
            $partnerId = (int) $insertPartner->fetchColumn();
            $partner['partner_id'] = $partnerId;
            $partnerMap[$key] = $partner;
            $partnerIds[] = $partnerId;
            $report['counts']['partners_created']++;
        }

        $agreementRepository->replacePartners($agreementId, $partnerIds);
        $agreementRepository->replaceSdgs($agreementId, $candidate['sdgs']);
        $agreementRepository->replaceRankings($agreementId, $candidate['rankings']);
        $agreementRepository->replaceMetrics($agreementId, $candidate['metrics']);

        $trackingInsert = $db->prepare('INSERT INTO agreement_legacy_imports (
            import_batch_id, source_file, source_row_number, source_record_id,
            source_hash, agreement_id, imported_by, source_payload, import_warnings
        ) VALUES (
            CAST(:import_batch_id AS UUID), :source_file, :source_row_number, :source_record_id,
            :source_hash, :agreement_id, :imported_by,
            CAST(:source_payload AS JSONB), CAST(:import_warnings AS JSONB)
        )');
        $trackingInsert->execute([
            'import_batch_id' => $batchId,
            'source_file' => $sourceFile,
            'source_row_number' => $candidate['row_number'],
            'source_record_id' => $agreement['source_record_id'],
            'source_hash' => $candidate['source_hash'],
            'agreement_id' => $agreementId,
            'imported_by' => (int) $creator['user_id'],
            'source_payload' => json_encode(
                $candidate['raw'],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ),
            'import_warnings' => json_encode(
                $candidate['warnings'],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ),
        ]);

        $snapshot = $agreementRepository->findById($agreementId);
        if ($snapshot === null) {
            throw new RuntimeException('Imported Agreement could not be reloaded: ' . $agreementId);
        }

        $versionRepository->create($agreementId, [
            'version_number' => 1,
            'change_summary' => 'Controlled import from ' . $sourceFile,
            'agreement_snapshot' => $snapshot,
            'created_by' => (int) $creator['user_id'],
        ]);
        $auditRepository->create([
            'table_name' => 'agreements',
            'record_id' => $agreementId,
            'action' => 'INSERT',
            'user_id' => (int) $creator['user_id'],
            'old_data' => null,
            'new_data' => $snapshot,
            'reason' => 'Controlled legacy CSV import from ' . $sourceFile
                . ' row ' . $candidate['row_number'],
            'ip_address' => null,
        ]);

        $reportIndex = $candidate['report_index'];
        $report['rows'][$reportIndex]['action'] = 'imported';
        $report['rows'][$reportIndex]['agreement_id'] = $agreementId;
        $report['counts']['agreements_imported']++;
    }

    $db->commit();
    $report['completed_at'] = date(DATE_ATOM);
    legacyImportWriteReport($report, $reportPath);
    exit(0);
} catch (Throwable $error) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    $report['fatal_error'] = $error->getMessage();
    $report['completed_at'] = date(DATE_ATOM);
    legacyImportWriteReport($report, $reportPath);
    fwrite(STDERR, "Legacy Agreement import failed: {$error->getMessage()}\n");
    exit(1);
}

function legacyImportReadCsv(string $path): array
{
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('CSV source is missing or unreadable: ' . $path);
    }
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('Could not open CSV source: ' . $path);
    }

    try {
        $headers = fgetcsv($handle, 0, ',', '"', '');
        if ($headers === false) {
            throw new RuntimeException('CSV source is empty.');
        }
        $headers = array_map(static function (string $header): string {
            return trim(preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header);
        }, $headers);
        if (count($headers) !== count(array_unique($headers))) {
            throw new RuntimeException('CSV contains duplicate header names.');
        }

        $rows = [];
        $rowNumber = 1;
        while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $rowNumber++;
            if ($values === [null] || $values === []) {
                continue;
            }
            if (count($values) !== count($headers)) {
                throw new RuntimeException("CSV row {$rowNumber} has an unexpected column count.");
            }
            $rows[] = [
                'row_number' => $rowNumber,
                'data' => array_combine($headers, $values),
            ];
        }
        return [$headers, $rows];
    } finally {
        fclose($handle);
    }
}

function legacyImportRequireTrackingTable(PDO $db): void
{
    $exists = $db->query("SELECT to_regclass('public.agreement_legacy_imports')")->fetchColumn();
    if ($exists === null || $exists === false) {
        throw new RuntimeException(
            'agreement_legacy_imports is missing. Apply 20260721_legacy_agreement_import_tracking.sql first.'
        );
    }
}

function legacyImportResolveCreator(PDO $db, ?string $email): array
{
    if ($email !== null && $email !== '') {
        $statement = $db->prepare('SELECT user_id, email FROM users
            WHERE lower(email) = lower(:email) AND is_active = TRUE');
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();
        if (!$user) {
            throw new RuntimeException('Active import creator was not found: ' . $email);
        }
        return $user;
    }

    $statement = $db->query("SELECT DISTINCT u.user_id, u.email
        FROM users u
        JOIN user_roles ur ON ur.user_id = u.user_id
        JOIN roles r ON r.role_id = ur.role_id
        WHERE r.role_name = 'System Administrator' AND u.is_active = TRUE
        ORDER BY u.user_id");
    $users = $statement->fetchAll();
    if (count($users) !== 1) {
        throw new RuntimeException(
            'Pass --creator-email because exactly one active System Administrator could not be resolved.'
        );
    }
    return $users[0];
}

function legacyImportTracking(PDO $db, string $sourceFile): array
{
    $statement = $db->prepare('SELECT source_row_number, source_record_id, source_hash, agreement_id
        FROM agreement_legacy_imports WHERE source_file = :source_file');
    $statement->execute(['source_file' => $sourceFile]);
    $result = ['rows' => [], 'source_ids' => []];
    foreach ($statement->fetchAll() as $row) {
        $result['rows'][(int) $row['source_row_number']] = $row;
        if ($row['source_record_id'] !== null) {
            $result['source_ids'][(string) $row['source_record_id']] = $row;
        }
    }
    return $result;
}

function legacyImportExistingAgreements(PDO $db): array
{
    $rows = $db->query("SELECT
            a.agreement_id, a.agreement_code, a.source_record_id, a.title,
            a.start_date::text AS start_date,
            COALESCE(array_agg(p.organization_name ORDER BY p.organization_name)
                FILTER (WHERE p.partner_id IS NOT NULL), ARRAY[]::varchar[]) AS partner_names
        FROM agreements a
        LEFT JOIN agreement_partners ap ON ap.agreement_id = a.agreement_id
        LEFT JOIN partners p ON p.partner_id = ap.partner_id
        GROUP BY a.agreement_id
        ORDER BY a.agreement_id")->fetchAll();
    $result = ['codes' => [], 'source_ids' => [], 'fingerprints' => []];
    foreach ($rows as $row) {
        if ($row['agreement_code'] !== null && trim((string) $row['agreement_code']) !== '') {
            $result['codes'][(string) $row['agreement_code']] = $row;
        }
        if ($row['source_record_id'] !== null && trim((string) $row['source_record_id']) !== '') {
            $result['source_ids'][(string) $row['source_record_id']] = $row;
        }
        $partnerNames = legacyImportPostgresArray((string) $row['partner_names']);
        $fingerprint = LegacyAgreementCsvMapper::fingerprint(
            (string) $row['title'],
            $partnerNames,
            $row['start_date'] !== null ? (string) $row['start_date'] : null
        );
        $result['fingerprints'][$fingerprint] = $row;
    }
    return $result;
}

function legacyImportExistingPartners(PDO $db): array
{
    return $db->query('SELECT partner_id, organization_name, partner_type, country,
        city, website, logo_url, latitude, longitude FROM partners ORDER BY partner_id')->fetchAll();
}

function legacyImportPartnerMap(array $partners): array
{
    $map = [];
    foreach ($partners as $partner) {
        $key = legacyImportPartnerKey(
            (string) $partner['organization_name'],
            (string) ($partner['country'] ?? '')
        );
        if (isset($map[$key])) {
            throw new RuntimeException(
                'Existing partner duplicates must be resolved before import: ' . $partner['organization_name']
            );
        }
        $map[$key] = $partner;
    }
    return $map;
}

function legacyImportPartnerKey(string $name, string $country): string
{
    return legacyImportNormalize($name) . '|' . legacyImportNormalize($country);
}

function legacyImportPartnersWithName(array $partnerMap, string $name): array
{
    $prefix = legacyImportNormalize($name) . '|';
    return array_filter(
        $partnerMap,
        static fn (string $key): bool => str_starts_with($key, $prefix),
        ARRAY_FILTER_USE_KEY
    );
}

function legacyImportUnits(PDO $db): array
{
    $units = [];
    foreach ($db->query('SELECT unit_id, name, code FROM organizational_units WHERE is_active = TRUE')->fetchAll() as $row) {
        $units[legacyImportNormalize((string) $row['name'])] = (int) $row['unit_id'];
        $units[legacyImportNormalize((string) $row['code'])] = (int) $row['unit_id'];
    }
    return $units;
}

function legacyImportResolveUnit(array $units, ?string $owner): ?int
{
    if ($owner === null || legacyImportNormalize($owner) === legacyImportNormalize('Not specified')) {
        return null;
    }
    $aliases = [
        'college of information technology' => 'CIT',
        'كلية تقنية المعلومات' => 'CIT',
        'vice president for academic programs and graduate studies' => 'VP',
        'vice president for partnerships and development' => 'VP',
    ];
    $key = legacyImportNormalize($owner);
    $target = $aliases[$key] ?? $owner;
    return $units[legacyImportNormalize($target)] ?? null;
}

function legacyImportPostgresArray(string $value): array
{
    if ($value === '{}' || $value === '') {
        return [];
    }
    $handle = fopen('php://temp', 'r+');
    if ($handle === false) {
        return [];
    }
    fwrite($handle, substr($value, 1, -1));
    rewind($handle);
    $parsed = fgetcsv($handle, 0, ',', '"', '\\');
    fclose($handle);
    return $parsed === false ? [] : array_map('strval', $parsed);
}

function legacyImportNormalize(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
    return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
}

function legacyImportUuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function legacyImportWriteReport(array $report, ?string $path): void
{
    $json = json_encode(
        $report,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . PHP_EOL;
    if ($path === null || $path === '') {
        echo $json;
        return;
    }
    $directory = dirname($path);
    if (!is_dir($directory)) {
        throw new RuntimeException('Report directory does not exist: ' . $directory);
    }
    if (file_put_contents($path, $json, LOCK_EX) === false) {
        throw new RuntimeException('Could not write import report: ' . $path);
    }
    echo sprintf(
        "Legacy Agreement import %s: %d ready, %d imported, %d skipped, %d conflicts, %d invalid. Report: %s\n",
        $report['mode'],
        $report['counts']['ready'],
        $report['counts']['agreements_imported'],
        $report['counts']['skipped'],
        $report['counts']['conflicts'],
        $report['counts']['invalid'],
        $path
    );
}
