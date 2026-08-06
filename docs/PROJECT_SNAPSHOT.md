You are finishing the current APRISM implementation session.

Your ONLY task is to generate a brand-new PROJECT_SNAPSHOT.md.

Do NOT review the previous snapshot.
Do NOT critique it.
Do NOT explain what should change.
Do NOT summarize the conversation.

Treat any previously uploaded PROJECT_SNAPSHOT.md as historical reference only.

Generate a COMPLETE replacement PROJECT_SNAPSHOT.md.

Carry forward only information that remains verified.
Update any outdated information.
Remove information that is no longer verified.
Mark anything uncertain as Unknown.

The output must be a complete PROJECT_SNAPSHOT.md ready to paste into the repository.

If verified implementation is insufficient, stop and ask only for the minimum additional information required.

Use exactly the following structure:

# APRISM Project Snapshot

## Snapshot Integrity Notice

## Conversation Continuity
- Current Conversation Mode
- One-sentence explanation
- Next Technical Lead continuation instruction

## Current Verified State

## Current Blocker

## Frozen During This Chat

## Overall Progress

## Completed Modules

## Current Architecture

## Frozen Decisions

## Current Module Status

## Reusable Components

## Known Placeholders

## Pending External Verification

## Current Folder / Project Notes

## Recommended Next Immediate Task

## Files the Next Technical Lead Should Request First

## Known Dead Ends

## Technical Lead Notes

Rules

- Generate the entire snapshot.
- Never review the previous snapshot.
- Never output suggestions instead of the snapshot.
- Never guess.
- Never reconstruct missing history.
- Preserve only verified implementation.
- Replace outdated information with updated verified information.
- The final output must be a complete replacement PROJECT_SNAPSHOT.md.

ends here....

2nd implementation chat:
# APRISM Project Snapshot

## Snapshot Integrity Notice

This snapshot is generated only from verified implementation completed during the current implementation cycle.

If information could not be verified from the implementation, it is marked as **Unknown** rather than inferred.

This document exists to onboard the next Technical Lead without repeating previous implementation analysis or architectural discussions.

---

## Conversation Continuity

### Current Conversation Mode

Implementation

### One-sentence explanation

The project is currently rebuilding the Academic Head frontend implementation while preserving the existing architecture and backend infrastructure.

### Next Technical Lead continuation instruction

Continue implementation mode only. Do not revisit architecture unless a genuine implementation blocker is discovered.

---

## Current Verified State

✓ Overall project architecture is frozen.

✓ Database blueprint remains frozen.

✓ Folder structure remains unchanged.

✓ Authentication architecture remains in use.

✓ Authorization architecture remains in use.

✓ Shared PHP components remain the implementation foundation.

Verified shared components include:

- head.php
- sidebar.php
- top-navbar.php
- footer.php

✓ Navigation architecture remains unchanged.

✓ Technical Administrator module remains the implementation baseline.

✓ Technical Administrator implementation is considered stable and should not be modified unless a verified implementation defect is discovered.

✓ Academic Head implementation has begun.

✓ Dashboard wireframe has been selected as the visual source of truth for Academic Head.

✓ Programs page is not visually consistent with the approved wireframe.

✓ Dashboard page implementation has accumulated multiple frontend iterations and requires controlled implementation cleanup.

✓ CSS loading was verified during debugging.

✓ Asset paths were verified.

✓ Shared layout loading was verified.

---

## Current Blocker

The Academic Head frontend no longer consistently reflects the approved wireframe because multiple frontend implementation iterations introduced inconsistent HTML and CSS.

Responsible files (verified):

- dashboard/academic_head.php
- dashboard/academic_head_programs.php
- assets/css/academic-head.css
- assets/css/pages/academic-head-dashboard.css
- assets/css/pages/academic-head-programs.css

Verified cause:

The issue is implementation inconsistency, not broken infrastructure.

Verified items already ruled out:

- CSS loading failures
- Shared component loading
- Asset path issues
- Sidebar include
- Navbar include
- Routing issues

---

## Frozen During This Chat

The following implementation decisions are frozen.

- Project architecture remains frozen.
- Database architecture remains frozen.
- Folder structure remains frozen.
- Shared PHP component strategy remains frozen.
- Navigation architecture remains frozen.
- Business logic remains frozen.
- Backend workflow remains frozen.
- Schedule acquisition workflow remains frozen.
- Technical Administrator implementation remains frozen.
- Academic Head Dashboard wireframe is the visual source of truth.
- Existing infrastructure should be preserved whenever possible.
- Rebuild only the Academic Head frontend implementation where necessary.
- Do not redesign architecture during frontend implementation.
- Reuse existing infrastructure before introducing new code.
- Promote CSS into shared Academic Head styles only after reuse is demonstrated.
- Dashboard becomes the visual reference for subsequent Academic Head modules.

---

## Overall Progress

Estimated implementation progress:

Approximately **45%**.

Verified completed work:

- Core infrastructure
- Authentication
- Authorization
- Shared layouts
- Technical Administrator module

Current work:

Academic Head frontend implementation.

Remaining work includes:

- Academic Head
- Teacher
- Guidance Office / Discipline Office
- Remaining backend integration for unfinished modules

---

## Completed Modules

### Authentication

Status:

Completed

Reusable architecture:

- Session management
- Login
- Logout
- Password validation
- Session guard
- Authorization helpers

Notes:

Architecture frozen.

---

### Shared Infrastructure

Status:

Completed

Reusable architecture:

- Shared layouts
- Shared navigation
- Shared helpers
- Shared assets
- Shared motion system

Notes:

Architecture frozen.

---

### Technical Administrator

Status:

Implementation complete.

Architecture frozen.

Reusable architecture:

- Dashboard
- User Management
- Audit Logs
- Database Backups
- System Settings
- Shared layouts
- Shared interaction patterns

Notes:

Future improvements are enhancements only and should not trigger architectural changes.

---

## Current Architecture

Authentication

- Session-based authentication
- Shared authorization helpers
- Role-based protection

Authorization

- Session guard
- Role helper
- Responsibility model

Shared Layouts

- head.php
- sidebar.php
- top-navbar.php
- footer.php
- logout modal

Shared Helpers

Verified helper architecture remains unchanged.

Shared Assets

CSS hierarchy:

Base

↓

Motion

↓

Role Shared CSS

↓

Page CSS

JavaScript hierarchy:

Shared role JavaScript

↓

Page-specific JavaScript

Folder organization remains modular.

Business logic remains outside presentation.

---

## Frozen Decisions

- Overall architecture
- Folder structure
- Database blueprint
- Navigation architecture
- Shared layout philosophy
- Shared helper philosophy
- Authentication architecture
- Authorization architecture
- Technical Administrator architecture
- Role + Responsibility model
- Schedule acquisition architecture
- Dashboard wireframe as Academic Head visual reference
- Preserve working implementation whenever possible
- Implementation-first workflow

---

## Current Module Status

Current module:

Academic Head

Current stage:

Frontend implementation

Last completed verified task:

Verified the root cause of Academic Head visual inconsistencies and selected the approved Dashboard wireframe as the implementation reference.

Remaining work before freeze:

- Implement Dashboard frontend
- Freeze Dashboard
- Implement Programs frontend
- Freeze Programs
- Continue remaining Academic Head modules

---

## Reusable Components

Verified reusable components include:

### Shared Layout

Responsible for:

- Page shell
- Navigation
- Sidebar
- Top navigation
- Footer

---

### Shared Authentication

Responsible for:

- Session handling
- Authorization
- Role validation

---

### Shared Helpers

Responsible for:

- Validation
- Flash messaging
- Audit logging
- System configuration

---

### Shared Motion System

Responsible for:

- UI transitions
- Shared animations

---

### Technical Administrator Design System

Responsible for:

Technical Administrator UI only.

It should not be treated as the visual design system for Academic Head.

---

## Known Placeholders

Verified placeholders:

- Academic Term integration
- Live analytics
- Notification system
- Export functionality
- Institution-specific integrations

---

## Pending External Verification

Unknown items requiring institutional verification:

- Academic Term synchronization
- Final reporting requirements
- Production analytics requirements
- Export requirements
- Institutional workflow confirmation

---

## Current Folder / Project Notes

Maintain the existing project organization.

Presentation:

dashboard/

Shared layouts:

includes/components/

Helpers:

includes/helper/

Role shared CSS:

assets/css/

Page CSS:

assets/css/pages/

Do not move files unless implementation proves it necessary.

---

## Recommended Next Immediate Task

Implement:

```
dashboard/academic_head.php
```

using the approved Dashboard wireframe while preserving existing PHP logic, shared components, routing, IDs, and backend integration.

---

## Files the Next Technical Lead Should Request First

- dashboard/academic_head.php
- assets/css/pages/academic-head-dashboard.css
- assets/css/academic-head.css
- dashboard/academic_head_programs.php
- assets/css/pages/academic-head-programs.css
- includes/components/head.php
- includes/components/sidebar.php
- includes/components/top-navbar.php

---

## Known Dead Ends

❌ Repeated architecture reviews

Reason:

Architecture has already been frozen.

---

❌ Repeated CSS loading investigations

Reason:

CSS loading, asset paths, and shared component loading were verified.

---

❌ Attempting to fix Academic Head by patching Technical Administrator styles

Reason:

Technical Administrator styles are role-specific and should remain isolated.

---

❌ Continuing incremental frontend patches on inconsistent Academic Head pages

Reason:

Multiple implementation iterations introduced inconsistent HTML and CSS mappings.

The approved wireframe should guide implementation instead.

---

## Technical Lead Notes

- Stay in implementation mode.
- Do not reopen architectural discussions unless implementation encounters a genuine blocker.
- Preserve working infrastructure.
- Preserve backend logic.
- Preserve routing.
- Preserve shared PHP components.
- Replace only presentation where required.
- Compare every frontend change against the approved wireframe.
- Freeze each Academic Head page immediately after implementation approval.
- Do not allow frontend redesign to delay backend integration indefinitely.
- Focus on completing working modules rather than repeatedly refining existing implementations.