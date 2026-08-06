<?php

/* Programs */

function getPrograms(PDO $pdo, ?string $status = null): array
{
    $sql = "
       SELECT
           program_id,
           program_code,
           program_name,
           academic_level,
           status,
           created_at,
           updated_at
       FROM programs
    ";

    if ($status !== null) {
        $sql .= " WHERE status = :status";
    }

    $sql .= "
        ORDER BY
            program_name ASC
    ";

    $stmt = $pdo->prepare($sql);

    if ($status !== null) {
        $stmt->bindValue(':status', $status);
    }

    $stmt->execute();

    return $stmt->fetchAll();
}

function getProgramById(PDO $pdo, int $programId): ?array
{
    $stmt = $pdo->prepare("
        SELECT *
        FROM programs
        WHERE program_id = :program_id
        LIMIT 1
    ");

    $stmt->execute([
        'program_id' => $programId
    ]);

    $program = $stmt->fetch();

    return $program ?: null;
}

function programCodeExists(
    PDO $pdo,
    string $programCode,
    ?int $excludeId = null
): bool {

    $sql = "
        SELECT COUNT(*)
        FROM programs
        WHERE program_code = :program_code
    ";

    if ($excludeId !== null) {
        $sql .= "
            AND program_id <> :program_id
        ";
    }

    $stmt = $pdo->prepare($sql);

    $stmt->bindValue(':program_code', $programCode);

    if ($excludeId !== null) {
        $stmt->bindValue(':program_id', $excludeId, PDO::PARAM_INT);
    }

    $stmt->execute();

    return (bool) $stmt->fetchColumn();
}

function programNameExists(
    PDO $pdo,
    string $programName,
    ?int $excludeId = null
): bool {

    $sql = "
        SELECT COUNT(*)
        FROM programs
        WHERE program_name = :program_name
    ";

    if ($excludeId !== null) {
        $sql .= "
            AND program_id <> :program_id
        ";
    }

    $stmt = $pdo->prepare($sql);

    $stmt->bindValue(':program_name', $programName);

    if ($excludeId !== null) {
        $stmt->bindValue(':program_id', $excludeId, PDO::PARAM_INT);
    }

    $stmt->execute();

    return (bool) $stmt->fetchColumn();
}

function createProgram(
    PDO $pdo,
    string $programCode,
    string $programName,
    string $academicLevel
): int {
    $stmt = $pdo->prepare("
        INSERT INTO programs (
            program_code,
            program_name,
            academic_level
        )
        VALUES (
            :program_code,
            :program_name,
            :academic_level
        )
    ");

    $stmt->execute([
        'program_code' => trim($programCode),
        'program_name' => trim($programName),
        'academic_level' => trim($academicLevel)
    ]);

    return (int) $pdo->lastInsertId();
}

function updateProgram(
    PDO $pdo,
    int $programId,
    string $programCode,
    string $programName,
    string $academicLevel
): bool {

    $stmt = $pdo->prepare("
        UPDATE programs
        SET
            program_code = :program_code,
            program_name = :program_name,
            academic_level = :academic_level
        WHERE program_id = :program_id
    ");

    return $stmt->execute([
        'program_code' => trim($programCode),
        'program_name' => trim($programName),
        'academic_level' => trim($academicLevel),
        'program_id' => $programId
    ]);
}

function updateProgramStatus(
    PDO $pdo,
    int $programId,
    string $status
): bool {

    $stmt = $pdo->prepare("
        UPDATE programs
        SET status = :status
        WHERE program_id = :program_id
    ");

    return $stmt->execute([
        'status' => $status,
        'program_id' => $programId
    ]);
}