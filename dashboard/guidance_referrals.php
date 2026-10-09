<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/role_helper.php';
$allowedRoles = [ROLE_DISCIPLINARY_OFFICER];
require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../auth/csrf_helper.php';
$pageTitle='Referral Queue'; $activePage='guidance_referrals'; $roleStylesheet='assets/css/teacher.css'; $pageStylesheet='assets/css/pages/guidance.css'; $csrf=generateCsrfToken();
$status=trim((string)($_GET['status']??'Submitted')); if(!in_array($status,['Submitted','Returned','Accepted','Closed','All'],true))$status='Submitted';
$sql="SELECT tr.teacher_referral_id,tr.reason,tr.status,tr.created_at,
    CONCAT_WS(' ',s.first_name,s.last_name) student_name,s.student_number,
    CONCAT_WS(' ',u.first_name,u.last_name) teacher_name,gc.guidance_case_id,
    clarification.note AS clarification_note, clarification.created_at AS clarification_at
    FROM teacher_referrals tr
    INNER JOIN students s ON s.student_id=tr.student_id
    INNER JOIN users u ON u.user_id=tr.teacher_id
    LEFT JOIN guidance_cases gc ON gc.teacher_referral_id=tr.teacher_referral_id
    LEFT JOIN teacher_referral_followups clarification
        ON clarification.referral_followup_id=(
            SELECT latest.referral_followup_id
            FROM teacher_referral_followups latest
            WHERE latest.teacher_referral_id=tr.teacher_referral_id
            ORDER BY latest.created_at DESC,latest.referral_followup_id DESC
            LIMIT 1
        )
    WHERE ((gc.guidance_case_id IS NULL AND tr.status IN ('Submitted','Returned'))
        OR EXISTS (
            SELECT 1 FROM guidance_case_assignments gca
            WHERE gca.guidance_case_id=gc.guidance_case_id
              AND gca.assigned_user_id=?
              AND gca.ended_at IS NULL
        ))";
$params=[$userId=(int)$_SESSION['user_id']];
if($status!=='All'){$sql.=' AND tr.status=?';$params[]=$status;}
$sql.=' ORDER BY tr.created_at DESC'; $stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
function guidanceReferralEsc(?string $value):string{return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
?>
<!doctype html><html lang="en"><?php require __DIR__.'/../includes/components/head.php';?><body><?php require __DIR__.'/../includes/components/sidebar.php';?><main class="main-content"><div class="main-wrapper"><?php require __DIR__.'/../includes/components/top-navbar.php';?><section class="guidance-page guidance-worklist-page guidance-referral-queue-page"><header class="guidance-page-header"><div><p class="page-eyebrow">Disciplinary Officer</p><h1>Referral Queue</h1></div><span class="guidance-count-badge"><i data-lucide="inbox" aria-hidden="true"></i><span><?=count($rows)?> referral<?=count($rows)===1?'':'s'?></span></span></header><section class="guidance-panel guidance-toolbar"><div class="guidance-panel-heading"><div><p class="guidance-section-kicker">Worklist</p><h2>Review referrals</h2></div></div><form method="get" class="guidance-toolbar-form"><label class="guidance-label" for="referral-status">Status</label><select id="referral-status" name="status" onchange="this.form.submit()"><?php foreach(['Submitted','Returned','Accepted','Closed','All'] as $option):?><option value="<?=guidanceReferralEsc($option)?>" <?=$status===$option?'selected':''?>><?=guidanceReferralEsc($option)?></option><?php endforeach;?></select></form></section><section class="guidance-panel guidance-worklist-panel"><div class="guidance-panel-heading"><div><p class="guidance-section-kicker">Intake and case referrals</p><h2>Referrals</h2></div><span class="guidance-toolbar-meta"><?=count($rows)?> record<?=count($rows)===1?'':'s'?></span></div><div class="guidance-table-wrap"><table class="guidance-table guidance-referral-table"><thead><tr><th>Student</th><th>Referred by</th><th>Reason</th><th>Submitted</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach($rows as $row):?><tr><td data-label="Student"><strong><?=guidanceReferralEsc($row['student_name'])?></strong><small><?=guidanceReferralEsc((string)$row['student_number'])?></small></td><td data-label="Referred by"><?=guidanceReferralEsc($row['teacher_name'])?></td><td data-label="Reason" class="guidance-reason-cell" title="<?=guidanceReferralEsc($row['reason'])?>"><?=guidanceReferralEsc($row['reason'])?><?php if($row['status']==='Submitted'&&$row['clarification_note']!==null):?><small class="guidance-referral-clarification">Clarification response<?= $row['clarification_at']!==null?' · '.guidanceReferralEsc($row['clarification_at']):''?>: <?=guidanceReferralEsc($row['clarification_note'])?></small><?php endif;?></td><td data-label="Submitted"><time><?=guidanceReferralEsc($row['created_at'])?></time></td><td data-label="Status"><span class="guidance-status <?=strtolower((string)$row['status'])?>"><?=guidanceReferralEsc($row['status'])?></span></td><td data-label="Action" class="guidance-actions"><?php if($row['guidance_case_id']):?><a class="btn btn-secondary" title="Open case" href="<?=APP_URL?>/dashboard/guidance_case.php?case_id=<?=(int)$row['guidance_case_id']?>">Open case</a><?php elseif(in_array($row['status'],['Submitted','Returned'],true)):?><form method="post" action="<?=APP_URL?>/actions/guidance/manage_case.php"><input type="hidden" name="csrf_token" value="<?=guidanceReferralEsc($csrf)?>"><input type="hidden" name="operation" value="accept_referral"><input type="hidden" name="teacher_referral_id" value="<?=(int)$row['teacher_referral_id']?>"><button class="btn btn-primary" title="Accept referral">Accept</button></form><?php if($row['status']==='Submitted'):?><form method="post" action="<?=APP_URL?>/actions/guidance/manage_case.php"><input type="hidden" name="csrf_token" value="<?=guidanceReferralEsc($csrf)?>"><input type="hidden" name="operation" value="return_referral"><input type="hidden" name="teacher_referral_id" value="<?=(int)$row['teacher_referral_id']?>"><input name="reason" required placeholder="Clarification reason" aria-label="Clarification reason"><button class="btn btn-secondary" title="Return for clarification">Return</button></form><?php endif;?><?php else:?><span class="guidance-table-muted">No action</span><?php endif;?></td></tr><?php endforeach;?><?php if($rows===[]):?><tr><td colspan="6" class="guidance-empty"><span>No referrals match this queue.</span></td></tr><?php endif;?></tbody></table></div></section></section></div></main><?php require __DIR__.'/../includes/components/footer.php';?></body></html>
