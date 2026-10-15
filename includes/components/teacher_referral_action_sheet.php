<?php

declare(strict_types=1);

$referralSheetId = $referralSheetId ?? 'teacher-referral-sheet';
$referralSheetTitleId = $referralSheetId . '-title';
$referralSheetInitiallyOpen = !empty($referralSheetInitiallyOpen);
$referralSheetData = is_array($referralSheetData ?? null) ? $referralSheetData : [];
$referralClasses = is_array($referralClasses ?? null) ? $referralClasses : [];
$formErrors = is_array($formErrors ?? null) ? $formErrors : [];
$selectedClassId = (int) ($selectedClassId ?? 0);
$selectedSceId = (int) ($selectedSceId ?? 0);
$selectedReasonCode = (string) ($selectedReasonCode ?? '');
$supportingNote = (string) ($supportingNote ?? '');
$referralSourceContext = in_array(($referralSourceContext ?? 'referrals'), ['risk_alerts', 'referrals'], true)
    ? (string) $referralSourceContext
    : 'referrals';
$referralSourcePage = max(1, (int) ($referralSourcePage ?? 1));
$reasonOptions = is_array($reasonOptions ?? null) ? $reasonOptions : aprismReferralReasonOptions();
$csrfToken = (string) ($csrfToken ?? generateCsrfToken());
$selectedEvidenceToken = (string) ($selectedEvidenceToken ?? '');

$selectedClassData = $selectedClassId > 0 ? ($referralSheetData[(string) $selectedClassId] ?? null) : null;
$selectedStudents = is_array($selectedClassData['students'] ?? null) ? $selectedClassData['students'] : [];
$selectedStudentData = null;
foreach ($selectedStudents as $student) {
    if ((int) ($student['student_class_enrollment_id'] ?? 0) === $selectedSceId) {
        $selectedStudentData = $student;
        break;
    }
}
$selectedEvidence = is_array($selectedStudentData['evidence'] ?? null) ? $selectedStudentData['evidence'] : null;
$studentName = trim((string) ($selectedStudentData['student_name'] ?? ''));
$studentNumber = trim((string) ($selectedStudentData['student_number'] ?? ''));
$classLabel = trim((string) ($selectedClassData['label'] ?? ''));
$hasNoteState = $supportingNote !== '' || isset($formErrors['note']) || $selectedReasonCode === 'other';
$isContextualReferral = $referralSourceContext === 'risk_alerts' && $selectedStudentData !== null;
?>
<div class="referral-action-sheet-layer" data-referral-sheet data-referral-contextual="<?= $isContextualReferral ? 'true' : 'false' ?>" <?= $referralSheetInitiallyOpen ? '' : 'hidden' ?> aria-hidden="<?= $referralSheetInitiallyOpen ? 'false' : 'true' ?>">
    <noscript><style>.referral-note-region{display:block}</style></noscript>
    <div class="referral-action-sheet-backdrop" data-referral-sheet-close aria-hidden="true"></div>
    <aside class="referral-action-sheet" data-referral-sheet-dialog role="dialog" aria-modal="true" aria-labelledby="<?= htmlspecialchars($referralSheetTitleId, ENT_QUOTES, 'UTF-8') ?>" tabindex="-1">
        <header class="referral-action-sheet-header">
            <div class="referral-action-sheet-heading">
                <h2 id="<?= htmlspecialchars($referralSheetTitleId, ENT_QUOTES, 'UTF-8') ?>" tabindex="-1" data-referral-sheet-title><?= $referralSourceContext === 'risk_alerts' ? 'Refer Student' : 'New Referral' ?></h2>
                <p data-referral-sheet-context <?= $studentName !== '' ? '' : 'hidden' ?>><?= $studentName !== ''
                    ? htmlspecialchars($studentName . ($studentNumber !== '' ? ' · ' . $studentNumber : ''), ENT_QUOTES, 'UTF-8')
                    : '' ?></p>
                <small data-referral-sheet-class-context <?= $classLabel !== '' ? '' : 'hidden' ?>><?= htmlspecialchars($classLabel, ENT_QUOTES, 'UTF-8') ?></small>
            </div>
            <button type="button" class="referral-action-sheet-close" data-referral-sheet-close aria-label="Close referral workspace" title="Close">
                <i data-lucide="x" aria-hidden="true"></i>
            </button>
        </header>

        <form method="post" action="<?= APP_URL ?>/actions/teacher/create_referral.php" class="referral-action-sheet-form" data-referral-form>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="source_context" value="<?= htmlspecialchars($referralSourceContext, ENT_QUOTES, 'UTF-8') ?>" data-referral-source-context>
            <input type="hidden" name="source_page" value="<?= $referralSourcePage ?>" data-referral-source-page>
            <input type="hidden" name="student_id" value="<?= (int) ($selectedStudentData['student_id'] ?? 0) ?>" data-referral-student-id>
            <input type="hidden" name="evidence_token" value="<?= htmlspecialchars($selectedEvidenceToken, ENT_QUOTES, 'UTF-8') ?>" data-referral-evidence-token>

            <div class="referral-action-sheet-body">
                <?php if (isset($formErrors['form'])): ?>
                    <div class="referral-form-error" role="alert"><?= htmlspecialchars((string) $formErrors['form'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <section class="referral-action-section referral-student-section" aria-labelledby="<?= htmlspecialchars($referralSheetId, ENT_QUOTES, 'UTF-8') ?>-student-heading" data-referral-context-section <?= $isContextualReferral ? 'hidden' : '' ?>>
                    <h3 class="referral-action-section-title" id="<?= htmlspecialchars($referralSheetId, ENT_QUOTES, 'UTF-8') ?>-student-heading">Student and class</h3>
                    <div class="referral-action-fields">
                        <label class="referral-field" for="<?= htmlspecialchars($referralSheetId, ENT_QUOTES, 'UTF-8') ?>-class">
                            <span>Class <b class="referral-required" aria-hidden="true">*</b></span>
                            <select id="<?= htmlspecialchars($referralSheetId, ENT_QUOTES, 'UTF-8') ?>-class" name="operational_class_id" required data-referral-class aria-invalid="<?= isset($formErrors['operational_class_id']) ? 'true' : 'false' ?>" <?= isset($formErrors['operational_class_id']) ? 'aria-describedby="' . htmlspecialchars($referralSheetId, ENT_QUOTES, 'UTF-8') . '-class-error"' : '' ?>>
                                <option value="">Select a class</option>
                                <?php foreach ($referralClasses as $class): ?>
                                    <?php $classId = (int) ($class['operational_class_id'] ?? 0); ?>
                                    <option value="<?= $classId ?>" <?= $classId === $selectedClassId ? 'selected' : '' ?>><?= htmlspecialchars(trim((string) ($class['subject_name'] ?? '') . ' · ' . (string) ($class['section_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($formErrors['operational_class_id'])): ?><small class="referral-field-error" id="<?= htmlspecialchars($referralSheetId, ENT_QUOTES, 'UTF-8') ?>-class-error" role="alert"><?= htmlspecialchars((string) $formErrors['operational_class_id'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                        </label>

                        <div class="referral-field" data-referral-student-field>
                            <span id="<?= htmlspecialchars($referralSheetId, ENT_QUOTES, 'UTF-8') ?>-student-label">Student <b class="referral-required" aria-hidden="true">*</b></span>
                            <select id="<?= htmlspecialchars($referralSheetId, ENT_QUOTES, 'UTF-8') ?>-student" name="student_class_enrollment_id" required data-referral-student aria-labelledby="<?= htmlspecialchars($referralSheetId, ENT_QUOTES, 'UTF-8') ?>-student-label" <?= $selectedClassId > 0 ? '' : 'disabled' ?>>
                                <option value=""><?= $selectedClassId > 0 ? 'Select a Student' : 'Select a class first' ?></option>
                                <?php foreach ($selectedStudents as $student): ?>
                                    <?php $sceId = (int) ($student['student_class_enrollment_id'] ?? 0); ?>
                                    <option value="<?= $sceId ?>" data-student-id="<?= (int) ($student['student_id'] ?? 0) ?>" data-student-name="<?= htmlspecialchars((string) ($student['student_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-student-number="<?= htmlspecialchars((string) ($student['student_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $sceId === $selectedSceId ? 'selected' : '' ?>><?= htmlspecialchars(trim((string) ($student['student_name'] ?? '') . ' · ' . (string) ($student['student_number'] ?? '')), ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="referral-student-combobox" data-referral-student-combobox hidden>
                                <input type="text" id="<?= htmlspecialchars($referralSheetId, ENT_QUOTES, 'UTF-8') ?>-student-search" role="combobox" autocomplete="off" aria-labelledby="<?= htmlspecialchars($referralSheetId, ENT_QUOTES, 'UTF-8') ?>-student-label" aria-autocomplete="list" aria-haspopup="listbox" aria-expanded="false" aria-controls="<?= htmlspecialchars($referralSheetId, ENT_QUOTES, 'UTF-8') ?>-student-options" aria-invalid="<?= isset($formErrors['student_class_enrollment_id']) ? 'true' : 'false' ?>" <?= isset($formErrors['student_class_enrollment_id']) ? 'aria-describedby="' . htmlspecialchars($referralSheetId, ENT_QUOTES, 'UTF-8') . '-student-error"' : '' ?> placeholder="Search by Student name or number">
                                <div class="referral-student-options" id="<?= htmlspecialchars($referralSheetId, ENT_QUOTES, 'UTF-8') ?>-student-options" role="listbox" aria-label="Students in the selected class" hidden></div>
                                <span class="visually-hidden" data-referral-student-status role="status" aria-live="polite"></span>
                            </div>
                            <small class="referral-field-error" id="<?= htmlspecialchars($referralSheetId, ENT_QUOTES, 'UTF-8') ?>-student-error" data-referral-student-error role="alert" <?= isset($formErrors['student_class_enrollment_id']) ? '' : 'hidden' ?>><?= htmlspecialchars((string) ($formErrors['student_class_enrollment_id'] ?? 'Select a Student from the matching results.'), ENT_QUOTES, 'UTF-8') ?></small>
                        </div>
                    </div>
                    <noscript>
                        <div class="referral-noscript-classes">
                            <p>JavaScript is unavailable. Choose a class to load its Student roster:</p>
                            <?php foreach ($referralClasses as $class): ?>
                                <a href="<?= APP_URL ?>/dashboard/teacher_referrals.php?<?= htmlspecialchars(http_build_query(['new' => 1, 'class_id' => (int) $class['operational_class_id']]), ENT_QUOTES, 'UTF-8') ?>#new-referral"><?= htmlspecialchars(trim((string) $class['subject_name'] . ' · ' . (string) $class['section_name']), ENT_QUOTES, 'UTF-8') ?></a>
                            <?php endforeach; ?>
                        </div>
                    </noscript>
                </section>

                <section class="referral-action-section referral-evidence" aria-labelledby="<?= htmlspecialchars($referralSheetId, ENT_QUOTES, 'UTF-8') ?>-evidence-heading" data-referral-evidence>
                    <h3 class="referral-action-section-title" id="<?= htmlspecialchars($referralSheetId, ENT_QUOTES, 'UTF-8') ?>-evidence-heading">Attendance evidence</h3>
                    <p class="referral-evidence-empty" data-referral-evidence-empty role="status" aria-live="polite" aria-atomic="true" <?= $selectedEvidence === null ? '' : 'hidden' ?>>Select a Student to review available attendance evidence.</p>
                    <div data-referral-evidence-content aria-busy="false" <?= $selectedEvidence === null ? 'hidden' : '' ?>>
                        <div class="referral-evidence-status">
                            <strong data-referral-evidence-tier><?= htmlspecialchars((string) ($selectedEvidence['tier_label'] ?? 'Insufficient evidence'), ENT_QUOTES, 'UTF-8') ?></strong>
                            <span data-referral-evidence-reason><?= htmlspecialchars((string) (($selectedEvidence['reason_strings'][0] ?? '') ?: 'Attendance evidence is not available yet.'), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <dl class="referral-evidence-values">
                            <div><dt>Recorded sessions</dt><dd data-evidence-recorded><?= (int) ($selectedEvidence['recorded_count'] ?? 0) ?></dd></div>
                            <div data-evidence-assessable-row <?= !empty($selectedEvidence['assessable']) ? '' : 'hidden' ?>><dt>Absences</dt><dd data-evidence-absent><?= !empty($selectedEvidence['assessable']) ? (int) ($selectedEvidence['absent_count'] ?? 0) : '—' ?></dd></div>
                            <div data-evidence-assessable-row <?= !empty($selectedEvidence['assessable']) ? '' : 'hidden' ?>><dt>Lates</dt><dd data-evidence-late><?= !empty($selectedEvidence['assessable']) ? (int) ($selectedEvidence['late_count'] ?? 0) : '—' ?></dd></div>
                            <div data-evidence-assessable-row <?= !empty($selectedEvidence['assessable']) ? '' : 'hidden' ?>><dt>Effective absences</dt><dd data-evidence-effective><?= !empty($selectedEvidence['assessable']) ? (int) ($selectedEvidence['effective_absent_count'] ?? 0) : '—' ?></dd></div>
                        </dl>
                        <p class="referral-evidence-caption">Evidence reviewed at <time data-evidence-captured-at><?= htmlspecialchars((string) ($selectedEvidence['captured_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></time>. This snapshot is separate from later live attendance.</p>
                    </div>
                </section>

                <section class="referral-action-section referral-reason-section" aria-label="Referral reason and supporting note">
                    <label class="referral-field" for="<?= htmlspecialchars($referralSheetId, ENT_QUOTES, 'UTF-8') ?>-reason">
                        <span>Referral Reason <b class="referral-required" aria-hidden="true">*</b></span>
                        <select id="<?= htmlspecialchars($referralSheetId, ENT_QUOTES, 'UTF-8') ?>-reason" name="reason_code" required data-referral-reason aria-invalid="<?= isset($formErrors['reason_code']) ? 'true' : 'false' ?>" <?= isset($formErrors['reason_code']) ? 'aria-describedby="' . htmlspecialchars($referralSheetId, ENT_QUOTES, 'UTF-8') . '-reason-error"' : '' ?>>
                            <option value="">Select a reason</option>
                            <?php foreach ($reasonOptions as $reasonCode => $reasonLabel): ?>
                                <option value="<?= htmlspecialchars($reasonCode, ENT_QUOTES, 'UTF-8') ?>" <?= $reasonCode === $selectedReasonCode ? 'selected' : '' ?>><?= htmlspecialchars($reasonLabel, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($formErrors['reason_code'])): ?><small class="referral-field-error" id="<?= htmlspecialchars($referralSheetId, ENT_QUOTES, 'UTF-8') ?>-reason-error" role="alert"><?= htmlspecialchars((string) $formErrors['reason_code'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                    </label>

                    <button type="button" class="referral-note-toggle" data-referral-note-toggle aria-expanded="<?= $hasNoteState ? 'true' : 'false' ?>" aria-controls="<?= htmlspecialchars($referralSheetId, ENT_QUOTES, 'UTF-8') ?>-note-region">
                        <i data-lucide="plus" aria-hidden="true"></i><span>Add supporting note</span>
                    </button>
                    <div class="referral-note-region <?= $hasNoteState ? 'is-open' : '' ?>" id="<?= htmlspecialchars($referralSheetId, ENT_QUOTES, 'UTF-8') ?>-note-region" data-referral-note-region>
                        <label class="referral-field" for="<?= htmlspecialchars($referralSheetId, ENT_QUOTES, 'UTF-8') ?>-note">
                            <span>Supporting Note <small class="referral-optional" data-referral-note-state>(Optional)</small></span>
                        <textarea id="<?= htmlspecialchars($referralSheetId, ENT_QUOTES, 'UTF-8') ?>-note" name="note" rows="4" maxlength="5000" data-referral-note<?= isset($formErrors['note']) ? ' aria-describedby="' . htmlspecialchars($referralSheetId, ENT_QUOTES, 'UTF-8') . '-note-error"' : '' ?> aria-invalid="<?= isset($formErrors['note']) ? 'true' : 'false' ?>" placeholder="Add brief factual context for the Disciplinary Officer."><?= htmlspecialchars($supportingNote, ENT_QUOTES, 'UTF-8') ?></textarea>
                            <small class="referral-field-error" id="<?= htmlspecialchars($referralSheetId, ENT_QUOTES, 'UTF-8') ?>-note-error" data-referral-note-error role="alert" <?= isset($formErrors['note']) ? '' : 'hidden' ?>><?= htmlspecialchars((string) ($formErrors['note'] ?? 'Explain the concern when Other Concern is selected.'), ENT_QUOTES, 'UTF-8') ?></small>
                        </label>
                    </div>
                </section>
            </div>

            <footer class="referral-action-sheet-footer">
                <button type="button" class="btn btn-outline-secondary" data-referral-sheet-close>Cancel</button>
                <button type="submit" class="btn btn-primary"><i data-lucide="send" aria-hidden="true"></i> Submit Referral</button>
            </footer>
        </form>
    </aside>
    <script type="application/json" data-referral-sheet-data><?= json_encode($referralSheetData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?></script>
</div>
