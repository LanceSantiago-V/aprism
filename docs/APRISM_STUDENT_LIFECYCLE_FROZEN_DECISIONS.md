# APRISM Student Lifecycle — Frozen Decisions

> Future Student-related changes must read and preserve this decision record before modifying Student identity, SAE, SCE, Class List import, Student placement, Section population, Teacher My Students, or Adviser Students.

This document is the canonical APRISM repository reference for Student lifecycle behavior. APRISM is a monitoring, assessment, and intervention-support system; it is not a Registrar, SIS, curriculum, promotion, or graduation engine.

## 1. Global Student identity

- APRISM has one global Student identity pool.
- `student_id` is the internal canonical identity.
- `student_number` is the authoritative external matching key.
- The same Student Number across Teachers, subjects, Operational Classes, and School Years reuses the same Student identity.
- Student identity persists historically.
- Students must never be merged by name alone.

## 2. Guaranteed normal entry path

The guaranteed normal Student-entry workflow is:

`Teacher Class List → match/create Student by Student Number → create/reuse SCE → Student usable in Teacher workflows`

APRISM must not depend on a hypothetical STI Registry file for Students to function.

## 3. SCE is actual class participation

Student Class Enrollment (SCE) records actual participation in an Operational Class.

- Same Student + same Operational Class → reuse SCE.
- Same Student + different Operational Class → create another SCE.
- Same Student + classes in a new School Year → same Student identity and new applicable SCE rows.
- SCE is not official academic placement.

## 4. SAE is academic placement

Student Academic Enrollment (SAE) records official placement for a School Year and broader academic/teaching context. It may contain Program/Strand, Year/Grade, home Section, School Year, and Semester/context.

SAE is separate from SCE and historical. A new placement must not overwrite a previous School Year placement.

## 5. Year level is never derived from classes

Official Year/Grade must never be inferred from a subject, class Section, schedule, class mix, or lower-/higher-year subjects. A Year 4 Student may take Year 4, Year 3, and repeated Year 2 subjects while retaining official Year 4 placement.

## 6. Regular, irregular, and repeater status

APRISM does not infer Regular, Irregular, or Repeater classifications. SAE represents official placement and SCE represents classes actually taken. Lower-year participation must not automatically label a Student Irregular.

## 7. No automatic promotion or graduation

APRISM must not automatically promote Year 1 → Year 2 → Year 3 → Year 4, infer graduation, retention, or shifting. It is not a curriculum or progression engine. New placement comes from current authoritative information.

## 8. Teacher placement contribution

During controlled Class List Review, a Teacher may optionally contribute or confirm Program, Year/Grade, and Section when legitimately known. These fields are optional. Missing placement must not block Student class participation, and Teachers do not receive unrestricted global placement editing.

## 9. Existing placement reuse

When a compatible current SAE exists for the same Student, School Year, and context, APRISM automatically reuses it. The Teacher is not asked to re-enter it and no duplicate SAE is created.

## 10. Later completion

If a Student first enters through a Class List without placement, Student + SCE remain valid. Later explicit compatible placement matches the same Student Number, creates or reuses SAE, and automatically links compatible NULL-linked SCE rows.

## 11. Conflicts

An established placement must never be silently overwritten. Incoming conflicting placement is marked for review, with existing and incoming values shown where supported. Academic Head may resolve the exception.

## 12. Academic Head role

Academic Head is not the routine Student encoder. The role covers genuine conflicts, ambiguity, multiple current placements, exceptional correction, and institutional oversight. Placement Data Issues is an exception/data-quality workspace.

## 13. Optional placement import

Student Placement Import / Import Placement Data may remain an optional bulk capability. APRISM must not imply that STI provides a confirmed file formally called an “Official Registry”.

## 14. Current Student state

For a selected academic context:

- valid current SAE **or** active applicable SCE → current Student evidence;
- current SAE + SCE → placed and participating;
- current SAE only → current institutional placement;
- current SCE only → current participating / placement unresolved;
- neither current SAE nor current SCE → historical / no current evidence.

## 15. Section population

Academic Head Section population is based on current valid SAE only. SCE must not determine institutional/home Section membership. The visible count terminology is **Placed Students**.

## 16. Teacher My Students

Teacher scope is authenticated Teacher → owned Operational Classes → SCE → Students. SAE is not required.

## 17. Adviser Students

Adviser scope is valid Adviser capability + valid Adviser assignment → assigned SHS Section → SAE → Students. Adviser membership must not be derived from Teacher SCE.

## 18. Teacher workflows with unresolved placement

Student identity + valid SCE remains usable, where supported, for Class List, My Students, Attendance, AprilTags, Grade Import, Referrals, Teacher Reports, and Teacher Monitoring.

## 19. Real STI Class List

The real official STI Teacher Class List file is pending. When provided, extraction/mapping compatibility may be adapted. Student architecture must not be redesigned around one workbook layout.

## 20. Smart system rule

APRISM intelligence comes from Student Number matching, deduplication, record reuse, deterministic context matching, missing-value completion, reconciliation, conflict detection, and review. AI/LLM prediction must not determine academic placement.

## 21. Real STI Class List clarification (2026-09-08)

The provided real STI Class List structure contains `no.`, `photo`, `Name`, `Grade Basis`, `Units`, `Program and Plan`, and `Level`; it does **not** contain Student Number. This source establishes class participation, not a complete permanent Student identity.

- Class List import must allow a mapped Student Name without Student Number when the name is structurally usable.
- A missing Student Number must not block creation/reuse of a Student Class Enrollment or the Teacher workflows that depend on class participation.
- If the schema permits, an unresolved participant is stored with `students.student_number = NULL`; the existing unique key remains authoritative for non-NULL numbers.
- Name-only matching may reuse only one exact normalized unresolved participant already active in the selected Operational Class. It must never globally merge Students or select an ambiguous participant.
- `Program and Plan` and `Level` are source evidence. They must not be converted into institutional Program, Section, or Year/Grade placement unless the mapping and institutional records are explicitly reviewed and safely compatible.
- A later Grade Workbook Student Number is authoritative and may assign a number to the same reviewed unresolved class participant through an explicit reconciliation confirmation. If that number already belongs to another canonical Student, automatic merging/reassignment is not allowed; the row remains a reconciliation exception for review.
- Student Number/name discrepancies, duplicate source numbers, ambiguous unresolved names, and roster mismatches remain review or blocked states. No new Student may be created from name alone without reviewed identity details, and no permanent identity is silently merged.

## 22. ID-bearing and no-ID Class List continuity (2026-09-11)

- Real STI Class Lists may arrive in both no-ID and ID-bearing variants. A no-ID Class List remains valid for unresolved Student identity plus actual class participation.
- An ID-bearing Class List may establish the authoritative `student_number` earlier, but only after source uniqueness, global ownership, class scope, and reconciliation safety checks pass.
- A later numbered Class List may reconcile an existing unresolved participant in the same Operational Class through an explicit reviewed decision; the existing `student_id` and SCE are preserved.
- Student Number matching takes priority over name. Name evidence alone never globally merges Students, across classes, or across School Years.
- A participant absent from a later roster is review information only and must not be automatically withdrawn, deleted, or deactivated.
- Duplicate Student Numbers within the source are blocking identity conflicts; no one number may be assigned to multiple people.
- Grade Workbook reconciliation remains the fallback when a Class List still has no authoritative Student Number.
