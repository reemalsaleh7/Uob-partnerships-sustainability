<?php
class AgreementValidator {
    public static function validateCreate(array $data): array {
        $errors = [];

        if (empty(trim($data['title'] ?? ''))) {
            $errors[] = 'Title is required';
        }

        if (empty(trim($data['agreement_type'] ?? ''))) {
            $errors[] = 'Agreement type is required';
        }

        if (empty(trim($data['description'] ?? ''))) {
            $errors[] = 'Description is required';
        }

        if (count(self::partnerIds($data)) !== 1) {
            $errors[] = 'Exactly one partner is required';
        }

        $errors = array_merge($errors, self::validateContent($data));

        return $errors;
    }

    public static function validateUpdate(array $data): array {
        $errors = [];

        if (array_key_exists('title', $data) && empty(trim($data['title'] ?? ''))) {
            $errors[] = 'Title is required';
        }

        if (array_key_exists('agreement_type', $data) && empty(trim($data['agreement_type'] ?? ''))) {
            $errors[] = 'Agreement type is required';
        }

        if (array_key_exists('description', $data) && empty(trim($data['description'] ?? ''))) {
            $errors[] = 'Description is required';
        }

        if (
            (array_key_exists('partner_id', $data) || array_key_exists('partner_ids', $data))
            && count(self::partnerIds($data)) !== 1
        ) {
            $errors[] = 'Exactly one partner is required';
        }

        $errors = array_merge($errors, self::validateContent($data));

        return $errors;
    }

    public static function validateForSubmission(array $data): array {
        $errors = [];
        $requiredText = [
            'title_ar' => 'Arabic Agreement name',
            'description' => 'Description',
            'need_justification' => 'Statement of need and justification',
            'expected_value' => 'Expected value and impact',
            'objectives' => 'Objectives',
        ];

        foreach ($requiredText as $field => $label) {
            if (trim((string) ($data[$field] ?? '')) === '') {
                $errors[] = $label . ' is required before submission';
            }
        }

        if (empty($data['geographic_scope'])) {
            $errors[] = 'Geographic scope is required before submission';
        }
        if (empty($data['start_date']) || empty($data['end_date'])) {
            $errors[] = 'Agreement start and end dates are required before submission';
        }
        if (empty($data['signing_date'])) {
            $errors[] = 'Signing date is required before submission';
        }
        if (empty($data['effective_date'])) {
            $errors[] = 'Effective date is required before submission';
        }
        if (count(self::partnerIds($data)) !== 1) {
            $errors[] = 'Exactly one partner is required before submission';
        }
        if (!self::hasCompleteRequiredContacts($data)) {
            $errors[] =
                'All UOB and partner coordinator and signatory fields are required before submission';
        }
        if (!self::hasCompleteExecutivePrograms($data)) {
            $errors[] =
                'At least one complete executive programme is required before submission';
        }
        if (!self::hasCompleteRequiredMetrics($data)) {
            $errors[] =
                'Every planned-outcome field is required before submission';
        }
        if (
            empty($data['auto_renew'])
            && (
                !isset($data['fixed_term_months'])
                || filter_var(
                    $data['fixed_term_months'],
                    FILTER_VALIDATE_INT
                ) === false
                || (int) $data['fixed_term_months'] < 1
            )
        ) {
            $errors[] =
                'Agreement term in months is required when automatic renewal is disabled';
        }
        if (
            !empty($data['auto_renew'])
            && (
                !isset($data['renewal_term_months'])
                || filter_var(
                    $data['renewal_term_months'],
                    FILTER_VALIDATE_INT
                ) === false
                || (int) $data['renewal_term_months'] < 1
            )
        ) {
            $errors[] =
                'Each automatic renewal term must be at least one month';
        }
        if (
            strtoupper(trim((string) ($data['geographic_scope'] ?? '')))
            === 'INTERNATIONAL'
        ) {
            foreach (($data['partners'] ?? []) as $partner) {
                if (trim((string) ($partner['country'] ?? '')) === '') {
                    $errors[] = 'Every international partner must have a country before submission';
                    break;
                }
            }
        }

        return array_merge($errors, self::validateContent($data));
    }

    private static function validateContent(array $data): array {
        $errors = [];
        $scope = strtoupper(trim((string) ($data['geographic_scope'] ?? '')));
        if ($scope !== '' && !in_array($scope, ['LOCAL', 'INTERNATIONAL'], true)) {
            $errors[] = 'Geographic scope must be LOCAL or INTERNATIONAL';
        }

        $binding = strtoupper(trim((string) ($data['legal_binding_status'] ?? '')));
        if ($binding !== '' && !in_array($binding, ['NON_BINDING', 'BINDING', 'MIXED'], true)) {
            $errors[] = 'Select a valid legal binding status';
        }

        $start = self::dateValue($data['start_date'] ?? null);
        $end = self::dateValue($data['end_date'] ?? null);
        if (($data['start_date'] ?? null) && !$start) {
            $errors[] = 'Start date is invalid';
        }
        if (($data['end_date'] ?? null) && !$end) {
            $errors[] = 'End date is invalid';
        }
        if ($start && $end && $end < $start) {
            $errors[] = 'End date cannot be earlier than start date';
        }
        foreach ([
            'signing_date' => 'Signing date',
            'effective_date' => 'Effective date',
        ] as $field => $label) {
            if (
                ($data[$field] ?? null)
                && !self::dateValue($data[$field])
            ) {
                $errors[] = $label . ' is invalid';
            }
        }

        foreach (['fixed_term_months', 'renewal_term_months', 'non_renewal_notice_months', 'termination_notice_months'] as $field) {
            $value = $data[$field] ?? null;
            if (
                $value !== null
                && $value !== ''
                && (
                    filter_var($value, FILTER_VALIDATE_INT) === false
                    || (int) $value < 0
                )
            ) {
                $errors[] = str_replace('_', ' ', ucfirst($field)) . ' must be zero or greater';
            }
        }

        if (!empty($data['financial_commitments'])) {
            $amount = $data['financial_amount'] ?? null;
            if ($amount !== null && $amount !== '' && (!is_numeric($amount) || (float) $amount < 0)) {
                $errors[] = 'Financial commitment amount must be zero or greater';
            }
            if (trim((string) ($data['financial_description'] ?? '')) === '') {
                $errors[] = 'Describe the financial commitments';
            }
        }
        if (
            !empty($data['human_resources_commitments'])
            && trim((string) ($data['human_resources_description'] ?? '')) === ''
        ) {
            $errors[] = 'Describe the human-resources commitments';
        }
        if (
            !empty($data['training_programs'])
            && trim((string) ($data['training_programs_description'] ?? '')) === ''
        ) {
            $errors[] = 'Describe the training programs';
        }

        foreach (($data['sdgs'] ?? []) as $sdg) {
            if (!is_numeric($sdg) || (int) $sdg < 1 || (int) $sdg > 17) {
                $errors[] = 'SDG selections must be between 1 and 17';
                break;
            }
        }

        $allowedRankings = ['QS_WORLD', 'THE_IMPACT', 'UI_GREENMETRIC'];
        foreach (($data['rankings'] ?? []) as $ranking) {
            if (!in_array(strtoupper((string) $ranking), $allowedRankings, true)) {
                $errors[] = 'Select only supported university rankings';
                break;
            }
        }

        foreach (($data['contacts'] ?? []) as $contact) {
            if (
                !empty($contact['email'])
                && !filter_var((string) $contact['email'], FILTER_VALIDATE_EMAIL)
            ) {
                $errors[] = 'A contact email address is invalid';
                break;
            }
        }

        foreach (($data['executive_programs'] ?? []) as $program) {
            $programStart = self::dateValue($program['start_date'] ?? null);
            $programEnd = self::dateValue($program['end_date'] ?? null);
            if (($program['start_date'] ?? null) && !$programStart) {
                $errors[] = 'An executive programme start date is invalid';
                break;
            }
            if (($program['end_date'] ?? null) && !$programEnd) {
                $errors[] = 'An executive programme end date is invalid';
                break;
            }
            if (
                $programStart
                && $programEnd
                && $programEnd < $programStart
            ) {
                $errors[] = 'An executive programme end date cannot be earlier than its start date';
                break;
            }
        }

        return $errors;
    }

    private static function partnerIds(array $data): array {
        $ids = $data['partner_ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }
        if (empty($ids) && !empty($data['partner_id'])) {
            $ids = [$data['partner_id']];
        }
        return array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id): bool => $id > 0
        )));
    }

    private static function hasCompleteExecutivePrograms(array $data): bool
    {
        $programs = $data['executive_programs'] ?? [];
        if (!is_array($programs) || $programs === []) {
            return false;
        }

        $required = [
            'title',
            'responsible_entity',
            'description',
            'objectives',
            'expected_outputs',
            'start_date',
            'end_date',
        ];
        foreach ($programs as $program) {
            foreach ($required as $field) {
                if (trim((string) ($program[$field] ?? '')) === '') {
                    return false;
                }
            }
        }

        return true;
    }

    private static function hasCompleteRequiredContacts(array $data): bool
    {
        $contacts = $data['contacts'] ?? [];
        if (!is_array($contacts)) {
            return false;
        }
        $requiredKeys = [
            'UOB:COORDINATOR',
            'PARTNER:COORDINATOR',
            'UOB:SIGNATORY',
            'PARTNER:SIGNATORY',
        ];
        $complete = [];
        foreach ($contacts as $contact) {
            $party = strtoupper(trim((string) (
                $contact['party_type'] ?? ''
            )));
            $role = strtoupper(trim((string) (
                $contact['contact_role'] ?? ''
            )));
            $key = $party . ':' . $role;
            if (!in_array($key, $requiredKeys, true)) {
                continue;
            }
            foreach (['full_name', 'job_title', 'email', 'phone'] as $field) {
                if (trim((string) ($contact[$field] ?? '')) === '') {
                    return false;
                }
            }
            if (
                filter_var(
                    (string) $contact['email'],
                    FILTER_VALIDATE_EMAIL
                ) === false
            ) {
                return false;
            }
            $complete[$key] = true;
        }

        foreach ($requiredKeys as $key) {
            if (empty($complete[$key])) {
                return false;
            }
        }

        return true;
    }

    private static function hasCompleteRequiredMetrics(array $data): bool
    {
        $metrics = $data['metrics'] ?? [];
        if (!is_array($metrics)) {
            return false;
        }
        $requiredCodes = [
            'STUDENTS_EXCHANGED',
            'TRAINED_STUDENTS',
            'FACULTY_EXCHANGED',
            'JOINT_PROGRAMS',
        ];
        $complete = [];
        foreach ($metrics as $metric) {
            $code = strtoupper(trim((string) (
                $metric['metric_code'] ?? ''
            )));
            if (!in_array($code, $requiredCodes, true)) {
                continue;
            }
            foreach (
                ['planned_value', 'actual_value', 'notes']
                as $field
            ) {
                if (
                    !array_key_exists($field, $metric)
                    || trim((string) $metric[$field]) === ''
                ) {
                    return false;
                }
            }
            if (
                filter_var(
                    $metric['planned_value'],
                    FILTER_VALIDATE_INT
                ) === false
                || (int) $metric['planned_value'] < 0
                || filter_var(
                    $metric['actual_value'],
                    FILTER_VALIDATE_INT
                ) === false
                || (int) $metric['actual_value'] < 0
            ) {
                return false;
            }
            $complete[$code] = true;
        }

        foreach ($requiredCodes as $code) {
            if (empty($complete[$code])) {
                return false;
            }
        }

        return true;
    }

    private static function dateValue(mixed $value): ?DateTimeImmutable {
        if ($value === null || $value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);
        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }
}
