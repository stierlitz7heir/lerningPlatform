<?php
require_once __DIR__ . '/db.php';

$db->beginTransaction();

try {
    $keepLogins = ['admin', 'stierlitz7heir', 'denlym'];
    $placeholders = implode(',', array_fill(0, count($keepLogins), '?'));
    $userStmt = $db->prepare("SELECT id, login FROM users WHERE login IN ($placeholders)");
    $userStmt->execute($keepLogins);
    $users = $userStmt->fetchAll();
    if (count($users) !== 3) {
        throw new RuntimeException('Не найдены все обязательные пользователи: admin, stierlitz7heir, denlym');
    }

    $userByLogin = [];
    foreach ($users as $row) {
        $userByLogin[$row['login']] = (int)$row['id'];
    }

    $adminId = $userByLogin['admin'];
    $studentId = $userByLogin['stierlitz7heir'];
    $teacherId = $userByLogin['denlym'];

    $db->prepare("UPDATE users SET role = 'admin', full_name = 'Администратор платформы', group_name = NULL, group_id = NULL WHERE id = ?")->execute([$adminId]);
    $db->prepare("UPDATE users SET role = 'teacher', full_name = 'Тугушев Денислям Умярович', group_name = NULL, group_id = NULL WHERE id = ?")->execute([$teacherId]);

    $db->exec("DELETE FROM submissions");
    $db->exec("DELETE FROM course_enrollments");
    $db->exec("DELETE FROM course_subscriptions");
    $db->exec("DELETE FROM assignments");
    $db->exec("DELETE FROM courses");
    $db->exec("DELETE FROM groups_table");
    $db->prepare("DELETE FROM users WHERE login NOT IN ($placeholders)")->execute($keepLogins);

    $groupStmt = $db->prepare("
        INSERT INTO groups_table (course_id, name, program, student_count, status)
        VALUES (NULL, ?, ?, ?, ?)
    ");
    $groupStmt->execute(['ИС-414/22', 'Веб-разработка и программирование', 1, 'Активная']);
    $groupId = (int)$db->lastInsertId();

    $db->prepare("UPDATE users SET role = 'student', full_name = 'Кооль Валерий Павлович', group_name = 'ИС-414/22', group_id = ? WHERE id = ?")
        ->execute([$groupId, $studentId]);

    $courseInsert = $db->prepare("
        INSERT INTO courses (name, teacher_id, description, image_path, status, category, duration_hours, price)
        VALUES (?, ?, ?, NULL, ?, ?, ?, ?)
    ");

    $courseInsert->execute([
        'WorldSkills: Веб-разработка PHP',
        $teacherId,
        'Практический трек по PHP, PDO и модульной платформе обучения с поэтапной сдачей уроков.',
        'Активный',
        'Профессиональное обучение',
        72,
        0.00
    ]);
    $courseWeb = (int)$db->lastInsertId();

    $courseInsert->execute([
        'WorldSkills: SQL и аналитика',
        $teacherId,
        'Отработка SQL-запросов, проектирование схемы данных и анализ учебной статистики.',
        'Активный',
        'Профессиональное обучение',
        48,
        0.00
    ]);
    $courseSql = (int)$db->lastInsertId();

    $courseInsert->execute([
        'WorldSkills: Финальный проект',
        $teacherId,
        'Итоговый курс с защитой мини-проекта и проверкой по критериям эксперта.',
        'Черновик',
        'Профессиональное обучение',
        36,
        0.00
    ]);
    $courseFinal = (int)$db->lastInsertId();

    $assignmentInsert = $db->prepare("
        INSERT INTO assignments (course_id, title, file_path, description, due_date, created_at)
        VALUES (?, ?, NULL, ?, ?, NOW())
    ");

    $assignments = [];
    $webLessons = [
        ['WS-1: Настройка проекта', 'Подготовить структуру проекта и проверить авторизацию.', '2026-05-10 23:59:59'],
        ['WS-2: Форма отправки решения', 'Собрать форму отправки, валидацию и загрузку файла.', '2026-05-13 23:59:59'],
        ['WS-3: Журнал преподавателя', 'Сделать матрицу статусов и модальное окно истории попыток.', '2026-05-16 23:59:59'],
        ['WS-4: Sidebar и блокировки', 'Реализовать линейность уроков и статусы доступа.', '2026-05-18 23:59:59'],
    ];
    foreach ($webLessons as $lesson) {
        $assignmentInsert->execute([$courseWeb, $lesson[0], $lesson[1], $lesson[2]]);
        $assignments['web'][] = (int)$db->lastInsertId();
    }

    $sqlLessons = [
        ['SQL-1: Агрегации и отчеты', 'Сформировать отчеты по попыткам и прогрессу студентов.', '2026-05-12 23:59:59'],
        ['SQL-2: Оптимизация запросов', 'Добавить индексы и проверить план выполнения.', '2026-05-15 23:59:59'],
    ];
    foreach ($sqlLessons as $lesson) {
        $assignmentInsert->execute([$courseSql, $lesson[0], $lesson[1], $lesson[2]]);
        $assignments['sql'][] = (int)$db->lastInsertId();
    }

    $finalLessons = [
        ['FP-1: ТЗ и архитектура', 'Подготовить ТЗ и схему модулей проекта.', '2026-05-20 23:59:59'],
    ];
    foreach ($finalLessons as $lesson) {
        $assignmentInsert->execute([$courseFinal, $lesson[0], $lesson[1], $lesson[2]]);
        $assignments['final'][] = (int)$db->lastInsertId();
    }

    $enrollmentInsert = $db->prepare("
        INSERT INTO course_enrollments (course_id, student_id, enrolled_at, grade, last_accepted_lesson_id, last_completed_lesson_id)
        VALUES (?, ?, NOW(), ?, ?, ?)
    ");
    $enrollmentInsert->execute([$courseWeb, $studentId, 84, $assignments['web'][0], $assignments['web'][0]]);
    $enrollmentInsert->execute([$courseSql, $studentId, 91, null, null]);

    $subscriptionInsert = $db->prepare("
        INSERT INTO course_subscriptions (user_id, course_id, created_at, status)
        VALUES (?, ?, NOW(), ?)
    ");
    $subscriptionInsert->execute([$studentId, $courseWeb, 'active']);
    $subscriptionInsert->execute([$studentId, $courseSql, 'completed']);
    $subscriptionInsert->execute([$studentId, $courseFinal, 'pending']);

    $submissionInsert = $db->prepare("
        INSERT INTO submissions (
            assignment_id, student_id, attempt_number, status, text_content,
            file_path, teacher_comment, file_url, teacher_feedback, answer_text,
            comment, grade, submitted_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $submissionInsert->execute([
        $assignments['web'][0],
        $studentId,
        1,
        'accepted',
        'Стартовый проект собран, авторизация и подключение PDO работают.',
        'uploads/submissions/ws_web_l1_a1.pdf',
        'Принято. Архитектура аккуратная.',
        'uploads/submissions/ws_web_l1_a1.pdf',
        'Принято. Архитектура аккуратная.',
        'Стартовый проект собран, авторизация и подключение PDO работают.',
        'Принято. Архитектура аккуратная.',
        92,
        '2026-05-09 14:20:00'
    ]);

    $submissionInsert->execute([
        $assignments['web'][1],
        $studentId,
        1,
        'revision',
        'Сделал форму отправки, но без полной валидации и истории попыток.',
        'uploads/submissions/ws_web_l2_a1.docx',
        'Вернуть на доработку: добавьте серверную проверку и обработку повторных попыток.',
        'uploads/submissions/ws_web_l2_a1.docx',
        'Вернуть на доработку: добавьте серверную проверку и обработку повторных попыток.',
        'Сделал форму отправки, но без полной валидации и истории попыток.',
        'Вернуть на доработку: добавьте серверную проверку и обработку повторных попыток.',
        63,
        '2026-05-11 11:00:00'
    ]);

    $submissionInsert->execute([
        $assignments['web'][1],
        $studentId,
        2,
        'on_review',
        'Добавил серверную валидацию, инкремент попыток и обновил отображение истории.',
        'uploads/submissions/ws_web_l2_a2.docx',
        null,
        'uploads/submissions/ws_web_l2_a2.docx',
        null,
        'Добавил серверную валидацию, инкремент попыток и обновил отображение истории.',
        null,
        null,
        '2026-05-12 16:35:00'
    ]);

    $submissionInsert->execute([
        $assignments['sql'][0],
        $studentId,
        1,
        'accepted',
        'Сформирован отчет по студенту, уроку и статусам с использованием оконных функций.',
        'uploads/submissions/ws_sql_l1_a1.sql',
        'Принято. Запросы корректные.',
        'uploads/submissions/ws_sql_l1_a1.sql',
        'Принято. Запросы корректные.',
        'Сформирован отчет по студенту, уроку и статусам с использованием оконных функций.',
        'Принято. Запросы корректные.',
        95,
        '2026-05-10 18:40:00'
    ]);

    $submissionInsert->execute([
        $assignments['sql'][1],
        $studentId,
        1,
        'on_review',
        'Добавлены индексы и объяснение оптимизации сложного запроса.',
        'uploads/submissions/ws_sql_l2_a1.sql',
        null,
        'uploads/submissions/ws_sql_l2_a1.sql',
        null,
        'Добавлены индексы и объяснение оптимизации сложного запроса.',
        null,
        null,
        '2026-05-13 09:25:00'
    ]);

    $db->commit();

    echo "Seed completed.\n";
    echo "Users kept: admin, stierlitz7heir, denlym\n";
    echo "Courses created: 3\n";
    echo "Assignments created: 7\n";
    echo "Submissions created: 5\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, "Seed failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
