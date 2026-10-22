# APRISM — Start Here on the Next Account

**Canonical checkout:** `C:\xampp\htdocs\aprism`

**Canonical local visual runtime:** `http://localhost/aprism/`

**Phase:** consultation-ready system, with bounded acceptance and evidence gaps still open. This is continuation, not a restart.

## First five steps

1. Transfer the **entire current working tree**, including all modified and untracked files in `docs/APRISM_MIGRATION_PACKAGE_MANIFEST.md`; do not rely on the remote branch or HEAD alone. Keep private configuration, SQL backups, and Student data out of ChatGPT uploads.
2. Read `AGENTS.md`, `docs/APRISM_FROZEN_DECISIONS.md`, `docs/APRISM_STUDENT_LIFECYCLE_FROZEN_DECISIONS.md`, `DESIGN.md`, `APRISM_UI_UX_AND_CODEX_RULES.md`, and `docs/APRISM_AGENT_PLAYBOOK.md` before changing anything.
3. Read `docs/APRISM_FINAL_MIGRATION_HANDOFF.md` for the current evidence boundary and `docs/APRISM_REMAINING_WORK_REGISTER.md` for priorities. Consult `docs/APRISM_SYSTEM_TESTING_RECONCILIATION.md` for original observations. `docs/APRISM_IMPLEMENTATION_HANDOFF.md` is useful history but has stale role/status passages.
4. Recheck Git status, the active local database name, and `schema_migrations` **read-only** in the new environment. At this handoff, branch `design/aprism-system-hardening` / HEAD `a578d45` had substantial dirty work; the local `aprism` ledger matched all 22 migration files, including both Class Handover migrations. Confirm private configuration separately: `config/environment.example.php` is missing the timezone required by `config/app.php`. Do not assume a different machine or hosted database matches.
5. Continue with **isolated reproduction of the reported Grade Import extraction error using the original workbook or an approved equivalent**. It is reported but not yet reproduced in current source. Keep Class Handover/physical-device Attendance and backup-recovery decisions on the register rather than relaunching implementation blindly.

## Never lose these decisions

- `student_id` is permanent; Student Number is the authoritative external matching key where present. SAE is historical academic placement, SCE is actual class participation, and missing SAE does not block valid SCE workflows. No global name-only merge or inferred placement.
- Roles: Technical Administrator, Academic Head, Teacher, Disciplinary Officer. Adviser extends Teacher responsibility; Program Head is deferred.
- AprilTag current ownership is global; offline Attendance is IndexedDB queue then **server-authorized** sync. The current scanner does not support reopening while offline, and physical-phone behavior remains unverified. Attendance timing and session timeout are configurable policy, not fixed examples.
- Class Handover changes current class responsibility and records audit/history; it does not rewrite historical actors. Legacy Attendance conductors remain unknown when no authoritative identity exists.
- Preserve current Product Owner-accepted Teacher Dashboard composition and APRISM design constitution. Use canonical localhost for visual decisions; isolated QA is for safe mutations.
- Do not mutate canonical academic records for testing, touch hosted data, reset/clean the dirty tree, or run migrations without environment-specific inspection and explicit approval.

**Evidence rule:** distinguish implemented, isolated QA verified, canonical browser verified, physical-device verified, hosted verified, and Product Owner approved. Source presence or an old screenshot is never a substitute for the missing level.
