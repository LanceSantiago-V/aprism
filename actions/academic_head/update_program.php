<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../includes/helper/program_helper.php';
require_once __DIR__ . '/../../includes/helper/audit_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: ' . APP_URL . '/dashboard/academic_head_programs.php');
    exit;

}

$programId = (int) ($_POST['program_id'] ?? 0);

$programCode = trim($_POST['program_code'] ?? '');

$programName = trim($_POST['program_name'] ?? '');

$academicLevel = trim($_POST['academic_level'] ?? '');


if (
    $programId <= 0 ||
    $programCode === '' ||
    $programName === '' ||
    $academicLevel === ''
) {

    $_SESSION['error_message'] =
        'Program information is incomplete.';

    header('Location: ' . APP_URL . '/dashboard/academic_head_programs.php');
    exit;

}

$program = getProgramById(
    $pdo,
    $programId
);

if ($program === null) {

    $_SESSION['error_message'] =
        'Program not found.';

    header('Location: ' . APP_URL . '/dashboard/academic_head_programs.php');
    exit;

}

if (
    programCodeExists(
        $pdo,
        $programCode,
        $programId
    )
) {

    $_SESSION['error_message'] =
        'The program code already exists.';

    header('Location: ' . APP_URL . '/dashboard/academic_head_programs.php');
    exit;

}

if (
    programNameExists(
        $pdo,
        $programName,
        $programId
    )
) {

    $_SESSION['error_message'] =
        'The program name already exists.';

    header('Location: ' . APP_URL . '/dashboard/academic_head_programs.php');
    exit;

}

try {

    updateProgram(
        $pdo,
        $programId,
        $programCode,
        $programName,
        $academicLevel
    );

    logAudit(
        $pdo,
        'Update Program',
        'Updated program "' . $programCode . '".'
    );

    $_SESSION['success_message'] =
        'Program updated successfully.';

} catch (PDOException $e) {

    $_SESSION['error_message'] =
        'Unable to update the program at this time.';

}

header('Location: ' . APP_URL . '/dashboard/academic_head_programs.php');
exit;