<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/pdo.php';
require_once __DIR__ . '/../auth/role_helper.php';
$allowedRoles = [ROLE_TEACHER];
require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../auth/csrf_helper.php';
require_once __DIR__ . '/../includes/helper/flash_message.php';
require_once __DIR__ . '/../includes/helper/teacher_referral_helper.php';

$teacherId = (int) ($_SESSION['user_id'] ?? 0);
$referralId = filter_input(INPUT_GET, 'referral_id', FILTER_VALIDATE_INT)
    ?: (filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0);
$referral = $referralId > 0 ? aprismTeacherReferralDetail($pdo, $teacherId, $referralId) : null;
if ($referral === null) {
    http_response_code(404);
    exit('Referral not found or outside your permitted scope.');
}

$followups = aprismTeacherReferralFollowupRows($pdo, $teacherId, $referralId);
$evidence = aprismTeacherReferralEvidence($pdo, $teacherId, $referral);
$canManage = (int) $referral['teacher_id'] === $teacherId;
$canClarify = $canManage
    && (string) $referral['status'] === 'Returned'
    && empty($referral['guidance_case_id']);
$backUrl = $canManage ? APP_URL . '/dashboard/teacher_referrals.php' : APP_URL . '/dashboard/teacher_referral_followups.php';
$backLabel = $canManage ? 'Back to Referrals' : 'Back to Referral Follow-ups';
$csrfToken = generateCsrfToken();
$pageTitle = 'Referral Details';
$activePage = 'referrals';
$roleStylesheet = 'assets/css/teacher.css';
$pageStylesheet = 'assets/css/pages/teacher-referrals.css';
?>
<!doctype html>
<html lang="en">
<head><?php require __DIR__ . '/../includes/components/head.php'; ?></head>
<body>
<?php require __DIR__ . '/../includes/components/sidebar.php'; ?>
<main class="main-content <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">
    <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>
    <div class="content-wrapper referral-workspace referral-detail-workspace">
        <header class="referral-page-header">
            <div>
                <p class="teacher-eyebrow">Referral history</p>
                <h1 class="teacher-page-title">Referral Details</h1>
            </div>
            <a class="referral-secondary-action" href="<?= $backUrl ?>"><i data-lucide="arrow-left" aria-hidden="true"></i> <?= htmlspecialchars($backLabel, ENT_QUOTES, 'UTF-8') ?></a>
        </header>

        <section class="teacher-panel referral-detail-panel" aria-labelledby="referral-detail-title">
            <div class="teacher-panel-header">
                <div>
                    <p class="teacher-eyebrow">Student concern</p>
                    <h2 id="referral-detail-title" class="teacher-panel-title"><?= htmlspecialchars((string) $referral['student_name'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <p class="referral-detail-subline"><?= htmlspecialchars((string) $referral['student_number'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <span class="status-pill status-<?= htmlspecialchars(strtolower((string) $referral['status']), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $referral['status'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="referral-detail-meta">
                <div><span>Class</span><strong><?= htmlspecialchars(trim((string) ($referral['subject_name'] ?? 'Class unavailable') . ' · ' . (string) ($referral['section_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?></strong></div>
                <div><span>Submitted by</span><strong><?= htmlspecialchars((string) $referral['referral_teacher_name'], ENT_QUOTES, 'UTF-8') ?></strong></div>
                <div><span>School Year</span><strong><?= htmlspecialchars((string) ($referral['school_year_snapshot'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></strong></div>
                <div><span>Semester</span><strong><?= htmlspecialchars((string) ($referral['semester_snapshot'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></strong></div>
                <div><span>Created</span><strong><?= htmlspecialchars(date('M j, Y g:i A', strtotime((string) $referral['created_at'])), ENT_QUOTES, 'UTF-8') ?></strong></div>
            </div>
            <div class="referral-detail-reason">
                <span>Observed concern</span>
                <p><?= nl2br(htmlspecialchars((string) $referral['reason'], ENT_QUOTES, 'UTF-8')) ?></p>
            </div>
        </section>

        <div class="referral-detail-grid">
            <section class="teacher-panel referral-evidence-panel" aria-labelledby="referral-evidence-title">
                <div class="teacher-panel-header"><div><p class="teacher-eyebrow">Supporting evidence</p><h2 id="referral-evidence-title" class="teacher-panel-title">Available context</h2></div></div>
                <div class="referral-evidence-grid">
                    <div><span>Present</span><strong><?= (int) $evidence['attendance']['present'] ?></strong></div>
                    <div><span>Late</span><strong><?= (int) $evidence['attendance']['late'] ?></strong></div>
                    <div><span>Absent</span><strong><?= (int) $evidence['attendance']['absent'] ?></strong></div>
                    <div><span>Your previous referrals</span><strong><?= (int) $evidence['previous_referrals'] ?></strong></div>
                </div>
                <p class="referral-evidence-note">Counts are limited to the recorded Student/Class context. No new risk score is inferred.</p>
            </section>

            <section class="teacher-panel referral-followup-panel" aria-labelledby="referral-followup-title">
                <div class="teacher-panel-header"><div><p class="teacher-eyebrow">Append-only history</p><h2 id="referral-followup-title" class="teacher-panel-title">Follow-ups</h2></div><span class="referral-history-count"><?= count($followups) ?> recorded</span></div>
                <?php if ($followups === []): ?><div class="referral-inline-empty">No follow-ups have been recorded.</div><?php else: ?>
                    <div class="referral-timeline"><?php foreach ($followups as $followup): ?><article class="referral-timeline-item"><div><strong><?= htmlspecialchars((string) $followup['author_name'], ENT_QUOTES, 'UTF-8') ?></strong><span><?= htmlspecialchars(date('M j, Y g:i A', strtotime((string) $followup['created_at'])), ENT_QUOTES, 'UTF-8') ?></span></div><p><?= nl2br(htmlspecialchars((string) $followup['note'], ENT_QUOTES, 'UTF-8')) ?></p><small>Status after update: <?= htmlspecialchars((string) $followup['status_after'], ENT_QUOTES, 'UTF-8') ?></small></article><?php endforeach; ?></div>
                <?php endif; ?>
            </section>
        </div>

        <?php if ($canClarify): ?>
        <section class="teacher-panel referral-add-followup-panel" aria-labelledby="add-followup-title">
            <div class="teacher-panel-header"><div><p class="teacher-eyebrow">Teacher action</p><h2 id="add-followup-title" class="teacher-panel-title">Respond to clarification</h2></div></div>
            <form method="post" action="<?= APP_URL ?>/actions/teacher/add_referral_followup.php" class="referral-followup-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="teacher_referral_id" value="<?= (int) $referralId ?>">
                <input type="hidden" name="status_after" value="Submitted">
                <label class="referral-field"><span>Follow-up note</span><textarea name="note" rows="4" maxlength="5000" required placeholder="Record the follow-up action or outcome."></textarea></label>
                <div class="referral-form-footer"><span class="referral-form-note">Your clarification is retained as history and resubmits this referral for review.</span><button class="btn btn-primary" type="submit"><i data-lucide="message-square-plus" aria-hidden="true"></i> Submit clarification</button></div>
            </form>
        </section>
        <?php endif; ?>
    </div>
</main>
<div class="toast-container-custom" id="toastContainer" aria-live="polite" aria-atomic="true"></div>
<?php require __DIR__ . '/../includes/components/logout_modal.php'; ?>
<?php require __DIR__ . '/../includes/components/footer.php'; ?>
<script>window.APRISM_REFERRAL_FLASH = <?= json_encode(['success' => $flash['success'], 'error' => $flash['error']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="<?= APP_URL ?>/assets/js/pages/teacher-referrals.js"></script>
</body>
</html>
