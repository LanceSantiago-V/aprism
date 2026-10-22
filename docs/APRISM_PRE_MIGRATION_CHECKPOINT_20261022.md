# APRISM Pre-Migration Checkpoint — 2026-10-22

This note preserves the current working context for transfer to another ChatGPT/Codex account. It summarizes existing documentation and the immediately preceding handover QA report; it is not a new system-wide audit or Product Owner approval.

## 1. Project and phase

**Project:** APRISM — *An AprilTag-Based Attendance, Assessment and Intervention Support System for Academic Personnel at STI College Dasmariñas*. APRISM is a single-campus monitoring and intervention-support system, not a SIS, ERP, LMS, or institutional academic-system replacement.

**Canonical repository:** `C:\xampp\htdocs\aprism`

**Canonical local runtime:** `http://localhost/aprism/`
**Current phase:** consultation-ready implementation undergoing final, bounded acceptance checks. Class Handover is implemented and locally migrated, but its remaining acceptance checks and Product Owner review are open. This checkpoint is for a context/account migration; it does not authorize a code, schema, or deployment migration.

## 2. Git and working-tree snapshot

At checkpoint creation:

- Branch: `design/aprism-system-hardening`
- HEAD: `a578d45`
- `git status --short` reported 82 modified tracked entries and 40 untracked entries (122 status entries total). Expanded untracked contents brought the path count to 178. These are pre-existing changes; preserve them.
- Significant modified areas include `AGENTS.md`, `DESIGN.md`, the Agent Playbook, frozen decisions, implementation handoff, Teacher Attendance/import/referral pages and actions, Academic Head pages/actions, Disciplinary Officer workflows, shared CSS/JS, helpers, and navigation.
- Significant untracked work includes Class Handover page/action/helper/CSS/JS, its two migrations and QA tests, Schedule OCR assets and pages, risk-history work, supporting tests, and the remaining-work/system-testing documents. `_design_handoff/` and `_qa_fixtures/` are also untracked protected material.

Do not reset, restore, clean, stage, or overwrite the working tree. The repository is dirty by design at this checkpoint. This checkpoint file is the only file created by the checkpoint task.

## 3. Implemented work and evidence boundaries

Statuses below reflect the dated remaining-work register and prior task reports. They were not all rerun for this checkpoint.

| Area | Existing implementation / evidence | Current boundary |
|---|---|---|
| Student identity and lifecycle | `student_id` is permanent identity; imports match by Student Number; SAE is historical placement and SCE is actual Operational Class participation. | Missing SAE must not block valid SCE participation. Do not infer placement from Section names. |
| Teacher My Students | Prior isolated functional and responsive QA is recorded in the register. | Canonical authenticated browser verification is separately unclaimed. |
| Dashboard card links | Role-specific KPI destinations, focus treatment, hover behavior, and reduced-motion handling were checked in canonical localhost on 2026-10-22. | Informational panels with independent controls remain non-clickable. |
| Class Handover and attendance attribution | Implemented locally with transactional ownership change, history/audit, attendance conductor attribution, and server-side stale-owner/session checks. See section 4. | Not approved complete; canonical conductor workflow, remaining authenticated checks, physical-device offline behavior, and Product Owner review remain. |
| Schedule OCR | Browser-based Tesseract.js/PDF.js capture, editable drafts, and explicit confirmation are implemented. | Accuracy against high-resolution institutional originals and independently verified ground truth remains unproven. |
| Grade Import extraction | Earlier Grade Import corrections and protected review/confirmation behavior are recorded. | The System Testing Docu extraction error still needs reproduction using the original workbook in an isolated current-schema runtime. |
| Attendance offline capture | IndexedDB queue-then-sync and server authority are implemented; offline contract coverage exists. | Physical camera, network loss/reconnect, reload/background persistence, and separate-device handover queue behavior need device verification. |
| Evaluation and handoff documentation | Remaining-work register records incomplete survey/evaluation evidence and stale portions of the implementation handoff. | Do not fabricate survey results or revise frozen decisions to match outdated documents. |

The remaining-work register also tracks: backup recovery procedure decision/rehearsal; current-local reproduction of the reported mobile Teacher Referral evidence error; focused regression of changed role workflows; and Product Owner/research-adviser review of evaluation wording. These are not newly confirmed application defects.

## 4. Recent Class Handover checkpoint

### Verified/documented facts

- The local canonical database has both migration ledger entries and schema changes for `20261022_create_operational_class_handovers.sql` and `20261022_add_attendance_session_conductor.sql`. They were applied previously through APRISM's migration process; do not reapply them.
- The migrations were tested against an isolated copy, and the prior documented canonical check found academic record counts unchanged and pre-existing attendance sessions still unattributed. No handover was performed against canonical academic records.
- The current isolated backend QA report used a disposable database named `aprism_handover_qa_review_20261022`. It reported successful handover regression, attendance-conductor regression, offline contract tests (4/4), server-side action checks for authorization/CSRF/validation, audit-failure rollback and retry, duplicate replay rejection, self-transfer rejection, and a concurrent-request test where one request committed and the competing stale request was rejected.
- That QA report found class, roster, schedule, attendance, grade/revision, referral, and AprilTag records remained associated with their existing identities. Successive transfers preserved one Operational Class and a traceable chain of Teacher IDs. Failed/rejected requests left ownership/history unchanged. The disposable database was left available for inspection.
- The remaining-work register records an earlier isolated authenticated browser handover: the success toast and history row displayed the transfer, the former Teacher lost class access, the replacement saw the class, and a Teacher was denied the Technical Admin route. This is prior evidence, not a browser test performed in the latest backend QA pass.

### Still open / not established

- Complete a current authenticated browser/HTTP acceptance pass for the remaining handover paths, including canonical-local verification of new Attendance Session conductor attribution without making a real transfer.
- Test IndexedDB queue behavior on a physical phone/device, especially scans queued on a former Teacher's device before transfer. APRISM cannot detect an unsynchronized queue that exists only on another device; the handover warning and server-side rejection must remain in place.
- Product Owner visual review and final acceptance remain outstanding. No screenshot or automated result is visual approval.
- Temporary replacement with an automatic return date is outside current behavior. A later explicit handover back is possible; no temporary-return policy should be invented without Product Owner approval.

### Current implementation paths

- `dashboard/technical_admin_class_handover.php`
- `actions/users/handover_operational_class.php`
- `includes/helper/operational_class_handover_helper.php`
- `database/migrations/20261022_create_operational_class_handovers.sql`
- `database/migrations/20261022_add_attendance_session_conductor.sql`
- `tools/operational-class-handover.test.php`
- `tools/attendance-session-handover.test.php`
- `tools/attendance-session-handover-offline.test.mjs`
- Attendance ownership/conductor paths include `actions/teacher/start_attendance_session.php` and `actions/teacher/record_attendance_scan.php`.

The helper changes current `operational_classes.teacher_id` and writes handover history plus required audit in one transaction. It does not generically transfer records, rewrite original authors, disable either account, or change Adviser responsibility. Historical attendance sessions with unknown conductors must remain unknown.

## 5. Protected decisions and boundaries

- Roles remain Technical Administrator, Academic Head, Teacher, and Disciplinary Officer. Adviser is a scoped Teacher responsibility, not a separate authentication role. Do not activate Program Head or create roles.
- `student_id` remains permanent identity; Student Number is the import matching key. SAE is historical placement; SCE is actual participation. Do not require SAE for valid SCE, Attendance, Grades, or Referrals, and do not auto-create placements.
- Preserve role boundaries, server-side authorization, CSRF, one-active-session enforcement, audit requirements, historical actors, and authoritative server-side attendance policy. Do not infer unknown historical attendance authors.
- Use the canonical local runtime for Product Owner visual judgment. Isolated QA evidence is not canonical or production evidence. Never touch hosted data or claim hosting/device behavior without testing it.
- Migrations belong in the established `database/migrations/` path. Do not rerun the two applied Class Handover migrations or mutate academic records for screenshots/tests.
- No commit, push, deployment, broad redesign, cleanup, or destructive Git operation is authorized by this checkpoint.

## 6. Existing documentation to continue from

- `AGENTS.md` — operating authority, role model, lifecycle and security rules.
- `DESIGN.md` and `APRISM_UI_UX_AND_CODEX_RULES.md` — visual/product-quality authority.
- `docs/APRISM_AGENT_PLAYBOOK.md` — implementation, runtime, verification, and handoff workflow.
- `docs/APRISM_FROZEN_DECISIONS.md` and `docs/APRISM_STUDENT_LIFECYCLE_FROZEN_DECISIONS.md` — protected domain decisions.
- `docs/APRISM_IMPLEMENTATION_HANDOFF.md` — continuity document; several role/status passages are historical and stale relative to the current register.
- `docs/APRISM_REMAINING_WORK_REGISTER.md` — latest dated priority and evidence register; use this for current outstanding work.
- `docs/APRISM_SYSTEM_TESTING_RECONCILIATION.md` — reconciliation of the supplied System Testing Docu against implementation evidence.

## 7. Recommended immediate continuation

Continue the remaining Class Handover acceptance checks without changing the workflow: verify canonical authenticated conductor behavior without transferring a real class, then arrange physical-device Attendance/offline testing and Product Owner review. Keep isolated HTTP/browser evidence, canonical reads, and physical-device results explicitly separated.
