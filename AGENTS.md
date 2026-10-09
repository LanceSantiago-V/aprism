# APRISM Codex Operating Guide

## 1. Project Identity

APRISM: An AprilTag-Based Attendance, Assessment and Intervention Support System for Academic Personnel at STI College Dasmariñas.

APRISM is a single-campus system for STI College Dasmariñas. It is a monitoring and intervention support system, not a SIS, ERP, LMS, Registrar, or institutional academic-system replacement.

## 2. Authority Hierarchy

1. User-approved frozen decisions and explicit Master Prompt decisions are highest authority.
2. Current verified local implementation is authoritative for current code state, but cannot silently override frozen architecture.
3. `docs/APRISM_FROZEN_DECISIONS.md` is the primary repository decision record.
4. `docs/APRISM_IMPLEMENTATION_HANDOFF.md` records implementation continuity and state; it is not the highest architectural authority.
5. `docs/APRISM_TECHNICAL_LEAD_PROMPT.md` governs workflow and process only where it does not conflict with frozen decisions.
6. Snapshots, release/deployment documents, backups, and focused schemas are contextual unless specifically targeted.

## 3. Implementation Workflow

- Inspect current repository files before modifying them.
- Never reconstruct an existing file from memory or use an older generated artifact as its baseline.
- Make only bounded requested changes; avoid broad refactors unless explicitly requested.
- Ask when architecture is genuinely unresolved.
- Do not invent missing schema, files, tables, columns, roles, or workflows.
- Run appropriate lint or static checks after changes.
- Do not claim authenticated browser or runtime tests that were not performed.
- The user performs local runtime verification unless it is explicitly delegated and technically possible.
- For any work affecting UI, UX, workflows, user-facing behavior or states, information hierarchy, wording, forms, tables, modals, navigation, mobile/responsive behavior, accessibility, efficiency, automation, context preservation, shared components/styles, or maintainability, read `APRISM_UI_UX_AND_CODEX_RULES.md` and apply its product-quality and verification rules automatically; the user need not repeat the filename. It supplements this guide and never supersedes frozen business/security decisions.

## 4. Git Safety

- Inspect Git status and diff before substantial changes.
- Do not stage, commit, push, reset, checkout, restore, delete, or rewrite history unless explicitly requested.
- Preserve unrelated user changes.
- Never broadly overwrite a dirty working tree.

## 5. Database Rules

- Numbered `database/*.sql` files plus later `database/migrations/*.sql` are the intended local schema evolution path.
- Root focused schemas such as `aprism_context_schema.sql` and `sce_schema.sql` are reference/context only and must not override later migrations.
- SQL backups are historical snapshots.
- The live deployment database is authoritative for production state during deployment work.
- Never modify the production database or schema without explicit instruction.

## 6. Frozen Role Model

- Technical Administrator
- Academic Head
- Teacher
- Disciplinary Officer (`ROLE_DISCIPLINARY_OFFICER`)
- Adviser is a Teacher responsibility extension with scoped permissions, not a separate authentication role.
- Program Head remains deferred; do not activate it as an authentication role or assume a responsibility implementation without an approved decision.
- Guidance Officer is not a canonical authentication role. Existing `guidance_*` internal identifiers may remain.

## 7. Academic Context Rules

- Consult and preserve `docs/APRISM_STUDENT_LIFECYCLE_FROZEN_DECISIONS.md` as the current repository Student-context/lifecycle record (older material may use the prior identifier `APRISM_STUDENT_CONTEXT_FROZEN_DECISIONS`); do not silently reinterpret it.
- `student_id` is the permanent internal Student identity.
- `student_number` is the institutional matching key for imports; name alone must never cause a merge.
- School Year is the institutional academic-year context.
- Only one School Year is Active at a time.
- Do not auto-activate a new School Year; Academic Head activation is explicit and historical School Years remain preserved.
- Semester or teaching context is broader than an Academic Period.
- Academic Period is a finer-grained grading or assessment period.
- Student Academic Enrollment represents historical placement and context.
- Student Class Enrollment represents actual participation in an Operational Class.
- Student Class Enrollment must not be blocked by missing Student Academic Enrollment.
- Never parse Section names to infer Program, Year Level, Semester, Strand, or Academic Period.

## Attendance Rules

- 10-minute grace period.
- Late after the grace period.
- Absent after 30 minutes.
- 3 lates = 1 absence.
- FDA concept = 20% of total class hours; never calculate it when the authoritative total-hours denominator is unavailable.

## 8. AprilTag Frozen Rules

- Family: `tagStandard52h13`.
- Usable IDs: `0`–`48713`.
- Student-to-AprilTag ownership is global and persistent.
- `april_tags.current_student_id` is the current ownership relation.
- School Year and Semester are not current-tag validity boundaries.
- Issuance School Year and Semester are provenance only.
- Student Class Enrollment determines active class participation.
- Attendance scanning direction is browser-camera AprilTag scanning; do not introduce QR/NFC-style attendance scanning.
- Offline scanning direction is IndexedDB capture followed by synchronization.
- AprilTag assignment history is term-aware for provenance/history, but current tag ownership remains global and persistent.
- There is no standalone Teacher reclaim workflow.
- Reclaim is internal only as part of explicit replacement while preserving history.
- `is_assigned_here` is replacement-authorization provenance only.
- The authoritative renderer functions are `aprism_apriltag_matrix()` and `aprism_apriltag_png()`.
- Dashboard server-preview integration and stale-request safeguards are accepted current implementation.
- `actions/teacher/distribute_april_tag.php` is intentional current local work.
- Individual View, Download, and Print AprilTag controls are implemented and locally runtime-verified.
- Bulk Download All ZIP and Print All are implemented and locally runtime-verified.
- Browser-camera AprilTag scanning foundation and local scanner integration are implemented.
- Physical-phone runtime verification remains pending.
- Consultation deployment synchronization remains pending.

## 9. Teacher Import/Class Rules

- Teachers input official teaching information only and should not be required to provide internal IDs.
- Existing institutional Subjects, Programs, and Sections are reused rather than silently duplicated.
- Import flow: Source → Extract/Enter → Normalize → Review/Edit → Resolve → Validate → Confirm → Operational Class + Schedule.
- Class List identity uses Student Number as the matching key; name alone must never merge Students.
- Ambiguity requires review.

## 10. Security Defaults

- 60-minute session timeout.
- 15-minute lock duration.
- Single active session.
- `must_change_password`.
- Session regeneration.
- Password hashing.

## 11. Current AprilTag Checkpoint

Preserve the current verified local dashboard behavior:

- Global AprilTag ownership join.
- `is_assigned_here` provenance behavior.
- `preview_april_tags.php` integration.
- `successfulPreviewKey`.
- `previewRequestSequence`.
- `AbortController` stale-request safeguards.
- Top-right toast behavior.
- No standalone reclaim UI.
- No `window.alert()`.
- No School Year or Semester current-tag validity filtering.

## 12. Deferred / Do Not Assume

- Do not assume removed Academic Head Programs or Subjects management UI is active.
- Do not assume ZIP or bulk AprilTag distribution is implemented.
- Do not assume successful runtime preview with a real Awaiting Student has been verified.
- Do not assume production PHP or GD capability unless verified.

## File Responsibility / Separation

- Do not cram unrelated concerns into one file; preserve established APRISM file boundaries.
- Page-specific CSS belongs in the page stylesheet; shared CSS belongs in shared role, layout, or component styles only when genuinely reusable.
- Page rendering and context belong in dashboard/page files; mutations belong in action files; reusable business logic belongs in helpers or engines.
- Shared navigation and components remain centralized.
- Before creating a file, inspect whether an existing appropriate file already owns that responsibility.
- Moving logic between files is an architectural decision, not merely a convenience refactor.

## Responsive / Mobile UI Requirement

- Responsive behavior is part of the definition of done for every new or modified APRISM UI; it must not be deferred to a final polishing phase.
- Preserve desktop behavior while supporting tablet and common phone widths, especially for Teacher workflows and browser-camera AprilTag Attendance scanning.
- Prefer established APRISM responsive patterns; page-specific responsive CSS belongs in the page stylesheet, while genuinely shared behavior belongs in the appropriate shared layout, role, or component stylesheet.
- Do not create a giant miscellaneous mobile stylesheet or allow unintended horizontal page overflow.
- Tables must have an intentional responsive strategy; buttons and controls must remain touch-friendly and primary actions discoverable on small screens.
- Forms and modals must remain usable with mobile viewports and virtual keyboards; cards, headers, badges, filters, and action groups must adapt without overlap or unreadable compression.
- Browser-camera/scanner interfaces must keep the camera viewport, status, and essential controls visible and reachable on common phone screens.
- Do not shrink typography or controls excessively merely to preserve desktop geometry. Whenever an existing page is modified, inspect both desktop and mobile implications.

## APRISM UI / UX Visual Reference

- The current polished shared APRISM system, verified role pages, and shared components are the primary visual and interaction reference. The provided wireframe/React prototype is a secondary reference only.
- Reuse the current design language for hierarchy, cards, spacing, typography, actions, tabs, forms, tables, empty states, status indicators, modals, responsive composition, and interaction flow.
- Translate those ideas into APRISM's existing PHP, Bootstrap 5, Vanilla JS, and CSS architecture; do not introduce React or Tailwind merely because the prototype uses them.
- The wireframe is a UI/UX reference only. Frozen APRISM architecture, business rules, current verified implementation, database model, roles, permissions, security rules, and business decisions override conflicting or outdated prototype behavior.
- Do not revive deprecated prototype terminology or workflows merely for visual similarity. Preserve functionality while moving modified pages naturally toward the approved wireframe design language.

## Product Quality Reference

- `APRISM_UI_UX_AND_CODEX_RULES.md` is the detailed standing reference for user-facing product quality, interaction architecture, context automation, entity-centric presentation, accessibility, responsive composition, and UX verification.
- Current explicit user-approved decisions and verified implementation outrank stale role/process claims in historical snapshots, handoffs, or prompts, consistent with the authority hierarchy above. Do not edit historical decision records merely to make them appear current; resolve conflicts explicitly and safely.
