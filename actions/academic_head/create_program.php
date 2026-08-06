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

$programCode = trim($_POST['program_code'] ?? '');
$programName = trim($_POST['program_name'] ?? '');
$academicLevel = trim($_POST['academic_level'] ?? '');

if (
    $programCode === '' ||
    $programName === '' ||
    $academicLevel === ''
) {

    $_SESSION['error_message'] =
        'Program code, program name, and academic level are required.';

    header('Location: ' . APP_URL . '/dashboard/academic_head_programs.php');
    exit;

}

if (programCodeExists($pdo, $programCode)) {

    $_SESSION['error_message'] =
        'The program code already exists.';

    header('Location: ' . APP_URL . '/dashboard/academic_head_programs.php');
    exit;

}

if (programNameExists($pdo, $programName)) {

    $_SESSION['error_message'] =
        'The program name already exists.';

    header('Location: ' . APP_URL . '/dashboard/academic_head_programs.php');
    exit;

}

try {

    $programId = createProgram(
        $pdo,
        $programCode,
        $programName,
        $academicLevel
    );

    logAudit(
        $pdo,
        'Create Program',
        'Created program "' . $programCode . '".'
    );

    $_SESSION['success_message'] =
        'Program created successfully.';

} catch (PDOException $e) {

    $_SESSION['error_message'] =
        'Unable to create the program at this time.';

}

header('Location: ' . APP_URL . '/dashboard/academic_head_programs.php');
exit;