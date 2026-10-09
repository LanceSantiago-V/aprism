<?php

declare(strict_types=1);

/**
 * Build the selected placement predicate. The default/All/Active view is
 * current-placement only; explicit non-current status filters remain useful
 * for historical and data-quality browsing.
 *
 * @return array{sql: string, params: array<int, mixed>, current: bool}
 */
function aprismRegistryPlacementSelection(array $filters, string $alias, string $studentAlias = 'st'): array
{
    $where = ["{$alias}.student_id = {$studentAlias}.student_id"];
    $params = [];
    $status = trim((string) ($filters['status'] ?? 'All'));
    $current = in_array($status, ['All', 'Active', 'Missing'], true);

    $schoolYearId = (int) ($filters['school_year_id'] ?? 0);
    if ($schoolYearId > 0) {
        $where[] = "{$alias}.school_year_id = ?";
        $params[] = $schoolYearId;
    }

    $semester = trim((string) ($filters['semester'] ?? 'All'));
    if (in_array($semester, ['First Semester', 'Second Semester'], true)) {
        $where[] = "{$alias}.semester = ?";
        $params[] = $semester;
    }

    if ($current) {
        $where[] = "{$alias}.status = 'Active'";
        $where[] = "{$alias}.effective_start <= CURRENT_DATE";
        $where[] = "({$alias}.effective_end IS NULL OR {$alias}.effective_end >= CURRENT_DATE)";
    } elseif ($status !== '' && $status !== 'All') {
        $where[] = "{$alias}.status = ?";
        $params[] = $status;
    }

    return [
        'sql' => implode(' AND ', $where),
        'params' => $params,
        'current' => $current,
    ];
}

/**
 * Build the current class-participation predicate for a selected academic
 * context. Participation is evidence of current activity only; it never
 * supplies institutional placement values.
 *
 * @return array{sql: string, params: array<int, mixed>}
 */
function aprismRegistryCurrentParticipationSelection(
    array $filters,
    string $studentAlias = 'st'
): array {
    $where = [
        "sce_current.student_id = {$studentAlias}.student_id",
        "sce_current.status = 'Active'",
        "oc_current.status = 'Active'",
    ];
    $params = [];
    $schoolYearId = (int) ($filters['school_year_id'] ?? 0);

    if ($schoolYearId <= 0) {
        $where[] = '1 = 0';
    } else {
        $where[] = 'sy_current.school_year_id = ?';
        $params[] = $schoolYearId;
    }

    $semester = trim((string) ($filters['semester'] ?? 'All'));
    if (in_array($semester, ['First Semester', 'Second Semester'], true)) {
        $where[] = 'oc_current.semester = ?';
        $params[] = $semester;
    }

    return [
        'sql' => 'EXISTS (
            SELECT 1
            FROM student_class_enrollments sce_current
            INNER JOIN operational_classes oc_current
                ON oc_current.operational_class_id = sce_current.operational_class_id
            INNER JOIN school_years sy_current
                ON sy_current.school_year = oc_current.school_year
            WHERE ' . implode(' AND ', $where) . '
        )',
        'params' => $params,
    ];
}

/**
 * Students are the identity base; placement is optional. This deliberately
 * does not use Student Class Enrollment to infer academic placement.
 *
 * @return array{join: string, where: string, params: array<int, mixed>, page_params: array<int, mixed>, placement_count_sql: string, placement_count_params: array<int, mixed>}
 */
function aprismRegistryStudentQueryParts(array $filters): array
{
    $selection = aprismRegistryPlacementSelection($filters, 'sae2');
    $countSelection = aprismRegistryPlacementSelection($filters, 'sae_count');
    $missingSelection = aprismRegistryPlacementSelection($filters, 'sae_missing');
    $participationSelection = aprismRegistryCurrentParticipationSelection($filters);
    $join = "\n        LEFT JOIN student_academic_enrollments sae\n            ON sae.student_academic_enrollment_id = (\n                SELECT sae2.student_academic_enrollment_id\n                FROM student_academic_enrollments sae2\n                WHERE {$selection['sql']}\n                ORDER BY sae2.effective_start DESC, sae2.student_academic_enrollment_id DESC\n                LIMIT 1\n            )\n            AND (\n                SELECT COUNT(*)\n                FROM student_academic_enrollments sae_count\n                WHERE {$countSelection['sql']}\n            ) <= 1\n        LEFT JOIN school_years sy ON sy.school_year_id = sae.school_year_id";
    $joinParams = array_merge($selection['params'], $countSelection['params']);

    $where = ['1 = 1'];
    $whereParams = [];
    $search = trim((string) ($filters['search'] ?? ''));
    if ($search !== '') {
        $where[] = '(st.student_number LIKE ? OR CONCAT_WS(\' \' , st.first_name, st.middle_name, st.last_name, st.suffix) LIKE ?)';
        $like = '%' . $search . '%';
        $whereParams[] = $like;
        $whereParams[] = $like;
    }
    if (($filters['academic_level'] ?? 'All') !== 'All') {
        $where[] = 'sae.academic_level = ?';
        $whereParams[] = $filters['academic_level'];
    }
    if ((int) ($filters['program_id'] ?? 0) > 0) {
        $where[] = 'sae.program_id = ?';
        $whereParams[] = (int) $filters['program_id'];
    }
    if ((int) ($filters['section_id'] ?? 0) > 0) {
        $where[] = 'sae.section_id = ?';
        $whereParams[] = (int) $filters['section_id'];
    }
    if (($filters['status'] ?? 'All') === 'Missing') {
        $where[] = 'sae.student_academic_enrollment_id IS NULL';
        $where[] = "(SELECT COUNT(*) FROM student_academic_enrollments sae_missing WHERE {$missingSelection['sql']}) = 0";
        $whereParams = array_merge($whereParams, $missingSelection['params']);
    } elseif (($filters['status'] ?? 'All') !== 'All') {
        $where[] = 'sae.status = ?';
        $whereParams[] = $filters['status'];
    }

    $placementCount = aprismRegistryPlacementSelection($filters, 'sae_count_page');

    return [
        'join' => $join,
        'where' => implode(' AND ', $where),
        'params' => array_merge($joinParams, $whereParams),
        'page_params' => array_merge($placementCount['params'], $placementCount['params'], $joinParams, $whereParams),
        'join_params' => $joinParams,
        'where_params' => $whereParams,
        'placement_count_sql' => "(SELECT COUNT(*) FROM student_academic_enrollments sae_count_page WHERE {$placementCount['sql']})",
        'placement_count_params' => $placementCount['params'],
        'participation_sql' => $participationSelection['sql'],
        'participation_params' => $participationSelection['params'],
        'current' => $selection['current'],
    ];
}

/**
 * Returns identity-base Students without a valid/open SAE for the selected
 * institutional context. SCE is only participation evidence and is not used
 * to infer placement.
 *
 * @return array{where: string, params: array<int, mixed>}
 */
function aprismRegistryUnresolvedStudentQueryParts(array $filters): array
{
    $where = ["st.status = 'Active'"];
    $params = [];
    $search = trim((string) ($filters['search'] ?? ''));
    if ($search !== '') {
        $where[] = '(st.student_number LIKE ? OR CONCAT_WS(\' \' , st.first_name, st.middle_name, st.last_name, st.suffix) LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $schoolYearId = (int) ($filters['school_year_id'] ?? 0);
    $semester = trim((string) ($filters['semester'] ?? ''));
    if ($schoolYearId <= 0 || !in_array($semester, ['First Semester', 'Second Semester'], true)) {
        $where[] = '1 = 0';
    } else {
        $contextFilters = [
            'school_year_id' => $schoolYearId,
            'semester' => $semester,
            'status' => 'All',
        ];
        $placementMissing = aprismRegistryPlacementSelection($contextFilters, 'sae_missing');
        $placementAmbiguous = aprismRegistryPlacementSelection($contextFilters, 'sae_ambiguous');
        $participation = aprismRegistryCurrentParticipationSelection($contextFilters);

        // Missing placement is actionable only when the Student is currently
        // participating. Multiple open placements remain a genuine issue.
        $where[] = "(
            (
                NOT EXISTS (
                    SELECT 1 FROM student_academic_enrollments sae_missing
                    WHERE {$placementMissing['sql']}
                )
                AND {$participation['sql']}
            )
            OR (
                SELECT COUNT(*)
                FROM student_academic_enrollments sae_ambiguous
                WHERE {$placementAmbiguous['sql']}
            ) > 1
        )";
        $params = array_merge(
            $params,
            $placementMissing['params'],
            $participation['params'],
            $placementAmbiguous['params']
        );
    }

    return ['where' => implode(' AND ', $where), 'params' => $params];
}

function aprismRegistryUnresolvedStudentsCount(PDO $pdo, array $filters): int
{
    $parts = aprismRegistryUnresolvedStudentQueryParts($filters);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students st WHERE {$parts['where']}");
    $stmt->execute($parts['params']);
    return (int) $stmt->fetchColumn();
}

function aprismRegistryUnresolvedStudentsPage(PDO $pdo, array $filters, int $limit, int $offset): array
{
    $parts = aprismRegistryUnresolvedStudentQueryParts($filters);
    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);
    $placementSelection = aprismRegistryPlacementSelection([
        'school_year_id' => (int) ($filters['school_year_id'] ?? 0),
        'semester' => trim((string) ($filters['semester'] ?? '')),
        'status' => 'All',
    ], 'sae_issue_count');
    $stmt = $pdo->prepare("SELECT st.student_id, st.student_number,
            CONCAT_WS(' ', st.first_name, st.middle_name, st.last_name, st.suffix) AS student_name,
            st.status,
            (SELECT COUNT(*)
             FROM student_academic_enrollments sae_issue_count
             WHERE {$placementSelection['sql']}) AS current_placement_count,
            (SELECT GROUP_CONCAT(DISTINCT CONCAT_WS(' · ', subj.subject_code, sec.section_name)
                ORDER BY subj.subject_code, sec.section_name SEPARATOR ', ')
             FROM student_class_enrollments sce
             INNER JOIN operational_classes oc ON oc.operational_class_id = sce.operational_class_id
             LEFT JOIN subjects subj ON subj.subject_id = oc.subject_id
             LEFT JOIN sections sec ON sec.section_id = oc.section_id
             WHERE sce.student_id = st.student_id
               AND sce.status = 'Active'
               AND oc.status = 'Active'
               AND oc.school_year = (SELECT school_year FROM school_years WHERE school_year_id = ?)
               AND oc.semester = ?) AS participation_evidence
        FROM students st
        WHERE {$parts['where']}
        ORDER BY st.last_name, st.first_name, st.student_number, st.student_id
        LIMIT {$limit} OFFSET {$offset}");
    $stmt->execute(array_merge(
        $placementSelection['params'],
        [
            (int) ($filters['school_year_id'] ?? 0),
            trim((string) ($filters['semester'] ?? '')),
        ],
        $parts['params']
    ));
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array<string, mixed> */
function aprismRegistryValidatePlacement(PDO $pdo, array $placement): array
{
    $schoolYearId = (int) ($placement['school_year_id'] ?? 0);
    $programId = (int) ($placement['program_id'] ?? 0);
    $sectionId = (int) ($placement['section_id'] ?? 0);
    $academicLevel = trim((string) ($placement['academic_level'] ?? ''));
    $yearLevel = trim((string) ($placement['year_level'] ?? ''));
    $semester = trim((string) ($placement['semester'] ?? ''));

    if ($schoolYearId <= 0 || $programId <= 0 || $sectionId <= 0 || $academicLevel === '' || $yearLevel === '') {
        throw new RuntimeException('School Year, academic level, Program, Year / Grade, and Section are required.');
    }
    if (!in_array($semester, ['First Semester', 'Second Semester'], true)) {
        throw new RuntimeException('Select a valid broader teaching context.');
    }
    if (!in_array($academicLevel, ['College', 'Senior High School'], true)) {
        throw new RuntimeException('Select a valid academic level.');
    }

    $schoolYearStmt = $pdo->prepare("SELECT school_year_id, school_year, start_date
        FROM school_years WHERE school_year_id = ? AND status IN ('Active', 'Inactive') LIMIT 1");
    $schoolYearStmt->execute([$schoolYearId]);
    $schoolYear = $schoolYearStmt->fetch(PDO::FETCH_ASSOC);
    if (!$schoolYear) {
        throw new RuntimeException('The selected School Year is not available.');
    }

    $programStmt = $pdo->prepare("SELECT program_id, program_code, program_name, academic_level
        FROM programs WHERE program_id = ? AND status = 'Active' LIMIT 1");
    $programStmt->execute([$programId]);
    $program = $programStmt->fetch(PDO::FETCH_ASSOC);
    if (!$program || $program['academic_level'] !== $academicLevel) {
        throw new RuntimeException('The selected Program does not match the chosen academic level.');
    }

    $sectionStmt = $pdo->prepare("SELECT section_id, section_name, program_id, year_level
        FROM sections WHERE section_id = ? AND program_id = ? AND status = 'Active' LIMIT 1");
    $sectionStmt->execute([$sectionId, $programId]);
    $section = $sectionStmt->fetch(PDO::FETCH_ASSOC);
    if (!$section) {
        throw new RuntimeException('The selected Section is not active or does not belong to the selected Program.');
    }

    $validYears = $academicLevel === 'Senior High School' ? ['11', '12'] : ['1', '2', '3', '4'];
    if (!in_array($yearLevel, $validYears, true)) {
        throw new RuntimeException('The selected Year / Grade is not valid for the chosen academic level.');
    }
    if ($section['year_level'] === null || (string) $section['year_level'] !== $yearLevel) {
        throw new RuntimeException('The selected Section year / grade does not match the reviewed placement.');
    }

    return [
        'school_year_id' => $schoolYearId,
        'school_year' => (string) $schoolYear['school_year'],
        'effective_start' => (string) $schoolYear['start_date'],
        'semester' => $semester,
        'academic_level' => $academicLevel,
        'program_id' => $programId,
        'program_code' => (string) ($program['program_code'] ?? ''),
        'program_name' => (string) $program['program_name'],
        'section_id' => $sectionId,
        'section_name' => (string) $section['section_name'],
        'year_level' => $yearLevel,
    ];
}

/**
 * Applies one reviewed placement to one or more Students atomically.
 * @param array<int, int> $studentIds
 * @param array<string, mixed> $placement
 * @return array{students: int, enrollments_created: int, enrollments_reused: int, sce_linked: int}
 */
function aprismRegistryReconcileStudentPlacements(PDO $pdo, array $studentIds, array $placement): array
{
    $placement = aprismRegistryValidatePlacement($pdo, $placement);
    $studentIds = array_values(array_unique(array_filter(array_map('intval', $studentIds), static fn (int $id): bool => $id > 0)));
    if ($studentIds === []) {
        throw new RuntimeException('Select at least one unresolved Student.');
    }

    $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
    $summary = ['students' => count($studentIds), 'enrollments_created' => 0, 'enrollments_reused' => 0, 'sce_linked' => 0];
    $pdo->beginTransaction();
    try {
        $studentStmt = $pdo->prepare("SELECT student_id, status FROM students WHERE student_id IN ({$placeholders}) FOR UPDATE");
        $studentStmt->execute($studentIds);
        $lockedStudents = [];
        foreach ($studentStmt->fetchAll(PDO::FETCH_ASSOC) as $student) {
            $lockedStudents[(int) $student['student_id']] = $student;
        }
        if (count($lockedStudents) !== count($studentIds)) {
            throw new RuntimeException('One or more selected Students no longer exist. Refresh the unresolved list.');
        }

        foreach ($studentIds as $studentId) {
            if (($lockedStudents[$studentId]['status'] ?? '') !== 'Active') {
                throw new RuntimeException('Only active Students can receive a current placement.');
            }
            $currentStmt = $pdo->prepare("SELECT student_academic_enrollment_id, academic_level, program_id, section_id, year_level
                FROM student_academic_enrollments
                WHERE student_id = ? AND school_year_id = ? AND semester = ?
                  AND status = 'Active' AND effective_end IS NULL
                ORDER BY student_academic_enrollment_id FOR UPDATE");
            $currentStmt->execute([$studentId, $placement['school_year_id'], $placement['semester']]);
            $currentRows = $currentStmt->fetchAll(PDO::FETCH_ASSOC);
            $enrollmentId = null;
            foreach ($currentRows as $current) {
                $isExact = $current['academic_level'] === $placement['academic_level']
                    && (int) $current['program_id'] === (int) $placement['program_id']
                    && (int) $current['section_id'] === (int) $placement['section_id']
                    && (string) $current['year_level'] === (string) $placement['year_level'];
                if (!$isExact || $enrollmentId !== null) {
                    throw new RuntimeException('A selected Student has a conflicting current Academic Enrollment in the same School Year and teaching context. No placement was overwritten.');
                }
                $enrollmentId = (int) $current['student_academic_enrollment_id'];
            }

            if ($enrollmentId === null) {
                $insert = $pdo->prepare("INSERT INTO student_academic_enrollments
                    (student_id, school_year_id, semester, academic_level, program_id, section_id, year_level, status, effective_start, effective_end)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Active', ?, NULL)");
                $insert->execute([$studentId, $placement['school_year_id'], $placement['semester'], $placement['academic_level'], $placement['program_id'], $placement['section_id'], $placement['year_level'], $placement['effective_start']]);
                $enrollmentId = (int) $pdo->lastInsertId();
                $summary['enrollments_created']++;
            } else {
                $summary['enrollments_reused']++;
            }

            $sceStmt = $pdo->prepare("SELECT sce.student_class_enrollment_id, sce.enrollment_id
                FROM student_class_enrollments sce
                INNER JOIN operational_classes oc ON oc.operational_class_id = sce.operational_class_id
                WHERE sce.student_id = ? AND oc.school_year = ? AND oc.semester = ?
                FOR UPDATE");
            $sceStmt->execute([$studentId, $placement['school_year'], $placement['semester']]);
            foreach ($sceStmt->fetchAll(PDO::FETCH_ASSOC) as $sce) {
                if ($sce['enrollment_id'] !== null && (int) $sce['enrollment_id'] !== $enrollmentId) {
                    throw new RuntimeException('A Class Enrollment is already linked to a different Academic Enrollment. No historical link was overwritten.');
                }
                if ($sce['enrollment_id'] === null) {
                    $link = $pdo->prepare("UPDATE student_class_enrollments SET enrollment_id = ? WHERE student_class_enrollment_id = ? AND student_id = ? AND enrollment_id IS NULL");
                    $link->execute([$enrollmentId, (int) $sce['student_class_enrollment_id'], $studentId]);
                    $summary['sce_linked']++;
                }
            }
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    return $summary;
}

function aprismRegistryStudentsCount(PDO $pdo, array $filters): int
{
    $parts = aprismRegistryStudentQueryParts($filters);
    $stmt = $pdo->prepare("SELECT COUNT(*)\n        FROM students st{$parts['join']}\n        LEFT JOIN programs p ON p.program_id = sae.program_id\n        LEFT JOIN sections sec ON sec.section_id = sae.section_id\n        WHERE {$parts['where']}");
    $stmt->execute($parts['params']);
    return (int) $stmt->fetchColumn();
}

function aprismRegistryStudentsPage(PDO $pdo, array $filters, int $limit, int $offset): array
{
    $parts = aprismRegistryStudentQueryParts($filters);
    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);
    $participationSql = $parts['current']
        ? ", CASE WHEN {$parts['participation_sql']} THEN 'Participating' ELSE 'No Current Class Participation' END AS participation_status"
        : ', NULL AS participation_status';
    $stmt = $pdo->prepare("SELECT sae.student_academic_enrollment_id, st.student_id, st.student_number,\n            CONCAT_WS(' ', st.first_name, st.middle_name, st.last_name, st.suffix) AS student_name,\n            st.status AS student_status, sae.academic_level, sae.year_level, sae.status,\n            {$parts['placement_count_sql']} AS current_placement_count,\n            CASE\n                WHEN {$parts['placement_count_sql']} > 1 THEN 'Ambiguous / Conflicting'\n                WHEN sae.student_academic_enrollment_id IS NULL THEN 'Missing / Unresolved'\n                ELSE sae.status\n            END AS placement_status,\n            sy.school_year, sae.semester, p.program_code, p.program_name, sec.section_name{$participationSql}\n        FROM students st{$parts['join']}\n        LEFT JOIN programs p ON p.program_id = sae.program_id\n        LEFT JOIN sections sec ON sec.section_id = sae.section_id\n        WHERE {$parts['where']}\n        ORDER BY st.last_name, st.first_name, st.student_number, sae.student_academic_enrollment_id DESC\n        LIMIT {$limit} OFFSET {$offset}");
    $pageParams = array_merge(
        $parts['placement_count_params'],
        $parts['placement_count_params'],
        $parts['current'] ? $parts['participation_params'] : [],
        $parts['join_params'],
        $parts['where_params']
    );
    $stmt->execute($pageParams);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function aprismRegistrySectionWhere(array $filters): array
{
    $where = ['1 = 1'];
    $params = [];
    $search = trim((string) ($filters['search'] ?? ''));
    if ($search !== '') {
        $where[] = '(sec.section_name LIKE ? OR p.program_code LIKE ? OR p.program_name LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like);
    }
    if (($filters['academic_level'] ?? 'All') !== 'All') {
        $where[] = 'p.academic_level = ?';
        $params[] = $filters['academic_level'];
    }
    if ((int) ($filters['program_id'] ?? 0) > 0) {
        $where[] = 'sec.program_id = ?';
        $params[] = (int) $filters['program_id'];
    }
    if (($filters['year_level'] ?? 'All') !== 'All') {
        $where[] = 'sec.year_level = ?';
        $params[] = $filters['year_level'];
    }
    if (($filters['status'] ?? 'All') !== 'All') {
        $where[] = 'sec.status = ?';
        $params[] = $filters['status'];
    }
    return [implode(' AND ', $where), $params];
}

function aprismRegistrySectionsCount(PDO $pdo, array $filters): int
{
    [$where, $params] = aprismRegistrySectionWhere($filters);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sections sec LEFT JOIN programs p ON p.program_id = sec.program_id WHERE {$where}");
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function aprismRegistrySectionsPage(PDO $pdo, array $filters, int $limit, int $offset): array
{
    [$where, $params] = aprismRegistrySectionWhere($filters);
    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);
    $schoolYearId = (int) ($filters['school_year_id'] ?? 0);
    $countParams = [];
    $countCondition = '1 = 0';
    if ($schoolYearId > 0) {
        $countCondition = "sae.school_year_id = ?
             AND sae.status = 'Active'
             AND sae.effective_start <= CURRENT_DATE
             AND (sae.effective_end IS NULL OR sae.effective_end >= CURRENT_DATE)
             AND EXISTS (SELECT 1 FROM students st_count WHERE st_count.student_id = sae.student_id AND st_count.status = 'Active')";
        $countParams[] = $schoolYearId;
    }
    $semester = trim((string) ($filters['semester'] ?? 'All'));
    if (in_array($semester, ['First Semester', 'Second Semester'], true)) {
        $countCondition .= ' AND sae.semester = ?';
        $countParams[] = $semester;
    }
    $stmt = $pdo->prepare("SELECT sec.section_id, sec.section_name, sec.year_level, sec.status,\n            p.program_code, p.program_name, p.academic_level,\n            (SELECT COUNT(DISTINCT sae.student_id) FROM student_academic_enrollments sae\n             WHERE sae.section_id = sec.section_id AND {$countCondition}) AS active_student_count\n        FROM sections sec LEFT JOIN programs p ON p.program_id = sec.program_id\n        WHERE {$where}\n        ORDER BY p.program_code, sec.year_level, sec.section_name, sec.section_id\n        LIMIT {$limit} OFFSET {$offset}");
    $stmt->execute(array_merge($countParams, $params));
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
