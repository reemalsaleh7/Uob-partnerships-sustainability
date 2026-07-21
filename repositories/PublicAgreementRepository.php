<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

final class PublicAgreementRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Return only Agreements that have completed the approval workflow or
     * have subsequently been activated. The selected columns are an explicit
     * public allow-list; creator identity, workflow comments, versions, and
     * document metadata are intentionally excluded.
     */
    public function findPublished(): array
    {
        $statement = $this->db->query("
            SELECT
                a.agreement_id,
                COALESCE(
                    NULLIF(a.agreement_code, ''),
                    'UOB-AGR-' || LPAD(a.agreement_id::text, 6, '0')
                ) AS public_reference,
                a.title,
                a.description,
                a.agreement_type,
                a.title_ar,
                a.start_date,
                a.end_date,
                a.auto_renew,
                a.objectives,
                a.expected_value,
                a.focus_areas,
                a.signing_link,
                a.status::text AS status,
                a.created_at,
                a.updated_at,
                COALESCE(
                    STRING_AGG(
                        DISTINCT p.organization_name,
                        ' / '
                        ORDER BY p.organization_name
                    ),
                    ''
                ) AS partner_names,
                COALESCE(
                    STRING_AGG(
                        DISTINCT p.partner_type,
                        ' / '
                        ORDER BY p.partner_type
                    ),
                    ''
                ) AS partner_types,
                COALESCE(
                    STRING_AGG(
                        DISTINCT p.country,
                        ' / '
                        ORDER BY p.country
                    ) FILTER (WHERE p.country IS NOT NULL),
                    ''
                ) AS countries,
                COALESCE(MIN(NULLIF(p.website, '')), '')
                    AS partner_website,
                COALESCE(
                    STRING_AGG(DISTINCT NULLIF(p.city, ''), ' / ' ORDER BY NULLIF(p.city, '')),
                    ''
                ) AS cities,
                COALESCE((
                    SELECT STRING_AGG('SDG ' || sdg_number::text, ', ' ORDER BY sdg_number)
                    FROM agreement_sdgs ags
                    WHERE ags.agreement_id = a.agreement_id
                ), '') AS sdgs,
                COALESCE((
                    SELECT STRING_AGG(ranking_code, ', ' ORDER BY ranking_code)
                    FROM agreement_rankings ar
                    WHERE ar.agreement_id = a.agreement_id
                ), '') AS rankings,
                COALESCE((
                    SELECT MAX(notes)
                    FROM agreement_metrics am
                    WHERE am.agreement_id = a.agreement_id
                      AND am.metric_code = 'STUDENTS_EXCHANGED'
                ), '') AS students_exchanged,
                COALESCE((
                    SELECT MAX(notes)
                    FROM agreement_metrics am
                    WHERE am.agreement_id = a.agreement_id
                      AND am.metric_code = 'FACULTY_EXCHANGED'
                ), '') AS faculty_exchanged,
                COALESCE((
                    SELECT MAX(notes)
                    FROM agreement_metrics am
                    WHERE am.agreement_id = a.agreement_id
                      AND am.metric_code = 'JOINT_PROGRAMS'
                ), '') AS joint_programs,
                COALESCE((
                    SELECT ou.name
                    FROM user_positions up
                    JOIN organizational_units ou
                        ON ou.unit_id = up.unit_id
                    WHERE up.user_id = a.created_by
                      AND up.is_active = TRUE
                      AND (
                          up.end_date IS NULL
                          OR up.end_date >= CURRENT_DATE
                      )
                    ORDER BY up.start_date DESC,
                             up.user_position_id DESC
                    LIMIT 1
                ), '') AS owner_unit,
                (
                    SELECT MAX(wi.completed_at)
                    FROM workflow_instances wi
                    WHERE wi.entity_type = 'AGREEMENT'
                      AND wi.entity_id = a.agreement_id
                      AND wi.status = 'COMPLETED'
                ) AS approved_at
            FROM agreements a
            LEFT JOIN agreement_partners ap
                ON ap.agreement_id = a.agreement_id
            LEFT JOIN partners p
                ON p.partner_id = ap.partner_id
            WHERE a.status IN ('APPROVED', 'ACTIVE')
            GROUP BY
                a.agreement_id,
                a.title,
                a.description,
                a.agreement_type,
                a.title_ar,
                a.start_date,
                a.end_date,
                a.auto_renew,
                a.objectives,
                a.expected_value,
                a.focus_areas,
                a.signing_link,
                a.status,
                a.created_by,
                a.created_at,
                a.updated_at
            ORDER BY
                COALESCE(
                    (
                        SELECT MAX(wi.completed_at)
                        FROM workflow_instances wi
                        WHERE wi.entity_type = 'AGREEMENT'
                          AND wi.entity_id = a.agreement_id
                          AND wi.status = 'COMPLETED'
                    ),
                    a.updated_at,
                    a.created_at
                ) DESC,
                a.agreement_id DESC
        ");

        return $statement->fetchAll();
    }
}
