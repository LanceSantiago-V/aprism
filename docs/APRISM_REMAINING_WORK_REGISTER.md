# APRISM Remaining Work Register

**Assessment date:** 2026-10-22  
**Scope:** Evidence-based follow-up from the complete *System Testing Docu* and current canonical source. This is a work register, not authorization to implement every suggestion.

## Status and priority meaning

- **P0:** Confirmed correctness, security, integrity, or consultation blocker. None was established by the supplied document in this assessment.
- **P1:** High operational-risk verification or correction. Verify first; implement only if a current defect is reproduced.
- **P2:** Important but non-blocking workflow, mobile, accuracy, or documentation follow-up.
- **P3:** Optional polish or unresolved preference. Leave out of the implementation queue until the Product Owner approves a need.

## P1 — Verify high-impact workflows before further code changes

### 1. Reproduce the reported Grade Import extraction error

- **Status:** Reported issue; needs current runtime verification.
- **Module/role:** Teacher → Grade Import.
- **Evidence:** *System Testing Docu*, Teacher tab, PDF pp. 46–47, shows an HTML-to-JSON parse error after extraction. Current paths are `dashboard/teacher_grade_import.php`, the relevant preview/parser action, and `actions/teacher/confirm_grade_import.php`.
- **Acceptance:** In an isolated current-schema runtime, replay the same workbook and record the exact upload, response, and browser state. Valid workbook data reaches review; malformed/unsupported input gets a clear recoverable error; no data is reported committed before confirmation; roster and Student Number protections, raw scores, revisions, and required audit behavior remain intact.
- **Dependencies:** Original failing workbook (or a safely redacted byte-identical reproduction), isolated Teacher, disposable database. Do not use canonical academic records.

### 2. Complete physical-device Attendance and offline-to-online acceptance

- **Status:** Source and contract coverage exist; physical camera and network/reload behavior remain unverified.
- **Module/role:** Teacher → Attendance Scanner.
- **Evidence:** *System Testing Docu*, Teacher tab, PDF p. 51, reports that the scanner appears offline. Current `assets/js/pages/teacher-attendance.js` uses IndexedDB; `tools/attendance-offline-sync-contract.test.mjs` covers the sync contract. Prior verification evidence explicitly leaves physical camera, network-loss/reconnect, and persistence across reload/backgrounding unverified.
- **Acceptance:** On a supported phone/browser in an isolated runtime, verify camera permission and scanning; disconnect/reconnect the network; confirm queued scans persist and become server-accepted only after sync, duplicates remain safe, current Teacher/session/class/SCE/tag ownership is revalidated, and UI never calls a locally queued scan server-recorded. Keep captured scan time informational and existing server authority unchanged.
- **Dependencies:** Physical device, disposable test session/student/tag, controlled network toggle. A desktop simulation does not satisfy this check.

### 3. Establish and test the backup recovery procedure

- **Status:** Local SQL backup and isolated restore verified on 2026-10-22 using both configured `mysqldump` and APRISM's PHP-native fallback. Recovery remains open as an operational/Product Owner decision; APRISM has no in-app restore action.
- **Module/role:** Technical Administrator → Database Backups.
- **Evidence:** *System Testing Docu*, Tech-Ad tab, PDF p. 21, asks how backups are restored. The existing `createDatabaseBackup()` helper exported the canonical **local** database to private disposable storage without using the UI action, which would write a canonical audit row and may remove files under the configured 45-day retention policy. Both SQL formats imported without errors into separate disposable databases. All 41 table row counts and row-content hashes matched canonical; table inventory, engines/collations, columns, and foreign keys matched; 79 foreign-key constraints had zero orphan references. Separately, a backup of the previously created synthetic handover QA database restored all 41 tables and all nine handover-history rows with identical row-content hashes and audit-log counts. MariaDB automatically added one nonunique supporting index on `adviser_assignments.section_id` in each canonical-data restore, so exact index lists differed without changing data or relationships. Unauthorized direct HTTP access to an existing backup file returned 403; the unauthenticated download action redirected to login. A simulated dump failure left no completed or partial backup file. The prefix-only validity check was confirmed to accept a truncated file, then corrected in `includes/helper/backup_helper.php` to require a completion marker; external and native exports, isolated external restore, and failure handling passed again.
- **Acceptance remaining:** Decide whether operator-led manual restore is the approved recovery model, then document its controlled steps and responsible operator. Verify authorized download and scheduling in an isolated authenticated runtime, check the deployment's backup path/permissions and restore tooling, and test consistency while the database receives writes. The local exercise proves database restoration; it does not prove hosted recovery or restoration of non-database storage files. Do not test restore against canonical or hosted data.
- **Dependencies:** Product Owner/administrator recovery decision, deployment environment, disposable database. Do not add restore UI or run a production restore without separate approval.

### 4. Finish verification of mid-semester Operational Class handover

- **Status:** Core handover and Attendance attribution/offline safeguards are implemented locally and remain **awaiting completion**. Both required migrations are now applied to canonical localhost and the page loads authenticated against the live local database. A fresh current-schema isolated browser transfer and targeted rollback/conflict checks passed. Product Owner review and physical-device offline verification remain open.
- **Module/role:** Technical Administrator account management; Academic Head academic context; Teacher Operational Classes and related workflows.
- **Evidence:** `actions/users/handover_operational_class.php`, `includes/helper/operational_class_handover_helper.php`, and `database/migrations/20261022_create_operational_class_handovers.sql` transfer only `operational_classes.teacher_id` and record from/to Teacher, authorizer, effective time, reason, and required audit in one transaction. `tools/operational-class-handover.test.php` previously passed isolated success, duplicate/schedule conflicts, disabled replacement, stale owner, open session, audit rollback, replay, historical score visibility, and unchanged academic-record snapshots. `tools/attendance-session-handover.test.php` passed against the isolated current-schema QA copy after applying `20261022_add_attendance_session_conductor.sql`: an audit-trigger failure rolled back the handover, legacy sessions remained unattributed, new session rows retained a conducting Teacher, the former Teacher and replacement could not sync into a session conducted by the former Teacher, and reports showed the current owner separately from the recorded conductor. The HTTP scan endpoint returned 401 to an unauthenticated QA request. Canonical localhost was changed only by the two approved migrations and normal administrator-login audit events; hosted data was untouched.
- **Acceptance remaining:**
  - **Completed in canonical localhost (2026-10-22):** backed up the local `aprism` database, restored and compared it in isolation, tested both migrations on a fresh isolated copy using APRISM's migration runner, then applied only the two required migrations through `database/migrate.php`. Both ledger entries and schema objects were verified; existing class, schedule, attendance, enrollment, grade, referral, Student, and user counts remained unchanged. All 27 pre-existing Attendance Sessions remain unattributed (`conducted_by_user_id IS NULL`). No canonical handover was performed.
  - **Completed in fresh isolated browser QA (2026-10-22):** a synthetic class was transferred through the page. The success toast and populated history row showed the original/replacement Teacher, effective time, reason, and authorizer. The former Teacher's class list became empty and a direct class-detail request was rejected; the replacement Teacher's dashboard and My Classes showed the transferred class. A Teacher was denied direct access to the Technical Admin handover route. Reversible isolated states also verified the no-eligible-class and no-replacement-Teacher messages and disabled controls.
  - **Completed in isolated database regression (2026-10-22):** invalid/inactive replacement, self-assignment, duplicate responsibility, stale owner, open Attendance Session, pending Grade Import review, schedule conflict, and required-audit failure were rejected. Invalid CSRF was rejected through the HTTP action and left the owner unchanged. Audit failure rolled back ownership/history/audit, and rejected operations left academic record counts unchanged. A synthetic post-handover Attendance Session kept its conductor; the former Teacher could not use it, and legacy sessions remained unattributed.
  - Canonical localhost authenticated page load, real class/Teacher choices, empty history, and no-page-overflow measurements were verified. Canonical empty-history and isolated populated-history screenshots were captured at desktop and phone widths for Product Owner review; screenshots do not constitute visual approval. No page overflow was measured at 1920, 1600, 1440, 1366, 1280, 1024, 768, 600, 430, 390, 360, or 320 CSS pixels.
  - **Remaining:** verify the new Attendance Session conductor in the canonical authenticated workflow without creating a real class transfer; complete focused QA for CSRF/unauthorized mutation and any remaining attendance/report assertions; review the layouts with the Product Owner; physically test queued AprilTag scans on a device. The server cannot detect unsynchronized scans stored only on another device, and the page warns that those scans remain there and cannot synchronize after transfer.
- **Dependencies:** The approved Technical Administrator/Teacher ownership direction; canonical migrations are applied locally; isolated current-schema QA. Do not implement a generic `user_id` transfer or bulk-update historical records.

## P2 — Reproduce reported errors and validate recent work

### 5. Recheck the mobile Teacher Referral evidence error

- **Status:** Historical runtime error; current local reproduction needed.
- **Module/role:** Teacher → Referrals → New Referral.
- **Evidence:** *System Testing Docu*, Teacher tab, PDF pp. 49–50, shows a side-positioned form and `Unexpected token '<'` while loading attendance evidence in a mobile view. Current source uses the responsive Referral action-sheet component and top-right toast flow.
- **Acceptance:** In authenticated local QA at phone width, load classes, Students, and attendance evidence; exercise validation and submission errors. Confirm JSON endpoints return the expected content type/body, field errors preserve safe input, no HTML/session redirect is parsed as success, and toasts do not hide contextual errors. Change code only for a reproduced current defect.
- **Dependencies:** Isolated QA Teacher with valid class/SCE and a supported mobile browser/viewport. The screenshot was from an older inspection deployment and is not current-local proof.

### 6. Benchmark Schedule OCR against real timetable ground truth

- **Status:** Browser OCR and review flow are implemented; extraction quality remains unproven on representative institutional originals.
- **Module/role:** Teacher → My Classes → Schedule Import.
- **Evidence:** *System Testing Docu*, OCR repos tab, PDF pp. 1–5, contains one photographed timetable and tool references. Current source packages Tesseract.js/PDF.js and supports editable drafts plus explicit batch validation/confirmation.
- **Acceptance:** Use the original high-resolution timetable file and an independently verified meeting list. Measure Subject, Section, day, start/end, and room precision/recall; count missed, merged, split, and incorrect associations; verify uncertain fields stay editable and are never fabricated or auto-saved. Test selectable-text and scanned PDFs separately. Report this sample’s limits; do not generalize to all schedules.
- **Dependencies:** Original permitted schedule samples, ground-truth mapping, isolated runtime. The embedded low-resolution image in the testing document is a candidate, not a complete accuracy dataset.

### 7. Run a focused regression on changed role workflows

- **Status:** Prior consultation smoke test was reported complete; current working tree contains approximately 100 dirty paths, so that earlier result does not validate every current uncommitted change.
- **Module/role:** Only changed or high-risk routes across Technical Administrator, Academic Head, Teacher, and Disciplinary Officer.
- **Evidence:** Current branch `design/aprism-system-hardening` has substantial existing modifications across user/session, imports, Academic Setup/Adviser, Attendance, Grade Import, Referrals, DO cases, shared CSS/JS, and documentation. The handoff’s role/status inventory is stale. This assessment authenticated each role and verified the Dashboard KPI-card destinations and responsive layouts; it did not repeat the broader mutation-flow regression campaign, which remains separate.
- **Acceptance:** Review the current diff, then run targeted authorized QA for changed mutation paths, permission denials, transactions/audit feedback, and representative responsive states. Do not rerun the entire role campaign or change canonical records. Report results by source checkout and runtime.
- **Dependencies:** Authorized QA sessions and isolated data for mutations. Do not create/reset canonical accounts or treat source/test presence as runtime proof.

## P2 — Complete evaluation and maintenance documentation

### 8. Align evaluation forms and implementation handoff

- **Status:** Documentation incomplete/out of sync; not an application defect.
- **Module/role:** Capstone/system evaluation; all roles.
- **Evidence:** *System Testing Docu*, Survey tab, PDF pp. 67–76, contains blank rating forms with no responses, sample count, or signatures. It includes unsupported “Excused”/“Paused” criteria and numbering gaps. `docs/APRISM_IMPLEMENTATION_HANDOFF.md` still describes Teacher and Guidance as unfinished despite current routes and role model.
- **Acceptance:** Before collecting results, reconcile survey criteria to frozen/current behavior, mark deferred or unsupported criteria separately, and retain actual evaluator role/sample/response data. Update the handoff status to the current implementation and verification boundary without changing frozen decisions. Do not fabricate ratings or treat the copied DESIGN.MD tab as a test result.
- **Dependencies:** Product Owner/research adviser approval of evaluation wording; verified current feature inventory.

## P3 — Suggestions not queued as defects

Do not implement these unless a concrete workflow need is approved and reproduced: search suggestions, universal live regex/capitalization, arbitrary centering of all table columns, first/last pagination everywhere, changing all toast placement to lower right, configurable AprilTag print size, global 12-hour time display, generic visual redesign, or merging Grade Import/Ledger/Analytics. The dashboard-card suggestion was checked separately: current KPI cards already use semantic links where a clear destination exists; panels with multiple controls/actions remain informational panels.

## Protected decisions and completed evidence

- Student identity stays `student_id` with Student Number as the import match key; SAE remains historical placement and SCE actual class participation. Missing SAE must not block valid class participation.
- Adviser assignment is not restricted to classes taught by the Teacher. Attendance offline mode remains a queue-then-sync workflow with server-side authority. MFA remains deferred until appropriate paid hosting per the Product Owner’s prior direction; one active session remains required.
- Teacher My Students has passed isolated functional/responsive QA per prior evidence. Canonical authenticated browser verification is separate and remains unclaimed here.
- Dashboard metric-card interactions were checked in canonical localhost on 2026-10-22: Technical Administrator KPI card → Manage Users, Academic Head scoped Placement Issues card → Placement Data Issues with School Year/Teaching Context preserved, Teacher Active Classes card → My Classes, and Disciplinary Officer New Referrals card → Submitted Referral Queue. Semantic anchor links, visible focus treatment, role-specific hover treatment, and reduced-motion CSS are already present. No card behavior change was warranted; panels with charts, filters, tables, or independent actions remain non-clickable.
- Mid-semester class handover now has a bounded local implementation plus an isolated Attendance attribution and stale-sync safeguard; see the P1 entry above. It is not a claim of production readiness or physical offline-queue verification. Existing Adviser responsibilities remain unchanged; legacy Attendance sessions retain no inferred author.
- The Product Owner reports the earlier role-based consultation smoke campaign succeeded. This register does not repeat that campaign or convert old screenshots into current defects.
- No confirmed P0 defect was established by the testing document. All listed code changes are conditional on current reproduction and must remain isolated from canonical and hosted records.

## Recommended next task

**Reproduce the Grade Import extraction error using the original reported workbook in an isolated current-schema runtime.** It is the clearest historical report that could block a core grade workflow. First capture the actual browser response and determine whether the failure still exists; implement only a narrowly scoped correction if it does.

