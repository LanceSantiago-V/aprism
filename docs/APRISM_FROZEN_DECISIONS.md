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

# `APRISM_STUDENT_CONTEXT_FROZEN_DECISIONS`

**Status:** FROZEN
**Purpose:** Source of truth for future APRISM Student/Class List, Enrollment, Attendance, Grades, Monitoring, Analytics, and related implementation decisions.

These decisions are now treated as **frozen implementation constraints** for this APRISM conversation.

If future code, schema, or newly discovered evidence conflicts with any rule below, the conflict must be explicitly reported as **`CONFLICT IDENTIFIED`** before any change is made.

---

## 1. Permanent Identity

### Student

`student_id` is the permanent APRISM identity of a student.

The same `student_id` persists throughout the student's institutional history.

A new Student must **NOT** be created merely because the student:

* changes School Year;
* changes semester/academic context;
* changes Program/Strand;
* changes Section;
* changes Year Level;
* becomes irregular;
* repeats a Subject;
* takes Subjects outside the normal year-level sequence.

Academic identity and academic placement are separate concepts.

### Student institutional identity

* `student_id` = APRISM internal permanent identity.
* `student_number` = institutional identity/matching key.

Student personal information must not be duplicated merely because academic placement changes.

---

# 2. Academic Placement

`student_academic_enrollments` represents the student's **historical academic placement/context**.

It describes what was true for the student during a defined academic context.

It may contain:

* Student
* School Year
* Academic Level
* Semester/context where applicable
* Program/Strand
* Section
* Year Level
* effective dates
* lifecycle/status

An old placement must never be overwritten simply because a newer placement exists.

### Important boundary

`academic_period_id` is **not** attached to the academic enrollment merely because the Academic Period changes from:

```text
Prelim
→ Midterm
→ Pre-Final
→ Final
```

Academic Enrollment represents the broader placement/context.

Specific Academic Periods are used by downstream records that actually require period-level context.

---

# 3. School Year

School Year is an institutional academic context controlled by **Academic Setup**.

A School Year rollover must preserve previous history.

When a new School Year becomes active:

* old Student records remain untouched;
* old academic enrollments remain historical;
* old class participation remains historical;
* old Attendance remains historical;
* old Grades remain historical;
* old Monitoring remains historical;
* old Analytics remain queryable;
* no new Student is created automatically;
* no new academic placement is invented automatically.

Authoritative Student/Class List data establishes the student's new placement.

### Example

```text
2026–2027
Grade 11
STEM 111 / STEM 112
```

followed by:

```text
2027–2028
Grade 12
STEM 121 / STEM 122
```

still represents **one Student identity** with different historical academic placements.

---

# 4. Academic Period

Academic Period is a **specific period inside Academic Setup**.

Conceptually:

```text
School Year
    ↓
Academic Context / Semester
    ↓
Academic Period
```

### College example

```text
2026–2027
First Semester
    ├── Prelim
    ├── Midterm
    ├── Pre-Final
    └── Final
```

The Academic Period is not itself a Student enrollment.

### Downstream usage

Period-specific records such as:

* Assessments
* Grades
* period-specific monitoring

may reference `academic_period_id` where appropriate.

The current Academic Setup remains the authoritative source for:

* applicable School Year;
* academic context;
* semester;
* Academic Period;
* period dates/boundaries.

**Do not create another academic-period system unless actual schema evidence proves the existing model incapable of representing the institutional requirements.**

---

# 5. College Context

College placement is semester-based.

A student can have different Section placement between semesters **within the same School Year**.

Example:

```text
Student #123

2026–2027
First Semester
BSIT
BSIT 4.1C
Year 4
```

and:

```text
2026–2027
Second Semester
BSIT
BSIT 4.2C
Year 4
```

These are separate historical academic-placement records.

The same `student_id` remains.

A simplistic uniqueness rule such as:

```text
student_id + school_year_id
```

must therefore not prevent legitimate semester-specific placements.

Likewise, placement changes within the same broader context must remain historically representable when they actually occur.

---

# 6. SHS Context

APRISM must support the institution's SHS structure without embedding Section naming conventions into identity logic.

Current institutional information:

### Grade 11

```text
111 = 1st–2nd period
112 = 3rd–4th period
```

### Grade 12

```text
121 = 1st–2nd period
122 = 3rd–4th period
```

Examples:

```text
STEM 111
STEM 112
STEM 121
STEM 122
```

These are **institutional/contextual conventions**, not database identity rules.

### Prohibited

APRISM must not contain logic such as:

```text
if section_name ends in "111"
    → Grade 11
```

or:

```text
STEM 112
    → automatically determine academic period
```

Academic Setup/institutional data is authoritative.

If STI's actual SHS structure changes, APRISM should accommodate the new configuration rather than requiring a code change to Section identity logic.

### Grade 11 → Grade 12

Progression is historical, not an identity mutation.

APRISM must record:

```text
Student #123
2026–2027 → Grade 11
```

and later:

```text
Student #123
2027–2028 → Grade 12
```

only when authoritative data establishes that placement.

APRISM must not assume progression automatically.

---

# 7. Program / Subject / Section Identity

Programs, Subjects, and Sections are persistent institutional entities.

They are **not copied simply because a new School Year or semester begins.**

### Program

Persistent institutional identity.

### Subject

Persistent institutional identity.

The same Subject can exist across:

* Teachers;
* Sections;
* Semesters;
* School Years;
* Operational Classes.

Do not use:

```text
student_id + subject_id
```

as a global uniqueness rule.

### Section

`section_id` is the persistent Section identity.

`section_name` is **not** globally unique.

Do not add:

```text
UNIQUE(section_name)
```

merely to simplify resolution.

Legitimate duplicate names may exist in different institutional contexts.

### Section resolution

```text
Existing unambiguous Section
        → reuse section_id

No matching Section
        → create new Section

Multiple plausible matches
        → Review / ambiguity
```

Never silently choose an arbitrary matching Section.

### Section metadata

Program and Year Level may initially be unavailable.

They may remain `NULL` and be enriched later using authoritative institutional information.

Do not invent Program or Year Level from the Section name.

---

# 8. Student Class Participation

Academic placement and actual class participation are separate.

`student_class_enrollments` answers:

> **What Operational Classes did this student actually take?**

Conceptually:

```text
student_id
+
enrollment_id
+
operational_class_id
```

This relationship supports:

* irregular students;
* repeated Subjects;
* cross-year Subjects;
* multiple Subjects;
* different classes within a semester;
* Section changes;
* students taking Subjects outside their normal year level.

The Student's academic placement does **not** determine every Subject they are permitted to participate in.

---

# 9. Irregular Students

An irregular Student remains the same Student.

Example:

```text
Academic Placement:
BSIT
Year 4
BSIT-4A
```

The student may participate in:

```text
Operational Class:
Subject normally associated with another Year Level
```

through Student Class Enrollment.

APRISM must **not**:

* change the student's permanent identity;
* create another Student;
* overwrite their official Year Level;
* change their Program/Section merely because of that Subject.

The authoritative academic placement and actual class participation remain separate.

---

# 10. Repeated Subjects

Repeated Subjects are valid.

Example:

```text
2026–2027
Database Systems
Operational Class #50
```

and:

```text
2027–2028
Database Systems
Operational Class #90
```

The Subject remains the same persistent institutional Subject.

The Operational Classes are separate teaching instances.

The Student's participation records remain historically distinct.

Therefore:

```text
student_id + subject_id
```

must **not** be the uniqueness boundary.

The actual class participation is identified through the Operational Class relationship.

---

# 11. School Year Rollover

A School Year transition is a **new academic context**, not a replacement of old data.

Forbidden behavior:

```text
UPDATE old Student placement
SET school_year = new year
```

when that would destroy historical context.

Correct behavior:

```text
Old academic enrollment
    → remains historical

New authoritative placement
    → new academic enrollment
```

No automatic progression.

No automatic Section reassignment.

No automatic Program/Strand reassignment.

No automatic Student duplication.

---

# 12. Import Matching

### Student identity

```text
student_number
```

is the institutional matching key.

### Existing Student

```text
Existing student_number
    → reuse existing student_id
```

### New Student

```text
Unknown valid student_number
    → create new student_id
```

### Missing or ambiguous identity

```text
Missing/ambiguous identity
    → Review
```

### Prohibited

Never merge Students based on name alone.

Never create a new Student merely because:

* Section changed;
* Program changed;
* Year Level changed;
* School Year changed;
* Semester changed;
* Subject changed.

---

# 13. Ambiguity / Review Rules

APRISM must prefer **Review over guessing** whenever identity or academic context cannot be established safely.

### Student

```text
Known Student
+ complete placement
→ accept

Known Student
+ incomplete placement
→ Review / Incomplete

Ambiguous Student identity
→ Review

Unknown valid Student
→ create
```

### Section

```text
Unambiguous existing Section
→ reuse

No matching Section
→ create

Multiple plausible Sections
→ Review
```

### Academic placement

If Program, Section, Year Level, or context cannot be established authoritatively:

* preserve Student identity;
* do not invent metadata;
* do not infer from Section naming;
* mark placement Review/Incomplete.

---

# 14. Historical Data Rules

Historical data must describe **what was true at that time**, not what is currently true.

A historical record must never depend solely on:

```text
current student.section_id
current student.program_id
current student.year_level
```

because those values may change.

The historical chain is:

```text
Student
    ↓
Student Academic Enrollment
    ↓
Student Class Enrollment
    ↓
Operational Class
    ↓
Attendance / Grades / Monitoring
```

This allows APRISM to reconstruct historical academic circumstances even after the Student's current placement changes.

---

# 15. Attendance / Grades / Monitoring Dependencies

## Attendance

Attendance must ultimately identify the Student through their actual class participation / Operational Class context.

It must not determine historical Section solely from the Student's current placement.

Conceptually:

```text
Student
    ↓
Student Class Enrollment
    ↓
Operational Class
    ↓
Attendance
```

## Grades / Assessment

Period-specific grades should be able to identify:

```text
Student
+
Operational Class / Class Participation
+
Academic Period
```

so:

```text
2026–2027 Midterm
```

cannot accidentally mix with:

```text
2027–2028 Midterm
```

## Monitoring

Monitoring must be able to distinguish:

* Student identity;
* academic placement;
* actual class participation;
* relevant Attendance;
* relevant Grades;
* relevant Academic Period.

Monitoring must not overwrite historical context with the Student's latest placement.

---

# 16. Analytics Requirements

Analytics must be capable of answering questions such as:

### Section history

> Show this student's Section history.

Expected conceptual path:

```text
Student
→ Academic Enrollments
→ Sections
→ ordered historical context
```

### School Year

> Show attendance for 2026–2027 First Semester.

Must filter through the relevant historical academic/class context.

### Academic Period

> Show grades for Midterm 2026–2027.

Must identify the specific Academic Period rather than assuming that "Midterm" alone identifies the record.

### Cross-year comparison

> Compare attendance across School Years.

Must preserve each School Year's historical context.

### Monitoring

> Show monitoring records during a specific academic year.

Must not use only the Student's current Program/Section/Year Level.

---

# 17. Autocomplete / Suggestion Rules

Database-backed suggestions may later be implemented for:

* Students;
* Programs/Strands;
* Subjects;
* Sections.

Purpose:

* reduce typing errors;
* reduce duplicate creation;
* surface existing institutional records;
* prioritize contextually relevant records.

### Important boundary

Autocomplete is **not identity logic**.

The backend remains authoritative.

Suggestions must not mean:

> "First match = automatically correct."

When multiple legitimate records are possible:

```text
→ explicit selection / Review
```

When no suitable existing record exists:

```text
→ genuinely new valid record may be created
```

where permitted by the workflow.

### Current status

**NOT TO BE IMPLEMENTED YET** unless a dependency audit demonstrates that it is necessary for the current workflow.

---

# 18. Frozen Schedule Boundaries

The already-tested Schedule architecture remains frozen.

Do not redesign or modify:

* Programs;
* Subjects;
* Sections;
* Operational Classes;
* Class Schedules;
* Schedule conflict logic.

Student/Class List implementation must reference the existing architecture.

### Existing Schedule principle

Persistent:

```text
Program
Subject
Section
```

Operational/contextual:

```text
Operational Class
Class Schedule
```

Student/Class List must not create duplicate institutional entities merely to accommodate academic history.

### Schedule change exception

A Schedule change is permitted only if a **genuine dependency blocker** is discovered.

If that happens:

```text
CONFLICT IDENTIFIED
```

must be reported before implementation.

No silent architectural alteration.

---

# 19. Known Unresolved Questions

These are deliberately **not silently resolved**.

## 19.1 Actual SHS Academic Setup rows

The schema supports SHS academic-level/context data, and the current structure is intended to accommodate it.

However, the exact current institutional SHS Academic Setup configuration must be verified when relevant.

Known institutional information:

```text
Grade 11:
111 → 1st–2nd period
112 → 3rd–4th period

Grade 12:
121 → 1st–2nd period
122 → 3rd–4th period
```

This remains contextual information, not hard-coded identity logic.

---

## 19.2 Exact academic-context representation

Before SQL generation, the implementation must verify how the existing:

```text
school_years
academic_periods
```

represent:

* College First Semester;
* College Second Semester;
* SHS Grade 11;
* SHS Grade 12;
* SHS period structure.

Do not invent another Academic Period table unless the existing schema genuinely cannot represent the requirements.

---

## 19.3 Source SQL vs live database drift

Where source SQL and the currently tested live database differ, the discrepancy must be explicitly identified.

Do not assume the source SQL is automatically the live schema.

Do not silently overwrite either one.

The actual schema must be inspected before generating Student/Class List SQL.

---

## 19.4 Existing downstream modules

Before implementing Student Class Enrollment relationships, inspect the actual current implementations of:

* Attendance;
* Grades/Assessment;
* Monitoring;
* Class List;
* Student import;
* related resolvers/helpers.

If a required dependency is missing:

**STOP and request it.**

Do not reconstruct or guess it.

---

# FROZEN IMPLEMENTATION PRINCIPLE

For every future APRISM implementation task, the dependency-first process remains:

```text
AUDIT
 ↓
inspect actual files/schema/dependencies
 ↓
identify conflicts
 ↓
compare against APRISM_STUDENT_CONTEXT_FROZEN_DECISIONS
 ↓
propose minimum change
 ↓
wait for approval when architecture/frozen rules are affected
 ↓
implement
 ↓
verify
```

A future requirement that conflicts with this record must **not** silently override it.

It must be reported as:

> **CONFLICT IDENTIFIED**

with:

1. the frozen rule;
2. the new requirement/evidence;
3. the exact conflict;
4. the minimum possible resolution;
5. approval requested before changing the frozen decision.

---

## FINAL STATUS

**FROZEN:** `APRISM_STUDENT_CONTEXT_FROZEN_DECISIONS`

**Source of truth:** Yes.

**SQL generated:** No.

**PHP changed:** No.

**Schedule modified:** No.

**Student schema modified:** No.

**Academic Period architecture modified:** No.

**Future implementation requirement:** This record must be consulted before implementing Student/Class List, Enrollment, Attendance, Grades, Monitoring, Analytics, imports, or related data relationships.

I’ll treat these decisions as the baseline going forward.

######

# APRISM CONTEXT / LIFECYCLE AUDIT

**Status: AUDIT COMPLETE — no SQL/PHP changes proposed.**

I am keeping `APRISM_STUDENT_CONTEXT_FROZEN_DECISIONS` unchanged. The important distinction that emerges is that **School Year, teaching term/semester, assessment period, academic placement, and actual class participation are different concepts** and should not be collapsed into one record.

---

## CONFIRMED

### 1. School Year

**Concept:** the institutional academic-year context.

Example:

`2026–2027`

The existing APRISM Academic Setup remains authoritative for which School Year is active and for its calendar boundaries.

A School Year rollover:

```text
2026–2027
     ↓
2027–2028
```

does **not** overwrite student history or automatically create new student placements.

### 2. College Semester / Teaching Term

For College, the student's academic context can change between semesters **within the same School Year**.

Example:

```text
2026–2027
├── First Semester
│    └── BSIT 4.1C
└── Second Semester
     └── BSIT 4.2C
```

Therefore, **Semester is part of the student's academic placement/context**, not merely an assessment period.

A student can have:

```text
student_id = 123

Enrollment A
2026–2027
First Semester
BSIT
Year 4
BSIT 4.1C

Enrollment B
2026–2027
Second Semester
BSIT
Year 4
BSIT 4.2C
```

Both remain historical.

---

### 3. `BSIT 4.1C` / `BSIT 4.2C`

These are **Section/placement names**, not academic-period identities.

The database must **not parse**:

```text
BSIT 4.1C
```

into:

```text
Program = BSIT
Year = 4
Semester = 1
Section = C
```

The meaning comes from the authoritative academic/context records and explicit database relationships.

This is especially important because the institutional naming convention could change.

**Section name = descriptive institutional data.**

**Section ID = persistent Section identity.**

---

### 4. College Prelim / Midterm / Pre-Final / Final

These are **assessment/grading periods inside the College teaching context**.

The conceptual hierarchy is:

```text
School Year
    ↓
College Semester
    ↓
Academic Period
    ├── Prelim
    ├── Midterm
    ├── Pre-Final
    └── Final
```

Therefore:

**Prelim is not a new Student Academic Enrollment.**

A student does not receive four academic enrollments merely because the semester progresses:

```text
❌ Enrollment → Prelim
❌ Enrollment → Midterm
❌ Enrollment → Pre-Final
❌ Enrollment → Final
```

Instead:

```text
Student Academic Enrollment
        ↓
College Semester / teaching context
        ↓
Academic Period
        ↓
Assessment / Grade records
```

---

### 5. SHS `111 / 112 / 121 / 122`

These must remain **institutional/contextual data**, exactly as frozen.

Current known interpretation:

```text
Grade 11
111 → 1st–2nd period
112 → 3rd–4th period

Grade 12
121 → 1st–2nd period
122 → 3rd–4th period
```

Thus examples such as:

```text
STEM 111
STEM 112
STEM 121
STEM 122
```

must **not** become parsing rules.

APRISM should represent the applicable academic context explicitly rather than determining it from the string `111`.

---

### 6. SHS Grading Periods

This is the SHS equivalent of the same important distinction.

The **configured SHS academic structure** determines what its periods mean.

We should therefore distinguish:

```text
SHS academic placement/context
        ↓
configured SHS academic period structure
        ↓
specific grading/assessment period
```

We should **not assume that College's four assessment-period model can simply be copied into SHS**.

The Academic Setup is the authority.

---

### 7. Student Academic Enrollment

This is the record representing:

> **Where/within what academic context the student was academically placed during that historical context.**

It can therefore carry things such as:

```text
Student
School Year
Academic Level
College Semester / applicable SHS context
Program / Strand
Section
Year Level
effective/status information
```

It is **not** the student's list of every Subject they took.

This distinction is critical.

---

### 8. Student ↔ Operational Class

This represents:

> **What actual class the student participated in.**

The Operational Class remains the anchor for the teaching instance.

Conceptually:

```text
Student
   ↓
Student Academic Enrollment
   ↓
Student Class Enrollment
   ↓
Operational Class
   ↓
Class Schedule
```

This permits:

* irregular students;
* repeated subjects;
* cross-year subjects;
* students taking additional subjects;
* students changing sections;
* multiple subjects/classes within one academic placement.

---

# CONFLICT IDENTIFIED

### No conflict with the frozen architecture.

However, there is one architectural rule that must be kept particularly strict:

> **Academic Period must not become a substitute for Semester or Student Academic Enrollment.**

For example, this would be wrong:

```text
Student
  ↓
academic_period_id = Prelim
```

and then changing it to:

```text
academic_period_id = Midterm
```

That would incorrectly make a grading-period transition look like a change in academic placement.

The same student should retain the same academic placement while their period-specific assessment records move from Prelim → Midterm → Pre-Final → Final.

---

# AMBIGUITY

There is one thing I **cannot honestly mark as fully confirmed from the material currently available**:

### Exact live representation of College Semester vs Academic Period in the current `academic_periods` implementation.

We know from the established APRISM architecture that:

```text
School Year
    ↓
Academic Period(s)
```

exists and that Academic Setup is authoritative.

We also have the current database visibly containing:

* `school_years`
* `academic_periods`
* `academic_periods_migration`

But I do **not** have the actual current `academic_periods` table structure / Academic Setup PHP implementation in this turn sufficient to prove every live column and constraint.

Therefore I will **not invent** whether, for example, Semester is:

* an explicit column,
* an encoded context,
* represented by another existing field,
* or otherwise modeled in the current implementation.

That needs to be verified from the actual implementation before SQL generation.

This is precisely the sort of dependency question our frozen rules require us to stop on rather than guessing.

---

# RECOMMENDED FROZEN RULE

The conceptual hierarchy should be frozen as:

```text
ACADEMIC SETUP
│
├── School Year
│   │
│   ├── College Semester / teaching context
│   │   │
│   │   └── Academic Period
│   │       ├── Prelim
│   │       ├── Midterm
│   │       ├── Pre-Final
│   │       └── Final
│   │
│   └── SHS configured academic context
│       │
│       └── configured SHS periods/grading periods
│
└── authoritative institutional calendar/context
```

Then:

```text
STUDENT
  │
  └── Student Academic Enrollment
        │
        ├── School Year
        ├── applicable teaching/context
        ├── Program / Strand
        ├── Section
        └── Year Level
              │
              └── Student Class Enrollment
                    │
                    └── Operational Class
                          │
                          └── Class Schedule
```

And separately:

```text
Operational Class / Student Class Enrollment
              │
              └── Attendance
```

while:

```text
Student Academic Enrollment / Operational Class
              │
              └── Assessment / Grades
                       ↑
                 Academic Period
```

and historical Monitoring should resolve its context through the historical records rather than asking:

> "What is this student's current Section?"

---

# NO CHANGE REQUIRED

### Schedule

**NO CHANGE.**

The already-tested:

```text
Operational Class
    ↓
Class Schedule
```

architecture remains frozen.

### Programs

**NO CHANGE.**

Persistent Program/Strand identity remains separate from student placement.

### Subjects

**NO CHANGE.**

A Subject remains persistent.

Repeated use is represented through different Operational Classes and student participation.

### Sections

**NO CHANGE.**

`section_id` remains persistent.

`section_name` remains non-global and must not be used to derive semester/year/program.

### Student identity

**NO CHANGE.**

```text
student_id
```

remains permanent.

### Academic Period

**NO CHANGE to the frozen conceptual rule.**

It remains a specific period within Academic Setup rather than becoming the student's permanent/current placement record.

---

## The resulting distinction is now clear

| Concept                                     | Answers                                                                                      |
| ------------------------------------------- | -------------------------------------------------------------------------------------------- |
| **School Year**                             | Which institutional academic year?                                                           |
| **College Semester / SHS academic context** | Which teaching/academic context within that year?                                            |
| **Academic Placement**                      | Where/under what program, section, and year level was this student academically placed?      |
| **Academic Period**                         | Which specific grading/assessment period?                                                    |
| **Operational Class**                       | What actual teaching instance exists?                                                        |
| **Student Class Enrollment**                | Did this particular student participate in that Operational Class?                           |
| **Attendance**                              | What happened to this student in that class/session?                                         |
| **Grades/Assessment**                       | What was recorded for this student for the applicable academic period?                       |
| **Monitoring/Analytics**                    | What does the student's historical academic/class data indicate within the relevant context? |

### Most important consequence

A change such as:

```text
BSIT 4.1C
        ↓
BSIT 4.2C
```

is an **academic-placement change**.

A change such as:

```text
Prelim
        ↓
Midterm
```

is an **assessment-period transition**.

A student taking:

```text
Database Systems
```

is a **class-participation relationship**.

Those three events must **never be represented as the same kind of relationship**.

---

## Current audit status

**CONFIRMED**

* Permanent Student identity.
* Historical academic placement.
* College semester-specific placement.
* SHS contextual structure.
* Academic Period as finer-grained period.
* Operational Class as actual teaching anchor.
* Student Class Participation separate from placement.
* Historical records must not depend on current placement.
* Schedule remains frozen.

**CONFLICT IDENTIFIED**

* None.

**AMBIGUITY**

* Exact live implementation of how the current `academic_periods` schema represents College Semester and SHS context still requires direct inspection of the current Academic Setup implementation.

**RECOMMENDED FROZEN RULE**

* Keep Academic Enrollment at the broader academic-placement/context level.
* Keep Academic Period for period-specific downstream records.
* Keep Student Class Enrollment as the bridge to Operational Class.
* Do not use Section-name parsing for any of these.

**NO CHANGE REQUIRED**

* Schedule, Programs, Subjects, Sections, and the frozen conceptual architecture.

I would **stop here before schema generation**, exactly as requested. The next implementation decision should wait until the actual current `school_years` / `academic_periods` implementation is inspected so we don't accidentally design a second academic-context system.

######

