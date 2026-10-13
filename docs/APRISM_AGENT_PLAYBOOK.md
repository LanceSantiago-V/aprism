# APRISM Agent Playbook

This file is the shared operating guide for APRISM work across:

- Codex
- ChatGPT / CAPSTONE Project
- Product Owner review

It explains how APRISM-specific skills, specialist agents, design rules, implementation work, verification, and decision-making should work together.

This file does not replace:

- `AGENTS.md`
- `DESIGN.md`
- frozen decision documents
- authoritative implementation/database rules

Instead, it explains how to use them efficiently.

---

## 1. Canonical APRISM Context

Canonical repository:

`C:\xampp\htdocs\aprism`

Canonical Product Owner runtime:

`http://localhost/aprism/`

Canonical design constitution:

`DESIGN.md`

Primary Codex operating rules:

`AGENTS.md`

APRISM is:

**APRISM: An AprilTag-Based Attendance, Assessment and Intervention Support System for Academic Personnel at STI College Dasmariñas.**

Do not treat APRISM as a generic:

- SIS
- LMS
- ERP
- Registrar replacement
- generic SaaS dashboard

---

## 2. Working Model

APRISM work has three layers.

### Product / Technical Direction

Primarily handled through ChatGPT + Product Owner.

Responsibilities include:

- deciding what APRISM should actually do
- resolving ambiguous requirements
- reconciling manuscript, frozen decisions, implementation, and adviser feedback
- deciding whether a Codex result is a good product decision
- prioritizing remaining work
- deciding when a feature belongs in APRISM
- deciding whether a behavior is:
  - defect
  - stale documentation
  - configurable policy
  - missing requirement
  - deferred feature
  - Product Owner decision

Do not delegate unresolved product semantics to implementation agents and allow them to guess.

### Implementation / Execution

Primarily handled by Codex.

Responsibilities include:

- tracing code
- implementing PHP/CSS/JS/SQL
- debugging
- accessibility corrections
- responsive implementation
- analytics implementation
- backend workflow implementation
- targeted automated checks
- diff review
- specialist-agent delegation

### Product Owner Review

The Product Owner determines whether the result actually feels correct in the real APRISM system.

Canonical visual/runtime review happens at:

`http://localhost/aprism/`

Do not use QA or worktree rendering as a second Product Owner visual baseline.

---

## 3. APRISM Project Skills

Project skills live under:

`.agents/skills/`

### `aprism-ui-change`

Use for meaningful:

- UI/UX changes
- responsive behavior
- detail pages
- forms
- tables
- navigation
- modals
- layout
- interaction behavior
- visual hierarchy

Core design principle:

**Fluid Shell → Adaptive Workspace → Locally Constrained Content**

Use real canonical runtime evidence rather than designing from imagination.

### `aprism-feature-change`

Use for:

- PHP/backend functionality
- validation
- imports
- Attendance
- Grade Import
- AprilTag workflows
- referrals/cases
- reports
- role/scope behavior
- database-backed workflows

This skill protects APRISM domain rules.

### `aprism-analytics`

Use for:

- dashboards
- KPIs
- trends
- charts
- distributions
- analytical reports
- supporting records
- drill-down interactions

Analytics must use authoritative data only.

Never invent:

- trends
- predictions
- final grades
- pass/fail
- arbitrary thresholds
- fake metrics
- AI risk scores

Missing data is not zero.

### `aprism-verification-gate`

Use before meaningful checkpoints or after substantial implementation batches.

Goal:

**understand diff → verify risky behavior → run representative checks → checkpoint if safe**

Do not turn every task into a full-system QA campaign.

### `audit-ai-design-slop`

Use when visual work risks becoming:

- generic
- overly decorative
- repetitive
- artificial
- gimmicky
- obviously AI-generated

Use it as a review tool, not as permission to redesign unrelated screens.

---

## 4. APRISM Specialist Agents

Project subagents live under:

`.codex/agents/`

Use only the smallest useful set.

### `code-mapper`

Use when:

- workflow ownership is unclear
- route → action → helper → query → database flow must be traced
- a risky workflow spans several files

Use before editing when understanding is more important than speed.

### `php-pro`

Use for:

- PHP-specific implementation
- PHP bugs
- request handling
- server-rendered APRISM pages
- PHP application structure

### `sql-pro`

Use for:

- nontrivial joins
- analytics queries
- aggregation
- double-counting risks
- SAE/SCE reasoning
- database correctness

### `backend-developer`

Use for:

- backend workflows
- server-side validation
- persistence
- mutation logic
- error handling

### `frontend-developer`

Use for:

- larger frontend implementation
- responsive CSS
- frontend interaction
- JavaScript behavior
- page/component composition

### `fullstack-developer`

Use when a single feature genuinely crosses:

- PHP
- backend logic
- HTML
- CSS
- JavaScript

Do not use merely because both frontend and backend files exist.

### `ui-fixer`

Use for a reproduced localized UI defect such as:

- overlap
- broken spacing
- bad mobile stacking
- clipped controls
- incorrect layout
- isolated interaction defect

Prefer the smallest coherent correction.

### `anti-ui-slop-reviewer`

Use for independent review of meaningful visual work.

Look for:

- generic card walls
- pointless pills
- arbitrary glows
- excessive radius/shadows
- weak hierarchy
- fake analytics
- repetitive SaaS patterns

### `accessibility-tester`

Use when changing:

- focus
- keyboard navigation
- dialogs
- labels
- semantics
- reduced motion
- contrast
- touch interaction

### `debugger`

Use for difficult bugs where the root cause is unclear.

### `browser-debugger`

Use for:

- DOM/runtime issues
- JavaScript behavior
- browser-only failures
- focus bugs
- network/request failures
- runtime-only behavior

### `architect-reviewer`

Use for:

- major refactors
- shared architecture
- system-wide structural changes
- cross-module coherence

Do not use for small CSS fixes.

### `reviewer`

Use before important checkpoints or whenever independent correctness review is valuable.

### `test-automator`

Use when a meaningful defect or workflow deserves durable regression protection.

Do not create tests merely to increase test count.

### `security-auditor`

Use for:

- authentication
- authorization
- session behavior
- CSRF
- sensitive data
- role/scope changes
- security-sensitive mutations

---

## 5. Typical Routing

### Broken mobile screen

Use:

`aprism-ui-change`
+
`ui-fixer`

Add `accessibility-tester` if controls/focus/interaction changed.

### Generic AI-looking screen

Use:

`aprism-ui-change`
+
`audit-ai-design-slop`
+
`anti-ui-slop-reviewer`

### New PHP/backend feature

Use:

`aprism-feature-change`
+
`php-pro`

Add:

`backend-developer`

when the workflow has meaningful validation/state behavior.

### Full feature crossing frontend and backend

Use:

`aprism-feature-change`
+
`aprism-ui-change`
+
`fullstack-developer`

Use `code-mapper` first when the current workflow is unclear.

### Difficult runtime bug

Use:

`debugger`

Add:

`browser-debugger`

when the defect only appears in browser/runtime behavior.

### Complex SQL analytics

Use:

`aprism-analytics`
+
`sql-pro`

### Grade Analytics upgrade

Use:

`aprism-analytics`
+
`frontend-developer`
+
`sql-pro`

Add `anti-ui-slop-reviewer` for final visual review.

### Security/session/auth change

Use:

`aprism-feature-change`
+
`security-auditor`

### Important checkpoint

Use:

`aprism-verification-gate`
+
`reviewer`

Add specialists only according to the changed risk.

---

## 6. Design Direction

APRISM follows:

**Fluid Shell → Adaptive Workspace → Locally Constrained Content**

Use available space intelligently.

Avoid:

- universal narrow max-widths
- accidental dead whitespace
- blind edge-to-edge stretching
- fixed-height deserts
- squeezed metadata
- page-level horizontal overflow

APRISM should not become a generic SaaS template.

Avoid:

- card walls
- meaningless icon boxes
- decorative gradients
- fake KPIs
- arbitrary glows
- excessive rounded rectangles
- excessive shadows
- giant headings
- unnecessary badges
- fake dashboard density
- hero layouts
- random animations

Preserve APRISM's existing navy/yellow institutional identity.

---

## 7. Analytics Direction

APRISM analytics should be:

- truthful
- useful
- interactive where helpful
- supported by records
- responsive
- accessible
- visually modern but restrained

Preferred patterns:

### Line / Area

For:

- time
- chronology
- attendance meetings
- ordered progression

### Donut / Ring

For legitimate part-to-whole composition.

### Bar

For discrete category comparison.

### Table / Records

When exact record detail is more useful than a chart.

Do not create charts merely to fill space.

Useful interaction:

summary
→ drill-down
→ supporting record
→ exact value

Temporary destination emphasis may be used for meaningful same-page drill-downs.

Do not glow ordinary page navigation.

---

## 8. Domain Protection

Never silently redefine APRISM domain behavior.

Important standing protections include:

- Technical Administrator, Academic Head, Teacher, Disciplinary Officer are canonical auth roles
- Adviser is a Teacher responsibility extension
- Program Head remains deferred/inactive
- Student identity must not be globally merged by name
- SAE and SCE are different concepts
- Semester/teaching context and Academic Period are different
- Section names are not authoritative academic context
- Teacher scope remains enforced
- Adviser scope remains enforced
- AprilTag family is `tagStandard52h13`
- usable AprilTag IDs are `0–48713`
- AprilTag replacement/reclaim remains explicit
- Attendance policy values may be configurable
- 3 lates = 1 effective absence
- FDA uses 20% of authoritative total class hours
- Grade Import uses authoritative/raw score data
- do not invent grading formulas
- do not invent predictive risk models
- preserve confidentiality boundaries

When semantics are unclear:

**stop and escalate to Product Owner / ChatGPT rather than inventing behavior.**

---

## 9. Worktree vs Canonical Checkout

Codex may run tasks inside a Codex-managed worktree such as:

`C:\Users\santi\.codex\worktrees\...\aprism`

This is normal.

However, Product Owner runtime remains:

`C:\xampp\htdocs\aprism`

Changes inside a worktree do not automatically appear in:

`http://localhost/aprism/`

For visual/runtime Product Owner review:

- ensure the intended changes have been applied back to the canonical checkout
- do not judge localhost against unapplied worktree changes

For isolated implementation/review, worktrees are acceptable.

Do not confuse:

**worktree state**
with
**canonical localhost state**

---

## 10. Verification Strategy

Verification effort should match risk.

### Small UI fix

Check:

- affected route
- relevant responsive behavior
- syntax/diff
- shared regression only if needed

### Meaningful UI workflow

Check:

- affected route chain
- 390px / 768px / desktop where relevant
- keyboard/focus
- accessibility
- runtime behavior

### Backend workflow

Check:

- valid path
- invalid input
- authorization
- duplicate/retry behavior where relevant
- resulting list/detail state

### Security-sensitive work

Check:

- allowed path
- denied path
- CSRF/session behavior
- independent security review

### Shared/system-wide work

Use representative cross-role verification.

Do not automatically perform another entire-system screenshot campaign.

---

## 11. Physical / Hosted Verification

Do not claim these are verified through desktop simulation:

- phone camera behavior
- AprilTag physical scanning
- mobile permission behavior
- hosted-only environment behavior

Record them honestly as not tested when still pending.

---

## 12. Git Safety

Before substantial work:

- inspect Git status
- inspect relevant diff
- preserve unrelated changes

Do not:

- push
- deploy
- reset
- delete unrelated work
- broadly overwrite dirty working state

unless explicitly instructed.

Protected unrelated paths include:

- `_design_handoff/`
- `_qa_fixtures/`

Do not stage them accidentally.

---

## 13. Communication Between ChatGPT and Codex

Avoid giant prompts when project rules already exist in:

- `AGENTS.md`
- project skills
- `DESIGN.md`
- this playbook

ChatGPT should primarily specify:

- the current goal
- current Product Owner decision
- important constraints specific to the task
- which skill/agent combination is appropriate when useful
- what outcome needs review

Codex should use the permanent project configuration for the rest.

Example:

> Upgrade Grade Analytics using the APRISM analytics and UI workflows. Preserve existing grade/domain behavior, use authoritative raw-score data only, use the appropriate analytics/frontend/SQL specialists, verify the result, and stop before deployment.

Do not restate the entire APRISM architecture in every task.

---

## 14. When to Return to ChatGPT / Product Owner

Return for decision when:

- requirements conflict
- domain meaning is ambiguous
- a new metric/threshold would need invention
- a grading formula would need invention
- an authorization rule is unclear
- a configurable policy is being mistaken for a fixed rule
- documentation and implementation disagree materially
- destructive canonical data changes may be required
- a UI technically works but the product decision is questionable
- prioritization is needed

ChatGPT/Product Owner resolves the decision.

Codex implements the approved direction.

---

## 15. Completion Standard

A useful completion report should be short.

Report:

1. what changed
2. what was verified
3. any suspicious issue
4. anything not tested
5. whether ready for Product Owner review

Do not claim:

- perfect
- fully polished
- fully secure
- production-ready
- completely responsive

without evidence.

The goal is not maximum agent activity.

The goal is:

**the smallest correct set of specialists producing a coherent, truthful, maintainable APRISM result.**