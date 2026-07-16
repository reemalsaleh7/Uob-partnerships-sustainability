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

        if (empty($data['partner_id'] ?? null)) {
            $errors[] = 'Partner is required';
        }

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

        if (array_key_exists('partner_id', $data) && empty($data['partner_id'] ?? null)) {
            $errors[] = 'Partner is required';
        }

        return $errors;
    }
}
