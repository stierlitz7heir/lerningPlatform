<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../db/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

$student_id = $_SESSION['user_id'];
$course_id = (int)($_GET['course_id'] ?? 0);
$requested_assignment_id = (int)($_GET['assignment_id'] ?? 0);
$modules = [];
$has_selected_module = false;
$sidebar_lessons = [];
$sidebar_course_id = 0;
$sidebar_current_assignment_id = 0;

try {
    $modulesStmt = $db->prepare("
        SELECT
            ce.course_id,
            c.name AS course_name,
            COALESCE(ce.last_accepted_lesson_id, ce.last_completed_lesson_id) AS last_accepted_lesson_id,
            (
                SELECT COUNT(*)
                FROM assignments a_total
                WHERE a_total.course_id = ce.course_id
            ) AS total_lessons
        FROM course_enrollments ce
        JOIN courses c ON c.id = ce.course_id
        WHERE ce.student_id = ?
        ORDER BY c.name ASC
    ");
    $modulesStmt->execute([$student_id]);
    $modules = $modulesStmt->fetchAll();

    $activeLessonByCourseStmt = $db->prepare("
        SELECT a.id
        FROM assignments a
        LEFT JOIN (
            SELECT ranked.assignment_id, ranked.student_id, ranked.status
            FROM (
                SELECT
                    s.assignment_id,
                    s.student_id,
                    s.status,
                    ROW_NUMBER() OVER (
                        PARTITION BY s.assignment_id, s.student_id
                        ORDER BY s.attempt_number DESC, s.id DESC
                    ) AS rn
                FROM submissions s
            ) ranked
            WHERE ranked.rn = 1
        ) ls ON ls.assignment_id = a.id AND ls.student_id = ?
        WHERE a.course_id = ?
          AND (ls.status IS NULL OR ls.status <> 'accepted')
        ORDER BY a.id ASC
        LIMIT 1
    ");
    $lastLessonByCourseStmt = $db->prepare("
        SELECT id
        FROM assignments
        WHERE course_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");

    foreach ($modules as &$module) {
        $module['course_id'] = (int)$module['course_id'];
        $module['total_lessons'] = (int)$module['total_lessons'];
        $module['completed_lessons'] = 0;
        $module['resume_assignment_id'] = 0;

        if ($module['total_lessons'] > 0) {
            $completedStmt = $db->prepare("
                SELECT COUNT(*) 
                FROM assignments a
                LEFT JOIN (
                    SELECT ranked.assignment_id, ranked.student_id, ranked.status
                    FROM (
                        SELECT
                            s.assignment_id,
                            s.student_id,
                            s.status,
                            ROW_NUMBER() OVER (
                                PARTITION BY s.assignment_id, s.student_id
                                ORDER BY s.attempt_number DESC, s.id DESC
                            ) AS rn
                        FROM submissions s
                    ) ranked
                    WHERE ranked.rn = 1
                ) ls ON ls.assignment_id = a.id AND ls.student_id = ?
                WHERE a.course_id = ? AND ls.status = 'accepted'
            ");
            $completedStmt->execute([$student_id, $module['course_id']]);
            $module['completed_lessons'] = (int)$completedStmt->fetchColumn();

            $activeLessonByCourseStmt->execute([$student_id, $module['course_id']]);
            $module['resume_assignment_id'] = (int)$activeLessonByCourseStmt->fetchColumn();
            if ($module['resume_assignment_id'] <= 0) {
                $lastLessonByCourseStmt->execute([$module['course_id']]);
                $module['resume_assignment_id'] = (int)$lastLessonByCourseStmt->fetchColumn();
            }
        }
    }
    unset($module);

    if ($course_id > 0) {
        foreach ($modules as $module) {
            if ((int)$module['course_id'] === $course_id) {
                $has_selected_module = true;
                break;
            }
        }
        if (!$has_selected_module) {
            $course_id = 0;
        }
    }

    if ($course_id <= 0) {
        $tasks = [];
    } else {
        $has_selected_module = true;
        $query = "
            SELECT
                a.id as assignment_id,
                a.title,
                a.file_path as task_file,
                a.due_date,
                c.name as course_name,
                COALESCE(ls.file_path, ls.file_url) as student_file,
                ls.grade,
                ls.submitted_at,
                ls.status as submission_status
            FROM assignments a
            JOIN courses c ON a.course_id = c.id
            JOIN course_enrollments ce ON c.id = ce.course_id
            LEFT JOIN (
                SELECT ranked.assignment_id, ranked.student_id, ranked.file_path, ranked.file_url, ranked.grade, ranked.submitted_at, ranked.status
                FROM (
                    SELECT
                        s.*,
                        ROW_NUMBER() OVER (
                            PARTITION BY s.assignment_id, s.student_id
                            ORDER BY s.attempt_number DESC, s.id DESC
                        ) AS rn
                    FROM submissions s
                ) ranked
                WHERE ranked.rn = 1
            ) ls ON (a.id = ls.assignment_id AND ls.student_id = ?)
            WHERE ce.student_id = ? AND c.id = ?
            ORDER BY a.id ASC
        ";
        $stmt = $db->prepare($query);
        $stmt->execute([$student_id, $student_id, $course_id]);
        $tasks = $stmt->fetchAll();
    }

    if (!empty($tasks)) {
        $active_assignment_id = 0;
        foreach ($tasks as $task) {
            if (($task['submission_status'] ?? null) !== 'accepted') {
                $active_assignment_id = (int)$task['assignment_id'];
                break;
            }
        }
        if ($active_assignment_id === 0) {
            $active_assignment_id = (int)$tasks[count($tasks) - 1]['assignment_id'];
        }

        if ($requested_assignment_id > 0) {
            $requestedIndex = -1;
            $activeIndex = -1;
            foreach ($tasks as $index => $task) {
                if ((int)$task['assignment_id'] === $requested_assignment_id) {
                    $requestedIndex = $index;
                }
                if ((int)$task['assignment_id'] === $active_assignment_id) {
                    $activeIndex = $index;
                }
            }

            if ($requestedIndex === -1) {
                header("Location: student_tasks.php?course_id={$course_id}&assignment_id={$active_assignment_id}&lock=1");
                exit;
            }
            if ($activeIndex !== -1 && $requestedIndex > $activeIndex) {
                header("Location: student_tasks.php?course_id={$course_id}&assignment_id={$active_assignment_id}&lock=1");
                exit;
            }
            $current_assignment_id = $requested_assignment_id;
        } else {
            $current_assignment_id = $active_assignment_id;
            header("Location: student_tasks.php?course_id={$course_id}&assignment_id={$current_assignment_id}");
            exit;
        }

        $activeIndex = -1;
        foreach ($tasks as $index => $task) {
            if ((int)$task['assignment_id'] === $active_assignment_id) {
                $activeIndex = $index;
                break;
            }
        }
        foreach ($tasks as $index => $task) {
            $task['is_locked'] = $activeIndex !== -1 && $index > $activeIndex;
            $sidebar_lessons[] = $task;
        }
        $sidebar_course_id = $course_id;
        $sidebar_current_assignment_id = $current_assignment_id;
    } else {
        $current_assignment_id = 0;
    }

    $current_task = null;
    foreach ($tasks as $task) {
        if ((int)$task['assignment_id'] === $current_assignment_id) {
            $current_task = $task;
            break;
        }
    }

    $history = [];
    $latestRevisionComment = '';
    if ($current_assignment_id > 0) {
        $historyStmt = $db->prepare("
            SELECT attempt_number, status, text_content, COALESCE(file_path, file_url) AS file_path, COALESCE(teacher_comment, teacher_feedback, comment) AS teacher_comment, submitted_at
            FROM submissions
            WHERE assignment_id = ? AND student_id = ?
            ORDER BY attempt_number DESC, id DESC
        ");
        $historyStmt->execute([$current_assignment_id, $student_id]);
        $history = $historyStmt->fetchAll();
        foreach ($history as $historyItem) {
            if (($historyItem['status'] ?? '') === 'revision') {
                $latestRevisionComment = trim((string)($historyItem['teacher_comment'] ?? ''));
                break;
            }
        }
    }

    $next_assignment_id = 0;
    if ($current_assignment_id > 0) {
        for ($i = 0, $len = count($tasks); $i < $len; $i++) {
            if ((int)$tasks[$i]['assignment_id'] === $current_assignment_id) {
                if (isset($tasks[$i + 1])) {
                    $next_assignment_id = (int)$tasks[$i + 1]['assignment_id'];
                }
                break;
            }
        }
    }

    $can_open_next = $current_task && ($current_task['submission_status'] ?? null) === 'accepted' && $next_assignment_id > 0;
} catch (PDOException $e) {
    die("<div style='padding:20px; background:#fff5f5; color:#c53030; border:1px solid #feb2b2; border-radius:8px; margin:20px; font-family:sans-serif;'>
            <strong>Ошибка базы данных:</strong> " . htmlspecialchars($e->getMessage()) . "
         </div>");
}

include '../../includes/header.php';
?>

<style>
.lesson-shell {
    max-width: 860px;
    margin: 0 auto;
}
.firpo-card {
    border-radius: 16px;
    border: 1px solid #eef2f7;
    background: #ffffff;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
    padding: 1.5rem;
    margin-bottom: 1rem;
}
.firpo-title {
    font-size: 2rem;
    line-height: 1.2;
    margin-bottom: 0.5rem;
}
.firpo-subtle {
    color: #6b7280;
    font-size: 0.9rem;
}
.firpo-badge {
    display: inline-block;
    border-radius: 999px;
    padding: 0.28rem 0.7rem;
    font-size: 0.75rem;
    font-weight: 600;
}
.status-submitted { background: #dbeafe; color: #1e40af; }
.status-review { background: #fef3c7; color: #92400e; }
.status-revision { background: #fee2e2; color: #991b1b; }
.status-accepted { background: #dcfce7; color: #166534; }
.history-item {
    border-radius: 16px;
    background: #f8fafc;
    border: 1px solid #e9eef5;
    padding: 0.85rem;
    margin-bottom: 0.75rem;
}
.history-answer-box {
    border: 1px solid #dbeafe;
    background: #eff6ff;
    border-radius: 10px;
    padding: 0.65rem 0.75rem;
    font-size: 0.8rem;
}
.history-feedback-box {
    border: 1px solid #fde68a;
    background: #fffbeb;
    border-radius: 10px;
    padding: 0.65rem 0.75rem;
    font-size: 0.8rem;
}
.next-step-wrap {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}
.button.is-link,
.button.is-success,
.button.is-light {
    border-radius: 8px;
    font-weight: 600;
}
.module-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 0.9rem;
}
.module-card {
    border-radius: 16px;
    border: 1px solid #eef2f7;
    background: #fff;
    padding: 1rem;
}
</style>

<section class="section">
    <div class="container">
        <div class="columns">
            <div class="column is-3">
                <?php include 'sidebar_student.php'; ?>
            </div>
            <div class="column">
                <div class="lesson-shell">
                    <?php if (isset($_GET['lock']) && $_GET['lock'] === '1'): ?>
                        <div class="notification is-warning is-light firpo-card">
                            Сначала завершите предыдущее задание.
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['upload']) && $_GET['upload'] === 'success'): ?>
                        <div class="notification is-success is-light firpo-card">
                            Решение успешно отправлено.
                        </div>
                    <?php endif; ?>

                    <article class="firpo-card">
                        <h1 class="title is-4 mb-2">Учебные задания</h1>
                        <p class="firpo-subtle mb-3">Сначала выберите модуль, затем система откроет шаг, на котором вы остановились.</p>
                        <?php if (empty($modules)): ?>
                            <p class="firpo-subtle">У вас пока нет активных модулей.</p>
                        <?php else: ?>
                            <div class="module-grid">
                                <?php foreach ($modules as $module): ?>
                                    <div class="module-card">
                                        <h2 class="title is-6 mb-2"><?= htmlspecialchars($module['course_name']) ?></h2>
                                        <p class="firpo-subtle mb-3">
                                            Прогресс: <?= (int)$module['completed_lessons'] ?> / <?= (int)$module['total_lessons'] ?>
                                        </p>
                                        <?php if ((int)$module['total_lessons'] > 0 && (int)$module['resume_assignment_id'] > 0): ?>
                                            <a class="button is-link is-small" href="student_tasks.php?course_id=<?= (int)$module['course_id'] ?>&assignment_id=<?= (int)$module['resume_assignment_id'] ?>">
                                                Открыть модуль
                                            </a>
                                        <?php else: ?>
                                            <button class="button is-light is-small" disabled>Нет уроков</button>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>

                    <?php if (!$has_selected_module): ?>
                        <div class="firpo-card">
                            <p class="firpo-subtle">Выберите модуль выше, чтобы продолжить обучение.</p>
                        </div>
                    <?php elseif (!$tasks || !$current_task): ?>
                        <div class="firpo-card">
                            <p class="firpo-subtle">Активных заданий не найдено.</p>
                        </div>
                    <?php else: ?>
                <?php
                $statusClassMap = [
                    'submitted' => 'status-submitted',
                    'review' => 'status-review',
                    'on_review' => 'status-review',
                    'revision' => 'status-revision',
                    'accepted' => 'status-accepted',
                ];
                $statusTitleMap = [
                    'submitted' => 'Отправлено',
                    'review' => 'Проверяется',
                    'on_review' => 'На проверке',
                    'revision' => 'Доработка',
                    'accepted' => 'Принято',
                ];
                $taskNumber = 1;
                foreach ($tasks as $index => $taskMeta) {
                    if ((int)$taskMeta['assignment_id'] === (int)$current_assignment_id) {
                        $taskNumber = $index + 1;
                        break;
                    }
                }
                ?>
                <article class="firpo-card">
                    <p class="firpo-subtle mb-2"><?= htmlspecialchars($current_task['course_name']) ?></p>
                    <h1 class="firpo-title"><?= htmlspecialchars($current_task['title']) ?></h1>
                    <p class="firpo-subtle mb-3">
                        Шаг <?= (int)$taskNumber ?> из <?= count($tasks) ?> · Срок: <?= date('d.m.Y', strtotime($current_task['due_date'])) ?>
                    </p>

                    <?php if (!empty($current_task['submission_status'])): ?>
                        <span class="firpo-badge <?= $statusClassMap[$current_task['submission_status']] ?? 'status-submitted' ?>">
                            <?= htmlspecialchars($statusTitleMap[$current_task['submission_status']] ?? $current_task['submission_status']) ?>
                        </span>
                    <?php endif; ?>

                    <div class="mt-4 mb-4">
                        <?php if (!empty($current_task['task_file'])): ?>
                            <a href="../../<?= htmlspecialchars($current_task['task_file']) ?>" class="button is-light" target="_blank">
                                Материалы урока
                            </a>
                        <?php else: ?>
                            <span class="firpo-subtle">Файл материалов не приложен.</span>
                        <?php endif; ?>
                    </div>
                </article>

                <article class="firpo-card">
                    <h2 class="title is-5 mb-3">Сдача задания</h2>
                    <?php if (($current_task['submission_status'] ?? '') === 'revision' && $latestRevisionComment !== ''): ?>
                        <div class="notification is-danger is-light">
                            <strong>Требуется доработка:</strong><br>
                            <?= nl2br(htmlspecialchars($latestRevisionComment)) ?>
                        </div>
                    <?php endif; ?>
                    <form action="upload_task.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="assignment_id" value="<?= (int)$current_task['assignment_id'] ?>">
                        <input type="hidden" name="course_id" value="<?= (int)$course_id ?>">
                        <div class="field">
                            <label class="label">Текст решения</label>
                            <textarea class="textarea" name="text_content" rows="5" placeholder="Опишите решение, ключевые шаги и результат"></textarea>
                        </div>
                        <div class="field">
                            <label class="label">Файл решения</label>
                            <input class="input" type="file" name="solution" required>
                        </div>
                        <div class="field mt-4">
                            <button type="submit" class="button is-link">Отправить на проверку</button>
                        </div>
                    </form>
                    <?php if (!empty($current_task['student_file'])): ?>
                        <p class="firpo-subtle mt-2">
                            Последний файл: <a href="../../<?= htmlspecialchars($current_task['student_file']) ?>" target="_blank">Открыть</a>
                        </p>
                    <?php endif; ?>
                    <hr>
                    <h3 class="title is-6 mb-3">Timeline попыток</h3>
                    <?php if (empty($history)): ?>
                        <p class="firpo-subtle">Пока нет отправок по этому уроку.</p>
                    <?php else: ?>
                        <?php foreach ($history as $item): ?>
                            <div class="history-item">
                                <p class="is-size-7 mb-2">
                                    Попытка #<?= (int)$item['attempt_number'] ?> ·
                                    <?= date('d.m.Y H:i', strtotime($item['submitted_at'])) ?>
                                    <span class="firpo-badge <?= $statusClassMap[$item['status']] ?? 'status-submitted' ?>">
                                        <?= htmlspecialchars($statusTitleMap[$item['status']] ?? $item['status']) ?>
                                    </span>
                                </p>
                                <div class="history-answer-box mb-2">
                                    <?= nl2br(htmlspecialchars($item['text_content'] ?? 'Текст ответа не указан')) ?>
                                </div>
                                <?php if (!empty($item['file_path'])): ?>
                                    <p class="is-size-7 mb-2">
                                        <a href="../../<?= htmlspecialchars($item['file_path']) ?>" target="_blank">Файл попытки</a>
                                    </p>
                                <?php endif; ?>
                                <?php if (!empty($item['teacher_comment'])): ?>
                                    <div class="history-feedback-box">
                                        <?= nl2br(htmlspecialchars($item['teacher_comment'])) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </article>

                <div class="firpo-card">
                    <div class="next-step-wrap">
                        <a href="student_tasks.php?course_id=<?= (int)$course_id ?>&assignment_id=<?= (int)$current_assignment_id ?>" class="button is-light">
                            Текущий шаг
                        </a>
                        <?php if ($can_open_next): ?>
                            <a href="student_tasks.php?course_id=<?= (int)$course_id ?>&assignment_id=<?= (int)$next_assignment_id ?>" class="button is-success">
                                Следующий шаг
                            </a>
                        <?php else: ?>
                            <button class="button is-success" disabled>Следующий шаг</button>
                        <?php endif; ?>
                    </div>
                </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include '../../includes/footer.php'; ?>