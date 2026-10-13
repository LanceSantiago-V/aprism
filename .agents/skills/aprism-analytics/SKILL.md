---
name: aprism-analytics
description: Design, implement, refine, or review APRISM dashboards, charts, KPIs, trends, distributions, drill-downs, reports, and analytical interactions using only authoritative APRISM data. Use for analytics-related UI, queries, chart behavior, supporting records, responsive analytics, and truthful data visualization.
---

# APRISM Analytics

Use this skill for analytics, dashboards, charts, KPIs, distributions, trends, drill-downs, analytical reports, and data-summary experiences in APRISM.

Canonical project:

`C:\xampp\htdocs\aprism`

Canonical Product Owner runtime:

`http://localhost/aprism/`

Also use:

- `DESIGN.md`
- `aprism-ui-change`
- `aprism-feature-change`

when the task crosses visual or backend/domain behavior.

# Core Principle

APRISM analytics must answer useful questions using authoritative system data.

Analytics are not decoration.

Every chart, KPI, ranking, summary, distribution, and trend must have a clear user purpose.

Prefer:

**summary → explanation → supporting records → action/context**

Do not add a visualization merely because space exists.

# Truthfulness

Never invent:

- records
- trends
- historical values
- thresholds
- benchmarks
- predictions
- risk probabilities
- AI scores
- final grades
- pass/fail labels
- disciplinary outcomes
- projected attendance
- synthetic comparison groups

unless the underlying APRISM system explicitly stores or authoritatively derives them.

If the required data does not exist, show an honest unavailable/empty state instead.

## Missing Data Is Not Zero

Never silently convert missing observations into zero.

Examples:

- a meeting with no attendance outcomes is not a zero-attendance meeting
- a missing grade is not automatically score `0`
- no referral data is not the same as zero institutional concern
- unavailable class-hour totals must not be treated as `0`

Preserve these distinctions visually and mathematically.

# APRISM Analytics Visual Direction

The established APRISM analytics direction is:

- modern
- restrained
- professional
- clean
- data-first
- interactive where interaction adds meaning
- visually compatible with the APRISM navy/yellow shell
- not a generic SaaS dashboard

Preferred neutral analytical accents may include:

- blue
- cyan
- indigo
- violet

Use semantic colors only when their meaning is legitimate:

- green for valid positive/success states
- amber for warning/attention states
- red for valid critical/error/problem states

Do not create rainbow analytics.

Do not rely on color alone for meaning.

# Preferred Chart Types

## Line / Area Charts

Use for:

- attendance over meetings
- activity over time
- chronology
- ordered progression
- historical trends

Use subtle gridlines.

Provide exact values through tooltip/focus/tap interaction.

Do not fake continuity where data points are missing.

## Donut / Ring Charts

Use for legitimate part-to-whole composition such as:

- Present / Late / Absent composition
- categorical composition where categories form a meaningful total

Do not use donuts for arbitrary unrelated categories.

Keep category count small enough to remain readable.

## Bar Charts

Use when comparing discrete categories is clearer than a trend line.

Avoid default-looking generic Chart.js bars.

Do not use bars merely because they are easy to implement.

## Tables / Records

Use tables or structured records instead of charts when:

- precise record comparison matters
- user actions occur at record level
- there are many categories
- chart abstraction would hide important context

Charts must not replace useful detailed records.

# Interaction Model

Analytics interactions should help the user connect summary data to evidence.

Useful patterns:

- hover → temporary tooltip/exact value
- keyboard focus → equivalent exact value
- tap → exact value on touch
- click/tap → persistent selection where useful
- selected chart point/category → highlight corresponding supporting records
- reset selection → clear the analytical filter/highlight

Important values must never be available only through hover.

# Destination Emphasis

Temporary destination emphasis is appropriate when:

- KPI → detailed section on the same page
- chart point → corresponding meeting/record
- category → supporting record group
- summary → deeper subsection

The emphasis should:

- be subtle
- identify the exact relevant destination
- clear automatically
- respect reduced-motion preferences
- never repeatedly blink
- never leave a persistent browser outline

Do not apply analytics-style glow to ordinary page navigation.

# Supporting Evidence

Whenever practical, analytical summaries should be accompanied by supporting records.

Example:

Attendance trend
→ selected meeting
→ exact Present/Late/Absent values
→ supporting meeting record

Avoid standalone charts that cannot be explained or verified by the user.

# Teacher Analytics

Teacher analytics must remain scoped to classes the Teacher is authorized to access.

Potential legitimate analytical areas include:

- class attendance chronology
- attendance composition
- grade-data completeness
- raw-score distributions
- students requiring review based on established factual evidence
- class participation/record coverage
- meeting-level detail
- report summaries

Do not invent predictive student risk.

Do not expose other Teachers' classes.

# Teacher Class Analytics

`teacher_analytics.php` represents broader selected Operational Class analytics.

Preserve the established direction:

- class-context filters
- attendance-over-meetings trend
- attendance composition
- supporting meeting records
- grade-data completeness
- objective review evidence

Meetings without outcomes may remain visible as records but must not be plotted as zero-value observations.

Class Analytics is distinct from Grade Analytics.

# Grade Analytics

Grade Analytics is a specialized grade-focused workspace.

Use authoritative raw-score data.

Do not invent:

- final grades
- transmutation
- passing marks
- weighted calculations
- grade predictions

unless APRISM explicitly stores/configures them.

Clearly label raw-score percentages as raw-score analytics when applicable.

Missing scores remain missing/unavailable.

Useful legitimate views may include:

- category/assessment score distribution
- grade-record completeness
- student raw-score comparison
- assessment coverage
- authoritative grading-period context

College classes must show applicable College periods.

SHS classes must show applicable SHS context.

Do not mix incompatible academic periods.

# Attendance Analytics

Use stored attendance outcomes:

- Present
- Late
- Absent

Respect configured Attendance policies.

Do not infer attendance outcomes from missing data.

Effective absences may use the established rule:

**3 lates = 1 effective absence**

FDA-related presentation may use:

**20% of authoritative total class hours**

only when authoritative total class hours exist.

Do not fabricate hours to produce a percentage.

# Adviser Analytics

Adviser analytics must remain limited to assigned advisory scope.

Useful legitimate areas may include:

- attendance-related advisory evidence
- referral provenance visible to Adviser scope
- recent-record timelines
- objective attention indicators
- advisory summary distributions supported by real stored data

Do not expose confidential disciplinary-case content.

Do not turn Adviser into a separate authentication role.

# Academic Head Analytics

Academic Head analytics may use authoritative institutional academic context.

Potential legitimate distributions include:

- Program
- Year Level
- Section
- School Year
- applicable academic context
- institutional attendance/report summaries

Use stored structured fields.

Never derive these distributions by parsing Section names.

Avoid charts where the number of categories makes them unreadable.

# Disciplinary Officer Analytics

Use only authoritative referral/case/follow-up information within established scope.

Potential legitimate views include:

- case counts by stored status
- referral trends over time
- follow-up activity
- assigned-case distribution
- report summaries

Do not invent:

- overdue semantics
- due-date rules
- severity scores
- case risk scores
- escalation probabilities

If those meanings are not already authoritative, mark them as requiring Product Owner decision.

# Technical Administrator

Do not force academic analytics onto Technical Administrator pages.

Technical Administrator is primarily operational.

Legitimate operational summaries may include existing factual system/account/security/audit information when useful.

Avoid decorative dashboards that do not help system administration.

# KPI Design

A KPI must answer a real question.

Each KPI should have:

- a clear label
- an authoritative value
- understandable scope
- meaningful context where needed

Avoid:

- giant numbers without context
- fake percentage change
- invented trend arrows
- decorative sparklines
- arbitrary green/red coloring
- metrics whose meaning is unclear

If a KPI is actionable and a detailed section exists on the same page, it may act as a drill-down.

# Filtering

Filters must map to real authoritative fields.

Potential filters include:

- Operational Class
- date range
- grading/academic period when applicable
- established scope fields

Do not create filters whose values are derived from unreliable text parsing.

Changing filters must update:

- summaries
- charts
- supporting records

consistently.

# Empty States

Analytics empty states must distinguish:

- no records yet
- no records in current filter
- unavailable calculation
- zero factual count
- insufficient authoritative data

Do not show an empty chart frame when a concise explanatory state is clearer.

# Tooltips

Tooltips should show exact useful values.

Keep them concise.

For time-based data, include enough context to identify the point.

Do not hide critical meaning in a tooltip.

Touch and keyboard users must have equivalent access.

# Accessibility

Charts must support understanding without color alone.

When practical:

- provide textual values
- provide supporting records
- use labels/icons/pattern differences appropriately
- support keyboard focus
- maintain visible focus
- preserve semantic chart titles/descriptions
- respect reduced motion

Do not make hover the only way to obtain values.

# Responsive Analytics

Analytics must adapt rather than simply shrink.

On mobile:

- stack analytical cards appropriately
- preserve exact values
- avoid unreadably compressed axes
- allow contained chart scrolling only when genuinely necessary
- keep horizontal scrolling inside the chart/data frame
- never force page-level horizontal overflow
- avoid massive vertical cards caused by desktop dimensions
- keep legends readable
- preserve touch interaction

Short datasets should not create unnecessary scrolling.

Long datasets may use contained scrolling when that is the clearest solution.

# Performance

Prefer scoped assets.

Do not load a heavy chart library site-wide if only specific analytics routes use it.

Reuse existing APRISM analytics components where appropriate.

Avoid duplicate queries when existing authoritative result sets can safely supply the visualization.

Avoid obvious N+1 query patterns.

Do not sacrifice correctness for premature optimization.

# Query Safety

When creating analytics queries:

- preserve role scope
- preserve academic context
- use authoritative joins
- understand SAE/SCE distinctions
- avoid double counting
- handle missing records explicitly
- verify aggregation boundaries
- inspect query behavior on empty and partial data

Use `sql-pro` when aggregation semantics are nontrivial.

# Reuse

Reuse the current APRISM analytics foundation when it fits:

- shared analytics chart CSS
- shared interaction behavior
- tooltip behavior
- selection/reset behavior
- destination-focus integration

Do not force every analytics page into identical layouts.

Shared behavior should be consistent; information architecture may differ according to the task.

# Avoid Generic AI Analytics

Use `audit-ai-design-slop` when useful.

Reject patterns such as:

- four arbitrary KPI cards followed by random charts
- meaningless mini-sparklines
- identical card grids on every role
- gradients for decoration
- excessive icon circles
- random percentages
- trend arrows with no historical comparison
- fabricated "insights"
- fake recommendation text
- oversized donut charts for tiny datasets
- charts where a simple value/table is more useful
- dashboard layouts copied mechanically across roles

APRISM analytics should look intentionally designed for academic personnel workflows.

# Validation Workflow

For a meaningful analytics change:

1. identify the user question the analytics should answer
2. identify the authoritative data source
3. confirm role/scope
4. confirm academic context
5. inspect missing/partial-data behavior
6. choose the clearest visualization or record view
7. implement
8. verify exact values against supporting records/query results
9. verify interaction
10. verify representative responsive behavior
11. verify accessibility
12. review for AI-slop/generic-dashboard patterns

Do not create synthetic canonical data merely to make the visualization populated.

# Stop Conditions

Stop and request Product Owner clarification when:

- the requested metric has no authoritative definition
- a threshold would need to be invented
- risk/prediction semantics are requested without an established rule
- grade meaning is ambiguous
- College/SHS applicability is unclear
- due/overdue meaning is not authoritative
- a chart would require guessing missing data
- two legitimate data sources disagree

Do not hide ambiguity behind a polished visualization.