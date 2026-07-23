<?php

final class AgreementStatus {
    public const DRAFT = 'DRAFT';
    public const UNDER_REVIEW = 'UNDER_REVIEW';
    public const REVISION_REQUIRED = 'REVISION_REQUIRED';
    public const APPROVED = 'APPROVED';
    public const ACTIVE = 'ACTIVE';
    public const EXPIRED = 'EXPIRED';

    public static function isEditable(string $status): bool
    {
        return in_array(
            $status,
            [self::DRAFT, self::REVISION_REQUIRED],
            true
        );
    }

    private function __construct() {}
}
