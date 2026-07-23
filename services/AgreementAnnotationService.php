<?php

declare(strict_types=1);

require_once __DIR__ . '/../repositories/AgreementAnnotationRepository.php';
require_once __DIR__ . '/../repositories/AgreementRepository.php';
require_once __DIR__ . '/../repositories/AgreementVersionRepository.php';
require_once __DIR__ . '/../services/AuditService.php';
require_once __DIR__ . '/../services/PermissionService.php';
require_once __DIR__ . '/../helpers/AuditAction.php';
require_once __DIR__ . '/../config/database.php';

final class AgreementAnnotationService
{
    private const FIELD_LABELS = [
        'title' => 'Agreement title',
        'title_ar' => 'Arabic title',
        'agreement_type' => 'Agreement type',
        'partner_names' => 'Partner organization',
        'description' => 'Description',
        'geographic_scope' => 'Geographic scope',
        'start_date' => 'Start date',
        'end_date' => 'End date',
        'signing_date' => 'Signing date',
        'effective_date' => 'Effective date',
        'auto_renew' => 'Automatic renewal',
        'renewal_term_months' => 'Renewal term',
        'non_renewal_notice_months' => 'Non-renewal notice',
        'termination_notice_months' => 'Termination notice',
        'legal_binding_status' => 'Legal effect',
        'responsible_unit_name' => 'Responsible unit',
        'need_justification' => 'Need and justification',
        'objectives' => 'Objectives',
        'expected_value' => 'Expected value and impact',
        'focus_areas' => 'Focus areas',
        'collaboration_areas' => 'Fields of cooperation',
        'implementation_methods' => 'Implementation methods',
        'financial_summary' => 'Financial commitments',
        'human_resources_summary' => 'Human-resources commitments',
        'training_programs_summary' => 'Training programs',
        'rankings_summary' => 'Rankings',
        'sdgs_summary' => 'Sustainable Development Goals',
        'annual_report_required' => 'Annual report requirement',
        'monitoring_plan' => 'Monitoring plan',
        'confidentiality_terms' => 'Confidentiality terms',
        'intellectual_property_terms' => 'Intellectual-property terms',
        'compliance_terms' => 'Legal and regulatory compliance',
        'relationship_disclaimer' => 'Relationship disclaimer',
        'amendment_terms' => 'Amendment terms',
        'dispute_resolution_terms' => 'Dispute-resolution terms',
        'other_terms' => 'Other terms',
        'signing_link' => 'Public signing link',
    ];

    private AgreementAnnotationRepository $annotations;
    private AgreementRepository $agreements;
    private AgreementVersionRepository $versions;
    private PermissionService $permissions;
    private AuditService $audit;

    public function __construct()
    {
        $this->annotations = new AgreementAnnotationRepository();
        $this->agreements = new AgreementRepository();
        $this->versions = new AgreementVersionRepository();
        $this->permissions = new PermissionService();
        $this->audit = new AuditService();
    }

    public function listForUser(int $agreementId, int $userId): array
    {
        $agreement = $this->visibleAgreement($agreementId, $userId);
        $isAdministrator = $this->isSystemAdministrator($userId);

        return array_map(
            function (array $annotation) use ($agreement, $userId, $isAdministrator): array {
                $isAuthor = (int) $annotation['author_user_id'] === $userId;
                $isPrivate = $annotation['visibility'] === 'PRIVATE';
                $annotation['field_label'] = self::FIELD_LABELS[$annotation['field_key']]
                    ?? $annotation['field_key'];
                $annotation['is_private'] = $isPrivate;
                $annotation['can_resolve'] = $annotation['status'] === 'OPEN'
                    && (
                        $isAuthor
                        || (!$isPrivate && (
                            (int) $agreement['created_by'] === $userId
                            || $isAdministrator
                        ))
                    );
                $annotation['can_delete'] = $isAuthor;

                return $annotation;
            },
            $this->annotations->findVisibleForAgreement($agreementId, $userId)
        );
    }

    public function create(int $agreementId, int $userId, array $data): array
    {
        $this->visibleAgreement($agreementId, $userId);
        $comment = trim((string) ($data['comment_text'] ?? ''));
        $fieldKey = trim((string) ($data['field_key'] ?? ''));
        $visibility = strtoupper(trim((string) ($data['visibility'] ?? 'SHARED')));
        $selectedText = trim((string) ($data['selected_text'] ?? ''));
        $selectionStart = $data['selection_start'] ?? null;
        $selectionEnd = $data['selection_end'] ?? null;

        if (!array_key_exists($fieldKey, self::FIELD_LABELS)) {
            throw new InvalidArgumentException('Select a valid Agreement field');
        }
        if ($comment === '' || $this->textLength($comment) > 4000) {
            throw new InvalidArgumentException('Comment must contain between 1 and 4000 characters');
        }
        if (!in_array($visibility, ['SHARED', 'PRIVATE'], true)) {
            throw new InvalidArgumentException('Comment visibility must be shared or private');
        }

        $latest = $this->versions->findLatest($agreementId);
        if ($latest === null) {
            throw new DomainException('The Agreement has no version to annotate');
        }

        $anchorType = $selectedText === '' ? 'FIELD' : 'TEXT';
        if ($anchorType === 'TEXT') {
            if (
                !is_numeric($selectionStart)
                || !is_numeric($selectionEnd)
                || (int) $selectionStart < 0
                || (int) $selectionEnd <= (int) $selectionStart
                || $this->textLength($selectedText) > 2000
            ) {
                throw new InvalidArgumentException('The selected text range is invalid');
            }

            $fieldValue = $this->displayValue(
                $fieldKey,
                (array) ($latest['agreement_snapshot'] ?? [])
            );
            if ($fieldValue === '' || !str_contains($fieldValue, $selectedText)) {
                throw new InvalidArgumentException(
                    'The selected text is not present in the current Agreement version'
                );
            }
        } else {
            $selectedText = null;
            $selectionStart = null;
            $selectionEnd = null;
        }

        $db = Database::connect();
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) {
            $db->beginTransaction();
        }
        try {
            $annotationId = $this->annotations->create([
                'agreement_id' => $agreementId,
                'agreement_version_id' => (int) $latest['version_id'],
                'author_user_id' => $userId,
                'visibility' => $visibility,
                'anchor_type' => $anchorType,
                'field_key' => $fieldKey,
                'selected_text' => $selectedText,
                'selection_start' => $selectionStart === null ? null : (int) $selectionStart,
                'selection_end' => $selectionEnd === null ? null : (int) $selectionEnd,
                'comment_text' => $comment,
            ]);
            $this->audit->write(
                'agreement_annotations',
                $annotationId,
                AuditAction::INSERT,
                $userId,
                null,
                [
                    'agreement_id' => $agreementId,
                    'agreement_version_id' => (int) $latest['version_id'],
                    'visibility' => $visibility,
                    'anchor_type' => $anchorType,
                    'field_key' => $fieldKey,
                    'comment_content_recorded' => true,
                ]
            );
            if ($ownsTransaction && $db->inTransaction()) {
                $db->commit();
            }

            return ['annotation_id' => $annotationId];
        } catch (Throwable $exception) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    public function resolve(int $agreementId, int $annotationId, int $userId): void
    {
        $agreement = $this->visibleAgreement($agreementId, $userId);
        $annotation = $this->annotationForAgreement($agreementId, $annotationId);
        $isAuthor = (int) $annotation['author_user_id'] === $userId;
        if ($annotation['visibility'] === 'PRIVATE' && !$isAuthor) {
            throw new DomainException('Comment not found');
        }
        $allowed = $isAuthor;

        if ($annotation['visibility'] === 'SHARED') {
            $allowed = $allowed
                || (int) $agreement['created_by'] === $userId
                || $this->isSystemAdministrator($userId);
        }
        if (!$allowed) {
            throw new DomainException('You cannot resolve this comment');
        }

        $this->annotations->resolve($annotationId, $userId);
    }

    public function delete(int $agreementId, int $annotationId, int $userId): void
    {
        $this->visibleAgreement($agreementId, $userId);
        $annotation = $this->annotationForAgreement($agreementId, $annotationId);
        if ((int) $annotation['author_user_id'] !== $userId) {
            throw new DomainException(
                $annotation['visibility'] === 'PRIVATE'
                    ? 'Comment not found'
                    : 'Only the comment author may delete it'
            );
        }

        $this->annotations->delete($annotationId);
    }

    public function reviewContext(
        int $agreementId,
        int $userId,
        ?int $fromVersion = null,
        ?int $toVersion = null
    ): array {
        $this->visibleAgreement($agreementId, $userId);
        $latest = $this->versions->findLatest($agreementId);
        if ($latest === null) {
            return [
                'latest_version' => null,
                'last_viewed_version' => null,
                'has_unseen_changes' => false,
                'changes' => [],
            ];
        }

        $latestNumber = (int) $latest['version_number'];
        $lastViewed = $this->annotations->lastViewedVersion($agreementId, $userId);
        $manual = $fromVersion !== null || $toVersion !== null;

        if ($manual) {
            $toVersion ??= $latestNumber;
            $fromVersion ??= max(1, $toVersion - 1);
        } elseif ($lastViewed !== null && (int) $lastViewed['version_number'] < $latestNumber) {
            $fromVersion = (int) $lastViewed['version_number'];
            $toVersion = $latestNumber;
        }

        $changes = [];
        $reason = null;
        if ($fromVersion !== null && $toVersion !== null && $fromVersion !== $toVersion) {
            $from = $this->versions->findByAgreementAndVersion($agreementId, $fromVersion);
            $to = $this->versions->findByAgreementAndVersion($agreementId, $toVersion);
            if ($from === null || $to === null || $fromVersion > $toVersion) {
                throw new InvalidArgumentException('Select valid Agreement versions to compare');
            }
            $comparison = $this->compareVersionRange(
                $agreementId,
                $fromVersion,
                $toVersion
            );
            $changes = $comparison['changes'];
            $reason = implode(' • ', $comparison['reasons']);
        }

        return [
            'latest_version' => $latestNumber,
            'last_viewed_version' => $lastViewed === null
                ? null
                : (int) $lastViewed['version_number'],
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'has_unseen_changes' => !$manual && $changes !== [],
            'revision_reason' => $reason,
            'changes' => $changes,
        ];
    }

    public function markViewed(int $agreementId, int $userId, ?int $versionNumber): void
    {
        $this->visibleAgreement($agreementId, $userId);
        $version = $versionNumber === null
            ? $this->versions->findLatest($agreementId)
            : $this->versions->findByAgreementAndVersion($agreementId, $versionNumber);
        if ($version === null) {
            throw new InvalidArgumentException('Agreement version not found');
        }

        $this->annotations->markViewed(
            $agreementId,
            $userId,
            (int) $version['version_id']
        );
    }

    private function visibleAgreement(int $agreementId, int $userId): array
    {
        $agreement = $this->agreements->findByIdVisibleToUser(
            $agreementId,
            $userId,
            $this->isSystemAdministrator($userId)
        );
        if ($agreement === null) {
            throw new DomainException('Agreement not found');
        }

        return $agreement;
    }

    private function annotationForAgreement(int $agreementId, int $annotationId): array
    {
        $annotation = $this->annotations->findById($annotationId);
        if ($annotation === null || (int) $annotation['agreement_id'] !== $agreementId) {
            throw new DomainException('Comment not found');
        }

        return $annotation;
    }

    private function isSystemAdministrator(int $userId): bool
    {
        return in_array(
            'System Administrator',
            $this->permissions->getRoleNames($userId),
            true
        );
    }

    private function compareSnapshots(array $before, array $after, string $reason): array
    {
        $changes = [];
        foreach (self::FIELD_LABELS as $fieldKey => $label) {
            $oldValue = $this->displayValue($fieldKey, $before);
            $newValue = $this->displayValue($fieldKey, $after);
            if ($oldValue === $newValue) {
                continue;
            }

            $changes[] = [
                'field_key' => $fieldKey,
                'field_label' => $label,
                'before' => $oldValue === '' ? null : $oldValue,
                'after' => $newValue === '' ? null : $newValue,
                'change_type' => $oldValue === '' ? 'ADDED' : ($newValue === '' ? 'REMOVED' : 'UPDATED'),
                'reason' => $reason,
            ];
        }

        return $changes;
    }

    private function compareVersionRange(
        int $agreementId,
        int $fromVersion,
        int $toVersion
    ): array {
        $versions = array_values(array_filter(
            $this->versions->findByAgreement($agreementId),
            static fn (array $version): bool =>
                (int) $version['version_number'] >= $fromVersion
                && (int) $version['version_number'] <= $toVersion
        ));
        usort(
            $versions,
            static fn (array $left, array $right): int =>
                (int) $left['version_number'] <=> (int) $right['version_number']
        );

        $changesByField = [];
        $reasons = [];
        for ($index = 1; $index < count($versions); $index++) {
            $previous = $versions[$index - 1];
            $current = $versions[$index];
            $currentReason = trim((string) ($current['change_summary'] ?? ''))
                ?: 'No revision reason recorded';
            $stepChanges = $this->compareSnapshots(
                (array) ($previous['agreement_snapshot'] ?? []),
                (array) ($current['agreement_snapshot'] ?? []),
                $currentReason
            );
            if ($stepChanges !== []) {
                $reasons[] = $currentReason;
            }
            foreach ($stepChanges as $change) {
                $key = $change['field_key'];
                if (isset($changesByField[$key])) {
                    $change['before'] = $changesByField[$key]['before'];
                    $change['change_type'] = $change['before'] === null
                        ? 'ADDED'
                        : ($change['after'] === null ? 'REMOVED' : 'UPDATED');
                }
                if ($change['before'] === $change['after']) {
                    unset($changesByField[$key]);
                    continue;
                }
                $changesByField[$key] = $change;
            }
        }

        return [
            'changes' => array_values($changesByField),
            'reasons' => array_values(array_unique($reasons)),
        ];
    }

    private function displayValue(string $fieldKey, array $snapshot): string
    {
        $value = match ($fieldKey) {
            'partner_names' => $snapshot['partner_names'] ?? array_column($snapshot['partners'] ?? [], 'organization_name'),
            'financial_summary' => $this->commitmentSummary(
                $snapshot['financial_commitments'] ?? false,
                [
                    $snapshot['financial_amount'] ?? null,
                    $snapshot['financial_currency'] ?? null,
                    $snapshot['financial_description'] ?? null,
                ]
            ),
            'human_resources_summary' => $this->commitmentSummary(
                $snapshot['human_resources_commitments'] ?? false,
                [$snapshot['human_resources_description'] ?? null]
            ),
            'training_programs_summary' => $this->commitmentSummary(
                $snapshot['training_programs'] ?? false,
                [$snapshot['training_programs_description'] ?? null]
            ),
            'renewal_term_months',
            'non_renewal_notice_months',
            'termination_notice_months' => isset($snapshot[$fieldKey])
                ? $snapshot[$fieldKey] . ' months'
                : null,
            'rankings_summary' => array_map(
                static fn ($ranking): string => str_replace('_', ' ', (string) $ranking),
                $snapshot['rankings'] ?? []
            ),
            'sdgs_summary' => array_map(
                static fn ($sdg): string => 'SDG ' . $sdg,
                $snapshot['sdgs'] ?? []
            ),
            default => $snapshot[$fieldKey] ?? null,
        };

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_array($value)) {
            $flat = array_map(
                static fn ($item): string => is_scalar($item)
                    ? (string) $item
                    : json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $value
            );
            sort($flat);
            return implode(', ', $flat);
        }
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    private function commitmentSummary(mixed $enabled, array $details): string
    {
        $isEnabled = in_array($enabled, [true, 1, '1', 't', 'true'], true);
        if (!$isEnabled) {
            return 'None';
        }
        $parts = array_values(array_filter(array_map(
            static fn ($value): string => trim((string) ($value ?? '')),
            $details
        )));

        return $parts === [] ? 'Yes' : implode(' · ', $parts);
    }

    private function textLength(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }
}
