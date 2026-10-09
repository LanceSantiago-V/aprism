# APRISM Visual System Reference

## Purpose and authority

This concise companion records reusable visual patterns visible in the current Technical Administrator runtime and shared APRISM styles. It complements `APRISM_UI_UX_RESPONSIVE_BASELINE.md`; it does not replace or override that baseline, current runtime, frozen decisions, permissions, or page-specific workflow evidence.

Reuse the shared visual language and components. Keep each role's information architecture and page composition appropriate to its actual tasks.

## A. Reusable APRISM design system

### Shared shell and identity

- Use the existing shared sidebar, top navbar, page shell, Lucide icon set, and local Inter font.
- Preserve the established navy sidebar, yellow active navigation, light workspace canvas, and blue/navy action hierarchy.
- Keep the current location and academic context easy to identify; shared shell behavior belongs to the shared components, not individual page patches.

### Typography and color

- Use the existing hierarchy: page title, section title, body/data, labels, then muted supporting metadata.
- The shared font tokens are Inter for interface text and JetBrains Mono for data that benefits from fixed-width alignment, such as identifiers or filenames.
- Respect the existing brand tokens in `base.css` and the later shared presentation tokens in `app-system.css` / `aprism-consolidation.css`. The final cascade uses restrained navy/blue content text, a pale neutral canvas, white surfaces, muted secondary text, semantic status colors, and a light border.
- Current shared sizing includes a fluid page title, compact section headings, roughly 40px form controls, and 40–42px standard actions. Use larger touch targets where the control and viewport call for them.

### Surfaces, spacing, and actions

- Prefer white, clearly grouped work surfaces over decorative nested cards. Shared panels use a restrained border, approximately 12px radius, and subtle shadow.
- Use the existing page gutters and spacing rhythm. Let page content determine height; allocate remaining viewport space only to a bounded workspace when it helps the task.
- Use the established primary, secondary, and destructive action variants. Make the task's primary action apparent while keeping secondary actions subordinate.
- Keep icons consistent with Lucide. Icon-only actions need an accessible name; focus must remain visible. Hover/pressed feedback is subtle and must not carry essential meaning.

### Data, states, and interaction

- Use compact, readable tables with clear headers, natural-height rows, useful alignment, and a bounded wrapper. Reflow filters and action groups as space narrows; transform dense tables into labeled records only when that is clearer for the actual data.
- Keep status text meaningful and pair color with text or another cue. Use badges for real states/categories rather than decoration.
- Keep empty, error, loading, and success feedback tied to the real state. Routine success uses the established top-right toast pattern; errors explain recovery where the workflow supports it.
- Reuse existing modal, menu, pagination, search, filter, and form patterns. Preserve semantic labels, keyboard operation, focus behavior, real record counts, and existing permission/domain boundaries.

### Responsive composition

- Use fluid sizing and allow flex/grid children to shrink. Recompose before controls, columns, or panels become cramped.
- Desktop supports scanning and useful multi-column work; tablet retains multiple zones only while readable; phone uses a clear reading path, stacked controls, and labeled records where needed.
- Keep normal pages free of page-level horizontal scrolling. A contained data region is a page-specific choice, not a shared default.
- Dashboard/workspace height behavior is contextual. Registry and management pages generally use natural content height; never stretch rows, records, cards, or controls to fill space.

## B. Technical-Administrator-specific composition

The Technical Administrator Dashboard currently combines four account KPIs with a System Audit Log preview and Database Backup History, plus a dashboard refresh action. Those metrics and panels reflect that role's system-maintenance work. Do not copy that panel arrangement, KPI set, or data priorities into Academic Head or other roles.

Technical Admin Users, Audit Logs, Backups, and Settings are useful references for APRISM controls, table density, actions, feedback, and shared shell behavior. Their exact page layouts remain specific to account administration, audit review, backup management, and system configuration.

## Implementation references

- Shared identity/tokens: `assets/css/base.css`, `assets/css/layout.css`, `assets/css/app-system.css`, `assets/css/aprism-consolidation.css`.
- Shared markup/loading: `includes/components/head.php`, `includes/components/sidebar.php`, `includes/components/top-navbar.php`.
- Technical Admin reference runtime: `dashboard/technical_admin.php` and its page styles under `assets/css/pages/technical-admin-*.css`.
