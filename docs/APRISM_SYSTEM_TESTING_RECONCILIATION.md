# System Testing Reconciliation

**Assessment date:** 2026-10-22  
**Canonical source:** `C:\xampp\htdocs\aprism`  
**Source document:** Google Drive native document **System Testing Docu**, updated 2026-10-21. Its complete nine-tab document was read and exported/rendered as a 76-page PDF for page references and screenshot review. The local `Downloads\System Testing Docu.pdf` is a 14-page Technical Administrator extract, not the complete document.

## How to read this reconciliation

The PDF page references below refer to the complete 76-page export. Screenshots and accompanying notes are historical observations, not proof of a current defect. Current source, frozen decisions, the approved APRISM UI rules, and verification evidence take precedence. No application code or database was changed or runtime workflow retested for this documentation assessment.

The canonical checkout was on `design/aprism-system-hardening`, HEAD `a578d45`; the working tree already contained about 100 modified or untracked paths. The handoff still contains older status text (for example, Teacher “In Progress,” Guidance “Not Started,” and a three-role navigation list), so it is not a reliable inventory of current implementation by itself.

## OCR references and example

**Document tab: OCR repos; PDF pp. 1–5.** The tab names Tesseract, `ocr-receipt`, OCRtable, PyMuPDF, `img2table`, Microsoft Table Transformer, and FileTailored. It includes a photographed timetable grid on p. 1 and a tool-output screenshot on p. 2, plus library/demo screenshots on pp. 3–5. These are research references and a candidate visual sample, not an accuracy benchmark or an approval to upload institutional records to third parties.

The current source now contains locally packaged Tesseract.js and PDF.js assets (`assets/vendor/schedule-ocr/`), OCR extraction/review scripts, and a Teacher My Classes integration that validates a reviewed batch before explicit confirmation. The browser-based, teacher-reviewed workflow is materially beyond the earlier “OCR absent” notes in older handoffs. However, the embedded timetable image is not accompanied by a machine-readable ground-truth meeting list or a separately supplied original file. No extraction accuracy should be inferred from source presence, the demo screenshots, or synthetic tests. Obtain a usable original timetable and its expected Subject, Section, day, start/end time, and room rows before judging accuracy.

**Decision:** Keep OCR local, optional, editable, and subordinate to existing validation and explicit confirmation. Do not add a server-side Python service or third-party upload based on these references. Real timetable accuracy and physical camera use remain verification work.

## General notes and cross-role suggestions

**Document tab: General Notes; PDF pp. 7–10.**

- **MFA and test sessions (p. 7):** The MFA request is deferred until suitable paid hosting/deployment infrastructure, per the Product Owner’s prior decision. The request to allow more simultaneous sessions for screen comparison conflicts with frozen single-active-session enforcement. Do not weaken either rule for testing.
- **Dashboard cards, legacy transfer, AprilTag print size (p. 8):** These are design/policy proposals, not reproduced failures. Do not make every panel clickable without checking action clarity and keyboard semantics. Data transfer on staff departure needs an ownership/history decision. Configurable print size is optional and not required by current AprilTag decisions.
- **Input capitalization (p. 9):** Global auto-capitalization is not approved. Preserve Student identity, official labels, and the supplied value semantics; any normalization must be field-specific and justified.

**Decision:** No implementation task is created from these suggestions alone. MFA and single-session behavior remain as approved; the other proposals require a concrete workflow need and Product Owner direction.

## Technical Administrator

**Document tab: Tech-Ad (DONE); PDF pp. 11–24.**

- **Dashboard and sidebar (pp. 11–12):** The screenshot comments describe discoverability/click-target preferences. The current Teacher Dashboard composition is accepted, and shell/sidebar work is shared. Do not reopen either based only on this historic note. Check a specific broken target only if a current user reproduces it.
- **Users and responsibility management (pp. 13–15):** The request to manage responsibilities through the UI is represented by current user/responsibility pages and actions. Search suggestions, a clear button, live field validation, and confirmation prompts are usability suggestions, not evidence that account creation or responsibility changes fail. Keep server-side validation and role boundaries; do not add regex indiscriminately. The request to move transient toasts to the lower right conflicts with APRISM’s current top-right toast rule.
- **Audit Logs (pp. 16–18):** The current page derives dates using `APRISM_TIMEZONE` and `DateTimeZone`, rather than a hard-coded numeric offset (`dashboard/technical_admin_audit_logs.php`). Pagination and responsive styling exist in the current source, but the reported 360×800 identifier overlap and description sizing were not reproduced in this assessment. Column centering is not a universal fix; preserve left alignment for descriptive fields and center only compact data where useful.
- **Database Backups (pp. 19–21):** Current actions create and download backups and configure schedule/retention (`actions/system/create_backup.php`, `download_backup.php`, and `update_backup_*`). No in-application restore action was found in `actions/system/`. The question “how do we recover?” is therefore an open operational/recovery decision, not proof that backup creation is broken. Document and test a safe isolated restore procedure before claiming recoverability; decide separately whether restore should ever be an in-app capability.
- **Settings (pp. 22–24):** The notes request review/confirmation for consequential policy changes and explanatory help. APRISM now treats attendance policy and session timeout as configurable institutional settings. Any review prompt must show the actual changed values and leave server validation authoritative. Icon-only Save and lower-right toast preferences do not override the current APRISM action/toast baseline.

**Decision:** The actionable remaining Admin work is a recovery procedure/decision and a focused runtime check only for the specifically reported 360×800 Audit Logs issue. Search autocomplete, column centering, and toast relocation are not required corrections.

## Academic Head

**Document tab: Acad-Head; PDF pp. 25–31.**

- **Teaching Context label (p. 25):** The suggestion to call this “Semester” is not adopted. School Year, teaching context, and Academic Period are distinct. Keep a clear teaching-context label unless the Product Owner approves a terminology change that preserves this distinction.
- **Academic Setup (pp. 26–27):** “Only dates should be editable” is too broad. The approved Academic Period safeguard blocks changes to protected fields when raw scores or historical risk snapshots depend on the period; periods without dependents remain editable under existing validation. Do not make all fields read-only or treat elapsed dates alone as protection.
- **Placement Import / Placement Data Issues (pp. 28–30):** The screenshot reports confusing return navigation, unclear field mappings, and no duplicate checks. Current routes implement a reviewed placement import, Student Number matching, conflict detection, and explicit transaction persistence (`dashboard/academic_head_students.php`, `academic_head_student_placements.php`, `includes/import/institutional_registry_import_engine.php`). Focused regression files also exist, including `tools/placement-import-post-commit.test.php` and `tools/student-lifecycle-integrity.test.php`. These source/test artifacts do not prove current browser behavior; the historical complaint is not a confirmed current defect. Preserve SAE as placement and SCE as participation; missing SAE must not block valid SCE.
- **Adviser Assignments (p. 30):** The request to limit a Teacher to Sections they teach conflicts with the approved Adviser workflow. Assignment is based on the Academic Head’s confirmed SHS Section/context selection, not teaching assignment. Do not add that restriction.
- **Time display (p. 31):** Twelve-hour formatting is a presentation preference, not a rule. Use the configured Asia/Manila context and the current form/storage contract; do not change stored time semantics.

**Decision:** Preserve the current SAE/SCE distinction, protected Academic Period behavior, and Adviser scope. Reproduce only the reported navigation/mapping issue if it remains observable in the current authenticated local UI.

## Teacher

**Document tab: Teacher; PDF pp. 32–51.**

- **Dashboard (pp. 32–33):** Older copy/clickability suggestions do not reopen the accepted four-KPI dashboard composition. Preserve real-data and action semantics.
- **My Classes and schedule import (pp. 34–40):** Duplicate buttons, schedule display, time validation, week navigation, drag/drop, and toast layering are historical suggestions or reports. The current source has a distinct schedule parser (`actions/teacher/parse_schedule_import.php`) and browser OCR review path; CSV/XLSX, Class List, and manual workflows remain separate. The “enrolled at” timestamp screenshot (p. 39) does not establish whether the value is wrong or whether it is an enrollment timestamp; compare it with the source record before changing its meaning. No current defect was reproduced here.
- **My Students (pp. 41–43):** The screenshots capture early layouts and suggestions for class filter, pagination, eye action, and Student Details. Per the user-provided prior evidence, My Students passed isolated functional and responsive QA, including its controls and Student navigation. Canonical authenticated browser verification remains a separate limitation; do not promote isolated QA to canonical or production evidence.
- **Attendance (p. 44):** The label/action suggestions are not independently verified current defects. The “defaults to offline” observation on p. 51 is ambiguous: APRISM intentionally queues scans locally when disconnected and synchronizes later. Current source has IndexedDB queueing, duplicate handling, reconnect/manual sync, and server-side revalidation; a focused contract test exists (`tools/attendance-offline-sync-contract.test.mjs`). Physical camera, network-loss/reconnect, persistence across reload/backgrounding, and hosted behavior remain unverified in existing evidence. The survey’s “Excused” and “Paused” wording is not current supported behavior and must not be implemented from the questionnaire.
- **Grade Import (pp. 46–47):** The screenshot contains an `Unexpected token '<' ... is not valid JSON` extraction response and the note says other attempts produced different errors. This is a credible historical failure report, but it came from an earlier screenshot/runtime and was not reproduced on current canonical code with the original workbook. Current Grade Import has explicit review/confirmation, raw-score persistence, and audit handling in `dashboard/teacher_grade_import.php` and `actions/teacher/confirm_grade_import.php`. Keep the three grade destinations distinct; the suggestion to combine Import, Ledger, and Analytics does not override their current workflows.
- **Referrals (pp. 48–50):** Historical visual suggestions are not requirements. Page 49 shows a side-positioned form; page 50 shows an HTML response surfaced as a JSON parsing error while loading attendance evidence on a mobile view. Current source uses a responsive action-sheet component and top-right toast feedback (`includes/components/teacher_referral_action_sheet.php`, `assets/css/components/teacher-referral-action-sheet.css`, `assets/js/components/teacher-referral-action-sheet.js`). The specific mobile evidence error still needs current authenticated local reproduction; the hosted screenshot is not proof of a current local defect.

**Decision:** Do not redesign the Teacher pages from old screenshots. The two specific follow-ups with credible operational impact are Grade Import extraction-error reproduction and mobile Referral evidence-error reproduction. Attendance offline behavior needs device/network acceptance, not a change to the intended queue model.

## Disciplinary Officer coverage

**Document tab: Guidance; PDF pp. 52–54.** This tab contains no substantive text or screenshots. It supplies no DO defect evidence. The prior role-based consultation smoke check is reported as completed by the Product Owner; do not repeat the entire campaign. A targeted regression is still appropriate for workflows changed after that checkpoint.

## Evaluation questionnaires and copied design material

**Document tab: Survey; PDF pp. 67–76.** The general and IT-expert evaluation forms have unmarked rating cells and blank remarks/signatures. The client form at the end is only a blank evaluation form. They contain no evaluator results, sample count, or evidence that criteria passed. They are documentation instruments, not defects.

Some questionnaire items describe “Excused” attendance, “Paused” sessions, and evaluation of behavior not present in the frozen/current workflow. The item numbering also skips category numbers in places. Before collecting ratings, revise the forms against current frozen behavior, separate unsupported or deferred criteria, and retain actual responses with evaluator role/sample information. Do not convert blank checkboxes into scores or introduce unsupported states to satisfy them.

**Document tab: DESIGN.MD; PDF pp. 55–66.** This is a copy of the design constitution, not a second testing report or backlog. Use the current repository `DESIGN.md` and approved decisions as governing guidance.

## Reconciliation outcome

- **Completed or already represented in the current implementation:** UI-based responsibility controls; current Academic Period protection; reviewed placement import with identity/conflict safeguards; Schedule Import separate from Class List Import; locally packaged Schedule OCR review/confirmation; Teacher My Students isolated QA per prior evidence; Referral request/audit protections and responsive action-sheet source; Attendance offline queue/sync source and contract test.
- **Confirmed source gap, not necessarily a product defect:** no in-application backup restore action was found. Recovery mode and ownership need an operational/Product Owner decision.
- **Reported, not currently reproduced:** Grade Import JSON/extraction error; mobile Referral evidence JSON error; 360×800 Audit Logs overlap/description sizing; post-import enrollment timestamp concern; Placement Import return/mapping usability.
- **Needs real runtime/device evidence:** Attendance physical camera and offline-to-online sync; Schedule OCR accuracy with source timetable ground truth; specific mobile Referral/Grade Import error paths; any checks on current dirty changes that were not covered by the earlier smoke campaign.
- **Do not implement from this document:** MFA on current/free hosting; multiple simultaneous sessions for one account; Adviser assignment limited to classes the Teacher teaches; global text capitalization; “Excused”/“Paused” attendance states; automatic panel-click behavior; combining Grade Import, Ledger, and Analytics; bottom-right toasts.

