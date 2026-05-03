<?php
require_once __DIR__ . '/db.php';

try {
    $columnExistsStmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = ?
          AND column_name = ?
    ");

    $hasColumn = function (string $table, string $column) use ($columnExistsStmt): bool {
        $columnExistsStmt->execute([$table, $column]);
        return (int)$columnExistsStmt->fetchColumn() > 0;
    };

    if (!$hasColumn('course_enrollments', 'last_accepted_lesson_id')) {
        $db->exec("ALTER TABLE course_enrollments ADD COLUMN last_accepted_lesson_id INT NULL AFTER grade");
    }

    if (!$hasColumn('submissions', 'attempt_number')) {
        $db->exec("ALTER TABLE submissions ADD COLUMN attempt_number INT NOT NULL DEFAULT 1 AFTER student_id");
    }
    if (!$hasColumn('submissions', 'status')) {
        $db->exec("ALTER TABLE submissions ADD COLUMN status ENUM('on_review','revision','accepted') NOT NULL DEFAULT 'on_review' AFTER attempt_number");
    }
    $db->exec("ALTER TABLE submissions MODIFY COLUMN status ENUM('on_review','revision','accepted','submitted','review') NOT NULL DEFAULT 'on_review'");
    if (!$hasColumn('submissions', 'text_content')) {
        $db->exec("ALTER TABLE submissions ADD COLUMN text_content TEXT NULL AFTER status");
    }
    if (!$hasColumn('submissions', 'file_path')) {
        $db->exec("ALTER TABLE submissions ADD COLUMN file_path VARCHAR(255) NULL AFTER text_content");
    }
    if (!$hasColumn('submissions', 'teacher_comment')) {
        $db->exec("ALTER TABLE submissions ADD COLUMN teacher_comment TEXT NULL AFTER file_path");
    }

    $existsStmt = $db->query("
        SELECT COUNT(*)
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'submissions'
          AND index_name = 'idx_submissions_assignment_student_attempt'
    ");
    $exists = (int)$existsStmt->fetchColumn();
    if ($exists === 0) {
        $db->exec("CREATE INDEX idx_submissions_assignment_student_attempt ON submissions (assignment_id, student_id, attempt_number)");
    }

    echo "Migration completed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Migration failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
