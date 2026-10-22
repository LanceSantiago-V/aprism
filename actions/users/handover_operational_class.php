<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../auth/role_helper.php';
$allowedRoles = [ROLE_TECHNICAL_ADMINISTRATOR];
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/pdo.php';
require_once __DIR__ . '/../../auth/session_guard.php';
require_once __DIR__ . '/../../auth/csrf_helper.php';
require_once __DIR__ . '/../../includes/helper/operational_class_handover_helper.php';

$redirect = APP_URL . '/dashboard/technical_admin_class_handover.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect);
    exit;
}

$classId = filter_var($_POST['operational_class_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$expectedTeacherId = filter_var($_POST['expected_teacher_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$replacementTeacherId = filter_var($_POST['replacement_teacher_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$reasonCategory = trim((string) ($_POST['reason_category'] ?? ''));
$reasonCode = trim((string) ($_POST['reason_code'] ?? ''));
$reasonDetails = trim((string) ($_POST['reason_details'] ?? ''));
$formToken = (string) ($_POST['handover_token'] ?? '');
$issuedToken = (string) ($_SESSION['class_handover_token'] ?? '');

$_SESSION['class_handover_form'] = [
    'operational_class_id' => $classId ?: 0,
    'replacement_teacher_id' => $replacementTeacherId ?: 0,
    'reason_category' => $reasonCategory,
    'reason_code' => $reasonCode,
    'reason_details' => $reasonDetails,
    'show_reason_details' => ($_POST['show_reason_details'] ?? '') === '1',
];

$errors = [];
if (!validateCsrfToken($_POST['csrf_token'] ?? null)
    || $formToken === '' || $issuedToken === '' || !hash_equals($issuedToken, $formToken)) {
    $errors['form'] = 'Refresh the page and review this handover again.';
}
if (!$classId || !$expectedTeacherId) {
    $errors['operational_class_id'] = 'Select an active class.';
}
if (!$replacementTeacherId) {
    $errors['replacement_teacher_id'] = 'Select an active replacement Teacher.';
}
$reasonCategoryLabels = [
    'personnel' => 'Personnel change',
    'assignment' => 'Teaching assignment',
    'other' => 'Other',
];
$reasonOptions = [
    'personnel' => [
        'resignation' => 'Resignation',
        'leave_of_absence' => 'Leave of Absence',
    ],
    'assignment' => [
        'change_in_teaching_assignment' => 'Change in Teaching Assignment',
        'schedule_reassignment' => 'Schedule Reassignment',
    ],
];
$reason = '';
if (!isset($reasonCategoryLabels[$reasonCategory])) {
    $errors['reason_category'] = 'Choose a handover reason.';
} elseif ($reasonCategory === 'other') {
    if ($reasonDetails === '') {
        $errors['reason_details'] = 'Explain the operational reason when Other is selected.';
    } elseif (mb_strlen($reasonDetails) > 900) {
        $errors['reason_details'] = 'Keep the explanation to 900 characters or fewer.';
    } else {
        $reason = 'Other: ' . $reasonDetails;
    }
} elseif (!isset($reasonOptions[$reasonCategory][$reasonCode])) {
    $errors['reason_code'] = 'Choose a specific reason for the selected category.';
} elseif (mb_strlen($reasonDetails) > 900) {
    $errors['reason_details'] = 'Keep optional details to 900 characters or fewer.';
} else {
    $reason = $reasonCategoryLabels[$reasonCategory] . ': ' . $reasonOptions[$reasonCategory][$reasonCode];
    if ($reasonDetails !== '') {
        $reason .= ' — ' . $reasonDetails;
    }
}
if ($reason !== '' && mb_strlen($reason) > 1000) {
    $errors['reason_details'] = 'The complete handover reason must be 1,000 characters or fewer.';
}
if (($_POST['confirmation_intent'] ?? '') !== 'confirm_handover') {
    $errors['confirmation_intent'] = 'Confirm that you reviewed the class, Teachers, and records before saving.';
}

if ($errors !== []) {
    $_SESSION['class_handover_errors'] = $errors;
    $_SESSION['error_message'] = 'Class handover was not saved. Review the highlighted fields.';
    header('Location: ' . $redirect);
    exit;
}

try {
    $handoverHistoryReady = $pdo->query("SHOW TABLES LIKE 'operational_class_handovers'")->fetchColumn() !== false;
    $attendanceConductorReady = (bool) $pdo->query("SHOW COLUMNS FROM attendance_sessions LIKE 'conducted_by_user_id'")->fetch(PDO::FETCH_ASSOC);
    if (!$handoverHistoryReady || !$attendanceConductorReady) {
        throw new DomainException('Class Handover is unavailable until both required database migrations are applied. No records were changed.');
    }

    aprismHandoverOperationalClass(
        $pdo,
        (int) $classId,
        (int) $expectedTeacherId,
        (int) $replacementTeacherId,
        (int) $_SESSION['user_id'],
        $reason
    );
    unset($_SESSION['class_handover_token'], $_SESSION['class_handover_form'], $_SESSION['class_handover_errors']);
    unset($_SESSION['error_message'], $_SESSION['warning_message']);
    $_SESSION['success_message'] = 'Class responsibility transferred. Existing academic records and original authors were preserved.';
} catch (DomainException $exception) {
    $_SESSION['class_handover_errors'] = ['form' => $exception->getMessage()];
    $_SESSION['error_message'] = 'Class handover was not saved. Review the explanation beside the form.';
} catch (Throwable $exception) {
    error_log('[APRISM Class Handover] ' . $exception->getMessage());
    $_SESSION['class_handover_errors'] = ['form' => 'The handover could not be saved. No class records were changed.'];
    $_SESSION['error_message'] = 'Class handover was not saved.';
}

header('Location: ' . $redirect);
exit;
