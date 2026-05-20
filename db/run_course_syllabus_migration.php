<?php
/**
 * Однократно: колонка syllabus — текст модулей/структуры для страницы курса.
 * Запуск: php db/run_course_syllabus_migration.php
 */
require_once __DIR__ . '/db.php';

try {
    $chk = $db->prepare("
        SELECT COUNT(*) FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'courses' AND column_name = 'syllabus'
    ");
    $chk->execute();
    if ((int)$chk->fetchColumn() === 0) {
        $db->exec("ALTER TABLE courses ADD COLUMN syllabus TEXT NULL AFTER description");
        echo "OK: column courses.syllabus added\n";
    } else {
        echo "Skip: courses.syllabus already exists\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
