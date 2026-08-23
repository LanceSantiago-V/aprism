<?php

declare(strict_types=1);

require_once __DIR__ . '/class_list_import_plan_engine.php';
require_once __DIR__ . '/../helper/audit_helper.php';

/**
 * The only Class List write boundary. It rebuilds the import plan from the
 * temporary source, locks the permanent Student identity, then creates or
 * reuses the associated historical placement and class participation inside
 * one transaction.
 */
final class ClassListPersistenceEngine
{
    /**
     * @param array<string, mixed> $source
     * @param array<string, mixed> $classContext
     * @param array<string, int|null> $mapping
     * @param array<int, array<string, string>> $identityOverrides
     * @param array<int, array<string, mixed>> $contextDecisions
     * @return array<string, mixed>
     */
    public function confirm(
        PDO $pdo,
        array $source,
        array $classContext,
        string $worksheetName,
        int $headerRow,
        int $firstDataRow,
        array $mapping,
        array $identityOverrides,
        array $contextDecisions,
        int $teacherId
    ): array {
        $plan = (new ClassListImportPlanEngine())->build(
            $pdo,
            $source,
            $classContext,
            $worksheetName,
            $headerRow,
            $firstDataRow,
            $mapping,
            $identityOverrides,
            $contextDecisions
        );

        if (($plan['confirmation_enabled'] ?? false) !== true) {
            throw new RuntimeException('The server-validated import plan still contains blocked or incomplete rows.');
        }

        $result = [
            'student_created' => 0,
            'student_reused' => 0,
            'academic_enrollment_created' => 0,
            'academic_enrollment_reused' => 0,
            'class_enrollment_created' => 0,
            'class_enrollment_reused' => 0,
            'processed_rows' => 0,
        ];

        try {
            $pdo->beginTransaction();

            foreach ($plan['rows'] as $row) {
                $studentId = $this->lockOrCreateStudent($pdo, $row, $result);

                if (($row['class_enrollment_action'] ?? '') === 'Reuse existing Class Enrollment') {
                    $this->lockExistingClassEnrollment(
                        $pdo,
                        $studentId,
                        (int) $classContext['operational_class_id']
                    );
                    $result['class_enrollment_reused']++;
                    $result['processed_rows']++;
                    continue;
                }

                $enrollmentId = $this->lockOrCreateAcademicEnrollment(
                    $pdo,
                    $studentId,
                    $row,
                    $classContext,
                    $result
                );

                if (
                    $this->lockExistingClassEnrollment(
                        $pdo,
                        $studentId,
                        (int) $classContext['operational_class_id']
                    ) !== null
                ) {
                    $result['class_enrollment_reused']++;
                } else {
                    $stmt = $pdo->prepare("\n                        INSERT INTO student_class_enrollments\n                            (student_id, enrollment_id, operational_class_id, status)\n                        VALUES (?, ?, ?, 'Active')\n                    ");
                    $stmt->execute([
                        $studentId,
                        $enrollmentId,
                        (int) $classContext['operational_class_id'],
                    ]);
                    $result['class_enrollment_created']++;
                }

                $result['processed_rows']++;
            }

            $description = sprintf(
                'Confirmed Class List import for Operational Class #%d: %d row(s); Students created %d/reused %d; Academic Enrollments created %d/reused %d; Class Enrollments created %d/reused %d.',
                (int) $classContext['operational_class_id'],
                $result['processed_rows'],
                $result['student_created'],
                $result['student_reused'],
                $result['academic_enrollment_created'],
                $result['academic_enrollment_reused'],
                $result['class_enrollment_created'],
                $result['class_enrollment_reused']
            );

            if (!logAudit($pdo, 'Class List Import Confirmed', $description)) {
                throw new RuntimeException('The Class List import could not be audited. No records were changed.');
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }

        return [
            'summary' => $result,
            'plan_summary' => $plan['summary'],
        ];
    }

    /** @param array<string, mixed> $row @param array<string, int> $result */
    private function lockOrCreateStudent(PDO $pdo, array $row, array &$result): int
    {
        $studentNumber = (string) $row['student_number'];
        $stmt = $pdo->prepare("\n            SELECT student_id, status\n            FROM students\n            WHERE student_number = ?\n            FOR UPDATE\n        ");
        $stmt->execute([$studentNumber]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($student !== null) {
            if ((string) $student['status'] !== 'Active') {
                throw new RuntimeException('A matched Student is no longer Active. Refresh Review & Resolve before confirming.');
            }

            $result['student_reused']++;
            return (int) $student['student_id'];
        }

        if (($row['student_action'] ?? '') !== 'Proposed new Student') {
            throw new RuntimeException('The Student identity changed after the import plan was prepared. Refresh Review & Resolve.');
        }

        $identity = $row['proposed_identity'] ?? [];
        $firstName = trim((string) ($identity['first_name'] ?? ''));
        $lastName = trim((string) ($identity['last_name'] ?? ''));

        if ($firstName === '' || $lastName === '') {
            throw new RuntimeException('A new Student requires reviewed First Name and Last Name.');
        }

        $created = false;

        try {
            $insert = $pdo->prepare("\n                INSERT INTO students (student_number, first_name, middle_name, last_name, suffix, status)\n                VALUES (?, ?, NULLIF(?, ''), ?, NULLIF(?, ''), 'Active')\n            ");
            $insert->execute([
                $studentNumber,
                $firstName,
                trim((string) ($identity['middle_name'] ?? '')),
                $lastName,
                trim((string) ($identity['suffix'] ?? '')),
            ]);
            $created = true;
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }
        }

        $stmt->execute([$studentNumber]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($student === null || (string) $student['status'] !== 'Active') {
            throw new RuntimeException('The Student identity could not be locked safely.');
        }

        if ($created) {
            $result['student_created']++;
        } else {
            $result['student_reused']++;
        }
        return (int) $student['student_id'];
    }

    /** @param array<string, mixed> $row @param array<string, mixed> $classContext @param array<string, int> $result */
    private function lockOrCreateAcademicEnrollment(PDO $pdo, int $studentId, array $row, array $classContext, array &$result): int
    {
        $decision = $row['context_decision'] ?? null;

        if (($row['academic_enrollment_action'] ?? '') === 'Reuse Academic Enrollment') {
            $enrollmentId = (int) ($row['planned_academic_enrollment_id'] ?? 0);

            if ($enrollmentId < 1 || !$this->lockAcademicEnrollmentById($pdo, $enrollmentId, $studentId)) {
                throw new RuntimeException('The selected Academic Enrollment changed after the import plan was prepared. Refresh Review & Resolve.');
            }

            $result['academic_enrollment_reused']++;
            return $enrollmentId;
        }

        if (($row['academic_enrollment_action'] ?? '') !== 'Proposed Academic Enrollment' || !is_array($decision)) {
            throw new RuntimeException('Academic context needs an explicit reviewed decision before confirmation.');
        }

        $matches = $this->lockCurrentAcademicEnrollmentMatches(
            $pdo,
            $studentId,
            (int) $classContext['school_year_id'],
            $decision
        );

        if (count($matches) > 1) {
            throw new RuntimeException('More than one current Academic Enrollment has the selected context. Resolve the records before confirming.');
        }

        if (count($matches) === 1) {
            $result['academic_enrollment_reused']++;
            return (int) $matches[0]['student_academic_enrollment_id'];
        }

        $insert = $pdo->prepare("\n            INSERT INTO student_academic_enrollments\n                (student_id, school_year_id, semester, academic_level, program_id, section_id, year_level, status, effective_start, effective_end)\n            VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, ''), ?, ?, NULL)\n        ");
        $insert->execute([
            $studentId,
            (int) $classContext['school_year_id'],
            $decision['semester'],
            $decision['academic_level'],
            $decision['program_id'],
            $decision['section_id'],
            $decision['year_level'],
            $decision['status'],
            $decision['effective_start'],
        ]);

        $result['academic_enrollment_created']++;
        return (int) $pdo->lastInsertId();
    }

    private function lockAcademicEnrollmentById(PDO $pdo, int $enrollmentId, int $studentId): bool
    {
        $stmt = $pdo->prepare("\n            SELECT student_academic_enrollment_id\n            FROM student_academic_enrollments\n            WHERE student_academic_enrollment_id = ?\n              AND student_id = ?\n              AND status IN ('Active', 'Review')\n              AND effective_end IS NULL\n            FOR UPDATE\n        ");
        $stmt->execute([$enrollmentId, $studentId]);
        return $stmt->fetchColumn() !== false;
    }

    /** @return array<int, array<string, mixed>> */
    private function lockCurrentAcademicEnrollmentMatches(PDO $pdo, int $studentId, int $schoolYearId, array $decision): array
    {
        $stmt = $pdo->prepare("\n            SELECT student_academic_enrollment_id\n            FROM student_academic_enrollments\n            WHERE student_id = ?\n              AND school_year_id = ?\n              AND semester <=> ?\n              AND academic_level = ?\n              AND program_id <=> ?\n              AND section_id <=> ?\n              AND year_level <=> ?\n              AND status IN ('Active', 'Review')\n              AND effective_end IS NULL\n            ORDER BY student_academic_enrollment_id\n            FOR UPDATE\n        ");
        $stmt->execute([
            $studentId,
            $schoolYearId,
            $decision['semester'],
            $decision['academic_level'],
            $decision['program_id'],
            $decision['section_id'],
            $decision['year_level'] === '' ? null : $decision['year_level'],
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function lockExistingClassEnrollment(PDO $pdo, int $studentId, int $operationalClassId): ?int
    {
        $stmt = $pdo->prepare("\n            SELECT student_class_enrollment_id\n            FROM student_class_enrollments\n            WHERE student_id = ?\n              AND operational_class_id = ?\n            FOR UPDATE\n        ");
        $stmt->execute([$studentId, $operationalClassId]);
        $value = $stmt->fetchColumn();

        return $value === false ? null : (int) $value;
    }
}
