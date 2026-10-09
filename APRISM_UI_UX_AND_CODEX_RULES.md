# APRISM Product, UI/UX, and Codex Implementation Rules

This is the detailed working reference for APRISM user-facing product changes. Read it together with the root `AGENTS.md` and the frozen decision records it names. It guides product quality; it does not authorize changes to institutional policy, security boundaries, or frozen data semantics.

## 1. Product standard

APRISM is one coherent, single-campus application for academic monitoring, attendance, assessment records, and intervention support. Role changes should feel like changing workspaces inside the same product, not entering a different site. Each role keeps its own responsibilities and workflow; consistency does not mean identical page layouts.

Use task-first design. Optimize for accuracy, efficiency, convenience, reduced manual effort, and work users can understand and complete: fast, clear, predictable, context-aware, accessible, responsive, and safe. “The page renders,” “the form submits,” and “the layout does not overflow” are necessary but not sufficient acceptance criteria. Judge whether the right information and action are obvious, whether the real task can be completed without avoidable recall or repetition, whether outcomes and recovery are clear, and whether the interface represents the correct real-world entity.

Use this operating principle throughout:

> NORMAL CASE = AUTOMATIC · AMBIGUOUS CASE = REVIEW · CONFLICT = HUMAN DECISION

Automate only from authoritative, already-known facts. Prefer recognition, defaults, prefill, context carry-forward, deterministic matching, safe idempotency, and focused attention queues. Keep uncertain identity, academic placement, institutional policy, and consequential judgments under human control. Automation must make clear what APRISM did, why it did it, and what still needs a decision.

## 2. Authority, evidence, and scope

- Follow the authority hierarchy and safety rules in `AGENTS.md`. User-approved frozen decisions and explicit current user instructions outrank historical snapshots and stale handoffs.
- Consult `docs/APRISM_FROZEN_DECISIONS.md` and the applicable focused records before touching business semantics. Student lifecycle and enrollment rules are documented in `docs/APRISM_STUDENT_LIFECYCLE_FROZEN_DECISIONS.md`.
- The current verified worktree is evidence of implementation, not permission to silently change an approved architecture. Inspect relevant current files, callers, guards, data contracts, and tests before editing.
- Older documents may contain superseded role lists, route claims, pending statuses, or workflow directions. Treat them as historical where they conflict with a later explicit approved decision or verified current state; do not silently revive them.
- A scoped task governs its scope. Do not expand a page fix into a redesign of unrelated modules. Preserve unrelated dirty-worktree changes.
- For runtime/browser claims, report only interactions actually performed. Separate runtime evidence, static evidence, unavailable hardware, and policy decisions.
- State whether the requested execution is unattended or supervised. For work explicitly delegated for autonomous execution, continue through safe in-scope tasks and report real blockers; for supervised work, identify the exact point that requires user action or approval.

## 3. Canonical role model

The active authentication roles are Technical Administrator, Academic Head, Teacher, and Disciplinary Officer (`ROLE_DISCIPLINARY_OFFICER`). Adviser is a scoped Teacher responsibility, not an authentication role. Program Head remains deferred as a responsibility unless an explicit approved decision changes that status. “Guidance Officer” is not a canonical authentication role; legacy `guidance_*` file/table/helper names may remain where they are internal implementation identifiers.

Do not grant access based on navigation visibility or a URL alone. Preserve server-side role, ownership, assignment, scope, and CSRF checks. Convenience/context propagation must never widen authority.

## 4. Interaction architecture and information hierarchy

Give every major screen one primary interaction type:

1. **Overview** — dashboard summaries, factual metrics, charts, operational attention.
2. **Workspace** — a queue, list, roster, report, schedule, or set of filters/actions.
3. **Detail** — one Student, class, referral, case, follow-up, session, import, or exception.
4. **Transient action** — a short edit, confirmation, or quick action that belongs in a modal/dialog when it is genuinely local.

Do not casually combine a full overview, operational workspace, and substantial record detail in one long page. A substantial drill-down should replace the workspace or use a dedicated detail route with an explicit return path. Keep the originating filters, selected class/period, search, and page position when reasonable and safe. Use modals for bounded actions, not dossiers or data-heavy detail.

Use progressive disclosure: show what is needed for the current decision; summarize secondary information; reveal edit controls and low-frequency detail on demand. Child records should not masquerade as top-level entities. Attach actions to the entity they affect. Prefer one clear primary action, visually subordinate secondary actions, and distinctly styled destructive actions.

Every page should make its purpose and next useful action understandable quickly. Prefer human-facing labels over internal table names, IDs, or implementation vocabulary. Do not add generic helper paragraphs for obvious controls. Add concise help only at a genuine decision point or where it prevents a consequential mistake.

Use outcome-based action labels when the result is known—for example, “Import Class List,” “Save Schedule,” “Assign Selected Tags,” “Start Attendance Scan,” “Confirm Grade Import,” or “Create Referral.” Avoid vague “Submit,” “Execute,” or “Proceed” labels when a clear outcome can be named. Use consistent sentence case or title case, explain optional fields where useful, and never rely on placeholder text as the only field label.

Place navigation where users expect it: a Back action belongs near the page title/left edge and should return to the prior scoped workspace when safe. Put object actions beside the object they affect. Keep primary, secondary, filter, navigation, and destructive actions distinguishable. Use a consistent required/optional field convention; labels remain visible after entry and helper text is not a substitute for a label.

## 5. Entity-centric presentation and counts

Choose the real user-facing entity deliberately for each workspace. Examples:

- My Classes: one Operational Class, with schedules as children.
- Class List: one Student participation in the selected Operational Class (SCE is appropriate here).
- My Students: one canonical `student_id`, aggregating the Teacher’s relevant class context rather than repeating one row per SCE.
- Academic Head Section population: SAE placement, not class participation.
- Adviser Students: exact Adviser assignment plus applicable SAE scope, never a broad SCE-derived roster.
- Grade Analytics roster: Student within the selected class and Academic Period.
- Referral Queue: one referral; Cases: one case; Follow-ups: one follow-up; Notifications: one event/action.
- Schedules remain schedule records nested under their Operational Class; they are not extra classes.

Counts must count the entity named by the label, using distinct keys where appropriate. Keep separate metrics separate (e.g. Operational Classes versus schedules; unique Students versus participations). Prevent join fan-out from duplicating cases, referrals, Students, or scores. If an aggregation changes, verify its scope, row identity, count, pagination, filters, and action links together.

Never globally merge Students by name. `student_id` is permanent internal identity; `student_number` is authoritative when known and can be nullable beforehand. SAE is academic placement; SCE is actual class participation. Do not infer placement, Year Level, academic status, or identity from class/section names or subject mix. Ambiguity requires review; conflicts require human resolution.

## 6. Context, defaults, and automation

Carry forward context when its source is authoritative and the destination contract expects it: signed-in user and role, School Year, teaching context/Semester, Operational Class, Student, Academic Period, Adviser assignment, referral, case, follow-up, useful filters, and return destination. Use explicit, validated route parameters or existing workflow state; avoid hidden persistent state that becomes stale or crosses users/sessions.

- Preselect a value only when it is the sole valid choice or the workflow has an unambiguous current selection. If multiple valid contexts exist, do not guess.
- Reuse server-verified ownership and assignment scope on every request. A carried `class_id` or `student_id` is a convenience hint, never authorization.
- Preserve filters when navigating to detail and back if doing so does not leak scope or misrepresent a changed dataset. Make reset/clear intentional and discoverable.
- Surface actionable work from existing facts (e.g. open cases or scheduled follow-ups) without inventing urgency, deadlines, outcomes, or predictive scores.
- Use safe batch actions only where every item is independently authorized and validated, partial failures are understandable, transactions/audit semantics are sound, and ambiguous rows remain reviewable.
- Do not automate final grades, pass/fail, ranking, predictive risk, FDA without its authoritative denominator, promotion, retention, graduation, withdrawal, counseling/disciplinary conclusions, or Student placement.

## 7. Feedback, empty states, and recovery

Communicate important action and page states: loading/waiting, success, failure, validation, warning, no-op, queued, synced, conflict, blocked, unauthorized, completed, and read-only. Transient user-triggered results should use the established top-right APRISM toast pattern where appropriate; persistent blockers belong next to the affected workflow. Explain what happened and the next safe action without exposing internals or private data. Avoid silent success that forces a manual refresh or database check.

Differentiate true empty, filtered-empty, unavailable/not-configured, out-of-scope, and action-required states. Keep empty states compact. State what is absent and, only when useful, offer the relevant permitted next action. Sparse data should not be stretched into oversized panels; populated data must expand naturally without clipping or duplicate rows.

Validate early when the page already knows an action is invalid, but retain authoritative server validation. On errors preserve safe user input, identify the relevant field/record, explain correction or recovery, and avoid partial destructive writes. Make cancel/back/close behavior predictable.

## 8. Visual language and composition

APRISM uses a shared shell and disciplined navy/yellow/light-neutral visual language with semantic status colors. Reuse current shared components, tokens, typography, radii, buttons, forms, tables, badges, dialogs, toasts, and navigation before adding local variants. Do not create duplicate styling systems or arbitrary pastel palettes.

The mature Technical Administrator pages are a **quality benchmark for craft**—spacing discipline, typography, control sizing, semantic color, restrained surfaces, and clear hierarchy—not a layout template for Teacher, Academic Head, or Disciplinary Officer workflows. The current polished shared APRISM system and verified role pages take precedence over an older prototype. The prototype and other external design inspiration are references only. Do not copy generic SaaS, marketing, ecommerce, or another product’s patterns unless they solve a demonstrated APRISM user problem and fit APRISM’s frozen workflow.

Use the established APRISM Inter typography and tokens with a stable hierarchy: page title, section title, item title, body/data, labels, and secondary metadata. Avoid excessive uppercase eyebrows, tiny metadata, mismatched display sizes, card soup, repeated nested borders, and large empty regions. Keep border radii restrained and consistent with existing components. Important work should carry the visual weight; metadata and secondary actions should not compete with it.

Use space intentionally:

- Wide screens should make useful use of width without turning short forms into full-monitor fields.
- Worklists should support fast desktop scanning and readable columns; use bounded table regions when that contains dense data without creating page-level overflow. Use sticky headers when a long table benefits from persistent column context. Avoid spreadsheet-like over-fragmentation.
- Detail pages may use main/support columns and should stack in a logical reading order.
- Dashboards should balance real metrics and action lists; sparse panels must remain content-aware.
- Charts need enough area to interpret, but should not dominate or be distorted by their containers.
- Calendar, scanner, imports, and dossiers may retain purpose-specific geometry while still using APRISM components and hierarchy.

## 9. Responsive behavior and input ergonomics

Responsive behavior is part of implementation, not deferred polish. Test actual rendered behavior at available representative phone, tablet, laptop, and desktop widths; record actual sizes. Do not claim unperformed viewport or device testing.

Design for reflow and device intent, not merely “no overflow”:

- Phone: one clear reading path, useful compact rows, reachable primary actions, touch-friendly controls (about 44 CSS px where practical), no ordinary page-level sideways scroll, and no desktop-sized empty panels.
- Tablet: retain useful two-zone/two-column composition where the available width supports it; do not collapse prematurely into a very tall phone layout.
- Desktop: support scanning and comparison; avoid stretching a mobile card into a tall desktop card or leaving avoidable dead gutters.
- Tables: use a deliberate overflow, priority-column, or semantic card strategy. Keep critical identifiers/actions available and avoid turning every row into an enormous labelled card.
- Dialogs: fit constrained viewports, allow internal content scrolling when needed, keep close and submit actions reachable, and respect keyboard/virtual keyboard behavior.
- Sticky/fixed shell elements must not cover focus targets or workflow actions.
- Verify responsive behavior in an actual mobile browser when the task and environment support it. Media queries or desktop emulation alone do not prove physical-device camera, permission, viewport chrome, keyboard, IndexedDB, or offline behavior.

Prefer existing design tokens and fluid grid/flex sizing. Add a page-specific breakpoint only for an observed composition change; do not stack many device-model-specific patches. Never shrink text or controls excessively to preserve a desktop arrangement.

### Frozen UI/UX decision — viewport-aware, content-aware workspace sizing

Academic Setup is APRISM's current behavioral/layout precedent for using available viewport space; it is not a page-composition template. Other pages must apply the same principle according to their own DOM, content, purpose, and interaction model. Technical Admin remains the broader visual/system baseline, but its page composition must not be copied blindly.

- At desktop/laptop widths, account for the actual APRISM shell, header, title/action area, and page-specific content before allocating remaining usable viewport space.
- Let the primary workspace/data region use otherwise-available vertical space where appropriate, and let tables or other primary content make useful use of that region without stretching individual rows.
- Do not use arbitrary fixed pixel heights to fill a screenshot. Content must still grow or overflow naturally when its genuine requirements exceed available space.
- At tablet/phone widths, do not force the desktop viewport-height model; return to natural document flow as needed.
- Do not create artificial whitespace, spacer elements, fake rows, stretched table rows, arbitrary margins, negative positioning, or `!important`-based layout forcing to achieve a target height.
- Where paired surfaces need equal outer boundaries, derive them from the layout relationship and content-aware sizing, never fixed heights.
- Static source/CSS inspection does not prove responsive correctness. Distinguish implemented, statically validated, and runtime verified; do not claim scrolling, responsiveness, overflow, alignment, or visual correctness is resolved unless the relevant runtime state and viewport were actually tested.

## 10. Accessibility and interaction quality

Use semantic headings and landmarks, associated labels, accessible names for icon-only controls, visible keyboard focus, logical tab order, keyboard-operable links/buttons, clear disabled states, meaningful modal semantics and focus handling, and status cues that do not rely on color alone. Ensure adequate contrast within the established brand and reachable touch targets. Critical information/actions must not depend on hover. Preserve native browser behavior where it is accessible and predictable.

For camera/scanner screens, keep permission/state/error messaging understandable, provide a safe manual path when the workflow supports one, and make start/stop/retry controls explicit. A rendered camera viewport is not proof of physical camera operation. Distinguish insecure-context, permission, missing-device, decoder/asset, and server-recording failures where safely observable; never silently treat a locally queued scan as server-accepted attendance.

## 11. Privacy and security are part of UX

Never make a workflow easier by weakening authentication, role/ownership checks, assignment scope, CSRF, session protections, upload validation, export hardening, auditability, or confidential-note boundaries. Notifications and previews must not disclose confidential disciplinary/counseling content. Avoid putting secrets or sensitive student/grade data into URLs, browser storage, console output, screenshots, fixtures, or reports. Treat route IDs as untrusted and re-authorize on the server.

Use existing server-side security helpers and mutation boundaries. Keep rendering/context in page files, mutations in action files, and reusable domain logic in the established helper/engine owner. Do not move responsibilities across files for convenience alone.

Review third-party dependencies before introducing them: justify need, prefer an established/local permitted asset, verify license/copyright and provenance, and avoid unnecessary remote calls, fonts, icons, or runtime dependencies. Do not copy third-party assets or text without appropriate rights. Add legal, privacy, cookie, or terms pages only when a real product, institutional, legal, or data-handling requirement applies; do not add them as generic polish. APRISM is an authenticated internal application: prioritize title/metadata hygiene, private-route indexing controls, and prevention of sensitive data in page metadata over marketing SEO. Controlled 404/503 pages are eventual polish work and should not be bundled into unrelated features. Never fabricate metrics, reviews, testimonials, ratings, institutional claims, or other trust signals.

## 12. APRISM domain guardrails to preserve

- Active auth roles: Technical Administrator, Academic Head, Teacher, Disciplinary Officer. Adviser is a Teacher responsibility; Program Head is deferred. Guidance Officer is not an auth role.
- Student identity and placement/participation distinctions in `docs/APRISM_STUDENT_LIFECYCLE_FROZEN_DECISIONS.md` remain frozen.
- Teacher Class List is the normal actual-class participation source; same Student + same Operational Class must not duplicate; re-import remains idempotent; missing later roster rows do not imply withdrawal.
- Attendance: 10-minute grace; Late after grace; Absent after 30 minutes; 3 lates equal one effective absence. FDA is a 20% total-class-hours concept but must not be calculated without the authoritative denominator.
- AprilTag: `tagStandard52h13`; IDs 0–48713; explicit globally current ownership; no silent auto-allocation; numeric fallback; preserve ownership/history and replacement rules. School Year/Semester are provenance, not current ownership boundaries.
- Grades: raw assessment evidence only unless authoritative rules exist. Do not invent final-grade formulas, pass/fail, rank, or predictive risk. Preserve Student Number → Student → SCE → score linkage, explicit confirmation, and revision/history behavior.
- Preserve auditability, confidential boundaries, current role/ownership checks, CSRF, secure sessions, and password handling.

## 13. Implementation and verification loop

For each confirmed issue:

1. Observe the actual user task or obtain concrete source/runtime evidence.
2. Classify its root cause (entity/granularity, workflow/context, feedback, hierarchy, responsive, accessibility, security, or policy).
3. Identify the owning page/component/action and nearby consumers.
4. Make the smallest coherent change that addresses the observed issue without destabilizing unrelated work.
5. Retest the same task/state and relevant viewport; check the neighboring shared surface for regression.
6. Run proportionate PHP/JavaScript/static/integrity checks and report what was and was not exercised.

Before editing, inspect the dirty worktree and exact existing files. Use `apply_patch` for intentional local edits; do not overwrite the user’s accumulated changes. Avoid speculative redesign, broad formatting churn, unnecessary schema changes, and generated files. Do not stage, commit, push, reset, restore, checkout, stash, clean, or deploy unless a later explicit instruction authorizes it.

Before changing CSS, inspect the rendered DOM/markup, current selectors, computed/shared styles, and relevant page/role/layout stylesheets. Prefer correcting the owning component or page rule over adding a broad override. Search for an existing helper/component before adding logic; avoid duplicate or competing CSS, JavaScript handlers, SQL/domain calculations, validation, and toast/empty-state implementations. Do not use a UI pass as an excuse for unrelated cleanup.

When an ambiguity is truly institutional or could change frozen semantics, do not guess: isolate that decision and continue unrelated safe work. Do not stop for a non-blocking verification limitation; state it precisely and use safe alternatives. Never label a workflow complete from a page render alone.

## 14. Current project phase

APRISM’s major approved feature and backend implementation is substantially complete. Default future work is verification and bounded correction: UI/UX polish, responsive/mobile hardening, accessibility, consistency, regression testing, export verification, physical-device camera/offline acceptance, evidence-based QA/data cleanup, release/deployment readiness, and manuscript synchronization. Do not reopen broad feature development unless a missing approved requirement, confirmed defect, security problem, or required institutional behavior is demonstrated. Continue to protect the current accumulated release candidate.

## 15. Model and effort selection advice

Choose model capability and reasoning effort to match risk, not habit:

- GPT-6 Luna Medium is the normal economical default for routine implementation, high-volume work, bounded UI fixes, debugging, tests, refactors, and routine verification.
- GPT-6 Luna High is appropriate for deeper repository tracing, difficult debugging, migrations, multi-file logic, validation, and scanner/offline investigation.
- GPT-6 Sol Medium or High is appropriate for architecture, ambiguous cross-module problems, risky business logic, security-sensitive changes, database/schema reasoning, and major workflow changes where stronger reasoning is justified.
- Use the cheapest reasonable model that can safely complete the task; do not automatically select High. Raise effort when evidence conflicts, the dirty worktree is difficult to isolate, or regression impact spans roles. Do not spend maximum effort on routine text/style-only changes.
- Model names are recommendations for environments where those models are available, not a reason to override the user’s requested model.
- Model selection never replaces inspecting current code, preserving frozen rules, testing the actual user flow, or accurately reporting evidence.
