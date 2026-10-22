<?php

declare(strict_types=1);

/**
 * Return whether an Academic Period has persisted grade or risk-history data.
 * A class capture counts as history even when its captured cohort is empty.
 *
 * @param array<int,mixed> $periodIds
 * @return array<int,array{raw_scores:bool,risk_history:bool}>
 */
function aprismAcademicPeriodDependencyMap(PDO $pdo, array $periodIds): array
{
    $ids = array_values(array_unique(array_filter(
        array_map('intval', $periodIds),
        static fn(int $id): bool => $id > 0
    )));
    if ($ids === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT ap.academic_period_id,
            EXISTS (
                SELECT 1
                FROM assessment_categories ac
                INNER JOIN assessment_components cmp
                    ON cmp.assessment_category_id = ac.assessment_category_id
                INNER JOIN student_assessment_scores sas
                    ON sas.assessment_component_id = cmp.assessment_component_id
                WHERE ac.academic_period_id = ap.academic_period_id
            ) AS has_raw_scores,
            EXISTS (
                SELECT 1
                FROM risk_period_class_captures rpc
                WHERE rpc.academic_period_id = ap.academic_period_id
            ) AS has_risk_history
         FROM academic_periods ap
         WHERE ap.academic_period_id IN ($placeholders)"
    );
    $stmt->execute($ids);

    $dependencies = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $dependencies[(int) $row['academic_period_id']] = [
            'raw_scores' => (bool) $row['has_raw_scores'],
            'risk_history' => (bool) $row['has_risk_history'],
        ];
    }
    return $dependencies;
}

/**
 * Protection applies to actual metadata changes, not an unchanged form save.
 * Proposed values must already have passed the action's normal validation.
 */
function aprismAcademicPeriodHasProtectedChanges(
    PDO $pdo,
    array $existing,
    array $proposed
): bool {
    $fields = [
        'school_year_id' => static fn(array $row): int => (int) $row['school_year_id'],
        'academic_level' => static fn(array $row): string => (string) $row['academic_level'],
        'semester' => static fn(array $row): string => (string) ($row['semester'] ?? ''),
        'period_name' => static fn(array $row): string => (string) $row['period_name'],
        'start_date' => static fn(array $row): string => (string) $row['start_date'],
        'end_date' => static fn(array $row): string => (string) $row['end_date'],
    ];

    foreach ($fields as $field => $value) {
        if ($value($existing) !== $value($proposed)) {
            $dependencies = aprismAcademicPeriodDependencyMap(
                $pdo,
                [(int) $existing['academic_period_id']]
            )[(int) $existing['academic_period_id']] ?? null;

            return $dependencies !== null
                && ($dependencies['raw_scores'] || $dependencies['risk_history']);
        }
    }

    return false;
}
