<?php

declare(strict_types=1);

/**
 * APRISM Import Resolver
 *
 * Teacher Schedule reference resolver.
 *
 * IMPORTANT:
 * - This resolver resolves existing institutional records and supports
 *   explicit Subject/Section create-or-reuse operations inside the caller's
 *   transaction.
 * - It may create a new Subject without Teacher-facing institutional codes.
 * - It may create a new Section from Section Name alone inside the caller's
 *   transaction.
 * - Teacher-facing database IDs are never required.
 * - Subject Code and Program Code are optional institutional metadata.
 *
 * Teacher Schedule identity:
 *
 *      Subject Name
 *      Section Name
 *
 * are resolved to:
 *
 *      subjects.subject_id
 *      sections.section_id
 *
 * The Section's existing program_id is then used internally.
 *
 * Missing institutional references are returned as "missing".
 * Subject and Section creation are performed only by explicit caller
 * requests and remain inside the caller's transaction.
 */

final class ImportResolver
{
    private PDO $pdo;


    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }


    /**
     * Normalize incoming text.
     */
    private function clean(mixed $value): ?string
    {
        $value =
            trim(
                (string) ($value ?? '')
            );

        return $value === ''
            ? null
            : $value;
    }


    /**
     * Case-insensitive text comparison.
     */
    private function sameText(
        ?string $a,
        ?string $b
    ): bool {

        if (
            $a === null ||
            $b === null
        ) {
            return false;
        }

        return
            mb_strtolower(
                trim($a)
            ) ===
            mb_strtolower(
                trim($b)
            );
    }


    /**
     * Resolve a complete normalized Teacher Schedule row.
     *
     * Only persistent institutional references are resolved here.
     *
     * Expected structure:
     *
     * [
     *     'subject_name' => 'Object-Oriented Programming 2',
     *     'subject_code' => null,
     *     'section_name' => 'BSIT-3A',
     *     'program_code' => null,
     *     'school_year'   => '2026-2027',
     *     'semester'      => '1st Semester',
     *     'day'           => 'Monday',
     *     'start_time'    => '08:00',
     *     'end_time'      => '10:00',
     *     'room'          => 'Room 101',
     * ]
     */
    public function resolve(
        array $row
    ): array {

        $subject =
            $this->resolveSubject(
                $row['subject_code'] ?? null,
                $row['subject_name'] ?? null
            );


        $section =
            $this->resolveSection(
                $row['section_name'] ?? null
            );


        $errors = [];
        $warnings = [];


        /*
         * Subject resolution
         */

        if (
            $subject['status'] ===
            'missing'
        ) {

            $errors[] = [

                'field' =>
                    'Subject',

                'code' =>
                    'SUBJECT_NOT_FOUND',

                'message' =>
                    'The selected Subject is not available in APRISM.',

            ];

        } elseif (
            $subject['status'] ===
            'ambiguous'
        ) {

            $errors[] = [

                'field' =>
                    'Subject',

                'code' =>
                    'SUBJECT_AMBIGUOUS',

                'message' =>
                    'The Subject matches more than one institutional record.',

            ];

        } elseif (
            $subject['status'] ===
            'invalid'
        ) {

            $errors[] = [

                'field' =>
                    'Subject',

                'code' =>
                    'SUBJECT_INVALID',

                'message' =>
                    $subject['message']
                    ??
                    'The Subject information is invalid.',

            ];

        }


        /*
         * Section resolution
         */

        if (
            $section['status'] ===
            'missing'
        ) {

            $errors[] = [

                'field' =>
                    'Section',

                'code' =>
                    'SECTION_NOT_FOUND',

                'message' =>
                    'The selected Section is not available in APRISM.',

            ];

        } elseif (
            $section['status'] ===
            'ambiguous'
        ) {

            $errors[] = [

                'field' =>
                    'Section',

                'code' =>
                    'SECTION_AMBIGUOUS',

                'message' =>
                    'The Section name matches more than one institutional Section record.',

            ];

        } elseif (
            $section['status'] ===
            'invalid'
        ) {

            $errors[] = [

                'field' =>
                    'Section',

                'code' =>
                    'SECTION_INVALID',

                'message' =>
                    $section['message']
                    ??
                    'The Section information is invalid.',

            ];

        }


        return [

            'success' =>
                count($errors) === 0,

            'errors' =>
                $errors,

            'warnings' =>
                $warnings,

            'subject' =>
                $subject,

            'section' =>
                $section,

        ];
    }


    /**
     * Resolve an existing Subject.
     *
     * Teacher Schedule identity is based on Subject Name.
     *
     * Subject Code is OPTIONAL metadata.
     *
     * If an official imported source supplies a code, it may be
     * checked against the resolved record.
     *
     * A missing Subject is NOT created here.
     */
    public function resolveSubject(
        ?string $subjectCode,
        ?string $subjectName
    ): array {

        $subjectCode =
            $this->clean(
                $subjectCode
            );

        $subjectName =
            $this->clean(
                $subjectName
            );


        if (
            $subjectName === null
        ) {

            return [

                'status' =>
                    'invalid',

                'record' =>
                    null,

                'matches' =>
                    [],

                'message' =>
                    'Subject is required.',

            ];

        }


        /*
         * Subject Name is the primary Teacher-facing resolution value.
         *
         * Do NOT require Subject Code.
         */

        $stmt =
            $this->pdo->prepare(
                "
                SELECT
                    subject_id,
                    subject_code,
                    subject_name,
                    units,
                    status
                FROM subjects
                WHERE status = 'Active'
                  AND LOWER(TRIM(subject_name))
                      = LOWER(TRIM(:subject_name))
                LIMIT 2
                FOR UPDATE
                "
            );


        $stmt->execute([

            ':subject_name' =>
                $subjectName,

        ]);


        $matches =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        /*
         * No institutional Subject exists.
         *
         * Teacher Schedule does not create one.
         */

        if (
            count($matches) === 0
        ) {

            return [

                'status' =>
                    'missing',

                'record' =>
                    null,

                'matches' =>
                    [],

                'message' =>
                    'The selected Subject is not available in APRISM.',

            ];

        }


        /*
         * Multiple active records with the same normalized name
         * indicate a master-data integrity problem.
         */

        if (
            count($matches) > 1
        ) {

            return [

                'status' =>
                    'ambiguous',

                'record' =>
                    null,

                'matches' =>
                    $matches,

                'message' =>
                    'The Subject name matches multiple active institutional Subject records.',

            ];

        }


        $record =
            $matches[0];


        /*
         * If an authoritative imported source happens to contain
         * a Subject Code, it may be used as a consistency check.
         *
         * Manual Teacher Schedule does not supply it.
         */

        if (
            $subjectCode !== null &&
            !empty(
            $record['subject_code']
        ) &&
            !$this->sameText(
                (string) 
                $record['subject_code'],
                $subjectCode
            )
        ) {

            return [

                'status' =>
                    'ambiguous',

                'record' =>
                    null,

                'matches' =>
                    [$record],

                'message' =>
                    'The supplied official Subject Code conflicts with the existing Subject record.',

            ];

        }


        return [

            'status' =>
                'resolved',

            'record' =>
                $record,

            'matches' =>
                [$record],

            'message' =>
                'Subject resolved successfully.',

        ];
    }


    /**
     * Create a persistent Subject only when resolution proves that no
     * existing active Subject matches the supplied human-readable name.
     *
     * Teacher Schedule may trigger this creation, but the Teacher never
     * supplies a database ID or an institutional Subject Code.
     *
     * The operation must run inside the caller's transaction. The unique
     * subject_name constraint is the database-level duplicate safety net.
     */
    public function createSubject(
        string $subjectName,
        ?string $subjectCode = null,
        ?string $units = null
    ): array {

        $subjectName = $this->clean($subjectName);
        $subjectCode = $this->clean($subjectCode);
        $units = $this->clean($units);

        if ($subjectName === null) {
            throw new RuntimeException('Subject is required.');
        }

        $existing = $this->resolveSubject(
            $subjectCode,
            $subjectName
        );

        if ($existing['status'] === 'resolved') {
            return $existing['record'];
        }

        if ($existing['status'] === 'ambiguous') {
            throw new RuntimeException(
                $existing['message'] ??
                'The Subject information is ambiguous.'
            );
        }

        if ($existing['status'] !== 'missing') {
            throw new RuntimeException(
                $existing['message'] ??
                'The Subject could not be resolved.'
            );
        }

        try {
            $stmt = $this->pdo->prepare(
                "
                INSERT INTO subjects (
                    subject_code,
                    subject_name,
                    units,
                    status
                )
                VALUES (?, ?, ?, 'Active')
                "
            );

            $stmt->execute([
                $subjectCode,
                $subjectName,
                $units !== null
                ? number_format((float) $units, 1, '.', '')
                : null,
            ]);

        } catch (PDOException $e) {

            /*
             * Another request may have established the same Subject
             * between resolution and INSERT. Re-resolve it instead of
             * creating a duplicate.
             */
            if ($e->getCode() === '23000') {
                $retry = $this->resolveSubject(
                    $subjectCode,
                    $subjectName
                );

                if ($retry['status'] === 'resolved') {
                    return $retry['record'];
                }

                throw new RuntimeException(
                    'The Subject already exists or conflicts with another institutional Subject record.'
                );
            }

            throw $e;
        }

        $subjectId = (int) $this->pdo->lastInsertId();

        if ($subjectId <= 0) {
            throw new RuntimeException(
                'APRISM could not establish the new Subject record.'
            );
        }

        $stmt = $this->pdo->prepare(
            "
            SELECT
                subject_id,
                subject_code,
                subject_name,
                units,
                status
            FROM subjects
            WHERE subject_id = ?
            FOR UPDATE
            "
        );

        $stmt->execute([$subjectId]);

        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            throw new RuntimeException(
                'APRISM created the Subject but could not reload the record.'
            );
        }

        return $record;
    }


    /**
     * Resolve an existing institutional Section by human-readable name.
     *
     * Teacher Schedule does not require Program Code, Program Name,
     * Academic Level, Year Level, or any database identifier.
     *
     * A Section may temporarily have NULL program_id/year_level when it is
     * created from the Teacher Schedule workflow. Existing authoritative
     * metadata may be enriched later without changing section_id.
     */
    public function resolveSection(
        ?string $sectionName
    ): array {

        $sectionName = $this->clean($sectionName);

        if ($sectionName === null) {
            return [
                'status' => 'invalid',
                'record' => null,
                'matches' => [],
                'message' => 'Section is required.',
            ];
        }

        $stmt = $this->pdo->prepare(
            "
            SELECT
                s.section_id,
                s.program_id,
                s.section_name,
                s.year_level,
                s.status,
                p.program_code,
                p.program_name,
                p.academic_level
            FROM sections AS s
            LEFT JOIN programs AS p
                ON p.program_id = s.program_id
            WHERE s.status = 'Active'
              AND LOWER(TRIM(s.section_name))
                  = LOWER(TRIM(:section_name))
            LIMIT 3
            FOR UPDATE
            "
        );

        $stmt->execute([
            ':section_name' => $sectionName,
        ]);

        $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($matches) === 0) {
            return [
                'status' => 'missing',
                'record' => null,
                'matches' => [],
                'message' => 'The Section is new to APRISM.',
            ];
        }

        if (count($matches) > 1) {
            return [
                'status' => 'ambiguous',
                'record' => null,
                'matches' => $matches,
                'message' => 'The Section name matches multiple institutional Sections. APRISM cannot safely determine which Section was intended.',
            ];
        }

        return [
            'status' => 'resolved',
            'record' => $matches[0],
            'matches' => $matches,
            'message' => 'Section resolved successfully.',
        ];
    }


    /**
     * Create a persistent Section using only its human-readable name.
     *
     * Program and Year Level are intentionally optional. They remain NULL
     * until authoritative institutional data is available for enrichment.
     * The generated section_id is the sole identity used by operational
     * classes and schedules.
     */
    public function createSection(
        string $sectionName,
        ?int $programId = null,
        ?string $yearLevel = null
    ): array {

        $sectionName = $this->clean($sectionName);
        $yearLevel = $this->clean($yearLevel);

        if ($sectionName === null) {
            throw new RuntimeException('Section is required.');
        }

        if ($programId !== null && $programId <= 0) {
            $programId = null;
        }

        if ($yearLevel !== null && !in_array($yearLevel, ['1', '2', '3', '4'], true)) {
            throw new RuntimeException('The Section Year Level is invalid.');
        }

        /*
         * Section creation must be deterministic even when two Teacher
         * requests arrive at the same time.
         *
         * We intentionally do NOT add a global UNIQUE(section_name)
         * constraint because legitimate institutional Sections may share
         * the same human-readable name.
         *
         * Instead, serialize creation attempts for the same normalized
         * Section Name with a short MySQL advisory lock. The lock is
         * connection-scoped, released in finally, and does not alter the
         * database schema or Section identity.
         */
        $lockKey =
            'aprism_section_' .
            substr(
                hash(
                    'sha256',
                    mb_strtolower(
                        trim($sectionName)
                    )
                ),
                0,
                48
            );

        $lockStmt = $this->pdo->prepare(
            'SELECT GET_LOCK(?, 5)'
        );

        $lockStmt->execute([
            $lockKey,
        ]);

        $lockAcquired =
            (int) $lockStmt->fetchColumn() === 1;

        if (!$lockAcquired) {
            throw new RuntimeException(
                'APRISM could not safely establish the Section because another Section operation is currently in progress. Please try again.'
            );
        }

        try {

            /*
             * Re-check AFTER acquiring the name-specific lock.
             *
             * This is important: a concurrent request may have created
             * the Section while this request was waiting for the lock.
             */
            $existing = $this->resolveSection($sectionName);

            if ($existing['status'] === 'resolved') {
                return $existing['record'];
            }

            if ($existing['status'] === 'ambiguous') {
                throw new RuntimeException(
                    $existing['message']
                    ?? 'The Section information is ambiguous.'
                );
            }

            try {

                $stmt = $this->pdo->prepare(
                    "
                    INSERT INTO sections (
                        program_id,
                        section_name,
                        year_level,
                        status
                    )
                    VALUES (?, ?, ?, 'Active')
                    "
                );

                $stmt->execute([
                    $programId,
                    $sectionName,
                    $yearLevel,
                ]);

            } catch (PDOException $e) {

                /*
                 * Keep the existing integrity-error handling. If another
                 * database constraint rejects the insert, re-resolve once
                 * before reporting failure.
                 */
                if ($e->getCode() === '23000') {

                    $retry =
                        $this->resolveSection(
                            $sectionName
                        );

                    if ($retry['status'] === 'resolved') {
                        return $retry['record'];
                    }

                    if ($retry['status'] === 'ambiguous') {
                        throw new RuntimeException(
                            $retry['message']
                            ?? 'The Section information is ambiguous.'
                        );
                    }

                    throw new RuntimeException(
                        'The Section already exists or conflicts with another institutional Section. No duplicate was created.'
                    );
                }

                throw $e;
            }

            $sectionId =
                (int) $this->pdo->lastInsertId();

            if ($sectionId <= 0) {
                throw new RuntimeException(
                    'APRISM could not establish the new Section record.'
                );
            }

            $stmt = $this->pdo->prepare(
                "
                SELECT
                    s.section_id,
                    s.program_id,
                    s.section_name,
                    s.year_level,
                    s.status,
                    p.program_code,
                    p.program_name,
                    p.academic_level
                FROM sections AS s
                LEFT JOIN programs AS p
                    ON p.program_id = s.program_id
                WHERE s.section_id = ?
                FOR UPDATE
                "
            );

            $stmt->execute([
                $sectionId,
            ]);

            $record =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                );

            if (!$record) {
                throw new RuntimeException(
                    'APRISM created the Section but could not reload the record.'
                );
            }

            return $record;

        } finally {

            /*
             * GET_LOCK is connection-scoped, so always release it even
             * when resolution, insertion, or reload throws.
             */
            try {

                $releaseStmt =
                    $this->pdo->prepare(
                        'SELECT RELEASE_LOCK(?)'
                    );

                $releaseStmt->execute([
                    $lockKey,
                ]);

            } catch (Throwable $releaseError) {

                /*
                 * Do not replace the original application exception with
                 * a lock-release exception.
                 */
            }
        }
    }


    /**
     * Resolve an existing Program.
     *
     * This method remains available for authoritative/imported
     * workflows and consistency checks.
     *
     * Teacher Schedule does NOT call this using Teacher-entered
     * Program Code.
     */
    public function resolveProgram(
        ?string $programCode,
        ?string $programName
    ): array {

        $programCode =
            $this->clean(
                $programCode
            );

        $programName =
            $this->clean(
                $programName
            );


        if (
            $programCode === null &&
            $programName === null
        ) {

            return [

                'status' =>
                    'invalid',

                'record' =>
                    null,

                'matches' =>
                    [],

                'message' =>
                    'Program information was not supplied.',

            ];

        }


        /*
         * Prefer exact Program Code when an official source provides it.
         */

        if (
            $programCode !== null
        ) {

            $stmt =
                $this->pdo->prepare(
                    "
                    SELECT
                        program_id,
                        program_code,
                        program_name,
                        academic_level,
                        status
                    FROM programs
                    WHERE status = 'Active'
                      AND program_code = :program_code
                    LIMIT 2
                    FOR UPDATE
                    "
                );


            $stmt->execute([

                ':program_code' =>
                    $programCode,

            ]);


            $matches =
                $stmt->fetchAll(
                    PDO::FETCH_ASSOC
                );


            if (
                count($matches) === 1
            ) {

                /*
                 * If a name was also supplied by an authoritative
                 * source, make sure it does not contradict the code.
                 */

                if (
                    $programName !== null &&
                    !$this->sameText(
                        (string) 
                        $matches[0]['program_name'],
                        $programName
                    )
                ) {

                    return [

                        'status' =>
                            'ambiguous',

                        'record' =>
                            null,

                        'matches' =>
                            $matches,

                        'message' =>
                            'The supplied Program Code and Program Name do not refer to the same institutional Program.',

                    ];

                }


                return [

                    'status' =>
                        'resolved',

                    'record' =>
                        $matches[0],

                    'matches' =>
                        $matches,

                    'message' =>
                        'Program resolved successfully.',

                ];

            }


            if (
                count($matches) > 1
            ) {

                return [

                    'status' =>
                        'ambiguous',

                    'record' =>
                        null,

                    'matches' =>
                        $matches,

                    'message' =>
                        'Multiple active Programs use the supplied Program Code.',

                ];

            }

        }


        /*
         * Fall back to exact Program Name when available.
         */

        if (
            $programName !== null
        ) {

            $stmt =
                $this->pdo->prepare(
                    "
                    SELECT
                        program_id,
                        program_code,
                        program_name,
                        academic_level,
                        status
                    FROM programs
                    WHERE status = 'Active'
                      AND LOWER(TRIM(program_name))
                          = LOWER(TRIM(:program_name))
                    LIMIT 2
                    FOR UPDATE
                    "
                );


            $stmt->execute([

                ':program_name' =>
                    $programName,

            ]);


            $matches =
                $stmt->fetchAll(
                    PDO::FETCH_ASSOC
                );


            if (
                count($matches) === 1
            ) {

                return [

                    'status' =>
                        'resolved',

                    'record' =>
                        $matches[0],

                    'matches' =>
                        $matches,

                    'message' =>
                        'Program resolved successfully by exact name.',

                ];

            }


            if (
                count($matches) > 1
            ) {

                return [

                    'status' =>
                        'ambiguous',

                    'record' =>
                        null,

                    'matches' =>
                        $matches,

                    'message' =>
                        'Multiple active Programs use the supplied Program Name.',

                ];

            }

        }


        return [

            'status' =>
                'missing',

            'record' =>
                null,

            'matches' =>
                [],

            'message' =>
                'No active institutional Program matched the supplied information.',

        ];
    }
}