# APRISM_IMPLEMENTATION_HANDOFF.md

# APRISM IMPLEMENTATION HANDOFF
## Authoritative Implementation Baseline
**Version:** Latest Implementation Baseline (Replaces All Previous Handoffs)

This document is the single source of truth for continuing APRISM implementation.

Assume the next implementation chat has **zero memory** of this project.

Everything below reflects verified implementation decisions only.

If something is not explicitly stated here, do not assume it.

---

# 1. PROJECT OVERVIEW

## Project Title

**APRISM: An AprilTag-Based Student Monitoring and Intervention Support System for Academic Personnel at STI College Dasmariñas**

---

## Purpose

APRISM is a centralized academic monitoring system designed for academic personnel.

It consolidates:

- Attendance
- Grade Import
- Student Monitoring
- Referrals
- Academic Records
- Guidance Support
- Reports
- Administrative Management

The project is a practical BSIT Capstone implementation focused on maintainability, usability, and realistic institutional workflows.

---

## Scope

Current user roles:

- Technical Administrator
- Academic Head
- Teacher
- Guidance Office

Explicitly excluded:

- Student Portal
- Parent Portal
- LMS
- Enrollment
- Payroll
- ERP
- AI Prediction
- Official STI Grade Submission

---

## System Philosophy

APRISM is built around:

- centralized architecture
- reusable frontend
- shared components
- minimal duplication
- maintainability
- incremental implementation

Every module should extend the shared architecture rather than creating new patterns.

---

# 2. FROZEN ARCHITECTURE

The following architectural decisions are frozen and must not be redesigned unless a genuine implementation blocker exists.

---

## Authentication

Role-based authentication.

Protected dashboards.

Session guards.

Role guards.

---

## Roles

Current roles:

- Technical Administrator
- Academic Head
- Teacher
- Guidance

---

## Teacher Responsibility Model

Teacher remains a single role.

Additional responsibilities extend Teacher.

Examples:

Teacher

↓

Teacher + Adviser

↓

Teacher + Program Head

There are no duplicate accounts.

There are no duplicate permissions.

Responsibilities extend capabilities.

---

## Adviser

Not a standalone role.

---

## Program Head

Not a standalone role.

---

## Permission Philosophy

Permissions derive from:

Role

+

Responsibilities

Never duplicate roles.

---

## Navigation Philosophy

Sidebar is dynamic.

Navigation is role-driven.

Navigation definitions live in:

```
navigation/sidebar_items.php
```

Never hardcode sidebar HTML.

---

## Shared Layout Philosophy

There is exactly one:

- Sidebar
- Navbar
- Application shell

Every module reuses them.

Never duplicate.

---

## Import Engine Philosophy

Teacher performs grade imports.

Academic Head configures academic structures.

Import engine remains shared.

---

## Identity Philosophy

One account.

One role.

Responsibilities extend permissions.

---

## Architecture Freeze

Never redesign:

- Authentication
- Roles
- Navigation
- Teacher model
- Shared layout
- Shared UI architecture
- Shared CSS philosophy

---

# 3. CURRENT IMPLEMENTATION STATUS

## Authentication

✅ Complete

- Session Guard
- Role Guard
- Login Protection
- Role Helper

---

## Shared Components

✅ Complete

- Sidebar
- Top Navbar
- Footer
- Logout Modal
- Flash Messages

---

## Shared Navigation

✅ Complete

Current supported roles:

- Technical Administrator
- Academic Head
- Teacher

---

## Shared CSS

Current shared styles:

- base.css
- layout.css
- motion.css

---

## Technical Administrator

✅ Complete

Implemented:

- Dashboard
- Users
- Audit Logs
- Database Backups
- Settings

Architecture frozen.

Visual baseline established.

---

## Academic Head

✅ Complete

Implemented:

- Dashboard
- Academic Setup
- Programs
- Sections
- Subjects
- Schedules
- Students
- Reports

Architecture frozen.

---

## Teacher

🟡 In Progress

Implemented:

- Dashboard
- Navigation
- Shared role stylesheet
- Initial dashboard

Dashboard currently undergoing visual refinement.

Remaining pages:

- My Classes
- Attendance
- Grade Import
- Referrals
- Reports

---

## Guidance

⬜ Not Started

---

## Backend

🟡 Placeholder Frontend

Backend integration deferred until frontend freeze.

---

# 4. PROJECT FOLDER STRUCTURE

Current structure (verified):

```
dashboard/

assets/
    css/
        base.css
        layout.css
        motion.css
        technical-admin.css
        academic-head.css
        teacher.css

    css/pages/
        technical-admin-dashboard.css
        technical-admin-users.css
        technical-admin-backups.css
        technical-admin-audit-logs.css
        technical-admin-settings.css

        academic-head-dashboard.css
        academic-head-academic-setup.css
        academic-head-programs.css
        academic-head-sections.css
        academic-head-subjects.css
        academic-head-schedules.css
        academic-head-students.css
        academic-head-reports.css

        teacher-dashboard.css

includes/
    components/
        head.php
        sidebar.php
        top-navbar.php
        footer.php
        logout_modal.php

    helper/
        flash_message.php

navigation/
    sidebar_items.php

config/
    app.php
    database.php

auth/
    session_guard.php
    role_helper.php
```

Do not invent additional folders.

---

# 5. SHARED DESIGN SYSTEM

## Shared (layout.css)

Contains reusable application-wide UI.

Examples:

- Sidebar
- Navbar
- Application shell
- Shared page layout
- Shared page header
- Shared page title
- Shared page metadata (planned)
- Shared buttons
- Shared modals
- Shared toasts
- Shared responsive behavior

---

## Role CSS

Technical Administrator:

Only Technical Administrator reusable styles.

Academic Head:

Only Academic Head reusable styles.

Teacher:

Only Teacher reusable styles.

Future:

Guidance receives its own reusable stylesheet.

---

# 6. SHARED UI STANDARDS (FROZEN)

These are the agreed UI standards going forward.

## Page Title

Shared.

Lives in layout.css.

Every role uses the same title sizing and spacing.

---

## Page Meta

Example:

School Year 2025–2026

•

2nd Semester

Should become shared.

Backend will eventually populate values.

---

## Panel Headers

Consistent spacing.

Consistent typography.

Same hierarchy across all dashboards.

---

## Dashboard Cards

Shared appearance:

- rounded
- subtle border
- equal spacing
- consistent height
- icon
- label
- value

Role CSS may change colors only.

---

## Empty States

Shared structure.

Shared spacing.

Shared typography.

Different icons/content only.

---

## Typography Hierarchy

Page Title

↓

Page Metadata

↓

Panel Title

↓

Card Label

↓

Card Value

↓

Table Header

↓

Body Text

This hierarchy must remain consistent.

---

## Spacing Philosophy

Large page spacing.

Consistent panel padding.

Equal dashboard gaps.

Balanced whitespace.

Avoid cramped layouts.

---

# 7. CSS REFACTORING STATUS

## Completed

Shared:

- Application shell
- Sidebar
- Navbar
- Buttons
- Modals
- Toasts
- Shared layout

Teacher CSS now includes reusable:

- Panels
- Typography
- Tables
- Buttons
- Forms
- Utilities
- Empty states
- Cards

---

## Remaining

Still candidates for centralization:

- Shared dashboard panels
- Shared dashboard cards
- Shared empty states
- Shared tables
- Shared page metadata

Only centralize after multiple modules reuse them.

---

# 8. IMPLEMENTATION RULES

These rules are frozen.

Always:

- Study first.
- Ask for files before modifying.
- Never assume existing code.
- Never redesign architecture.
- Never rewrite working systems.
- Reuse existing components.
- Extend existing architecture.
- Never duplicate layouts.
- Never duplicate sidebar.
- Never duplicate navbar.
- Provide complete replacement blocks unless partial snippets are requested.
- Modify one file at a time.
- Implement one feature at a time.
- Review after implementation.
- Wait for confirmation before proceeding.

---

# 9. CODING STYLE

PHP

- Section comment blocks.
- Consistent spacing.
- Descriptive variables.
- Vertical readability.

CSS

- Large comment sections.
- Organized by feature.
- Shared before role-specific.
- Consistent naming.

Naming

Teacher:

teacher-*

Academic Head:

academic-head-*

Technical Administrator:

technical-admin-*

Shared:

page-*

layout

toolbar

modal

etc.

---

# 10. FRONTEND DESIGN PHILOSOPHY

The UI emphasizes:

- consistency
- readability
- minimalism
- reusable components
- balanced spacing

Dashboard order:

Page Header

↓

Dashboard Cards

↓

Main Content Grid

↓

Panels

↓

Tables

↓

Empty States

Wireframe fidelity remains a priority.

---

# 11. CURRENT IMPLEMENTATION CHECKPOINT

## Current Module

Teacher

---

## Current Page

Teacher Dashboard

---

## Completed

- Shared navigation
- Dashboard layout
- Dashboard grid
- Stat cards
- Panels
- Tables
- Empty states
- Teacher reusable stylesheet
- Typography refinement
- Panel title refinement
- Dashboard visual cleanup

---

## Remaining

- Add stat card icons
- Move page metadata styling to layout.css
- Final visual polishing against Technical Administrator
- Backend placeholder replacement (future)
- Final spacing review

---

## Next Page

Teacher → My Classes

Only after dashboard polish is frozen.

---

# 12. PAIR PROGRAMMING WORKFLOW (FROZEN)

Implementation always follows this workflow:

1. Study existing implementation.

2. Request required files.

3. Understand architecture.

4. Modify only one file.

5. Return complete replacement block.

6. Review implementation.

7. Freeze.

8. Proceed only after confirmation.

Never skip steps.

---

# 13. UI CONSISTENCY RULES

## layout.css

Contains:

- shared application shell
- shared sidebar
- shared navbar
- shared buttons
- shared page title
- shared page metadata
- shared spacing
- shared layout
- shared responsive behavior

---

## Role CSS

Contains:

Only reusable components unique to that role.

Examples:

Teacher:

teacher-panel

teacher-table

teacher-card

teacher-form

Teacher CSS must never duplicate layout.css.

---

## Guidance

Future Guidance implementation must follow the same architecture:

layout.css

+

guidance.css

+

page CSS

No duplicated layouts.

---

# 14. KNOWN TODO / TECHNICAL DEBT

Current technical debt:

- Shared dashboard panels still role-specific.
- Shared tables may eventually move into layout.css.
- Shared dashboard cards may eventually move into layout.css.
- Shared empty states may eventually become global.
- Page metadata should become fully shared.
- Backend placeholders remain temporary.

These are refinements only.

They are not blockers.

---

# 15. FROZEN DESIGN SYSTEM

Current standards:

Shared:

- Page Title
- Page Metadata (planned)
- Application Shell
- Sidebar
- Navbar
- Buttons
- Shared spacing
- Shared layout

Role:

- Panels
- Tables
- Forms
- Role utilities
- Role-specific dashboard styles

The shared design system should continue expanding only when multiple modules reuse a component.

---

# 16. CURRENT VISUAL BASELINE

Technical Administrator is the current visual reference for dashboard consistency.

However:

**layout.css is the single source of truth for all shared UI components.**

Technical Administrator should guide proportions and hierarchy, not duplicate implementation.

---

# 17. CURRENT TEACHER DASHBOARD STATUS

## Completed

- Dashboard layout
- Grid
- Panels
- Tables
- Empty states
- Typography improvements
- Smaller section headers
- Reusable Teacher stylesheet
- Cleaner visual hierarchy

---

## Remaining Refinements

- Add stat card icons.
- Final dashboard spacing review.
- Move page metadata into shared layout.css.
- Final visual comparison against Technical Administrator.
- Replace hardcoded academic year placeholders with backend variables in the future.

---

## Next Step

Teacher Dashboard freeze.

↓

Teacher My Classes implementation.

---

# 18. LONG-TERM ROADMAP

Teacher

Dashboard

↓

My Classes

↓

Attendance

↓

Grade Import

↓

Referrals

↓

Reports

↓

Adviser Extension

↓

Program Head Extension

↓

Guidance Module

↓

Backend Integration

↓

Testing

↓

Final Freeze

---

# 19. THINGS THE NEXT IMPLEMENTATION CHAT MUST NOT DO

Do NOT redesign:

- authentication
- roles
- responsibilities
- navigation
- layout
- sidebar
- navbar
- shared architecture
- naming conventions

Do NOT duplicate:

- layouts
- sidebars
- navbars
- shared components

Do NOT rewrite working modules.

Only extend.

Only refine.

Only improve consistency.

---

# 20. FINAL INSTRUCTIONS FOR THE NEXT CHAT

The next implementation chat must behave like a senior software engineer.

It must:

- Preserve every frozen architectural decision.
- Never redesign working systems.
- Continue implementation incrementally.
- Ask for files whenever exact code is required.
- Never guess existing code.
- Modify one file at a time.
- Provide complete replacement blocks unless partial snippets are explicitly requested.
- Review every implementation before proceeding.
- Freeze completed work before moving to the next feature.
- Maintain consistency with the shared design system.
- Treat `layout.css` as the authoritative source for shared UI and each role stylesheet as the authoritative source for reusable role-specific UI.
- Prioritize maintainability, reuse, and consistency over speed.

---

# 21. TECHNOLOGY STACK (FROZEN)

## Frontend

- HTML5
- CSS3
- JavaScript
- Bootstrap 5

## Backend

- PHP (Procedural, helper-oriented)

## Database

- MySQL

## Development Environment

- XAMPP
- Visual Studio Code
- Git
- GitHub

This technology stack is frozen.

Do NOT redesign APRISM into:

- Laravel
- Symfony
- CodeIgniter
- React
- Vue
- Angular
- Node.js
- ASP.NET

unless explicitly instructed.

---

# 22. BACKEND ARCHITECTURE STYLE (FROZEN)

APRISM intentionally follows a procedural PHP architecture.

Current structure:

config/

auth/

dashboard/

actions/

includes/components/

includes/helper/

navigation/

Business logic should continue following existing helper-oriented patterns.

Never redesign the project into:

- MVC
- Repository Pattern
- Service Layer
- Domain Driven Design
- Clean Architecture

unless explicitly instructed.

Implementation should preserve the current helper-oriented architecture.

---

# 23. TEACHER NAVIGATION (FROZEN)

Base Teacher Navigation

Dashboard

My Classes
    • Import Schedule

Attendance

Grade Import

Referrals

Reports

Future Responsibility Extensions

Adviser

- Advisory Monitoring
- Advisory Students
- Risk Alerts
- Advisory Reports

Program Head

- Program Head Console

Important:

Program Head and Adviser are NOT separate authentication roles.

The Teacher interface remains a single adaptive frontend.

Responsibilities extend the Teacher experience without creating duplicate dashboards.

---

# 24. TEACHER WORKFLOW (FROZEN)

Teacher operational workflow:

Dashboard

↓

My Classes

↓

Import Schedule

↓

Attendance

↓

Grade Import

↓

Referrals

↓

Reports

Every future Teacher page should naturally support this workflow.

Do not rearrange the workflow unless implementation reveals a genuine blocker.

---

# 25. FRONTEND IMPLEMENTATION STATUS

Current Frontend Freeze

Technical Administrator

✅ Frozen

Academic Head

✅ Frozen

Teacher

🟡 In Progress

Current implementation focus:

Teacher Dashboard

Next implementation target:

Teacher → My Classes

Backend implementation will begin only after the Teacher frontend has been completed and the overall frontend architecture has been reviewed.

---

# 26. DATABASE IMPLEMENTATION POLICY

The database schema is intentionally NOT frozen yet.

The implementation order is:

Technical Administrator Frontend

↓

Academic Head Frontend

↓

Teacher Frontend

↓

Frontend Review

↓

Database Validation

↓

Database Freeze

↓

Backend Implementation

Do NOT redesign the database during frontend implementation unless a genuine implementation blocker appears.

---

# 27. PRIMARY IMPLEMENTATION PRINCIPLE

The architecture serves the implementation.

Implementation does NOT repeatedly redesign the architecture.

When implementation reveals a genuine blocker:

1. Investigate
2. Discuss
3. Validate
4. Freeze the decision
5. Continue implementation

Avoid speculative redesign.

Avoid architecture discussions that do not improve implementation.

---

# 28. DEVELOPMENT PHILOSOPHY

APRISM is implemented incrementally.

Every implementation session should prioritize:

- Maintainability
- Consistency
- Reusability
- Practical BSIT implementation
- Minimal technical debt
- Realistic institutional workflows

One completed feature is more valuable than multiple partially implemented features.

Implementation should always move forward in small, reviewable steps.

---

# 29. CURRENT VISUAL REFERENCE

Technical Administrator currently serves as the visual reference for:

- Dashboard proportions
- Typography hierarchy
- Visual consistency
- Component spacing

However,

layout.css remains the single source of truth for every shared UI component.

Whenever a shared component changes, every role should inherit the change instead of redefining it.

Role-specific stylesheets should contain only role-specific reusable components.

---

# 30. IMPLEMENTATION METHODOLOGY (FROZEN)

Every implementation session should follow this exact methodology.

1. Understand the requested feature.

2. Determine which file(s) are required.

3. If the exact code is unavailable, request the file first.

4. Study the existing implementation.

5. Preserve existing architecture.

6. Modify only the required file.

7. Return complete replacement blocks unless partial edits are explicitly requested.

8. Explain the purpose of the change.

9. Wait for review and confirmation.

10. Freeze the completed work.

11. Proceed to the next implementation step.

Never:

- Guess existing code.
- Rewrite working modules.
- Skip implementation steps.
- Modify unrelated files.
- Introduce architectural redesigns without necessity.

This workflow should remain consistent throughout the remainder of the APRISM project.