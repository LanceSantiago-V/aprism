##### APRISM Frozen Decision — Schedules Module (Refined)

Core Principle (Frozen)

APRISM is not the institution's official scheduling system.

The institution's official teaching schedules and faculty loads remain under the school's existing systems (e.g., PSCS).

APRISM maintains its own working schedule records solely to support its internal modules:

Attendance
Gradebook
Student Monitoring
Guidance Referrals
Reports

APRISM stores schedule information required for its own operations and does not replace the institution's official scheduling authority.

Academic Head Schedule Module (Frozen)

The Academic Head Schedule module is an institution-wide schedule management module.

Its purpose is to maintain the schedule records that APRISM requires for its internal operations.

The page is not an academic timetable generator.

The page is not the official institutional scheduling system.

Instead, it serves as APRISM's centralized schedule management interface.

Academic Head Responsibilities (Frozen)

The Academic Head may:

View all schedules
Search and filter schedules
Record schedule information into APRISM
View schedule details
Edit schedule records
Reassign teachers
Change rooms
Change meeting days
Change meeting times
Resolve teacher scheduling conflicts
Resolve room conflicts
Resolve section conflicts
Archive schedules
Maintain institution-wide schedule consistency inside APRISM

The Academic Head manages APRISM's schedule records only.

Institutional schedule authority remains outside APRISM.

Record Schedule (Frozen)

The primary action is:

Record Schedule

rather than

Create Schedule

This terminology reflects APRISM's role as a support system that records schedule information for its own operations rather than generating the institution's official teaching schedule.

Teacher Schedule Module (Frozen)

Teachers manage only their own schedule records.

Teachers may:

View their assigned schedules
Record their assigned schedules inside APRISM
View schedule details
Import the official class list into a schedule
Start attendance sessions
Open the corresponding gradebook

Teachers cannot:

Manage institution-wide schedules
Assign teachers
Modify another teacher's schedules
Resolve scheduling conflicts across departments
Archive schedules belonging to other faculty members
Schedule Validation (Frozen)

Whenever schedule records are created or revised, APRISM shall validate:

Teacher conflicts
Room conflicts
Section conflicts
Duplicate schedule records

These validations apply only to APRISM's internal schedule records and do not replace institutional schedule validation performed by PSCS.

Schedule Revisions (Frozen)

Schedules remain editable because legitimate institutional changes may occur, including:

Teacher substitutions
Room changes
Time revisions
Day revisions
Administrative corrections

APRISM shall preserve historical integrity through audit logging and revision tracking where appropriate.

Implementation strategies for preserving historical records are implementation details and are not frozen as business rules.

Schedule Lifecycle (Frozen)
Official Schedule (PSCS)
│
▼
Schedule information is recorded into APRISM
│
▼
Academic Head manages institution-wide schedule records
│
▼
Teacher imports the official class list
│
▼
Students become associated with the schedule
│
├── Attendance
├── Gradebook
├── Student Monitoring
├── Guidance Referrals
└── Reports
UI Scope (Frozen)

The Academic Head Schedule page is an institution-wide schedule management interface.

Typical operations include:

View
Record
Edit
Archive

The Teacher Schedule page is a separate module focused only on the teacher's assigned schedules.

Implementation Details (Not Frozen)

The following are implementation decisions and are intentionally not frozen:

Schedule import technology (OCR, image, PDF, Excel, CSV, manual entry, API integration, etc.)
Specific import workflow
Internal storage implementation
Revision implementation strategy
Audit implementation strategy
Automatic vs. assisted schedule extraction

The frontend may initially support Manual Entry while remaining extensible for future import methods.

No specific import technology shall be considered part of APRISM's frozen architecture until verified with STI and formally adopted.

##### DECISION FOR CENTER DATA ON TABLES

Use "class="text-center"

##### FROZEN DECISION !!!!!!!

I think this is the right point to freeze the architecture. The implementation has reached a stage where further frontend work will benefit much more from stable responsibilities than from additional iteration.

I reviewed everything against **our frozen APRISM decisions**, not against the React prototype. Overall, I think the architecture has become **more coherent** than the prototype. The React UI can continue to serve as a visual reference, but the business architecture now stands on its own.

---

# Overall Assessment

I would classify this architecture as:

**Mature:** Yes

**Internally consistent:** Yes

**Faithful to APRISM's scope:** Yes

**Appropriate for a BSIT Capstone:** Yes

**Needs major redesign before implementation:** No

At this point I would recommend freezing it.

---

# Module-by-Module Review

## 1. Academic Setup

**Status:** Keep

I actually think moving this toward the center of the architecture was one of the best refinements we've made.

It establishes the operational context for everything else:

```
School Year
      ↓
Academic Term
      ↓
Schedules
      ↓
Attendance
      ↓
Academic Records
      ↓
Monitoring
      ↓
Reports
```

Monitoring Thresholds also belong here because they are system configuration, not operational monitoring.

### Responsibility

Owns academic configuration.

Nothing else.

That is clean.

---

## 2. Programs

**Status:** Keep

Single responsibility.

Academic structure.

No overlap.

---

## 3. Sections

**Status:** Keep

Single responsibility.

Defines academic groupings.

Supports schedules, students, attendance, and reporting.

No overlap.

---

## 4. Subjects

**Status:** Keep

Single responsibility.

Defines academic offerings.

No overlap.

---

## 5. Schedules

This module changed the most from the prototype.

I think the current architecture is significantly better.

The key principle now is:

> APRISM stores working schedule records.

Not

> APRISM creates the institution's official timetable.

That distinction keeps the project realistic.

The separation of responsibilities also makes sense:

### Teacher

Own schedule only.

### Academic Head

Institution-wide schedule administration.

Exactly what an Academic Head would reasonably do.

I would freeze this exactly as written.

---

## 6. Students

I strongly agree with changing this into a Student Master Reference.

This avoids one of the biggest ERP traps:

Building another student information system.

Instead, APRISM simply references students.

That aligns with:

- Attendance
- Monitoring
- Guidance
- Reports

without pretending to become PSCS.

### One small recommendation

I would define what "Edit APRISM-owned information" actually means.

For example:

Allowed:

- APRISM status
- monitoring notes
- archived flag

Not allowed:

- official name
- student number
- birthdate
- enrollment

That keeps institutional ownership clear.

---

## 7. Teachers

I agree with not introducing a Teacher module.

Currently:

```
User Management

↓

Teacher Accounts

↓

Schedules
Subjects
Attendance
Academic Records
Reports
```

That is sufficient.

Adding Teacher Management now would mostly duplicate User Management.

The proposed "Faculty Reference" remains a good contingency if implementation later reveals a genuine need.

I would leave it out of the frozen baseline.

---

## 8. Academic Records

This rename is excellent.

The original Gradebook implied editing.

Academic Head should not edit grades.

Academic Records communicates exactly what the module actually does.

Responsibilities:

- View
- Review
- Analyze
- Report

Not:

- Encode
- Compute
- Submit

I would freeze this terminology.

---

## 9. Student Monitoring

I think keeping this separate is correct.

Students answers:

> Who is this student?

Monitoring answers:

> Who currently requires institutional attention?

Those are fundamentally different questions.

One is identity.

One is operational decision support.

I would not merge them.

---

## 10. Guidance Referrals

This is another area where the architecture improved.

Originally it risked becoming a Guidance module.

Now it is clearly:

Academic Head oversight.

Guidance Office owns:

- interventions
- counseling
- referral workflow

Academic Head owns:

- visibility
- trends
- institutional oversight

That separation is realistic.

---

## 11. Reports

Keep.

No concerns.

Every operational module naturally feeds Reports.

---

# Duplication Review

I looked specifically for duplicated responsibilities.

## Students vs Monitoring

No longer duplicated.

Good separation.

---

## User Management vs Teachers

No duplication.

Good.

---

## Academic Records vs Reports

Different.

Academic Records:

Operational academic oversight.

Reports:

Institution-wide aggregation.

Good separation.

---

## Monitoring vs Guidance

Monitoring identifies students.

Guidance manages interventions.

No duplication.

---

## Schedules vs Academic Setup

Setup defines academic periods.

Schedules define teaching activity.

Good separation.

---

# Modules That No Longer Belong

I don't see any module that should be removed.

However, I do think one concept should disappear completely:

> "Teacher Management"

The current architecture already covers that responsibility.

I would explicitly state:

> Teacher accounts are managed through User Management.

That closes the question.

---

# Missing Modules

I do not think anything major is missing.

The architecture now covers:

Academic structure

Academic operations

Student monitoring

Institutional reporting

Configuration

Administration

without becoming an ERP.

---

# One Thing I Would Add

Not a module.

Rather a system-wide principle.

I would add this to the frozen implementation baseline.

---

## Historical Data Preservation

Every operational module should preserve historical records across academic periods.

This includes:

Schedules

Attendance

Academic Records

Monitoring

Referrals

Reports

Changing School Year or Academic Term starts a new operational cycle but never overwrites previous academic periods.

That principle already appears implicitly throughout our discussions.

I think it deserves to become an explicit architectural rule.

---

# Final Academic Head Architecture

I would freeze this.

```
Dashboard

User Management

Academic Setup

Programs

Sections

Subjects

Schedules

Students
(Student Master Reference)

Academic Records

Student Monitoring

Guidance Referral Overview

Reports
```

Notice that I intentionally moved **Academic Setup** above the academic structure modules. It functions as foundational configuration for the operational cycle, even if you choose to place it elsewhere in the sidebar for usability. Architecturally, it underpins the rest of the system.

---

# Final Recommendation

I believe this is the right point to freeze these decisions.

The architecture now exhibits several qualities that weren't fully present earlier:

- Every module has a single, well-defined owner.
- Responsibilities are separated cleanly with minimal overlap.
- APRISM consistently acts as a **student monitoring and intervention support system**, not an institutional ERP.
- Integration with PSCS is based on coexistence rather than replacement.
- The scope remains realistic for a BSIT capstone while still demonstrating meaningful system design.

At this stage, I would treat this as the **Academic Head Implementation Baseline v1.0** and proceed with the remaining frontend and backend implementation under these decisions. Future changes should be limited to implementation refinements or institution-specific validation rather than architectural redesign.

Frozen Decision — Academic Data Ownership & Import Architecture
Core Principle

APRISM is not the authoritative owner of institutional academic data. APRISM consumes exported academic data, validates it, normalizes it, and stores a working copy solely to support attendance tracking, student monitoring, analytics, reporting, and intervention workflows.

APRISM does not function as a Student Information System (SIS) or Enterprise Resource Planning (ERP) system.

Data Ownership

Institutional academic entities—including Programs, Sections, Subjects, Students, and Schedules—remain owned by the institution.

APRISM maintains normalized internal registry tables for these entities to support its monitoring and intervention functions.

Import Architecture

APRISM uses one reusable Import Engine as the foundation for all academic data imports.

The Import Engine is responsible for:

File validation
Column detection
Field mapping
Business validation
Data normalization
Deduplication
Transactional database updates
Import summary generation

All institutional and operational imports pass through this same pipeline.

Registry Tables

The following tables are treated as registry tables, not manually maintained CRUD tables:

Programs
Sections
Subjects
Students
Schedules

These tables are populated and updated through validated imports. Existing records are reused or updated based on stable identifiers, while new records are created only when no matching authoritative record exists.

Role Responsibilities

Academic Head

Imports official institutional academic datasets.
Reviews import summaries.
Monitors academic data.
Generates reports.
Does not manually create institutional academic entities.

Teacher

Manages operational workflows within APRISM.
Imports operational datasets relevant to assigned classes where applicable.
Does not manage institution-wide master data.
Permanent Rule

Every academic data import must pass through the Import Engine before any changes are committed to the database.

No backend module may bypass the Import Engine when introducing or updating institutional academic data.


######

APRISM ARCHITECTURAL DECISION REMINDER
Status: FROZEN
Version: Frontend & Permission Architecture v1.0

This document serves as the project's architectural constitution for the remainder of implementation.

Changes are only allowed if:
- a genuine implementation blocker is discovered,
- data integrity would be compromised,
- or implementation reveals an unavoidable architectural contradiction.

UI polish, wording, visual preferences, and "better ideas" are NOT reasons to reopen architecture.

==================================================
SYSTEM PHILOSOPHY
==================================================

APRISM is a Student Monitoring and Intervention Support System.

APRISM is NOT:
- Student Information System
- Enrollment System
- ERP
- Official Institutional Database

APRISM consumes institutional academic data, validates it, stores an internal working registry, and uses that registry for operational monitoring, attendance, intervention, analytics, and reporting.

Institutional data is consumed—not owned.

==================================================
USER MODEL
==================================================

Every user is composed of four independent concepts:

Identity
↓
Role
↓
Responsibility (Optional)
↓
Scope (Optional)

Identity
- Permanent user account.

Role
- Technical Administrator
- Academic Head
- Teacher

Only these three authentication roles exist.

Responsibility
(Optional Teacher extensions)
- Adviser
- Program Head

A Teacher may have:
- no responsibility,
- one responsibility,
- or multiple responsibilities simultaneously.

Responsibilities never replace the Teacher role.

Scope

Responsibilities apply to specific entities such as:
- Program
- Section
- School Year
- Academic Term (if applicable)

Scope determines WHAT data the responsibility can access.

==================================================
PERMISSION PHILOSOPHY
==================================================

Role
→ Defines baseline permissions.

Responsibility
→ Adds additional capabilities.

Scope
→ Restricts those capabilities to the assigned data.

Example:

Teacher

↓

Attendance
Grades
Assigned Classes

+

Adviser

↓

Advisory Monitoring

+

BSIT-3A

↓

Can monitor only BSIT-3A.

==================================================
TEACHER FRONTEND
==================================================

There will only be ONE Teacher frontend.

Separate interfaces for:
- Adviser
- Program Head

will NEVER be created.

Instead:

Teacher frontend

↓

Dynamic modules

↓

Dynamic navigation

↓

Dynamic permissions

↓

Dynamic dashboards

based on assigned responsibilities.

==================================================
RESPONSIBILITY PHILOSOPHY
==================================================

Responsibilities are operational assignments.

NOT permanent user properties.

Teacher identity never changes.

Only assignments change.

Responsibilities may be:

- assigned
- updated
- removed
- reassigned

without changing:
- account
- authentication
- role

==================================================
ACADEMIC YEAR CHANGES
==================================================

Assignments are expected to change every academic year.

Example:

2026

Teacher
↓

Program Head
↓

BSIT

↓

2026–2027

Next Year

Teacher
↓

Program Head
↓

BSCS

No new account is created.

Only the assignment changes.

==================================================
ACADEMIC HEAD
==================================================

Institution-wide oversight.

Can view:
- all students
- all teachers
- all programs
- all sections
- all reports
- registry data

==================================================
PROGRAM HEAD
==================================================

Teacher extension.

Visibility limited to assigned program.

Examples:

- Program Monitoring
- Program Analytics
- Faculty Overview
- Program Reports

Never receives institution-wide visibility.

==================================================
ADVISER
==================================================

Teacher extension.

Visibility limited to assigned advisory section.

Examples:

- Advisory Monitoring
- Referral Follow-ups
- Advisory Reports

==================================================
IMPORT ENGINE
==================================================

APRISM contains ONE Import Engine.

Academic Head imports:
- Registry datasets

Teacher imports:
- Operational datasets

The workflow is shared.

Only permissions differ.

==================================================
FRONTEND STATUS
==================================================

Academic Head Frontend
✅ Frozen

Teacher Frontend
🔄 Current Phase

Next:

Teacher Frontend

↓

Final Frontend Review

↓

Database Validation

↓

Database Freeze

↓

Backend Planning

↓

Backend Implementation

==================================================
REMINDER
==================================================

Do not redesign architecture during implementation.

Validate through implementation.

Only revisit architecture if a genuine blocker appears.


######

# IMPLEMENTATION MODIFICATION PROTOCOL (FROZEN)

The implementation chat must follow this exact workflow whenever code changes are required.

This protocol is mandatory unless I explicitly instruct otherwise.

---

## Rule 1 — Never Guess Existing Code

If the exact implementation is not available, always request the relevant file(s) first.

Do not assume the current implementation.

Do not reconstruct files from memory.

Study the actual implementation before making changes.

---

## Rule 2 — Identify the File First

Before writing code, clearly state:

File to modify:

dashboard/teacher.php

or

File to create:

dashboard/teacher_my_classes.php

If multiple files are required, list them in implementation order.

Never modify unrelated files.

---

## Rule 3 — Complete Replacement Blocks

When modifying an existing file, always provide:

### A. File Name

Example:

File:

assets/css/layout.css

---

### B. Exact Code to Find

Provide the COMPLETE code block to replace.

From the first line through the final line.

Never use:

"around line..."

"near..."

"after..."

or similar descriptions.

The user should be able to locate the block by copy-and-search.

---

### C. Complete Replacement

Provide the ENTIRE replacement block.

Never provide partial snippets unless explicitly requested.

The replacement should be immediately copy-pasteable.

---

## Rule 4 — Creating New Files

When creating a new file:

Clearly state:

Create:

dashboard/teacher_my_classes.php

Then generate the COMPLETE contents of the file.

Do not generate placeholders unless requested.

---

## Rule 5 — Explain the Purpose

Before presenting code, briefly explain:

Why the file is being modified.

What the change accomplishes.

How it fits into the existing architecture.

Keep explanations concise and implementation-focused.

---

## Rule 6 — One Step at a Time

Implement incrementally.

One feature.

One file.

One review.

Then continue.

Never implement multiple unrelated features simultaneously.

---

## Rule 7 — Preserve Existing Architecture

Every modification must:

Reuse existing helpers.

Reuse shared components.

Reuse existing CSS architecture.

Reuse existing navigation.

Avoid duplication.

Never redesign working systems without a genuine implementation blocker.

---

## Rule 8 — Wait for Confirmation

After each implementation step:

Wait for my review.

Wait for my confirmation.

Only then proceed to the next file or feature.

---

## Preferred Response Format

Every implementation response should follow this structure:

1. Purpose

(Brief explanation.)

---

2. File to Modify

Example:

assets/css/layout.css

---

3. Replace This

(Complete code block from beginning to end.)

---

4. With This

(Complete replacement code block.)

---

5. Expected Result

(What should change after saving and refreshing.)

---

6. Next Step

(Only after I confirm.)

###### 