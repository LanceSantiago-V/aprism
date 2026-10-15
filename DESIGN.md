# APRISM Design Constitution

**Status:** Canonical guidance for future APRISM implementation.  
**Product:** APRISM, an authenticated internal academic personnel system.  
**Scope:** Preserve the approved product identity and workflows while making presentation coherent, readable, and adaptive. This document does not authorize code changes or token normalization by itself.

## 1. Authority order

Resolve design conflicts in this order:

1. Current approved APRISM product and domain decisions, including frozen workflow and security decisions.
2. This DESIGN.md.
3. [APRISM_UI_UX_RESPONSIVE_BASELINE.md](docs/APRISM_UI_UX_RESPONSIVE_BASELINE.md).
4. [APRISM_VISUAL_SYSTEM_REFERENCE.md](docs/APRISM_VISUAL_SYSTEM_REFERENCE.md).
5. Current canonical shared components and tokens.
6. Page-specific implementation.

Generic external design guidance, Impeccable, frontend-design skills, and other design tools are advisory only. APRISM's approved identity, roles, workflows, and information hierarchy take precedence.

## 2. Core APRISM design laws

- APRISM is an internal academic personnel system, not a marketing or generic SaaS website.
- Preserve STI/APRISM's navy-blue and yellow identity. Retain the current color roles until their ownership is reviewed.
- Technical Administrator is the strongest shared visual anchor for craft and common patterns. It is not a layout template for every role.
- Roles share one product language and may use role-specific composition when their work differs.
- Information hierarchy, real workflow context, and task clarity come before decoration.
- Show real data and states only. Never fabricate analytics, status, urgency, or risk.
- Keep information compact but readable; preserve clear labels, identifiers, status text, and useful context.
- Use restrained radii, shadows, motion, and decoration. Preserve current successful page-specific treatments.

## 3. Workspace law

### FLUID SHELL → ADAPTIVE WORKSPACE → LOCALLY CONSTRAINED CONTENT

The application shell should respond to the available viewport. Within it, each workspace should use available room when that helps its task, while readable content keeps its natural measure.

**Intentional whitespace** supports grouping, hierarchy, readability, or visual rest.

**Useful expandable workspace** is room that tables, reports, dashboards, charts, imports, and other data workspaces can use as the viewport grows.

**Readability constraint** keeps forms, prose, narrow controls, helper text, and similar content from expanding merely because space exists.

**Accidental dead space** is unused room caused by an arbitrary max-width, fixed page height, weak composition, or a workspace that stops growing without a task-based reason.

Rules:

- Use desktop and laptop width and height intelligently. Let data-heavy regions grow where the task benefits.
- Keep content and rows at their natural height. Do not stretch controls, cards, or rows to fill a viewport.
- A coherent workspace may occupy useful vertical room. Pagination or a footer may settle toward its bottom when that improves the workspace.
- Keep sparse content content-aware. Equal-height related panels may align in a desktop row when the composition benefits; stacked or narrow layouts return to natural height.
- Eligible full-page record surfaces derive page capacity from the usable workspace and complete rendered item size; resolve that capacity before revealing the final record set.
- Avoid giant blank areas outside the composition, universal max-width rules, and fixed page heights unless functionally required.
- Prevent page-level horizontal overflow. A deliberately contained table scroller is not page-level overflow.
- At tablet and phone widths, recompose the task instead of simply shrinking the desktop layout.

This law follows the inspected shell, the viewport-aware workspace precedent in the UI/UX baseline, and the approved sparse-workspace decision. It does not make the Database Backups minimum height a global rule.

## 4. Responsive law

Responsive behavior is fluid between breakpoints; it is not a collection of designs for exact device widths. Compose intentionally around 1920, 1600, 1440, 1366, and 1280 CSS px, and verify representative narrower layouts.

Use intrinsic sizing, wrapping flex layouts, grid and minmax, clamp where justified, content-driven breakpoints, and container-aware behavior where they fit the existing component. Do not add a breakpoint without an observed composition need.

Tablet and phone layouts prioritize information order, readable stacking, usable touch targets, preserved primary actions, safe table/card behavior, and freedom from clipping or accidental overflow. Prefer reordering or recomposition over shrinking readable text or controls. Do not force desktop viewport-height workspace sizing onto narrow stacked layouts.

## 5. Approved owner decisions

These decisions are approved guidance for the current stable APRISM system:

1. **Teacher Attendance at 390px:** Preserve every current field and recompose records as labeled stacked cards. Do not hide fields or infer a secondary-field priority.
2. **Grade Import at 390px:** Place the operational-class selector before its related contextual information; use a readable single-column arrangement.
3. **Class List at tablet width:** Contained horizontal table scrolling remains acceptable unless later evidence shows that it harms the task. Do not create page-level horizontal overflow.
4. **Brand colors:** Preserve current colors. Map semantic and design ownership before any consolidation.
5. **Semantic state colors:** Preserve current mappings and text labels until a dedicated state-color review.
6. **Keyboard focus:** Preserve current focus treatments until a cross-surface review establishes a canonical mapping. Keep focus visible and keyboard operation usable.
7. **Database Backups:** Preserve its approved page-specific surface and radii, including the 30px outer card and 18px table wrapper. Do not force the common panel radius onto it.
8. **Sparse workspace height:** Preserve adaptive wide-screen workspace behavior. Stacked and narrow layouts use natural content height; do not generalize a page-specific minimum height.
9. **Titles and actions:** New titles follow established Title Case conventions. Action labels use clear, context-appropriate language. Do not rewrite existing copy only to normalize casing.

## 6. Design tokens

The names below are semantic documentation candidates, not CSS variables created by this document. Values are observations from the extraction report and current CSS/runtime; declarations may be shadowed in the cascade.

**Status meanings**

- **CANONICAL NOW** — a current shared value or pattern is established in its stated role; this does not make it universal.
- **CANDIDATE FOR CONSOLIDATION** — repeated or near-repeated values may be mapped later after cascade and appearance checks.
- **INTENTIONAL VARIANT** — retain a component-, role-, or page-specific value.
- **NEEDS FUTURE REVIEW** — ownership, meaning, or cross-surface behavior is not settled.

| Proposed semantic token | Currently observed value(s) | Status and evidence |
|---|---|---|
| color.brand.navy / color.brand.blue | STI navy #002448; sidebar #003366; shared navy #06396b; content heading #123b5d; blues #005596, #075aa2, #356b8e | **NEEDS FUTURE REVIEW.** Preserve the blue family; map shell, content, and action roles before consolidation. Extraction §5; base.css, app-system.css, aprism-consolidation.css. |
| color.brand.accent | Active sidebar #ffc72c; shared system yellow #ffd400; local yellow focus/accent #f2c94c | **NEEDS FUTURE REVIEW.** Yellow is a defining identity signal, but these values and uses are not interchangeable. Extraction §5; approved decision 4. |
| color.surface.canvas | Runtime #f4f7fa; source alternatives #f4f6fa and #f3f7fb | **CANDIDATE FOR CONSOLIDATION.** Keep the current rendered result while checking all cascade consumers. Extraction §5. |
| color.surface.default / color.surface.soft | White #ffffff; soft surfaces #f7fafd, #f8fafc, #f6f9fc, #fafbfc | **CANONICAL NOW** for white primary work surfaces; **INTENTIONAL VARIANT** for quiet/table/modal surfaces. Extraction §5. |
| color.text.primary / color.text.heading | Body #102d4b; heading #123b5d; local/vendor alternatives #172b4d and #212529 | **CANONICAL NOW** for the common APRISM body and heading roles; keep local/vendor behavior scoped. Extraction §5. |
| color.text.muted | #64748b, #475569, #8fa0b5, #637487 | **NEEDS FUTURE REVIEW.** Multiple contrast levels exist; do not collapse them without role and contrast review. Extraction §5. |
| color.border.default | Common panel #dbe5ef; alternatives #d9e3ec, #dbe4ee, #d8e0ea, Bootstrap #dee2e6 | **CANDIDATE FOR CONSOLIDATION** for exact duplicates; preserve distinct computed borders until audited. Extraction §5. |
| color.state.success / warning / danger / info | Success #217a5a, #079669, #198754; warning/attention #92400e, #d97706, #ffc107; danger #b43c4b, #e94560, #dc3545; info Bootstrap #0dcaf0 family and APRISM blues | **NEEDS FUTURE REVIEW.** Preserve current mappings and accompanying text labels. Brand yellow is not automatically warning. Extraction §5; approved decision 5. |
| color.focus.indicator | rgba(13,110,253,.55); rgba(7,90,162,.26) outline and .1 shadow; Grade Import rgba(242,201,76,.6) | **NEEDS FUTURE REVIEW.** Retain current component treatments and visible keyboard focus until cross-surface review. Extraction §5; approved decision 6. |
| type.family.interface / type.family.technical | Inter; JetBrains Mono | **CANONICAL NOW.** Runtime confirms Inter for the interface; JetBrains Mono is the declared technical family. Extraction §6. |
| type.body / type.page-title / type.section-title | Body 16px/24px/400; title clamp(1.45rem, 2vw, 1.9rem); section title 1.25rem | **CANONICAL NOW** for shared roles; retain explicit page exceptions. Extraction §6. |
| type.label / type.helper / type.table / type.kpi | Labels about .62–.8rem at 700–800; helper about .7rem/600; sampled table headings 9.76–10.4px/800; KPI values vary by dashboard | **INTENTIONAL VARIANT.** Keep compact roles readable; no universal normalization is approved. Extraction §6. |
| space.scale.1–6 | .5rem, .75rem, 1rem, 1.25rem, 1.5rem, 2rem | **CANDIDATE FOR CONSOLIDATION.** The shared scale exists, but adoption is incomplete. Extraction §7. |
| layout.gutter.page | 24px wide desktop; about 20px at 1024, 15px at 768, and 11px at 390 | **CANONICAL NOW** as observed responsive shell behavior; retain local page gutters where required. Extraction §7. |
| space.surface / space.section / space.form / space.table | Surface padding commonly 2rem desktop and 1.25rem mobile; sections about 1–1.5rem; Grade Import field gap 1rem and label/control gap .45rem; table cells commonly about .55rem | **INTENTIONAL VARIANT.** These are role- and component-dependent values, not one global spacing rule. Extraction §7. |
| radius.panel / radius.control | Common panel 12px; common controls about 9px; compact buttons 8px; active sidebar 10px; topbar 14px desktop and 12px phone | **CANONICAL NOW** for the common panel role; **INTENTIONAL VARIANT** for controls and navigation. Extraction §8. |
| radius.modal / radius.backup | Modal examples 16px, 24px, and legacy values up to 30px; Backup outer card 30px and table wrapper 18px | **INTENTIONAL VARIANT.** Preserve Database Backups treatment; modal variants remain scoped. Extraction §8; approved decision 7. |
| border.panel / shadow.panel | Common panel border 1px #dbe5ef; shadow 0 4px 14px rgba(15,45,75,.055) | **CANONICAL NOW** for shared panels. Overlays may use stronger component-specific shadows. Extraction §8. |
| control.height.default / compact / touch | 40px common; 34px compact; 42px fields; 44–46px touch/action overrides | **CANONICAL NOW** for the common control baseline; **INTENTIONAL VARIANT** for compact and touch contexts. Extraction §15. |
| layout.sidebar.width / layout.main.cap | Sidebar 280px desktop; current main wrapper cap 1680px; local profile cap 1180px | **CANONICAL NOW** for current shell geometry, not a universal content max-width; **INTENTIONAL VARIANT** for local readable measure. Extraction §15. |
| workspace.backup / workspace.dashboard-kpi | Backup history about 390px wide-screen minimum with a flexing table and bottom-aligned pagination; sampled Technical Admin KPI 178px and Adviser stat 112px | **INTENTIONAL VARIANT.** Keep local workspace behavior and role-specific KPI sizing; do not generalize dimensions. Extraction §§7, 15; approved decision 8. |
| responsive.breakpoint.shared / responsive.breakpoint.local | Shared shell/layout thresholds around 1200, 768, and 576px; page thresholds include 1100, 767/768, 700, and other local values | **NEEDS FUTURE REVIEW.** Use breakpoints for observed reflow needs; do not flatten them into a universal device list. Extraction §§11, 14. |
| motion.duration / motion.reduced | Existing durations 120ms, 200ms, 240ms, and 320ms; reduced-motion handling is present | **INTENTIONAL VARIANT** for task-specific transitions; reduced-motion support is **CANONICAL NOW**. Extraction §15 and motion.css. |

Do not create or change CSS variables under this constitution alone. Exact-value aliases may be appearance-preserving only after confirming the winning cascade and comparing the same rendered states. Similar-looking colors, radii, breakpoints, or spacing values are not proof of equivalent meaning.

## 7. Component constitution

Classifications describe the shared contract and the permitted variation. They do not require every page to have the same composition.

| Area | Constitution | Classification |
|---|---|---|
| Application shell and sidebar | Keep role-aware navigation, shared shell hierarchy, responsive off-canvas behavior, and the established navy/yellow identity. Keep page content in the flexible main workspace. | **CANONICAL SHARED PATTERN**; navigation contents are **ROLE-SPECIFIC COMPOSITION**. |
| Page and section headers | Use a clear page title, useful context, and nearby primary action. Preserve the shared title hierarchy while allowing workflow-specific actions and metadata. New titles use Title Case. | **CANONICAL SHARED PATTERN** with **INTENTIONAL VARIANT**. |
| Buttons | Use clear action language and APRISM hierarchy. Preserve control purpose and workflow context; do not use color or icon alone to communicate an action. | **CANONICAL SHARED PATTERN** with compact/touch **INTENTIONAL VARIANTS**. |
| Forms and filters | Associate labels and controls, group related fields, retain readable measures, and place local validation beside the problem. Narrow forms recompose in the user's task order. | **CANONICAL SHARED PATTERN**; field order is **ROLE-SPECIFIC COMPOSITION**. |
| Panels and general cards | Use restrained borders/elevation and natural content height. Related panels may align on wide screens when helpful; stacked panels return to natural height. | **CANONICAL SHARED PATTERN** with approved **INTENTIONAL VARIANTS** such as Database Backups. |
| KPI cards | Present only real, traceable measures with clear labels. Use role-appropriate density and do not fabricate comparisons, trends, or risk. | **ROLE-SPECIFIC COMPOSITION** using shared surface language. |
| Data tables | Preserve meaningful identifiers, readable column hierarchy, and useful comparison. Align text-heavy names, identifiers, filenames, and descriptions left; compact badges, statuses, numeric metadata, and action controls may be centered. Do not center every column by default. Use compact data presentation without shrinking text until unreadable. | **CANONICAL SHARED PATTERN** with page-specific column and density variants. |
| Responsive tables and cards | Choose a deliberate contained scroller, priority-column strategy, or labeled-card recomposition based on the task. Keep page-level overflow absent. Attendance at 390px retains all fields in labeled stacked cards. Class List may use contained horizontal scrolling at tablet width unless task evidence shows harm. | **INTENTIONAL VARIANT** governed by the approved responsive decisions. |
| Pagination | Keep it associated with its table/workspace and understandable after filtering. It may settle at a workspace bottom where that workspace intentionally expands; do not add fixed page height to force placement. | **CANONICAL SHARED PATTERN** with workspace-specific placement. |
| Badges and status | Pair semantic color with text or another non-color cue. Preserve existing state mappings until reviewed; do not treat brand accent as a status mapping automatically. | **CANONICAL SHARED PATTERN**; color values **NEED FUTURE REVIEW**. |
| Tabs | Preserve clear selected state, keyboard focus, and local workflow order. Grade Import context follows the class selector at 390px. | **CANONICAL SHARED PATTERN** with **ROLE-SPECIFIC COMPOSITION**. |
| Modals | Use for focused decisions or contained tasks; keep content/actions reachable, keyboard behavior usable, and scrolling within the dialog when needed. Preserve approved page-specific variants. | **CANONICAL SHARED PATTERN** with **INTENTIONAL VARIANT**. |
| Toasts and warnings | Use a top-right toast for transient user-triggered feedback. Keep persistent blockers and warnings beside the affected workflow. | **CANONICAL SHARED PATTERN**. |
| Inline validation | Identify the affected field or record, explain the correction, and retain safe input where appropriate. | **CANONICAL SHARED PATTERN**. |
| Empty, loading, and error states | Distinguish empty, filtered-empty, unavailable, loading, blocked, and failed states. Explain the next safe action when one exists. | **CANONICAL SHARED PATTERN** with workflow-specific messages. |
| Charts and reports | Use real data and labels; give charts enough useful room without distorting them. Keep report filters and scope clear, and let data-heavy report workspaces expand where useful. | Shared patterns with **ROLE-SPECIFIC COMPOSITION**. |
| Import and review workspaces | Keep source, selected context, validation, preview, and next actions understandable in the real workflow order. Preserve review and confirmation boundaries; do not imply persistence before it succeeds. | **ROLE-SPECIFIC COMPOSITION** using shared controls, tables, and feedback. |

## 8. Feedback and copy

- Use plain, human wording and clear action verbs. Avoid decorative filler copy.
- Use Title Case for new titles in keeping with established APRISM titles. Choose action labels for the outcome and context. Do not rewrite existing copy just to normalize casing.
- Use a top-right toast for transient user-triggered success or error feedback.
- Keep persistent blockers, warnings, and workflow-specific messages inline near the affected work.
- Put validation close to the problem and explain a safe correction.
- Never report success before the relevant persistence or confirmation succeeds.
- Confirm only destructive, sensitive, or high-impact actions.
- Empty states should say what is absent and help users understand what they can do next.
- Preserve text labels for states; color alone does not carry meaning.
- Live search updates the visible result set from the current query, keeps unrelated filters, and shows a clear-search control only while a query exists; use suggestions only when the current data source can provide useful matches without a disruptive loading state.
- Large paginated record sets should provide direct first/previous/next/last navigation where useful and preserve the active query, filters, and page-size state across pages. Icon-only pagination controls need accessible names and hover/focus tooltips.
- Display institutional date/time values in the configured IANA timezone (`Asia/Manila` for APRISM); follow the actual storage/session contract and never apply an assumed numeric offset to stored values.
- Icon-only controls need an accessible name and a concise tooltip on hover and keyboard focus when nearby visible text does not already explain the action.
- After a successful action returns the user to a specific visible affected record, a shared temporary destination-focus cue may accompany the success toast when it improves orientation; do not apply it automatically to unrelated actions or hidden records.
- Settings sections enable Save only when their values differ from the persisted baseline; reverting edits returns the section to its clean state.
- Confirm material operational, security, or backup configuration changes with a concise review of changed values before persistence; keep server validation authoritative and do not confirm trivial preferences.
- When a section title already identifies the save context, use a consistent icon-plus-text `Save` action rather than a repetitive long label or icon-only button.
- Use brief contextual help for settings whose effects are not self-evident; help icons need accessible names and hover/focus tooltips.

## 9. Anti-patterns

Do not introduce:

- A generic SaaS makeover or a card wall for every workflow.
- Meaningless icon containers, decorative gradients, or excessive shadows.
- Excessive rounding, oversized hero/marketing headings, or unnecessary all-caps labels.
- Fake analytics, unsupported status, or decorative data.
- Arbitrary animation or motion that competes with the task.
- Forced centered composition or stretching every component to fill the viewport.
- Artificial equal-height content where the meaning does not justify it.
- A universal max-width or fixed page height without a functional reason.
- Page-specific CSS patches where a shared component should own the behavior.
- A responsive fix that clips primary actions, hides task-critical data, or creates page-level horizontal overflow.

## 10. Visual iteration and objective verification

For a meaningful UI change, use this bounded loop:

**Current runtime screenshot → identify a small set of visible defects → implement → rerender the same viewport and state → compare → correct → rerender.**

Aim for no more than about three bounded correction iterations per task unless a real blocker requires more. Check objective conditions such as overflow, collision, wrapping, alignment, responsive behavior, visible content, and interaction/state. Report the viewport and state actually inspected.

Agents may report objective verification. They must not certify subjective claims such as “perfect,” “beautiful,” “polished,” or “ready to freeze.” Final subjective approval belongs to the Product Owner/Main Chat.

## 11. External design tools

Impeccable, frontend-design guidance, and other external design tools may audit, critique, detect inconsistency, and suggest alternatives. They may not automatically redefine APRISM, redesign it, alter approved workflows, normalize intentional variants, or implement suggestions.

Classify each proposal **KEEP**, **CHANGE**, **IGNORE**, or **TEST** before implementation. Check it against the authority order and the current runtime evidence.

## 12. Supporting evidence

This constitution records the approved current design and rules for future work; it does not replace the deeper references or product decisions.

- Runtime and extracted current values: C:\xampp\aprism-design-extraction\APRISM_DESIGN_EXTRACTION_REPORT.md
- Owner-approved decisions: C:\xampp\aprism-design-extraction\OWNER_DECISION_SHEET.md
- Responsive and accessibility rules: [APRISM_UI_UX_RESPONSIVE_BASELINE.md](docs/APRISM_UI_UX_RESPONSIVE_BASELINE.md)
- Shared visual reference and role-specific composition: [APRISM_VISUAL_SYSTEM_REFERENCE.md](docs/APRISM_VISUAL_SYSTEM_REFERENCE.md)
- Frozen product/domain decisions remain authoritative under section 1 and their existing project documents.

The two existing UI guidance documents remain in place as supporting material.

