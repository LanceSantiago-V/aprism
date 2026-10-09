<?php

declare(strict_types=1);

/** @return array{where: string, params: array<int, mixed>} */
function aprismAcademicScheduleWhere(array $filters): array
{
    $where = ['1 = 1'];
    $params = [];
    $search = trim((string) ($filters['search'] ?? ''));
    if ($search !== '') {
        $where[] = '(s.subject_name LIKE ? OR s.subject_code LIKE ? OR sec.section_name LIKE ? OR CONCAT_WS(\' \' , u.first_name, u.last_name) LIKE ? OR u.employee_number LIKE ? OR cs.room LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like, $like, $like);
    }
    if ((int) ($filters['section_id'] ?? 0) > 0) {
        $where[] = 'oc.section_id = ?';
        $params[] = (int) $filters['section_id'];
    }
    if ((int) ($filters['teacher_id'] ?? 0) > 0) {
        $where[] = 'oc.teacher_id = ?';
        $params[] = (int) $filters['teacher_id'];
    }
    if ((int) ($filters['school_year_id'] ?? 0) > 0) {
        $where[] = 'sy.school_year_id = ?';
        $params[] = (int) $filters['school_year_id'];
    }
    if (($filters['semester'] ?? 'All') !== 'All') {
        $where[] = 'oc.semester = ?';
        $params[] = $filters['semester'];
    }
    if (($filters['status'] ?? 'All') !== 'All') {
        $where[] = 'cs.status = ?';
        $params[] = $filters['status'];
    }

    return ['where' => implode(' AND ', $where), 'params' => $params];
}

function aprismAcademicSchedulesCount(PDO $pdo, array $filters): int
{
    $parts = aprismAcademicScheduleQueryParts($filters);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM operational_classes oc\n        INNER JOIN class_schedules cs ON cs.operational_class_id = oc.operational_class_id\n        INNER JOIN subjects s ON s.subject_id = oc.subject_id\n        INNER JOIN sections sec ON sec.section_id = oc.section_id\n        INNER JOIN users u ON u.user_id = oc.teacher_id\n        INNER JOIN roles r ON r.role_id = u.role_id AND r.role_name = 'Teacher'\n        LEFT JOIN school_years sy ON sy.school_year = oc.school_year\n        WHERE {$parts['where']}");
    $stmt->execute($parts['params']);
    return (int) $stmt->fetchColumn();
}

function aprismAcademicSchedulesPage(PDO $pdo, array $filters, int $limit, int $offset): array
{
    $parts = aprismAcademicScheduleQueryParts($filters);
    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);
    $stmt = $pdo->prepare("SELECT oc.operational_class_id, cs.class_schedule_id, s.subject_code, s.subject_name,\n            sec.section_name, u.employee_number, CONCAT_WS(' ', u.first_name, u.last_name) AS teacher_name,\n            cs.day, cs.start_time, cs.end_time, cs.room, oc.school_year, oc.semester, cs.status\n        FROM operational_classes oc\n        INNER JOIN class_schedules cs ON cs.operational_class_id = oc.operational_class_id\n        INNER JOIN subjects s ON s.subject_id = oc.subject_id\n        INNER JOIN sections sec ON sec.section_id = oc.section_id\n        INNER JOIN users u ON u.user_id = oc.teacher_id\n        INNER JOIN roles r ON r.role_id = u.role_id AND r.role_name = 'Teacher'\n        LEFT JOIN school_years sy ON sy.school_year = oc.school_year\n        WHERE {$parts['where']}\n        ORDER BY oc.school_year DESC, s.subject_name, sec.section_name, u.last_name, u.first_name,\n            FIELD(cs.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),\n            cs.start_time, cs.class_schedule_id\n        LIMIT {$limit} OFFSET {$offset}");
    $stmt->execute($parts['params']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array{where: string, params: array<int, mixed>} */
function aprismAcademicScheduleQueryParts(array $filters): array
{
    return aprismAcademicScheduleWhere($filters);
}
