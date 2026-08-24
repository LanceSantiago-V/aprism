<?php

declare(strict_types=1);

require_once __DIR__ . '/class_list_resolution_engine.php';

/**
 * Builds the one server-side Class List import plan used for review now and
 * for the later confirmation boundary. This class intentionally has no write
 * path: browser rows are never accepted as authoritative import data.
 */
final class ClassListImportPlanEngine
{
    /**
     * @param array<string, mixed> $source
     * @param array<string, mixed> $classContext
     * @param array<string, int|null> $mapping
     * @param array<int, array<string, string>> $identityOverrides
     * @return array<string, mixed>
     */
    public function build(
        PDO $pdo,
        array $source,
        array $classContext,
        string $worksheetName,
        int $headerRow,
        int $firstDataRow,
        array $mapping,
        array $identityOverrides = [],
        array $contextDecisions = []
    ): array {
        $resolution = (new ClassListResolutionEngine())->preview(
            $pdo,
            $source,
            $classContext,
            $worksheetName,
            $headerRow,
            $firstDataRow,
            $mapping,
            $identityOverrides
        );

        $summary = [
            'roster_rows' => 0,
            'ready_rows' => 0,
            'already_enrolled_rows' => 0,
            'blocked_rows' => 0,
            'student_reuse' => 0,
            'student_create_proposed' => 0,
            'academic_reuse' => 0,
            'class_enrollment_create_proposed' => 0,
        ];
        $plannedRows = [];

        foreach ($resolution['rows'] as $row) {
            $plan = $this->planRow(
                $pdo,
                $row,
                $classContext,
                $contextDecisions[(int) $row['source_row_number']] ?? null
            );
            $plannedRows[] = array_merge($row, $plan);
            $summary['roster_rows']++;

            if ($plan['plan_status'] === 'Ready for confirmation') {
                $summary['ready_rows']++;
            }

            if ($plan['plan_status'] === 'Already enrolled') {
                $summary['already_enrolled_rows']++;
            }

            if ($plan['plan_status'] === 'Blocked') {
                $summary['blocked_rows']++;
            }

            if ($plan['student_action'] === 'Reuse existing Student') {
                $summary['student_reuse']++;
            }

            if ($plan['student_action'] === 'Proposed new Student') {
                $summary['student_create_proposed']++;
            }

            if ($plan['academic_enrollment_action'] === 'Reuse Academic Enrollment') {
                $summary['academic_reuse']++;
            }

            if ($plan['class_enrollment_action'] === 'Proposed Class Enrollment') {
                $summary['class_enrollment_create_proposed']++;
            }
        }

        $sourceWasTruncated = (bool) ($resolution['summary']['is_truncated'] ?? false);

        return [
            'source' => $resolution['source'],
            'class_context' => $resolution['class_context'],
            'resolution_summary' => $resolution['summary'],
            'summary' => $summary,
            'rows' => $plannedRows,
            'context_catalog' => $this->contextCatalog($pdo),
            'confirmation_enabled' => !$sourceWasTruncated && $summary['blocked_rows'] === 0,
            'source_was_truncated' => $sourceWasTruncated,
            'message' => 'This is a server-validated import plan only. No Student or enrollment record has been created or changed.',
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function planRow(
        PDO $pdo,
        array $row,
        array $classContext,
        ?array $contextDecision
    ): array {
        $blockingReasons = [];
        $studentAction = 'Blocked';
        $academicAction = 'Blocked';
        $classAction = 'Blocked';
        $academicEnrollmentId = null;
        $normalizedDecision = null;

        if (($row['structural_status'] ?? '') !== 'Structurally Valid') {
            $blockingReasons[] = 'Required mapped source values are missing.';
        }

        if (($row['source_duplicate'] ?? false) === true) {
            $blockingReasons[] = 'The uploaded source contains this Student Number more than once.';
        }

        $identityStatus = (string) ($row['identity_status'] ?? '');

        if ($blockingReasons === []) {
            if ($identityStatus === 'Existing Student matched') {
                $studentAction = 'Reuse existing Student';
            } elseif ($identityStatus === 'New Student candidate') {
                $studentAction = 'Proposed new Student';
            } elseif ($identityStatus === 'Identity conflict') {
                $blockingReasons[] = 'The source identity conflicts with an existing Student Number.';
            } else {
                $blockingReasons[] = 'New Student identity details are incomplete.';
            }
        }

        if ($blockingReasons === [] && $identityStatus === 'Existing Student matched') {
            if ((string) ($row['candidate_student_status'] ?? 'Active') !== 'Active') {
                $blockingReasons[] = 'The matched Student is not Active and cannot be changed by a Class List import.';
            }
        }

        if ($blockingReasons === [] && ($row['class_status'] ?? '') === 'Already enrolled in this class') {
            return $this->plannedRow(
                $studentAction,
                'No Academic Enrollment action',
                'Reuse existing Class Enrollment',
                'Already enrolled',
                [],
                null,
                null
            );
        }

        if ($blockingReasons === [] && $identityStatus === 'Existing Student matched') {
            $academicStatus = (string) ($row['academic_status'] ?? '');

            if ($academicStatus === 'Academic context candidate') {
                $academicEnrollmentId = (int) ($row['candidate_academic_enrollment_id'] ?? 0) ?: null;

                if ($academicEnrollmentId === null) {
                    $blockingReasons[] = 'The Academic Enrollment candidate is unavailable. Refresh Review & Resolve.';
                } else {
                    $academicAction = 'Reuse Academic Enrollment';
                }
            }
        }

        if ($blockingReasons === [] && $academicAction !== 'Reuse Academic Enrollment') {
            if ($contextDecision === null) {
                $blockingReasons[] = 'Academic context needs an explicit Teacher review decision.';
            } else {
                try {
                    $normalizedDecision = $this->normalizeContextDecision(
                        $pdo,
                        $contextDecision,
                        $classContext
                    );
                } catch (RuntimeException $exception) {
                    $blockingReasons[] = $exception->getMessage();
                }
            }
        }

        if ($blockingReasons === [] && $normalizedDecision !== null && $identityStatus === 'Existing Student matched') {
            $matches = $this->findCurrentEnrollmentMatches(
                $pdo,
                (int) $row['candidate_student_id'],
                (int) $classContext['school_year_id'],
                $normalizedDecision
            );

            if (count($matches) === 1) {
                $academicAction = 'Reuse Academic Enrollment';
                $academicEnrollmentId = (int) $matches[0]['student_academic_enrollment_id'];
            } elseif (count($matches) > 1) {
                $blockingReasons[] = 'More than one current Academic Enrollment has the selected context.';
            } else {
                $academicAction = 'Proposed Academic Enrollment';
            }
        }

        if ($blockingReasons === [] && $normalizedDecision !== null && $identityStatus === 'New Student candidate') {
            $academicAction = 'Proposed Academic Enrollment';
        }

        if ($blockingReasons === []) {
            $classAction = 'Proposed Class Enrollment';

            return $this->plannedRow($studentAction, $academicAction, $classAction, 'Ready for confirmation', [], $academicEnrollmentId, $normalizedDecision);
        }

        return $this->plannedRow($studentAction, $academicAction, $classAction, 'Blocked', $blockingReasons, $academicEnrollmentId, $normalizedDecision);
    }

    /** @return array<string, mixed> */
    private function plannedRow(string $studentAction, string $academicAction, string $classAction, string $status, array $reasons, ?int $academicEnrollmentId, ?array $contextDecision): array
    {
        return [
            'student_action' => $studentAction,
            'academic_enrollment_action' => $academicAction,
            'class_enrollment_action' => $classAction,
            'plan_status' => $status,
            'blocking_reasons' => $reasons,
            'planned_academic_enrollment_id' => $academicEnrollmentId,
            'context_decision' => $contextDecision,
            'context_decision_required' => $studentAction !== 'Blocked'
                && $classAction !== 'Reuse existing Class Enrollment'
                && $academicAction !== 'Reuse Academic Enrollment',
        ];
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function contextCatalog(PDO $pdo): array
    {
        $programs = $pdo->query("\n            SELECT program_id, program_code, program_name, academic_level\n            FROM programs\n            WHERE status = 'Active'\n            ORDER BY program_name, program_id\n        ")->fetchAll(PDO::FETCH_ASSOC);

        $sections = $pdo->query("\n            SELECT section_id, program_id, section_name, year_level\n            FROM sections\n            WHERE status = 'Active'\n            ORDER BY section_name, section_id\n        ")->fetchAll(PDO::FETCH_ASSOC);

        return [
            'programs' => $programs,
            'sections' => $sections,
        ];
    }

    /**
     * A selected context is authoritative only because the Teacher explicitly
     * confirms it in Review & Resolve. Source and class values remain evidence.
     *
     * @param array<string, mixed> $decision
     * @param array<string, mixed> $classContext
     * @return array<string, mixed>
     */
    private function normalizeContextDecision(PDO $pdo, array $decision, array $classContext): array
    {
        $semester = trim((string) ($decision['semester'] ?? ''));
        $academicLevel = trim((string) ($decision['academic_level'] ?? ''));
        $effectiveStart = trim((string) ($decision['effective_start'] ?? ''));
        $programId = $this->nullablePositiveInt($decision['program_id'] ?? null);
        $sectionId = $this->nullablePositiveInt($decision['section_id'] ?? null);
        $yearLevel = trim((string) ($decision['year_level'] ?? ''));

        if ($semester === '' || strlen($semester) > 30) {
            throw new RuntimeException('Choose an Academic Enrollment semester for this row.');
        }

        if (!in_array($academicLevel, ['College', 'Senior High School'], true)) {
            throw new RuntimeException('Choose a valid Academic Level for this row.');
        }

        $startDate = DateTimeImmutable::createFromFormat('!Y-m-d', $effectiveStart);

        if ($startDate === false || $startDate->format('Y-m-d') !== $effectiveStart) {
            throw new RuntimeException('Choose a valid Academic Enrollment effective start date.');
        }

        $schoolYearStart = (string) ($classContext['school_year_start_date'] ?? '');
        $schoolYearEnd = (string) ($classContext['school_year_end_date'] ?? '');

        if (
            ($schoolYearStart !== '' && $effectiveStart < $schoolYearStart)
            || ($schoolYearEnd !== '' && $effectiveStart > $schoolYearEnd)
        ) {
            throw new RuntimeException('The Academic Enrollment effective start date must fall within the selected School Year.');
        }

        if (!in_array($yearLevel, ['1', '2', '3', '4'], true)) {
            throw new RuntimeException('Choose a valid Year Level for this row.');
        }

        if ($programId === null || $sectionId === null) {
            throw new RuntimeException('Choose an active Program and Section for this row.');
        }

        $stmt = $pdo->prepare("\n            SELECT program_id, academic_level\n            FROM programs\n            WHERE program_id = ?\n              AND status = 'Active'\n            LIMIT 1\n        ");
        $stmt->execute([$programId]);
        $program = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($program === null || (string) $program['academic_level'] !== $academicLevel) {
            throw new RuntimeException('The selected Program is unavailable or conflicts with the Academic Level.');
        }

        $stmt = $pdo->prepare("\n            SELECT section_id, program_id, year_level\n            FROM sections\n            WHERE section_id = ?\n              AND status = 'Active'\n            LIMIT 1\n        ");
        $stmt->execute([$sectionId]);
        $section = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($section === null) {
            throw new RuntimeException('The selected Section is unavailable.');
        }

        if ((int) ($section['program_id'] ?? 0) !== $programId) {
            throw new RuntimeException('The selected Program and Section do not belong together.');
        }

        if ((string) ($section['year_level'] ?? '') !== $yearLevel) {
            throw new RuntimeException('The selected Section and Year Level conflict.');
        }

        return [
            'semester' => $semester,
            'academic_level' => $academicLevel,
            'program_id' => $programId,
            'section_id' => $sectionId,
            'year_level' => $yearLevel,
            'effective_start' => $effectiveStart,
            'status' => 'Active',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function findCurrentEnrollmentMatches(PDO $pdo, int $studentId, int $schoolYearId, array $decision): array
    {
        $stmt = $pdo->prepare("\n            SELECT student_academic_enrollment_id\n            FROM student_academic_enrollments\n            WHERE student_id = ?\n              AND school_year_id = ?\n              AND semester <=> ?\n              AND academic_level = ?\n              AND program_id <=> ?\n              AND section_id <=> ?\n              AND year_level <=> ?\n              AND status = 'Active'\n              AND effective_end IS NULL\n            ORDER BY student_academic_enrollment_id\n        ");
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

    private function nullablePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($integer === false) {
            throw new RuntimeException('An Academic Enrollment reference is invalid.');
        }

        return (int) $integer;
    }
}