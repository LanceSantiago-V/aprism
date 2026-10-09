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
     * @param array<int, array<string, mixed>> $identityReconciliations
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
        array $identityReconciliations,
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
            $contextDecisions,
            $identityReconciliations
        );

        if (($plan['confirmation_enabled'] ?? false) !== true) {
            throw new RuntimeException('The server-validated import plan still contains blocked or incomplete rows.');
        }

        $result = [
            'student_created' => 0,
            'student_reused' => 0,
            'student_number_reconciled' => 0,
            'academic_enrollment_created' => 0,
            'academic_enrollment_reused' => 0,
            'class_enrollment_created' => 0,
            'class_enrollment_reused' => 0,
            'processed_rows' => 0,
        ];

        try {
            $pdo->beginTransaction();

            foreach ($plan['rows'] as $row) {
                $studentId = $this->lockOrCreateStudent(
                    $pdo,
                    $row,
                    (int) $classContext['operational_class_id'],
                    $result
                );
                $enrollmentId = $this->lockOrCreateAcademicEnrollment(
                    $pdo,
                    $studentId,
                    $row,
                    $classContext,
                    $result
                );

                if (($row['class_enrollment_action'] ?? '') === 'Reuse existing Class Enrollment') {
                    $existingClassEnrollmentId = $this->lockExistingClassEnrollment(
                        $pdo,
                        $studentId,
                        (int) $classContext['operational_class_id']
                    );

                    if ($existingClassEnrollmentId === null) {
                        throw new RuntimeException('The existing Class Enrollment changed after the import plan was prepared. Refresh Review & Resolve.');
                    }

                    if ($enrollmentId !== null) {
                        $this->attachAcademicEnrollmentToExistingClassEnrollment(
                            $pdo,
                            $existingClassEnrollmentId,
                            $studentId,
                            $enrollmentId
                        );
                    }

                    $result['class_enrollment_reused']++;
                    $result['processed_rows']++;
                    continue;
                }

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

    /**
     * @param array<string, mixed> $row
     * @param array<string, int> $result
     */
    private function lockOrCreateStudent(
        PDO $pdo,
        array $row,
        int $operationalClassId,
        array &$result
    ): int
    {
        $studentNumber = trim((string) ($row['student_number'] ?? ''));

        if (($row['student_action'] ?? '') === 'Reconcile Student Number') {
            $candidateId = (int) ($row['candidate_student_id'] ?? 0);
            if ($studentNumber === '' || $candidateId < 1) {
                throw new RuntimeException('The reviewed Student Number reconciliation is incomplete. Refresh Review & Resolve.');
            }
            $candidate = $this->lockUnresolvedStudentById($pdo, $candidateId, $operationalClassId);
            if ($candidate === null) {
                throw new RuntimeException('The same-class unresolved Student changed before reconciliation. Refresh Review & Resolve.');
            }
            $check = $pdo->prepare("SELECT student_id FROM students WHERE student_number = ? FOR UPDATE");
            $check->execute([$studentNumber]);
            if ($check->fetchColumn() !== false) {
                throw new RuntimeException('The incoming Student Number is already owned by another Student. Review the identity conflict before confirming.');
            }
            $update = $pdo->prepare("UPDATE students SET student_number = ? WHERE student_id = ? AND student_number IS NULL");
            $update->execute([$studentNumber, $candidateId]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('The Student Number reconciliation could not be applied safely. Refresh Review & Resolve.');
            }
            $result['student_reused']++;
            $result['student_number_reconciled'] = ($result['student_number_reconciled'] ?? 0) + 1;
            return $candidateId;
        }

        if ($studentNumber !== '') {
            $stmt = $pdo->prepare("\n                SELECT student_id, status\n                FROM students\n                WHERE student_number = ?\n                FOR UPDATE\n            ");
            $stmt->execute([$studentNumber]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($student !== null) {
                if ((string) $student['status'] !== 'Active') {
                    throw new RuntimeException('A matched Student is no longer Active. Refresh Review & Resolve before confirming.');
                }

                $result['student_reused']++;
                return (int) $student['student_id'];
            }
        } else {
            $candidateStudentId = (int) ($row['candidate_student_id'] ?? 0);

            if ($candidateStudentId > 0) {
                $candidate = $this->lockUnresolvedStudentById(
                    $pdo,
                    $candidateStudentId,
                    $operationalClassId
                );

                if ($candidate !== null) {
                    if (($row['student_action'] ?? '') !== 'Reuse existing Student') {
                        throw new RuntimeException('The unresolved Student match changed after the import plan was prepared. Refresh Review & Resolve.');
                    }

                    $result['student_reused']++;
                    return (int) $candidate['student_id'];
                }
            }

            $identity = $row['proposed_identity'] ?? [];
            $sameClassMatches = $this->lockUnresolvedStudentsByIdentity(
                $pdo,
                $operationalClassId,
                $identity
            );

            if (count($sameClassMatches) > 1) {
                throw new RuntimeException('More than one unresolved class participant matches this identity. Refresh Review & Resolve and choose explicitly.');
            }

            if (count($sameClassMatches) === 1) {
                if (($row['student_action'] ?? '') !== 'Reuse existing Student') {
                    throw new RuntimeException('An unresolved class participant now matches this identity. Refresh Review & Resolve.');
                }

                $result['student_reused']++;
                return (int) $sameClassMatches[0]['student_id'];
            }
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
                $studentNumber === '' ? null : $studentNumber,
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

        if ($created) {
            $studentId = (int) $pdo->lastInsertId();
            $stmt = $pdo->prepare("\n                SELECT student_id, status\n                FROM students\n                WHERE student_id = ?\n                FOR UPDATE\n            ");
            $stmt->execute([$studentId]);
        } else {
            $stmt = $pdo->prepare("\n                SELECT student_id, status\n                FROM students\n                WHERE student_number = ?\n                FOR UPDATE\n            ");
            $stmt->execute([$studentNumber]);
        }
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

    /** @return array<string, mixed>|null */
    private function lockUnresolvedStudentById(
        PDO $pdo,
        int $studentId,
        int $operationalClassId
    ): ?array {
        $stmt = $pdo->prepare("\n            SELECT s.student_id, s.student_number, s.status\n            FROM students AS s\n            INNER JOIN student_class_enrollments AS sce\n                ON sce.student_id = s.student_id\n            WHERE s.student_id = ?\n              AND s.student_number IS NULL\n              AND s.status = 'Active'\n              AND sce.operational_class_id = ?\n              AND sce.status = 'Active'\n            FOR UPDATE\n        ");
        $stmt->execute([$studentId, $operationalClassId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @param array<string, mixed> $identity @return array<int, array<string, mixed>> */
    private function lockUnresolvedStudentsByIdentity(
        PDO $pdo,
        int $operationalClassId,
        array $identity
    ): array {
        $firstName = trim((string) ($identity['first_name'] ?? ''));
        $lastName = trim((string) ($identity['last_name'] ?? ''));

        if ($firstName === '' || $lastName === '') {
            return [];
        }

        $stmt = $pdo->prepare("\n            SELECT s.student_id, s.student_number, s.first_name, s.middle_name, s.last_name, s.suffix, s.status\n            FROM students AS s\n            INNER JOIN student_class_enrollments AS sce\n                ON sce.student_id = s.student_id\n            WHERE s.student_number IS NULL\n              AND s.status = 'Active'\n              AND sce.operational_class_id = ?\n              AND sce.status = 'Active'\n            FOR UPDATE\n        ");
        $stmt->execute([$operationalClassId]);
        $targetKey = $this->normalizeIdentityName($identity);
        $matches = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $student) {
            if ($this->normalizeIdentityName($student) === $targetKey) {
                $matches[] = $student;
            }
        }

        return $matches;
    }

    /** @param array<string, mixed> $identity */
    private function normalizeIdentityName(array $identity): string
    {
        $value = mb_strtolower(trim(implode(' ', array_filter([
            (string) ($identity['first_name'] ?? ''),
            (string) ($identity['middle_name'] ?? ''),
            (string) ($identity['last_name'] ?? ''),
            (string) ($identity['suffix'] ?? ''),
        ], static fn(string $part): bool => trim($part) !== ''))));
        $normalized = preg_replace('/[^\\p{L}\\p{N}]+/u', ' ', $value);

        return trim((string) ($normalized ?? $value));
    }

    /** @param array<string, mixed> $row @param array<string, mixed> $classContext @param array<string, int> $result */
    private function lockOrCreateAcademicEnrollment(PDO $pdo, int $studentId, array $row, array $classContext, array &$result): ?int
    {
        $decision = $row['context_decision'] ?? null;

        if (($row['academic_enrollment_action'] ?? '') === 'No Academic Enrollment action') {
            // The roster proves participation in this Operational Class, but
            // does not independently prove an academic placement. Do not
            // fabricate an SAE solely to satisfy a Class Enrollment link.
            return null;
        }

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

        $conflicts = $this->lockCurrentAcademicEnrollmentContext(
            $pdo,
            $studentId,
            (int) $classContext['school_year_id'],
            $decision['semester']
        );

        if ($conflicts !== []) {
            throw new RuntimeException('The reviewed placement conflicts with an existing current Academic Enrollment for the same Student, School Year, and teaching context. No placement was overwritten.');
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
        $stmt = $pdo->prepare("\n            SELECT student_academic_enrollment_id\n            FROM student_academic_enrollments\n            WHERE student_academic_enrollment_id = ?\n              AND student_id = ?\n              AND status = 'Active'\n              AND effective_end IS NULL\n            FOR UPDATE\n        ");
        $stmt->execute([$enrollmentId, $studentId]);
        return $stmt->fetchColumn() !== false;
    }

    /** @return array<int, array<string, mixed>> */
    private function lockCurrentAcademicEnrollmentMatches(PDO $pdo, int $studentId, int $schoolYearId, array $decision): array
    {
        $stmt = $pdo->prepare("\n            SELECT student_academic_enrollment_id\n            FROM student_academic_enrollments\n            WHERE student_id = ?\n              AND school_year_id = ?\n              AND semester <=> ?\n              AND academic_level = ?\n              AND program_id <=> ?\n              AND section_id <=> ?\n              AND year_level <=> ?\n              AND status = 'Active'\n              AND effective_end IS NULL\n            ORDER BY student_academic_enrollment_id\n            FOR UPDATE\n        ");
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

    /** @return array<int, array<string, mixed>> */
    private function lockCurrentAcademicEnrollmentContext(PDO $pdo, int $studentId, int $schoolYearId, ?string $semester): array
    {
        $stmt = $pdo->prepare("\n            SELECT student_academic_enrollment_id\n            FROM student_academic_enrollments\n            WHERE student_id = ?\n              AND school_year_id = ?\n              AND semester <=> ?\n              AND status = 'Active'\n              AND effective_end IS NULL\n            ORDER BY student_academic_enrollment_id\n            FOR UPDATE\n        ");
        $stmt->execute([$studentId, $schoolYearId, $semester]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function lockExistingClassEnrollment(PDO $pdo, int $studentId, int $operationalClassId): ?int
    {
        $stmt = $pdo->prepare("\n            SELECT student_class_enrollment_id\n            FROM student_class_enrollments\n            WHERE student_id = ?\n              AND operational_class_id = ?\n            FOR UPDATE\n        ");
        $stmt->execute([$studentId, $operationalClassId]);
        $value = $stmt->fetchColumn();

        return $value === false ? null : (int) $value;
    }

    private function attachAcademicEnrollmentToExistingClassEnrollment(
        PDO $pdo,
        int $classEnrollmentId,
        int $studentId,
        int $academicEnrollmentId
    ): void {
        $stmt = $pdo->prepare("\n            SELECT enrollment_id\n            FROM student_class_enrollments\n            WHERE student_class_enrollment_id = ?\n              AND student_id = ?\n            FOR UPDATE\n        ");
        $stmt->execute([$classEnrollmentId, $studentId]);
        $currentEnrollmentId = $stmt->fetchColumn();

        if ($currentEnrollmentId !== false && $currentEnrollmentId !== null && (int) $currentEnrollmentId !== $academicEnrollmentId) {
            throw new RuntimeException('The existing Class Enrollment is linked to a different Academic Enrollment. No historical placement was overwritten.');
        }

        if ($currentEnrollmentId === null) {
            $update = $pdo->prepare("\n                UPDATE student_class_enrollments\n                SET enrollment_id = ?\n                WHERE student_class_enrollment_id = ?\n                  AND student_id = ?\n                  AND enrollment_id IS NULL\n            ");
            $update->execute([$academicEnrollmentId, $classEnrollmentId, $studentId]);
        }
    }
}
