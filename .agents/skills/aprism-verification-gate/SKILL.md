---
name: aprism-verification-gate
description: Run APRISM's lightweight pre-checkpoint safety review after meaningful UI, analytics, backend, security, or workflow changes. Use to inspect the diff, verify representative runtime behavior, catch accidental domain/security/data changes, run syntax/accessibility checks, and decide whether the current work is safe to checkpoint.
---

# APRISM Verification Gate

Use this skill before an important APRISM checkpoint or after a meaningful implementation batch.

Canonical project:

`C:\xampp\htdocs\aprism`

Canonical Product Owner runtime:

`http://localhost/aprism/`

Do not turn this into another full-system audit unless the change genuinely justifies one.

The goal is:

**understand the current diff → verify the risky parts → run representative checks → checkpoint only if safe**

# 1. Preflight

Before testing:

- confirm current branch
- confirm current HEAD
- inspect `git status`
- identify tracked modifications
- identify new source files
- identify staged files
- identify untracked files/directories

Protected/unrelated paths must not be staged accidentally.

Known APRISM paths such as:

- `_design_handoff/`
- `_qa_fixtures/`

must remain untouched unless explicitly requested.

Do not delete untracked material merely to make the working tree clean.

# 2. Review the Complete Relevant Diff

Review every file intended for the checkpoint.

Classify changes mentally as:

- visual/responsive
- accessibility/semantics/navigation
- analytics presentation
- controlled error/recovery UX
- functional/domain/security/data behavior

Functional/domain/security/data changes require the highest scrutiny.

Check especially:

- `auth/`
- `actions/`
- helpers
- shared components
- database queries
- session logic
- authorization checks
- Attendance actions
- Grade Import actions
- AprilTag actions
- referral/case actions

A UI-oriented checkpoint must not hide an unrelated behavioral change.

# 3. Preserve APRISM Domain Rules

Confirm the diff did not accidentally alter:

- authentication roles
- Teacher ownership
- Adviser scope
- Academic Head scope
- Disciplinary Officer scope
- Technical Administrator authority
- Student identity rules
- SAE/SCE semantics
- School Year / Semester / Academic Period meanings
- Attendance policy semantics
- Grade Import reconciliation
- AprilTag behavior
- referral/case meaning
- database schema/data

If any such behavior changed, verify that it was explicitly intended.

If not, stop before checkpointing.

# 4. Configurable Settings

Do not treat a default as a forced runtime value.

For configurable institutional settings distinguish:

- source/default value
- stored configured value
- runtime-enforced value

Examples include:

- session timeout
- Attendance Grace Period
- Attendance Absent Threshold
- Attendance Pre-start Allowance

A configured value differing from the historical/default value is not automatically wrong.

Verify the configuration path instead of resetting it.

# 5. Representative Runtime Verification

Use canonical localhost.

Do not automatically traverse every route.

Test representative routes affected by the current change.

When system-wide/shared code changed, include representative coverage for relevant roles:

- Technical Administrator
- Academic Head
- Teacher
- Adviser responsibility
- Disciplinary Officer

Depending on the change, representative surfaces may include:

- dashboard
- list/table
- detail page
- form/modal
- analytics page
- controlled error page

Do not perform destructive actions merely for verification.

# 6. Responsive Checks

For meaningful UI changes, verify representative widths:

- 390px
- 768px
- 1366px

Use wider widths only when relevant to the changed layout.

Check:

- no page-level horizontal overflow
- no clipped primary controls
- no overlapping content
- readable metadata
- usable navigation
- sensible stacking
- contained scrolling where necessary
- no giant mobile cards caused by desktop dimensions

Do not generate a screenshot package unless explicitly requested.

# 7. Accessibility Checks

Check changed/relevant areas for:

- keyboard access
- visible focus
- correct labels
- semantic headings
- dialog accessible name
- focus placement
- focus return
- Escape behavior where intended
- reduced-motion behavior
- contrast
- hover-independent access to important values

Use `accessibility-tester` when the change materially affects interaction or semantics.

Automated accessibility checks do not replace manual keyboard inspection.

# 8. Browser / Runtime Checks

Where tooling permits, check for:

- unexpected console errors
- failed application requests
- unexpected 4xx/5xx
- uncaught JavaScript errors
- broken asset loading

Use `browser-debugger` when a runtime issue cannot be understood from source inspection alone.

Do not falsely report browser instrumentation as verified if the current environment cannot provide it.

# 9. PHP / JavaScript / Diff Checks

For changed PHP:

- run PHP lint

For changed JavaScript:

- run syntax validation appropriate to the existing project

Always run:

`git diff --check`

If the project already has relevant targeted tests, run them when appropriate.

Do not introduce a huge unrelated test campaign just because tests exist.

# 10. Analytics Verification

When analytics changed, additionally verify:

- data source is authoritative
- role scope is preserved
- no invented metrics
- missing data is not converted to zero
- College/SHS context is correct
- raw scores remain raw scores
- chart values match supporting records/query results
- drill-down is presentation-only unless explicitly designed otherwise
- ordinary navigation does not receive unnecessary destination glow
- no temporary QA data was written to canonical data merely to populate charts

Use `aprism-analytics` for the detailed analytics rules.

# 11. Security Verification

When authentication, authorization, CSRF, sessions, or sensitive data paths changed:

- verify authorized success path
- verify relevant unauthorized denial
- verify server-side ownership/scope
- verify CSRF where applicable
- verify safe failure behavior

Use `security-auditor` for meaningful security-sensitive changes.

Never weaken authorization simply to make a route load successfully.

# 12. Review Agents

For meaningful checkpoints, use independent review when useful:

- `reviewer` for general correctness
- `architect-reviewer` for structural/system-wide coherence
- `security-auditor` for security-sensitive changes
- `anti-ui-slop-reviewer` for meaningful visual changes

Do not ask every agent to review every task.

Choose based on risk.

# 13. Testing Effort Must Match Risk

Localized CSS fix:
- inspect diff
- verify affected route
- responsive check if relevant
- syntax/diff check

Meaningful UI workflow:
- affected route chain
- responsive
- keyboard/accessibility
- runtime errors
- relevant shared-route regression

Backend workflow:
- success path
- invalid input
- authorization boundary
- duplicate/retry behavior where relevant
- related list/detail state

Security-sensitive change:
- authorized path
- denied path
- CSRF/session behavior
- independent security review

Shared/system-wide change:
- representative cross-role coverage

Do not run a multi-hour full-system regression for every small patch.

# 14. Physical / Hosted Limitations

Do not claim physical-device behavior is verified through desktop emulation.

Examples:

- browser-camera AprilTag scanning
- actual phone permissions
- hosted environment restrictions

Record these as NOT TESTED when they remain outstanding.

Do not block a safe local code checkpoint merely because a separate physical-device test has not yet happened, unless that device behavior is the feature being checkpointed.

# 15. Data Safety

Do not create, modify, or delete canonical records solely for verification unless the Product Owner explicitly authorizes it.

Do not assume artificial-looking records are disposable test fixtures.

Establish provenance before destructive cleanup.

Use isolated QA data for destructive regression where necessary.

# 16. Commit Decision

Checkpoint only when:

- intended diff is understood
- no accidental domain/security/data behavior exists
- relevant syntax checks pass
- relevant runtime verification passes
- no known blocker remains inside the changed scope

Stage only intended canonical source/configuration files.

Do not stage:

- temporary screenshots
- QA artifacts
- external concept files
- unrelated untracked directories
- generated debugging files

Do not push or deploy unless explicitly instructed.

# 17. Report Concisely

Return:

1. what changed
2. what was verified
3. any suspicious behavior found
4. remaining NOT TESTED items
5. whether safe to checkpoint
6. commit hash if a checkpoint was explicitly requested and completed

Do not bury the result under a giant audit report.

# 18. No False Sign-Off

Do not claim:

- production-ready
- fully tested
- perfectly responsive
- secure
- complete
- visually polished

unless the performed evidence genuinely supports that claim.

Use precise language such as:

- verified in representative localhost routes
- syntax checks passed
- responsive checks passed at tested widths
- physical camera not tested
- hosted environment not tested
- no unintended behavioral change found in reviewed diff