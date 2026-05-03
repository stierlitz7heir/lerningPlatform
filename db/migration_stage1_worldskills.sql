ALTER TABLE course_enrollments
    ADD COLUMN IF NOT EXISTS last_accepted_lesson_id INT NULL AFTER grade;

ALTER TABLE submissions
    ADD COLUMN IF NOT EXISTS attempt_number INT NOT NULL DEFAULT 1 AFTER student_id,
    ADD COLUMN IF NOT EXISTS status ENUM('on_review', 'revision', 'accepted') NOT NULL DEFAULT 'on_review' AFTER attempt_number,
    ADD COLUMN IF NOT EXISTS text_content TEXT NULL AFTER status,
    ADD COLUMN IF NOT EXISTS file_path VARCHAR(255) NULL AFTER text_content,
    ADD COLUMN IF NOT EXISTS teacher_comment TEXT NULL AFTER file_path;

ALTER TABLE submissions
    MODIFY COLUMN status ENUM('on_review', 'revision', 'accepted', 'submitted', 'review') NOT NULL DEFAULT 'on_review';

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'submissions'
      AND index_name = 'idx_submissions_assignment_student_attempt'
);
SET @idx_sql := IF(
    @idx_exists = 0,
    'CREATE INDEX idx_submissions_assignment_student_attempt ON submissions (assignment_id, student_id, attempt_number)',
    'SELECT 1'
);
PREPARE stmt FROM @idx_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
