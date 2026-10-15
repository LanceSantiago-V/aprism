# APRISM Guidance / Disciplinary Officer Dashboard Data Contract

This document defines truthful metric meanings, available sources, and drill-down destinations for future Guidance / DO Dashboard work. It is not a visual specification. Visual hierarchy, chart styling, card composition, proportions, spacing, and dashboard layout remain intentionally unfrozen for the Product Owner and Kezia visual reference.

## Scope and source rules

- Risk is calculated dynamically from recorded attendance for active Students in active Student Class Enrollments (SCEs) in active Operational Classes under the active School Year. It is evaluated per class; a Student's current overall Tier is the highest current class Tier. Do not add absence counts across unrelated classes.
- The shared evaluator is `aprismMonitoringEvaluateClassRisk()` in `includes/helper/monitoring_helper.php`; class evidence and student aggregation are `aprismMonitoringCurrentClassRiskEvidence()` and `aprismMonitoringStudentRiskSummaries()`.
- Effective absences are recorded absences plus `floor(recorded lates / 3)`. Tiers are Tier 1: 1–2; Tier 2: 3–4; Tier 3: 5+. Three consecutive recorded absences are supporting evidence, not an extra Tier.
- No recorded attendance is insufficient evidence, not zero-risk or a safe status. Students without a current active class/SCE are outside this current-class risk population.
- Raw assessment scores may support a profile, but no academic evidence changes Tier. No grade-risk threshold is approved.
- Referral, Case, and Follow-up statuses are separate. Referral statuses: Submitted, Returned, Accepted, Closed. Case statuses: Open, Active, Closed. Follow-up statuses: Scheduled, Completed, Cancelled.
- Queue referral visibility is scope-aware: unaccepted Submitted/Returned intake is visible to authorized DOs; case-linked referrals are visible to the currently assigned DO. Case and active follow-up metrics should use the same assigned-DO scope. Completed/Cancelled follow-up history retains its recorded assignee.
- “Today”, due, and overdue boundaries use the configured APRISM application timezone (`Asia/Manila` in canonical local configuration) and request-time clock; these are dynamic groupings, not persisted states.

## Metric and drill-down contract

Availability means whether authoritative data and a defined calculation exist now; it does not mean the metric is already displayed on the DO Dashboard.

| Metric / visual | Authoritative data and meaning | Availability | Meaningful drill-down |
|---|---|---|---|
| Students at Risk | Distinct current students whose highest current class Tier is 1, 2, or 3. | AVAILABLE WITH CURRENT DYNAMIC CALCULATION | Risk & Referral Queue, default prioritized worklist. |
| Tier 1 count | Distinct current students whose highest class Tier is 1. | AVAILABLE WITH CURRENT DYNAMIC CALCULATION | `guidance_referrals.php?tier=1`. |
| Tier 2 count | Distinct current students whose highest class Tier is 2. | AVAILABLE WITH CURRENT DYNAMIC CALCULATION | `guidance_referrals.php?tier=2`. |
| Tier 3 count | Distinct current students whose highest class Tier is 3. | AVAILABLE WITH CURRENT DYNAMIC CALCULATION | `guidance_referrals.php?tier=3`. |
| Students with insufficient evidence | Current active class/SCE students with no recorded attendance rows in those contexts. This excludes students with no current active class/SCE. | AVAILABLE WITH CURRENT DYNAMIC CALCULATION | **Drill-down gap:** the Queue's Insufficient filter can show referral-backed students, but the prioritized queue intentionally does not add every unassessable student without a referral. Do not show a dashboard-wide count until a complete scoped record destination exists. |
| New / Submitted referrals | `teacher_referrals.status = 'Submitted'`; “New” is not a separate stored status or defined time window. Count the authorized unaccepted intake queue. | AVAILABLE NOW | `guidance_referrals.php?status=Submitted`. |
| Returned referrals | `teacher_referrals.status = 'Returned'`; clarification is pending. | AVAILABLE NOW | `guidance_referrals.php?status=Returned`. |
| Accepted referrals | `teacher_referrals.status = 'Accepted'`; acceptance opens a Case. For a DO dashboard, count only cases in that DO's active assignment scope. | AVAILABLE NOW | `guidance_referrals.php?status=Accepted` (assigned cases only). |
| Active cases | APRISM's existing case summary treats `Open` and `Active` as ongoing/non-closed work. Keep the underlying statuses distinct. | AVAILABLE NOW | The Cases route filters one status at a time. Drill to `guidance_cases.php?status=Open` and `?status=Active` separately, or add an explicit non-closed filter before using one combined metric. |
| Open cases | `guidance_cases.case_status = 'Open'` in the current DO's assigned-case scope. | AVAILABLE NOW | `guidance_cases.php?status=Open`. |
| Closed cases | `guidance_cases.case_status = 'Closed'` in the current DO's assigned-case scope. History remains available. | AVAILABLE NOW | `guidance_cases.php?status=Closed`. |
| Overdue follow-ups | Assigned follow-ups with status Scheduled and scheduled time earlier than the current APRISM application time. | AVAILABLE NOW, calculated from schedule and status | Follow-ups page, list view, Overdue group. A dedicated URL filter is not currently defined. |
| Follow-ups due today | Scheduled follow-ups whose scheduled date is today in the APRISM application timezone and whose time has not passed. | AVAILABLE NOW, calculated from schedule and status | Follow-ups page, calendar/list view for the current date. |
| Upcoming follow-ups | Scheduled follow-ups later than the current application time, excluding overdue and due-today items. | AVAILABLE NOW, calculated from schedule and status | Follow-ups page, Upcoming group or the scheduled item in the calendar. |
| Completed / Cancelled follow-ups | `guidance_followups.followup_status` and existing completion/cancellation timestamps and note. Counts can be separated by status; notes are history, not a new outcome category. | AVAILABLE NOW | Follow-ups page, Completed or Cancelled group; exact Case for chronology. |
| Risk by Program | Current class-level Tier signals grouped by the authoritative Program on the Operational Class Section. This is a class-signal distribution, not a sum of absences; a Student may contribute to more than one class/group. | AVAILABLE WITH CURRENT DYNAMIC CALCULATION | Queue search scoped to the Program code/name. |
| Risk by Section | Current class-level Tier signals grouped by the authoritative Section attached to the Operational Class. Do not infer context from a Section label. | AVAILABLE WITH CURRENT DYNAMIC CALCULATION | Queue search scoped to the Section label. |
| Referral status distribution | Counts by the distinct stored referral statuses. Keep intake referrals and case-assigned referrals' access scope explicit; do not merge them with Case statuses. | AVAILABLE NOW | Queue filtered by the corresponding referral status. |
| Case status distribution | Counts by Open, Active, and Closed within the current DO's assigned-case scope. | AVAILABLE NOW | Cases page filtered to the matching status. |
| Recent intervention activity | `guidance_case_events` chronology plus referral created/updated timestamps and Follow-up schedule/completion/cancellation history. Event-linked items have a Case ID; referral intake has a referral ID. | AVAILABLE NOW | Exact Case for case events/follow-ups; exact Referral in the Queue for Submitted/Returned intake. |
| Prioritized students requiring attention | Current Queue worklist, ordered by highest class Tier (3 → 2 → 1), then referral status/activity. The queue can also contain no-Tier referrals; do not describe every row as a risk alert. | AVAILABLE WITH CURRENT DYNAMIC CALCULATION | Risk & Referral Queue; a specific student opens `guidance_student_profile.php?student_id={student_id}`. |
| Historical Tier trend | No persisted Tier snapshots or transition history are currently established. Current Tier is recalculated from present class attendance evidence. | NEEDS PERSISTENCE | Future snapshots should drill to the relevant Student Profile and evidence context. Do not render a historical Tier trend until snapshots exist. |
| Academic-risk Tier or grade-based escalation | No academic-risk threshold is approved. Raw scores remain evidence only. | NOT YET APPROVED | No grade-based Tier metric or drill-down should be proposed as a risk decision. |

## Persistence boundary

Current counts and distributions can be derived from authoritative current attendance, referral, case, event, and follow-up records. Do not add risk persistence for these current-state metrics.

If Product Owner approval later requires historical Tier transitions, escalation notifications, or acknowledgment state, the smallest starting point is a risk-transition record keyed to `student_id`, `operational_class_id`, School Year, and teaching context, with prior/new Tier, `observed_at`, and a compact evidence snapshot (absence, late, effective-absence counts and source record/context identifiers). Define deduplication/idempotency and retention before migration. This is a recommendation only; no migration is included here.

## Drill-down limitations to preserve

- The Cases route filters one case status at a time. Keep Open and Active as separate metrics unless a future approved contract adds a combined non-closed filter.
- Follow-up calendar/list groupings are computed at request time. Do not imply durable “overdue” state; it changes as time advances.
- Program/Section groupings are derived from current Operational Class context. They do not define a new student-wide risk score or cross-class absence total.
- Risk-only students do not receive a referral or Case automatically. Referral/case creation remains a human decision.
