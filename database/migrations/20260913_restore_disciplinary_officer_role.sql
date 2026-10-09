/* Restore the capstone's canonical name for the existing fourth role. */
UPDATE roles
SET role_name = 'Disciplinary Officer',
    description = 'Manages confidential intervention cases, follow-ups, and permitted referral workflows.'
WHERE role_id = 4
  AND role_name = 'Guidance Officer';
