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

        if (empty(self::partnerIds($data))) {
            $errors[] = 'At least one partner is required';
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
            && empty(self::partnerIds($data))
        ) {
            $errors[] = 'At least one partner is required';
        }

        $errors = array_merge($errors, self::validateContent($data));

        return $errors;
    }

    public static function validateForSubmission(array $data): array {
        $errors = [];
        $requiredText = [
            'description' => 'Description',
            'need_justification' => 'Statement of need and justification',
            'expected_value' => 'Expected value and impact',
            'objectives' => 'Objectives',
            'collaboration_areas' => 'Collaboration areas',
            'implementation_methods' => 'Implementation methods',
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
        if (empty(self::partnerIds($data))) {
            $errors[] = 'At least one partner is required before submission';
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

        foreach (['renewal_term_months', 'non_renewal_notice_months', 'termination_notice_months'] as $field) {
            $value = $data[$field] ?? null;
            if ($value !== null && $value !== '' && (!is_numeric($value) || (int) $value < 0)) {
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
        return array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));
    }

    private static function dateValue(mixed $value): ?DateTimeImmutable {
        if ($value === null || $value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);
        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }
}
