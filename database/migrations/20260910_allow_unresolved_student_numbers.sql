-- Allows a real STI Class List participant to exist before the
-- institutional Student Number is available. The existing unique key remains
-- authoritative for known numbers; MySQL permits multiple NULL values in a
-- unique index, which is required for unresolved participants.
ALTER TABLE students
    MODIFY student_number VARCHAR(50) NULL;
