---
name: aprism-feature-change
description: Implement, repair, extend, or refactor APRISM application features, PHP workflows, backend logic, validation, database interactions, permissions, imports, attendance, grades, referrals, AprilTag behavior, reports, and other functional product behavior while preserving established APRISM domain rules. Use for meaningful APRISM feature/backend work.
---

# APRISM Feature Change

Use this skill for meaningful APRISM functional, backend, PHP, workflow, validation, data, authorization, or feature implementation work.

Canonical project:

`C:\xampp\htdocs\aprism`

Canonical local runtime:

`http://localhost/aprism/`

Timezone:

`Asia/Manila`

Do not invent APRISM behavior.

When existing behavior, documentation, source code, database structure, and Product Owner instructions disagree, determine whether the issue is:

- implementation defect
- documentation defect
- stale/legacy behavior
- configurable policy
- missing requirement
- Product Owner decision required

Do not silently choose a new domain rule.

## Before Meaningful Changes

For an existing workflow:

1. inspect the current canonical source
2. trace the actual route/action/helper/query/data path
3. identify authorization and data boundaries
4. identify existing validation and failure behavior
5. identify affected related routes
6. implement the smallest coherent change
7. verify the real workflow
8. review the diff for unintended behavioral changes

Use the installed `code-mapper` agent when the ownership/path is unclear or the workflow spans several files.

Useful specialists when appropriate:

- `php-pro` for PHP-specific implementation
- `backend-developer` for server-side workflows and validation
- `fullstack-developer` when PHP + HTML/CSS/JS must change together
- `sql-pro` for SQL/data reasoning
- `debugger` for difficult root-cause bugs
- `browser-debugger` for runtime/browser/network behavior
- `security-auditor` for authentication, authorization, CSRF, session, or sensitive access changes
- `reviewer` before important checkpoints
- `test-automator` when a meaningful regression deserves durable automated coverage

Do not delegate merely to create activity. Use specialists when they materially improve correctness.

# Frozen APRISM Domain Rules

## Authentication Roles

Canonical authentication roles are:

- Technical Administrator
- Academic Head
- Teacher
- Disciplinary Officer

Adviser is a Teacher responsibility extension with scoped permissions.

Adviser is NOT a separate authentication role.

Program Head is currently deferred/inactive.

Do not revive Program Head behavior unless explicitly instructed.

Legacy filenames/routes may still use `guidance`.

The canonical product role is **Disciplinary Officer**.

Do not perform broad filename/route renames merely to remove legacy naming unless specifically requested.

## Student Identity

Never identify or merge a Student globally by name alone.

Student Number is authoritative when available.

A Student may temporarily exist without an authoritative Student Number in supported import workflows.

Name-only reuse is allowed only under an explicitly safe, already-defined unresolved-participant workflow.

Ambiguous identity must remain blocked for review.

Do not silently merge two Students because their normalized names match.

## Academic Context

Keep these concepts distinct:

### School Year

Institutional academic-year context.

### Semester / Teaching Context

Broader College teaching context, and applicable SHS teaching context, within the School Year.

### Academic Period

Fine-grained grading/assessment period.

Academic Period must not substitute for Semester or Student Academic Enrollment.

### Student Academic Enrollment (SAE)

Historical academic placement/context.

### Student Class Enrollment (SCE)

Actual participation in an Operational Class.

SAE and SCE are not interchangeable.

Do not derive Program, Year Level, Semester, academic period, or authoritative placement by parsing section names.

Section names are labels, not canonical structured academic context.

## Teacher Scope

A Teacher may access only data permitted by their assigned/owned teaching context.

Do not weaken Teacher ownership checks for convenience.

Cross-Teacher access must remain denied unless another established responsibility explicitly permits it.

## Adviser Scope

Adviser visibility must remain restricted to the established advisory scope.

Do not expose unrelated students, confidential disciplinary information, or broader institutional data through Adviser routes.

Teacher scope and Adviser scope may share implementation, but their authorization/data boundaries must remain explicit.

## Academic Head Scope

Preserve Academic Head's institutional/academic-management scope.

Do not reintroduce previously removed/deferred Program or Subject management behavior merely because old code or documentation references it.

## Disciplinary Officer Scope

Preserve established referral, case, follow-up, and assignment boundaries.

Do not invent queue ownership, overdue semantics, due-date rules, or confidentiality behavior when the current product rules are ambiguous.

Mark such cases as requiring Product Owner clarification.

## Technical Administrator

Technical Administrator manages technical/system controls and institutional configuration.

Do not transform configurable settings into hardcoded constants.

# Configurable Institutional Policy

Some values have defaults but remain configurable through Technical Administrator settings.

A current stored value differing from its historical/default value is not automatically a defect.

Examples include security and Attendance policy settings.

Always distinguish:

- default value
- currently configured value
- actual runtime-enforced value

Verify the configuration path before declaring a mismatch.

## Session Timeout

Session timeout is configurable by Technical Administrator.

The historical/default value may be 60 minutes, but a valid configured value such as 70 minutes must be respected when stored and enforced correctly.

Do not reset configurable values merely to match a default.

# Attendance

Browser-camera attendance scanning uses **AprilTag**, not QR/NFC.

Preserve existing Attendance policy configuration.

Established policy concepts include:

- Grace Period
- Absent Threshold
- Pre-start Allowance

These are configurable institutional parameters.

Do not hardcode current numeric values into business logic when the setting is configurable.

Established attendance rule:

**3 lates = 1 effective absence**

FDA threshold:

**20% of total class hours**

Apply the FDA threshold only when authoritative total class hours are available.

Do not fabricate total class hours merely to calculate FDA status.

Preserve stored attendance outcomes:

- Present
- Late
- Absent

Do not reinterpret missing attendance records as zero attendance events.

## Attendance Offline Behavior

APRISM has an offline attendance queue/sync architecture.

Preserve distinctions such as:

- Pending Sync
- Synced

Do not claim full offline-app capability merely because IndexedDB queueing exists.

Physical camera/device behavior must not be considered proven solely through desktop emulation.

When reconciling offline scan time vs sync time, preserve existing authoritative/security decisions. Do not trust uncontrolled client clock values blindly.

# AprilTag

Frozen family:

`tagStandard52h13`

Usable IDs:

`0–48713`

Tag assignment must remain explicit and controlled.

Preserve established assignment/replacement/reclaim behavior.

Do not silently auto-allocate, silently replace, or silently reclaim tags unless an established workflow explicitly authorizes it.

Do not change AprilTag family or usable ID range without a Product Owner decision.

# Grade Import and Grades

Grade Import is based on authoritative/raw score data.

Do not invent grade formulas.

Do not infer final grades, pass/fail status, weighted calculations, transmutation, or grading thresholds unless explicitly supported by authoritative APRISM configuration/data.

Preserve review/validation/confirmation steps where required.

A later authoritative Student Number may safely reconcile an unresolved Student only through the established explicit reconciliation workflow.

Existing canonical Student conflicts must remain blocked for manual review.

Do not perform global name-based reconciliation.

## Grade Analytics

Raw-score percentages must remain clearly represented as raw-score analytics when applicable.

Do not relabel them as final grades.

Missing grades must not automatically become zero.

# Referrals and Cases

Preserve referral provenance.

Teacher-visible Student Detail may show only referral information allowed by the existing Teacher scope.

Do not expose confidential case content through Teacher or Adviser views.

Do not invent:

- case status transitions
- due/overdue meanings
- escalation rules
- disciplinary outcomes
- risk scoring

unless authoritative system behavior already defines them.

# Risk and Intervention

APRISM does not use predictive AI risk modeling.

Do not invent:

- AI risk scores
- predictive labels
- probability of failure
- behavioral predictions
- opaque recommendation engines

Any attention/risk presentation must be based on explainable stored evidence and established deterministic rules.

# Reports and Exports

Reports must respect role scope.

Do not expose records outside the authenticated user's authorized context.

Where Excel/PDF exports exist or are implemented:

- use the same authoritative filtered data as the visible report
- preserve role scope
- preserve academic context
- do not fabricate unsupported report values

# Schedule Work

Do not derive authoritative academic context from section-name parsing.

Schedule import/capture must resolve visible evidence into authoritative fields and require review when ambiguous.

If OCR-assisted schedule capture is implemented later, it should follow:

image/PDF
→ extract visible fields
→ normalize
→ Teacher review/edit
→ authoritative resolution
→ validation/conflict checks
→ explicit confirmation

OCR is an assistance layer, not an authority.

Do not invent missing Subject, Program, Section, Room, Day, Start Time, End Time, or academic context.

# Database Safety

Do not modify canonical data merely to make a page look populated.

Before SQL changes:

- inspect actual schema
- inspect foreign keys/constraints
- understand existing indexes
- understand affected workflows
- preserve historical records

Avoid N+1 queries where an obvious safe set-based query exists.

Do not redesign the database schema solely for aesthetic implementation convenience.

Schema/migration changes require a clear functional reason.

Use transactions for multi-step mutations where partial completion would corrupt state.

Validate server-side even when client-side validation exists.

Use parameterized/prepared SQL.

Never trust request parameters merely because the UI generated them.

# Security

Preserve:

- authentication
- authorization
- CSRF protections
- session regeneration
- `must_change_password`
- configured session behavior
- scoped access controls
- safe failure responses

Do not convert an authorization failure into successful access just to remove a 403.

A controlled branded 403 page may replace raw server text while keeping the denial intact.

When changing security-sensitive paths, use `security-auditor` for independent review when useful.

# Actions and Mutations

For destructive or important actions:

- verify authorization server-side
- verify CSRF
- validate identifiers
- validate ownership/scope
- handle invalid state safely
- provide clear success/failure feedback
- avoid duplicate mutation on refresh/retry where practical

Do not use GET for destructive state changes.

Do not silently perform destructive actions merely because a test needs data.

# Error Handling

Differentiate:

- validation error
- authorization denial
- missing resource
- conflict
- unavailable data
- server/system failure

Do not collapse everything into a generic error.

Do not expose stack traces, SQL details, secrets, or sensitive internals to normal users.

# Existing Data vs Test Data

Do not assume a record is QA/test data solely because its name looks artificial.

Before deleting or modifying suspicious records, determine provenance.

Never clean canonical data automatically just because a record looks like a fixture.

Use isolated QA fixtures for destructive testing when needed.

# Testing Strategy

Match testing effort to risk.

For localized safe changes:

- lint/syntax
- targeted runtime path
- relevant authorization
- relevant validation
- inspect diff

For meaningful workflow changes, additionally verify:

- success path
- invalid input
- authorization boundary
- duplicate/retry behavior when relevant
- related detail/list state
- responsive/UI path if presentation changed

Do not launch a giant whole-system QA campaign for every feature.

Use broader regression only when shared ownership or risk justifies it.

# No False Completion

Automated checks cannot certify subjective product quality.

Do not claim:

- fully production-ready
- perfectly secure
- completely responsive
- visually polished
- all workflows verified

unless those claims are actually supported by the performed testing.

Report:

- what changed
- what behavior was preserved
- what was tested
- what remains untested
- any Product Owner decision still required

# Stop Conditions

Stop and ask for a Product Owner decision instead of guessing when:

- two authoritative requirements conflict
- domain semantics are genuinely unclear
- implementing the request would weaken authorization
- records would need destructive canonical mutation
- a new grading formula/risk rule/status rule would need to be invented
- academic context cannot be resolved authoritatively
- a configurable institutional setting is being mistaken for an immutable rule