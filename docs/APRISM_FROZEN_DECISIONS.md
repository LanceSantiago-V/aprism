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

* Attendance
* Monitoring
* Guidance
* Reports

without pretending to become PSCS.

### One small recommendation

I would define what "Edit APRISM-owned information" actually means.

For example:

Allowed:

* APRISM status
* monitoring notes
* archived flag

Not allowed:

* official name
* student number
* birthdate
* enrollment

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

* View
* Review
* Analyze
* Report

Not:

* Encode
* Compute
* Submit

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

* interventions
* counseling
* referral workflow

Academic Head owns:

* visibility
* trends
* institutional oversight

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

* Every module has a single, well-defined owner.
* Responsibilities are separated cleanly with minimal overlap.
* APRISM consistently acts as a **student monitoring and intervention support system**, not an institutional ERP.
* Integration with PSCS is based on coexistence rather than replacement.
* The scope remains realistic for a BSIT capstone while still demonstrating meaningful system design.

At this stage, I would treat this as the **Academic Head Implementation Baseline v1.0** and proceed with the remaining frontend and backend implementation under these decisions. Future changes should be limited to implementation refinements or institution-specific validation rather than architectural redesign.
