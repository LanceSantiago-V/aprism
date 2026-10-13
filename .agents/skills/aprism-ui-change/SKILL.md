---
name: aprism-ui-change
description: Implement or refine APRISM UI/UX, responsive behavior, detail pages, forms, tables, dashboards, navigation, modals, states, and visual interactions while preserving APRISM domain behavior and the established design system. Use for meaningful APRISM frontend or visual work.
---

# APRISM UI Change

Use this skill for meaningful UI/UX work in APRISM.

Canonical project:

`C:\xampp\htdocs\aprism`

Canonical Product Owner runtime:

`http://localhost/aprism/`

The canonical design constitution is:

`DESIGN.md`

Supporting references when needed:

- `docs/APRISM_UI_UX_RESPONSIVE_BASELINE.md`
- `docs/APRISM_VISUAL_SYSTEM_REFERENCE.md`

Read `DESIGN.md` before substantial visual work.

## Core Layout Principle

APRISM follows:

**Fluid Shell → Adaptive Workspace → Locally Constrained Content**

Interpret this as:

- use available viewport space intelligently
- let the application workspace expand naturally
- constrain forms, prose, metadata, and narrow reading content locally
- allow tables, analytics, records, and data-heavy workspaces to use more width when useful
- avoid arbitrary universal max-width rules
- avoid accidental empty deserts
- avoid blind edge-to-edge stretching
- avoid brittle fixed heights
- sibling panels in the same desktop row may stretch to equal height naturally
- stacked mobile panels return to content-driven height

## Preserve APRISM Identity

Do not turn APRISM into a generic SaaS template.

Preserve the established APRISM shell, navy/yellow identity, typography hierarchy, navigation behavior, and institutional character unless the Product Owner explicitly requests a system-wide change.

Avoid:

- generic AI card walls
- excessive nested cards
- meaningless icon boxes
- decorative gradients with no role
- excessive shadows
- excessive border radii
- oversized headings
- excessive all-caps
- fake analytics
- fake metrics
- decorative sparklines
- unnecessary glass effects
- repeated glow effects
- arbitrary animation
- marketing-site hero layouts
- excessive centered content
- unnecessary badges or pills
- decorative whitespace that harms information density

Use the installed `audit-ai-design-slop` skill when a design looks generic, excessive, artificial, or visually confused.

## Evidence-Driven Visual Work

Do not redesign from imagination when the real route already exists.

For meaningful UI changes:

1. inspect the current canonical localhost route
2. identify the smallest set of visible/root-cause defects
3. inspect the owning HTML/PHP/CSS/JS
4. implement the smallest coherent correction
5. inspect the same runtime again
6. correct remaining target defects only
7. stop after about three bounded correction iterations unless explicitly asked to continue

Do not create standalone concept HTML, screenshot galleries, mockup decks, or visual comparison packages unless explicitly requested.

Do not use `aprism_qa` as the Product Owner visual baseline.

## Route Scope

A reachable destination counts as part of the product.

When a requested workflow includes secondary routes, inspect the relevant reachable chain:

page → card/action → row action → detail route → modal/state → filtered destination

Do not automatically launch a whole-system sweep for a localized task.

For example, if fixing My Students → View Student, inspect the actual Student Detail destination and any directly shared Adviser rendering, but do not audit unrelated roles unless the shared change can affect them.

## Responsive Behavior

APRISM must recompose rather than merely shrink.

For changed layouts, verify representative widths when relevant:

- 390px mobile
- 768px tablet
- 1366px laptop/desktop

Use wider widths when a change specifically concerns adaptive desktop use.

Check for:

- page-level horizontal overflow
- clipped controls
- squeezed metadata
- unusable tables
- giant mobile cards
- full-width buttons that should not be full-width
- awkward field order
- excessive dead space
- unreadable chart labels
- fixed-height empty areas
- collisions or overlap

Prefer CSS Grid/Flexbox, `minmax()`, `clamp()`, wrapping, and content-driven sizing over hardcoded dimensions.

## Interaction Rules

Use feedback because it improves orientation, not because it looks impressive.

Temporary destination emphasis is appropriate for:

- KPI/summary → detailed section on the same page
- chart point/category → supporting record
- in-page navigation
- an explicit deep-linked subsection that would otherwise be hard to locate

Do not apply unnecessary glow to:

- normal sidebar navigation
- ordinary View/Open → new page navigation
- normal page changes where the destination is already obvious

Temporary emphasis must:

- be subtle
- clear completely
- never leave a persistent outline on a noninteractive container
- respect `prefers-reduced-motion`
- preserve legitimate `:focus-visible` keyboard focus
- never blink repeatedly

## Navigation

Use the established APRISM page-level back control.

Do not add duplicate Back buttons inside content cards when the shared top-left page control already owns page navigation.

Actions should use clear outcome-driven labels.

## Accessibility

Preserve or improve:

- semantic headings
- labels
- landmarks
- keyboard navigation
- visible keyboard focus
- modal focus behavior
- accessible dialog names
- Escape behavior where appropriate
- focus return to the opener
- contrast
- reduced-motion handling
- touch-friendly targets
- non-hover access to important values

Do not suppress real keyboard focus indicators to hide a visual bug.

## Empty and Error States

Empty states should explain the actual state and next useful action where one exists.

Keep distinct:

- no records
- unavailable data
- zero values
- filtered-empty results
- access denied
- system error

Do not represent missing data as zero.

Controlled errors such as 403/404/503 should use APRISM-branded recovery UI rather than raw server text when the application owns the response.

## Tables and Data Views

Use tables when comparison across columns matters.

On narrow screens:

- preserve important information
- recompose to readable cards/records when necessary
- do not squeeze desktop tables until they become unreadable
- keep actions understandable
- avoid page-level horizontal overflow unless the specific data workspace genuinely requires contained horizontal scrolling

## Analytics Visuals

For analytics-specific work, also use the dedicated APRISM analytics skill once installed.

Do not invent trends, predictions, thresholds, or metrics just to make the UI look richer.

## Behavior Protection

A UI task must not silently change:

- permissions
- role scope
- Teacher ownership
- Adviser scope
- Student identity behavior
- academic-context semantics
- Attendance rules
- Grade Import rules
- referral/case behavior
- AprilTag behavior
- database data

If a visual request appears to require changing product behavior, stop and identify the product decision instead of guessing.

Configurable institutional settings must not be treated as immutable constants merely because a default value exists.

## Quality Standard

A successful APRISM UI change should be:

- understandable on first use
- visually coherent with surrounding APRISM screens
- responsive
- accessible
- truthful
- information-efficient
- calm rather than decorative
- consistent without becoming mechanically uniform

Do not claim subjective outcomes such as “perfect,” “beautiful,” “production-ready,” or “fully polished” merely because automated checks passed.

Report what was changed and what was objectively verified.