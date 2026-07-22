<?php

declare(strict_types=1);

final class LegacyAgreementCsvMapper
{
    public const REQUIRED_HEADERS = [
        'agreement_code',
        'agreement_name',
        'agreement_type',
        'partner_entity',
        'entity_type',
        'country',
        'start_date',
        'end_date',
        'auto_renew',
        'owner_entity',
        'status',
        'admin_status',
        'submitted_by',
        'submitted_at',
        'source_record_id',
        'agreement_summary',
        'focus_area',
        'agreement_signing_link',
        'city',
        'partner_website',
        'partner_logo_link',
        'students_exchanged',
        'faculty_exchanged',
        'joint_programs',
        'sdgs',
        'latitude',
        'longitude',
    ];

    /**
     * Convert one row from data/agreements.csv into the normalized structures
     * used by the PostgreSQL Agreement model. Missing legacy data stays null;
     * the importer never invents legal clauses, objectives, or commitments.
     */
    public static function map(array $row, int $rowNumber): array
    {
        $errors = [];
        $warnings = [];

        $code = self::required($row, 'agreement_code', $errors);
        $title = self::required($row, 'agreement_name', $errors);
        $agreementType = self::required($row, 'agreement_type', $errors);
        $partnerNames = self::splitList(self::required($row, 'partner_entity', $errors));
        $country = self::required($row, 'country', $errors);
        $startDate = self::dateValue($row['start_date'] ?? '', 'start_date', $errors);
        $endDate = self::dateValue($row['end_date'] ?? '', 'end_date', $errors);

        if ($startDate !== null && $endDate !== null && $endDate < $startDate) {
            $errors[] = 'end_date precedes start_date';
        }
        if (count($partnerNames) === 0) {
            $errors[] = 'partner_entity did not contain a partner name';
        }

        $adminStatus = self::text($row['admin_status'] ?? '');
        if (!in_array(self::normalized($adminStatus), ['approved', 'معتمد'], true)) {
            $errors[] = 'admin_status is not an approved value';
        }

        $legacyStatus = self::normalized(self::text($row['status'] ?? ''));
        if (!in_array($legacyStatus, ['active', 'current', 'سارية', 'نشطة'], true)) {
            $errors[] = 'status is not an active legacy value';
        }

        if ($endDate !== null && $endDate < date('Y-m-d')) {
            $warnings[] = 'Legacy end date has passed; ACTIVE is preserved from the approved source row.';
        }

        $sourceRecordId = self::nullable($row['source_record_id'] ?? null);
        if ($sourceRecordId === null) {
            $errors[] = 'source_record_id is required for traceability';
        }

        $signingLink = self::httpUrl($row['agreement_signing_link'] ?? null);
        if ($signingLink === null && self::nullable($row['agreement_signing_link'] ?? null) !== null) {
            $warnings[] = 'Non-URL agreement_signing_link was retained only in the import payload.';
        }

        $partners = self::partners($row, $partnerNames, $country, $warnings);
        $sdgs = self::sdgs(self::text($row['sdgs'] ?? ''), $warnings);
        $rankings = self::rankings($row);
        $metrics = self::metrics($row);

        $agreement = [
            'agreement_code' => $code,
            'source_record_id' => $sourceRecordId,
            'title' => $title,
            'title_ar' => self::hasArabic($title) ? $title : null,
            'agreement_type' => $agreementType,
            'description' => self::nullable($row['agreement_summary'] ?? null),
            'geographic_scope' => self::isBahrain($country) ? 'LOCAL' : 'INTERNATIONAL',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'auto_renew' => self::boolean($row['auto_renew'] ?? null),
            'focus_areas' => self::nullable($row['focus_area'] ?? null),
            'signing_link' => $signingLink,
            'status' => 'ACTIVE',
            'legacy_owner_entity' => self::nullable($row['owner_entity'] ?? null),
            'legacy_submitted_by' => self::nullable($row['submitted_by'] ?? null),
            'legacy_submitted_at' => self::timestamp($row['submitted_at'] ?? null, $warnings),
        ];

        return [
            'row_number' => $rowNumber,
            'agreement' => $agreement,
            'partners' => $partners,
            'sdgs' => $sdgs,
            'rankings' => $rankings,
            'metrics' => $metrics,
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    public static function canonicalRowHash(array $row): string
    {
        ksort($row);
        return hash(
            'sha256',
            json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );
    }

    public static function fingerprint(string $title, array $partnerNames, ?string $startDate): string
    {
        $partners = array_map([self::class, 'normalized'], $partnerNames);
        sort($partners);
        return hash('sha256', implode('|', [
            self::normalized($title),
            implode(';', $partners),
            (string) $startDate,
        ]));
    }

    private static function partners(
        array $row,
        array $names,
        string $country,
        array &$warnings
    ): array {
        $type = self::nullable($row['entity_type'] ?? null) ?? 'Other';
        $city = self::nullable($row['city'] ?? null);
        $website = self::httpUrl($row['partner_website'] ?? null);
        $logo = self::httpUrl($row['partner_logo_link'] ?? null);
        $latitude = self::coordinate($row['latitude'] ?? null, -90, 90, 'latitude', $warnings);
        $longitude = self::coordinate($row['longitude'] ?? null, -180, 180, 'longitude', $warnings);

        if (count($names) === 1) {
            return [[
                'organization_name' => $names[0],
                'partner_type' => $type,
                'country' => $country,
                'city' => $city,
                'website' => $website,
                'logo_url' => $logo,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]];
        }

        $warnings[] = 'Multiple partners were split into separate records; shared location metadata was not guessed.';
        $websites = self::assignUrls(
            $names,
            self::splitList(self::text($row['partner_website'] ?? ''))
        );
        $logos = self::assignUrls(
            $names,
            self::splitList(self::text($row['partner_logo_link'] ?? ''))
        );
        $cities = preg_split('/\s*\/\s*/u', self::text($row['city'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $partners = [];

        foreach ($names as $index => $name) {
            $partners[] = [
                'organization_name' => $name,
                'partner_type' => $type,
                'country' => $country,
                'city' => self::nullable($cities[$index] ?? null),
                'website' => $websites[$index] ?? null,
                'logo_url' => $logos[$index] ?? null,
                'latitude' => null,
                'longitude' => null,
            ];
        }

        return $partners;
    }

    private static function assignUrls(array $names, array $values): array
    {
        $urls = array_values(array_filter(array_map(
            [self::class, 'httpUrl'],
            $values
        )));
        $assigned = [];
        $unusedUrls = array_fill_keys(array_keys($urls), true);

        foreach ($names as $nameIndex => $name) {
            $tokens = preg_split('/\s+/u', self::normalized($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $tokens = array_filter($tokens, static fn (string $token): bool => strlen($token) >= 3);
            $bestIndex = null;
            $bestScore = 0;
            foreach (array_keys($unusedUrls) as $urlIndex) {
                $haystack = self::normalized($urls[$urlIndex]);
                $score = 0;
                foreach ($tokens as $token) {
                    if (str_contains($haystack, $token)) {
                        $score++;
                    }
                }
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestIndex = $urlIndex;
                }
            }
            if ($bestIndex !== null && $bestScore > 0) {
                $assigned[$nameIndex] = $urls[$bestIndex];
                unset($unusedUrls[$bestIndex]);
            }
        }

        foreach (array_keys($names) as $nameIndex) {
            if (isset($assigned[$nameIndex]) || $unusedUrls === []) {
                continue;
            }
            $urlIndex = array_key_first($unusedUrls);
            $assigned[$nameIndex] = $urls[$urlIndex];
            unset($unusedUrls[$urlIndex]);
        }

        ksort($assigned);
        return $assigned;
    }

    private static function sdgs(string $value, array &$warnings): array
    {
        preg_match_all('/(?:SDG|Goal|الهدف)?\s*(1[0-7]|[1-9])/iu', $value, $matches);
        $sdgs = array_values(array_unique(array_map('intval', $matches[1] ?? [])));
        sort($sdgs);
        if ($value !== '' && count($sdgs) === 0) {
            $warnings[] = 'SDG text could not be parsed and remains in the import payload only.';
        }
        return $sdgs;
    }

    private static function rankings(array $row): array
    {
        $rankings = [];
        if (self::boolean($row['supports_qs_ranking'] ?? $row['supports_qs'] ?? null)) {
            $rankings[] = 'QS_WORLD';
        }
        if (self::boolean($row['supports_ui_greenmetric'] ?? $row['supports_greenmetric'] ?? null)) {
            $rankings[] = 'UI_GREENMETRIC';
        }
        return $rankings;
    }

    private static function metrics(array $row): array
    {
        $map = [
            'STUDENTS_EXCHANGED' => 'students_exchanged',
            'FACULTY_EXCHANGED' => 'faculty_exchanged',
            'JOINT_PROGRAMS' => 'joint_programs',
        ];
        $metrics = [];
        foreach ($map as $code => $field) {
            $notes = self::nullable($row[$field] ?? null);
            if ($notes !== null) {
                $metrics[] = [
                    'metric_code' => $code,
                    'planned_value' => null,
                    'actual_value' => null,
                    'notes' => $notes,
                ];
            }
        }
        return $metrics;
    }

    private static function required(array $row, string $field, array &$errors): string
    {
        $value = self::text($row[$field] ?? '');
        if ($value === '') {
            $errors[] = $field . ' is required';
        }
        return $value;
    }

    private static function dateValue(mixed $value, string $field, array &$errors): ?string
    {
        $value = self::text($value);
        if ($value === '') {
            $errors[] = $field . ' is required';
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $state = DateTimeImmutable::getLastErrors();
        if ($date === false || ($state !== false && ($state['warning_count'] > 0 || $state['error_count'] > 0))) {
            $errors[] = $field . ' must use YYYY-MM-DD';
            return null;
        }
        return $date->format('Y-m-d');
    }

    private static function timestamp(mixed $value, array &$warnings): ?string
    {
        $value = self::text($value);
        if ($value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
        $state = DateTimeImmutable::getLastErrors();
        if ($date === false || ($state !== false && ($state['warning_count'] > 0 || $state['error_count'] > 0))) {
            $warnings[] = 'submitted_at could not be parsed; import time will be used.';
            return null;
        }
        return $date->format('Y-m-d H:i:s');
    }

    private static function coordinate(
        mixed $value,
        float $minimum,
        float $maximum,
        string $field,
        array &$warnings
    ): ?float {
        $value = self::text($value);
        if ($value === '') {
            return null;
        }
        if (!is_numeric($value) || (float) $value < $minimum || (float) $value > $maximum) {
            $warnings[] = $field . ' is invalid and was not imported.';
            return null;
        }
        return (float) $value;
    }

    private static function boolean(mixed $value): bool
    {
        return in_array(self::normalized(self::text($value)), [
            '1', 'true', 'yes', 'y', 'نعم', 'active', 'supported',
        ], true);
    }

    private static function isBahrain(string $country): bool
    {
        return in_array(self::normalized($country), ['bahrain', 'البحرين'], true);
    }

    private static function splitList(string $value): array
    {
        $parts = preg_split('/\s*;\s*/u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_values(array_filter(array_map('trim', $parts), static fn (string $part): bool => $part !== ''));
    }

    private static function httpUrl(mixed $value): ?string
    {
        $value = self::nullable($value);
        if ($value === null || !filter_var($value, FILTER_VALIDATE_URL)) {
            return null;
        }
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true) ? $value : null;
    }

    private static function hasArabic(string $value): bool
    {
        return preg_match('/\p{Arabic}/u', $value) === 1;
    }

    private static function nullable(mixed $value): ?string
    {
        $value = self::text($value);
        return $value === '' ? null : $value;
    }

    private static function text(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private static function normalized(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}
