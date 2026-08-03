<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/database.php';

final class InitiativeAssigneeResolver
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * @return array{user_id:int, unit_id:int}
     */
    public function resolve(
        string $stageKey,
        ?int $requesterUnitId
    ): array {
        $normalizedStage = strtoupper(trim($stageKey));

        return match ($normalizedStage) {
            'DEPARTMENT_HEAD' => $this->resolveDepartmentHead(
                $requesterUnitId
            ),
            'DEAN' => $this->resolveDean($requesterUnitId),
            'VICE_PRESIDENT' => $this->resolveUniversityLeader(
                'VP',
                ['Vice President']
            ),
            'PRESIDENT' => $this->resolveUniversityLeader(
                'PRES',
                ['President']
            ),
            default => throw new DomainException(
                "Unsupported Initiative approval stage: {$normalizedStage}"
            ),
        };
    }

    /**
     * @return array{user_id:int, unit_id:int}
     */
    private function resolveDepartmentHead(
        ?int $requesterUnitId
    ): array {
        if ($requesterUnitId === null) {
            throw new DomainException(
                'The requester does not have an organizational unit, so the Department Head cannot be resolved.'
            );
        }

        $departmentUnitId = $this->findNearestUnitByType(
            $requesterUnitId,
            'DEPARTMENT'
        );

        if ($departmentUnitId === null) {
            throw new DomainException(
                'The requester is not assigned to a Department in the University hierarchy.'
            );
        }

        return $this->findActivePositionHolder(
            $departmentUnitId,
            ['Department Head', 'Head of Department'],
            'Department Head'
        );
    }

    /**
     * @return array{user_id:int, unit_id:int}
     */
    private function resolveDean(
        ?int $requesterUnitId
    ): array {
        if ($requesterUnitId === null) {
            throw new DomainException(
                'The requester does not have an organizational unit, so the Dean cannot be resolved.'
            );
        }

        $collegeUnitId = $this->findNearestUnitByType(
            $requesterUnitId,
            'COLLEGE'
        );

        if ($collegeUnitId === null) {
            throw new DomainException(
                'The requester Department is not connected to a College in the University hierarchy.'
            );
        }

        return $this->findActivePositionHolder(
            $collegeUnitId,
            ['Dean'],
            'Dean'
        );
    }

    /**
     * @param list<string> $positionNames
     * @return array{user_id:int, unit_id:int}
     */
    private function resolveUniversityLeader(
        string $unitCode,
        array $positionNames
    ): array {
        $statement = $this->db->prepare(
            "SELECT unit_id
             FROM organizational_units
             WHERE UPPER(code) = :unit_code
               AND is_active = TRUE
             LIMIT 1"
        );
        $statement->execute([
            'unit_code' => strtoupper($unitCode),
        ]);

        $unitId = $statement->fetchColumn();

        if ($unitId === false) {
            throw new DomainException(
                "The required University office {$unitCode} was not found."
            );
        }

        return $this->findActivePositionHolder(
            (int) $unitId,
            $positionNames,
            implode(' / ', $positionNames)
        );
    }

    private function findNearestUnitByType(
        int $startUnitId,
        string $unitType
    ): ?int {
        $statement = $this->db->prepare(
            "WITH RECURSIVE unit_chain AS (
                SELECT
                    unit_id,
                    parent_unit_id,
                    unit_type,
                    0 AS depth
                FROM organizational_units
                WHERE unit_id = :start_unit_id
                  AND is_active = TRUE

                UNION ALL

                SELECT
                    parent.unit_id,
                    parent.parent_unit_id,
                    parent.unit_type,
                    chain.depth + 1
                FROM organizational_units parent
                JOIN unit_chain chain
                  ON parent.unit_id = chain.parent_unit_id
                WHERE parent.is_active = TRUE
            )
            SELECT unit_id
            FROM unit_chain
            WHERE UPPER(unit_type::TEXT) = :unit_type
            ORDER BY depth
            LIMIT 1"
        );
        $statement->execute([
            'start_unit_id' => $startUnitId,
            'unit_type' => strtoupper($unitType),
        ]);

        $unitId = $statement->fetchColumn();

        return $unitId === false ? null : (int) $unitId;
    }

    /**
     * @param list<string> $positionNames
     * @return array{user_id:int, unit_id:int}
     */
    private function findActivePositionHolder(
        int $unitId,
        array $positionNames,
        string $displayName
    ): array {
        $normalizedNames = array_map(
            static fn (string $name): string => strtolower(trim($name)),
            $positionNames
        );

        $placeholders = [];
        $parameters = [
            'unit_id' => $unitId,
        ];

        foreach ($normalizedNames as $index => $name) {
            $key = "position_name_{$index}";
            $placeholders[] = ':' . $key;
            $parameters[$key] = $name;
        }

        $statement = $this->db->prepare(
            "SELECT
                user_position.user_id,
                user_position.unit_id
             FROM user_positions user_position
             JOIN users user_account
               ON user_account.user_id = user_position.user_id
              AND user_account.is_active = TRUE
             JOIN positions position
               ON position.position_id = user_position.position_id
             WHERE user_position.unit_id = :unit_id
               AND user_position.is_active = TRUE
               AND (
                    user_position.end_date IS NULL
                    OR user_position.end_date >= CURRENT_DATE
               )
               AND LOWER(position.name) IN (" .
               implode(', ', $placeholders) .
               ")
             ORDER BY
                user_position.start_date DESC,
                user_position.user_position_id DESC
             LIMIT 1"
        );
        $statement->execute($parameters);
        $holder = $statement->fetch();

        if (!$holder) {
            throw new DomainException(
                "No active {$displayName} is assigned to the required organizational unit."
            );
        }

        return [
            'user_id' => (int) $holder['user_id'],
            'unit_id' => (int) $holder['unit_id'],
        ];
    }
}
