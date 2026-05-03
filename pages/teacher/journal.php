<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../db/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit;
}

$viewerRole = $_SESSION['role'];
$viewerId = (int)($_SESSION['user_id'] ?? 0);

if (isset($_GET['action']) && $_GET['action'] === 'history') {
    header('Content-Type: application/json; charset=utf-8');

    $studentId = (int)($_GET['student_id'] ?? 0);
    $assignmentId = (int)($_GET['assignment_id'] ?? 0);

    if (!$studentId || !$assignmentId) {
        echo json_encode(['success' => false, 'message' => 'Некорректный запрос']);
        exit;
    }

    $accessSql = "
        SELECT a.id
        FROM assignments a
        JOIN courses c ON c.id = a.course_id
        WHERE a.id = ?
    ";
    $accessParams = [$assignmentId];
    if ($viewerRole === 'teacher') {
        $accessSql .= " AND c.teacher_id = ?";
        $accessParams[] = $viewerId;
    }
    $accessStmt = $db->prepare($accessSql . " LIMIT 1");
    $accessStmt->execute($accessParams);
    if (!$accessStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Нет доступа к уроку']);
        exit;
    }

    $historyStmt = $db->prepare("
        SELECT
            id,
            attempt_number,
            status,
            text_content,
            COALESCE(file_path, file_url) AS file_path,
            COALESCE(teacher_comment, teacher_feedback, comment) AS teacher_comment,
            submitted_at
        FROM submissions
        WHERE assignment_id = ? AND student_id = ?
        ORDER BY attempt_number DESC, id DESC
    ");
    $historyStmt->execute([$assignmentId, $studentId]);
    $history = $historyStmt->fetchAll();

    echo json_encode(['success' => true, 'history' => $history], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'review_submission') {
    $studentId = (int)($_POST['student_id'] ?? 0);
    $assignmentId = (int)($_POST['assignment_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $feedback = trim($_POST['teacher_comment'] ?? '');
    $gradeRaw = trim((string)($_POST['grade'] ?? ''));
    $grade = $gradeRaw === '' ? null : (int)$gradeRaw;

    $allowedStatuses = ['on_review', 'revision', 'accepted'];
    if (
        !$studentId ||
        !$assignmentId ||
        !in_array($status, $allowedStatuses, true) ||
        ($grade !== null && ($grade < 2 || $grade > 5))
    ) {
        header('Location: journal.php?error=invalid');
        exit;
    }

    $scopeSql = "
        SELECT
            a.id AS assignment_id,
            a.course_id,
            c.teacher_id
        FROM assignments a
        JOIN courses c ON c.id = a.course_id
        WHERE a.id = ?
        LIMIT 1
    ";
    $scopeStmt = $db->prepare($scopeSql);
    $scopeStmt->execute([$assignmentId]);
    $scope = $scopeStmt->fetch();
    if (!$scope) {
        header('Location: journal.php?error=missing_assignment');
        exit;
    }
    if ($viewerRole === 'teacher' && (int)$scope['teacher_id'] !== $viewerId) {
        header('Location: journal.php?error=forbidden');
        exit;
    }

    $submissionStmt = $db->prepare("
        SELECT id
        FROM submissions
        WHERE assignment_id = ? AND student_id = ?
        ORDER BY attempt_number DESC, id DESC
        LIMIT 1
    ");
    $submissionStmt->execute([$assignmentId, $studentId]);
    $latestSubmission = $submissionStmt->fetch();
    if (!$latestSubmission) {
        header('Location: journal.php?error=no_submission');
        exit;
    }

    $db->beginTransaction();
    try {
        $updateSubmission = $db->prepare("
            UPDATE submissions
            SET
                status = ?,
                teacher_comment = ?,
                teacher_feedback = ?,
                comment = ?,
                grade = ?
            WHERE id = ?
        ");
        $feedbackValue = $feedback !== '' ? $feedback : null;
        $updateSubmission->execute([
            $status,
            $feedbackValue,
            $feedbackValue,
            $feedbackValue,
            $grade,
            (int)$latestSubmission['id']
        ]);

        if ($status === 'accepted') {
            $hasAcceptedColumnStmt = $db->prepare("
                SELECT COUNT(*)
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = 'course_enrollments'
                  AND column_name = 'last_accepted_lesson_id'
            ");
            $hasAcceptedColumnStmt->execute();
            $hasAcceptedColumn = (int)$hasAcceptedColumnStmt->fetchColumn() > 0;
            $updateEnrollment = $db->prepare("
                UPDATE course_enrollments
                SET " . ($hasAcceptedColumn ? "last_accepted_lesson_id" : "last_completed_lesson_id") . " = ?
                WHERE course_id = ? AND student_id = ?
            ");
            $updateEnrollment->execute([
                $assignmentId,
                (int)$scope['course_id'],
                $studentId
            ]);
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        header('Location: journal.php?error=save_failed');
        exit;
    }

    header('Location: journal.php?course_id=' . (int)$scope['course_id'] . '&updated=1');
    exit;
}

$courseSql = "
    SELECT c.id, c.name
    FROM courses c
";
$courseParams = [];
if ($viewerRole === 'teacher') {
    $courseSql .= " WHERE c.teacher_id = ?";
    $courseParams[] = $viewerId;
}
$courseSql .= " ORDER BY c.name ASC";
$courseStmt = $db->prepare($courseSql);
$courseStmt->execute($courseParams);
$courses = $courseStmt->fetchAll();

$selectedCourseId = (int)($_GET['course_id'] ?? 0);
if (!$selectedCourseId && !empty($courses)) {
    $selectedCourseId = (int)$courses[0]['id'];
}

$lessons = [];
$students = [];
$statusByPair = [];
$reviewQueue = [];
$statusCounters = [
    'on_review' => 0,
    'revision' => 0,
    'accepted' => 0,
    'empty' => 0,
];

if ($selectedCourseId > 0) {
    $hasAccessSql = "
        SELECT id
        FROM courses
        WHERE id = ?
    ";
    $hasAccessParams = [$selectedCourseId];
    if ($viewerRole === 'teacher') {
        $hasAccessSql .= " AND teacher_id = ?";
        $hasAccessParams[] = $viewerId;
    }
    $hasAccessStmt = $db->prepare($hasAccessSql . " LIMIT 1");
    $hasAccessStmt->execute($hasAccessParams);
    if (!$hasAccessStmt->fetch()) {
        $selectedCourseId = 0;
    }
}

if ($selectedCourseId > 0) {
    $lessonsStmt = $db->prepare("
        SELECT id, title
        FROM assignments
        WHERE course_id = ?
        ORDER BY id ASC
    ");
    $lessonsStmt->execute([$selectedCourseId]);
    $lessons = $lessonsStmt->fetchAll();

    $studentsStmt = $db->prepare("
        SELECT
            ce.student_id,
            u.full_name
        FROM course_enrollments ce
        JOIN users u ON u.id = ce.student_id
        WHERE ce.course_id = ?
        ORDER BY u.full_name ASC
    ");
    $studentsStmt->execute([$selectedCourseId]);
    $students = $studentsStmt->fetchAll();

    if (!empty($lessons) && !empty($students)) {
        $submissionStatusStmt = $db->prepare("
            SELECT
                ranked.assignment_id,
                ranked.student_id,
                ranked.status
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
                JOIN assignments a ON a.id = s.assignment_id
                WHERE a.course_id = ?
            ) ranked
            WHERE ranked.rn = 1
        ");
        $submissionStatusStmt->execute([$selectedCourseId]);
        foreach ($submissionStatusStmt->fetchAll() as $row) {
            $statusByPair[(int)$row['student_id'] . ':' . (int)$row['assignment_id']] = $row['status'];
        }
    }

    $reviewQueueStmt = $db->prepare("
        SELECT
            ranked.assignment_id,
            ranked.student_id,
            ranked.status,
            ranked.submitted_at,
            a.title AS assignment_title,
            u.full_name AS student_name
        FROM (
            SELECT
                s.assignment_id,
                s.student_id,
                s.status,
                s.submitted_at,
                ROW_NUMBER() OVER (
                    PARTITION BY s.assignment_id, s.student_id
                    ORDER BY s.attempt_number DESC, s.id DESC
                ) AS rn
            FROM submissions s
            JOIN assignments a ON a.id = s.assignment_id
            WHERE a.course_id = ?
        ) ranked
        JOIN assignments a ON a.id = ranked.assignment_id
        JOIN users u ON u.id = ranked.student_id
        WHERE ranked.rn = 1
        ORDER BY
            CASE ranked.status
                WHEN 'on_review' THEN 1
                WHEN 'review' THEN 1
                WHEN 'submitted' THEN 1
                WHEN 'revision' THEN 2
                WHEN 'accepted' THEN 3
                ELSE 4
            END,
            ranked.submitted_at DESC,
            u.full_name ASC
    ");
    $reviewQueueStmt->execute([$selectedCourseId]);
    $reviewQueue = $reviewQueueStmt->fetchAll();

    foreach ($reviewQueue as $queueItem) {
        $queueStatus = (string)($queueItem['status'] ?? '');
        if ($queueStatus === 'on_review' || $queueStatus === 'review' || $queueStatus === 'submitted') {
            $statusCounters['on_review']++;
        } elseif ($queueStatus === 'revision') {
            $statusCounters['revision']++;
        } elseif ($queueStatus === 'accepted') {
            $statusCounters['accepted']++;
        } else {
            $statusCounters['empty']++;
        }
    }
}

$statusLabelMap = [
    'on_review' => 'На проверке',
    'revision' => 'Доработка',
    'accepted' => 'Принято',
];

include '../../includes/header.php';
?>

<section class="section">
    <div class="container">
        <div class="columns">
            <div class="column is-3">
                <?php if ($viewerRole === 'admin'): ?>
                    <?php include '../admin/sidebar.php'; ?>
                <?php else: ?>
                    <?php include 'sidebar_teacher.php'; ?>
                <?php endif; ?>
            </div>
            <div class="column">
                <div class="box ws-control-center">
                    <div class="level mb-4">
                        <div class="level-left">
                            <div>
                                <h1 class="title is-4">Журнал и проверка заданий</h1>
                                <p class="panel-description is-size-6 has-text-grey">Один экран для очереди проверки, статусов уроков и истории попыток.</p>
                            </div>
                        </div>
                    </div>

                    <?php if (isset($_GET['updated'])): ?>
                        <div class="notification is-success is-light">Статус попытки обновлен.</div>
                    <?php endif; ?>
                    <?php if (isset($_GET['error'])): ?>
                        <div class="notification is-danger is-light">Не удалось обновить статус. Проверьте доступ и данные.</div>
                    <?php endif; ?>

                    <form method="GET" class="mb-4">
                        <div class="field is-grouped is-align-items-flex-end">
                            <div class="control is-expanded">
                                <label class="label">Курс</label>
                                <div class="select is-fullwidth">
                                    <select name="course_id" required>
                                        <?php foreach ($courses as $course): ?>
                                            <option value="<?= (int)$course['id'] ?>" <?= (int)$course['id'] === $selectedCourseId ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($course['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="control">
                                <button class="button is-info" type="submit">Показать</button>
                            </div>
                        </div>
                    </form>

                    <?php if (empty($courses)): ?>
                        <div class="notification is-light">Нет доступных курсов для журнала.</div>
                    <?php elseif (empty($lessons)): ?>
                        <div class="notification is-light">Для выбранного курса пока нет уроков.</div>
                    <?php elseif (empty($students)): ?>
                        <div class="notification is-light">Для выбранного курса пока нет зачисленных студентов.</div>
                    <?php else: ?>
                        <div class="ws-summary-grid mb-5">
                            <article class="ws-summary-card">
                                <span class="ws-summary-label">На проверке</span>
                                <strong class="ws-summary-value"><?= (int)$statusCounters['on_review'] ?></strong>
                            </article>
                            <article class="ws-summary-card">
                                <span class="ws-summary-label">На доработке</span>
                                <strong class="ws-summary-value"><?= (int)$statusCounters['revision'] ?></strong>
                            </article>
                            <article class="ws-summary-card">
                                <span class="ws-summary-label">Принято</span>
                                <strong class="ws-summary-value"><?= (int)$statusCounters['accepted'] ?></strong>
                            </article>
                            <article class="ws-summary-card">
                                <span class="ws-summary-label">Без попыток</span>
                                <strong class="ws-summary-value"><?= max(0, count($students) * count($lessons) - count($reviewQueue)) ?></strong>
                            </article>
                        </div>

                        <div class="box ws-review-queue">
                            <div class="is-flex is-justify-content-space-between is-align-items-center mb-4">
                                <div>
                                    <h2 class="title is-5 mb-1">Очередь проверки</h2>
                                    <p class="is-size-7 has-text-grey">Сначала показываются работы, которые ждут проверки или возвращены на доработку.</p>
                                </div>
                            </div>

                            <?php if (empty($reviewQueue)): ?>
                                <div class="notification is-light">По этому курсу пока нет отправленных работ.</div>
                            <?php else: ?>
                                <div class="ws-queue-list">
                                    <?php foreach ($reviewQueue as $queueItem): ?>
                                        <?php
                                        $queueStatus = (string)($queueItem['status'] ?? '');
                                        $queueStatusLabel = $statusLabelMap[$queueStatus] ?? 'Нет попыток';
                                        $queueTagClass = 'is-light';
                                        if ($queueStatus === 'on_review' || $queueStatus === 'review' || $queueStatus === 'submitted') {
                                            $queueTagClass = 'is-warning';
                                        } elseif ($queueStatus === 'revision') {
                                            $queueTagClass = 'is-danger';
                                        } elseif ($queueStatus === 'accepted') {
                                            $queueTagClass = 'is-success';
                                        }
                                        ?>
                                        <article class="ws-queue-card">
                                            <div>
                                                <p class="ws-queue-title"><?= htmlspecialchars($queueItem['student_name']) ?></p>
                                                <p class="is-size-7 has-text-grey">
                                                    <?= htmlspecialchars($queueItem['assignment_title']) ?>
                                                    <?php if (!empty($queueItem['submitted_at'])): ?>
                                                        · <?= htmlspecialchars(date('d.m.Y H:i', strtotime($queueItem['submitted_at']))) ?>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                            <div class="is-flex is-align-items-center" style="gap:0.75rem;">
                                                <span class="tag <?= $queueTagClass ?>"><?= htmlspecialchars($queueStatusLabel) ?></span>
                                                <button
                                                    type="button"
                                                    class="button is-small is-link is-light ws-open-history"
                                                    data-student-id="<?= (int)$queueItem['student_id'] ?>"
                                                    data-assignment-id="<?= (int)$queueItem['assignment_id'] ?>"
                                                    data-student-name="<?= htmlspecialchars($queueItem['student_name'], ENT_QUOTES) ?>"
                                                    data-lesson-title="<?= htmlspecialchars($queueItem['assignment_title'], ENT_QUOTES) ?>"
                                                    data-status-label="<?= htmlspecialchars($queueStatusLabel, ENT_QUOTES) ?>"
                                                >
                                                    Открыть
                                                </button>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <h2 class="title is-5 mb-3">Полный журнал по урокам</h2>
                        <div class="table-container">
                            <table class="table is-fullwidth ws-journal-table">
                                <thead>
                                    <tr>
                                        <th>Студент</th>
                                        <?php foreach ($lessons as $lesson): ?>
                                            <th class="has-text-centered" title="<?= htmlspecialchars($lesson['title']) ?>">
                                                Урок <?= (int)$lesson['id'] ?>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($student['full_name']) ?></strong></td>
                                            <?php foreach ($lessons as $lesson): ?>
                                                <?php
                                                $key = (int)$student['student_id'] . ':' . (int)$lesson['id'];
                                                $status = $statusByPair[$key] ?? null;
                                                $dotClass = 'dot-empty';
                                                if ($status === 'on_review' || $status === 'review' || $status === 'submitted') {
                                                    $dotClass = 'dot-on-review';
                                                } elseif ($status === 'revision') {
                                                    $dotClass = 'dot-revision';
                                                } elseif ($status === 'accepted') {
                                                    $dotClass = 'dot-accepted';
                                                }
                                                ?>
                                                <td class="has-text-centered">
                                                    <button
                                                        type="button"
                                                        class="ws-status-dot <?= $dotClass ?> ws-open-history"
                                                        data-student-id="<?= (int)$student['student_id'] ?>"
                                                        data-assignment-id="<?= (int)$lesson['id'] ?>"
                                                        data-student-name="<?= htmlspecialchars($student['full_name'], ENT_QUOTES) ?>"
                                                        data-lesson-title="<?= htmlspecialchars($lesson['title'], ENT_QUOTES) ?>"
                                                        data-status-label="<?= htmlspecialchars($statusLabelMap[$status] ?? 'Нет попыток', ENT_QUOTES) ?>"
                                                    >
                                                        <span class="is-sr-only"><?= htmlspecialchars($statusLabelMap[$status] ?? 'Нет попыток') ?></span>
                                                    </button>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="modal" id="historyModal">
    <div class="modal-background"></div>
    <div class="modal-card">
        <header class="modal-card-head">
            <p class="modal-card-title">История попыток</p>
            <button class="delete" aria-label="close" id="historyModalCloseTop"></button>
        </header>
        <section class="modal-card-body">
            <p class="mb-1" id="historyMeta"></p>
            <p class="mb-3 ws-status-text" id="historyStatusLabel"></p>
            <div id="historyList" class="ws-history-list"></div>
            <hr>
            <form method="POST">
                <input type="hidden" name="action" value="review_submission">
                <input type="hidden" id="modalStudentId" name="student_id" value="">
                <input type="hidden" id="modalAssignmentId" name="assignment_id" value="">
                <div class="field">
                    <label class="label">Оценка статуса</label>
                    <div class="select is-fullwidth">
                        <select name="status" required>
                            <option value="on_review">На проверке</option>
                            <option value="revision">Вернуть на доработку</option>
                            <option value="accepted">Принять</option>
                        </select>
                    </div>
                </div>
                <div class="field">
                    <label class="label">Оценка</label>
                    <div class="select is-fullwidth">
                        <select name="grade">
                            <option value="">Без оценки</option>
                            <option value="5">5</option>
                            <option value="4">4</option>
                            <option value="3">3</option>
                            <option value="2">2</option>
                        </select>
                    </div>
                </div>
                <div class="field">
                    <label class="label">Комментарий преподавателя</label>
                    <textarea class="textarea" name="teacher_comment" rows="4" placeholder="Укажите критерии, замечания и рекомендации"></textarea>
                </div>
                <div class="buttons is-right ws-action-buttons">
                    <button type="submit" class="button is-success" name="status" value="accepted">Принять</button>
                    <button type="submit" class="button is-warning" name="status" value="revision">Вернуть</button>
                    <button type="submit" class="button is-light" name="status" value="on_review">Оставить на проверке</button>
                </div>
            </form>
        </section>
        <footer class="modal-card-foot">
            <button class="button" id="historyModalCloseBottom">Закрыть</button>
        </footer>
    </div>
</div>

<style>
.ws-journal-table th,
.ws-journal-table td {
    vertical-align: middle;
}
.ws-journal-table {
    border-collapse: separate;
    border-spacing: 0;
}
.ws-journal-table thead th {
    background: #f8fafc;
    font-size: 0.8rem;
    text-transform: uppercase;
    color: #64748b;
}
.ws-journal-table tbody tr:nth-child(odd) {
    background: #fafcff;
}
.ws-control-center {
    border-radius: 16px;
    border: 1px solid #eef2f7;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
}
.ws-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 0.9rem;
}
.ws-summary-card {
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #fff;
    padding: 0.9rem 1rem;
}
.ws-summary-label {
    display: block;
    color: #64748b;
    font-size: 0.8rem;
    margin-bottom: 0.35rem;
}
.ws-summary-value {
    font-size: 1.7rem;
    color: #0f172a;
    line-height: 1;
}
.ws-review-queue {
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: none;
    margin-bottom: 1.25rem;
}
.ws-queue-list {
    display: grid;
    gap: 0.75rem;
}
.ws-queue-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    padding: 0.9rem 1rem;
    border: 1px solid #edf2f7;
    border-radius: 14px;
    background: #f8fafc;
}
.ws-queue-title {
    font-weight: 600;
    color: #0f172a;
}
.ws-status-dot {
    border: none;
    width: 22px;
    height: 22px;
    border-radius: 999px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.15s ease;
}
.ws-status-dot:hover {
    transform: scale(1.08);
}
.dot-empty {
    background: #e5e7eb;
}
.dot-on-review {
    background: #f59e0b;
}
.dot-revision {
    background: #ef4444;
}
.dot-accepted {
    background: #22c55e;
}
.ws-history-list {
    max-height: 320px;
    overflow: auto;
    padding-right: 4px;
}
.ws-history-card {
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 0.85rem;
    margin-bottom: 0.8rem;
    background: #f8fafc;
}
.ws-history-user,
.ws-history-teacher {
    border-radius: 8px;
    padding: 0.6rem 0.75rem;
    margin-top: 0.55rem;
}
.ws-history-user {
    background: #eef2ff;
}
.ws-history-teacher {
    background: #ecfeff;
}
.ws-status-text {
    color: #475569;
    font-size: 0.9rem;
}
@media screen and (max-width: 768px) {
    .ws-queue-card {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>

<script>
const modalEl = document.getElementById('historyModal');
const historyMetaEl = document.getElementById('historyMeta');
const historyStatusLabelEl = document.getElementById('historyStatusLabel');
const historyListEl = document.getElementById('historyList');
const studentIdInput = document.getElementById('modalStudentId');
const assignmentIdInput = document.getElementById('modalAssignmentId');

function closeHistoryModal() {
    modalEl.classList.remove('is-active');
}

function renderHistoryItem(item) {
    const submittedAt = item.submitted_at ? new Date(item.submitted_at).toLocaleString('ru-RU') : '—';
    const textContent = item.text_content ? item.text_content.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>') : '—';
    const feedback = item.teacher_comment ? item.teacher_comment.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>') : '';
    const fileLink = item.file_path ? `<a href="../../${item.file_path}" target="_blank">Открыть файл</a>` : '—';
    return `
        <div class="ws-history-card">
            <p><strong>Попытка #${item.attempt_number}</strong> · Статус: <strong>${item.status}</strong></p>
            <p class="is-size-7 has-text-grey mb-2">${submittedAt}</p>
            <div class="ws-history-user">
                <p><strong>Ответ студента</strong></p>
                <p>${textContent}</p>
                <p><strong>Файл:</strong> ${fileLink}</p>
            </div>
            ${feedback ? `<div class="ws-history-teacher"><p><strong>Ответ преподавателя</strong></p><p>${feedback}</p></div>` : ''}
        </div>
    `;
}

async function openHistory(studentId, assignmentId, studentName, lessonTitle, statusLabel) {
    studentIdInput.value = studentId;
    assignmentIdInput.value = assignmentId;
    historyMetaEl.textContent = `${studentName} · ${lessonTitle}`;
    historyStatusLabelEl.textContent = `Текущий статус: ${statusLabel}`;
    historyListEl.innerHTML = 'Загрузка...';

    const url = `journal.php?action=history&student_id=${encodeURIComponent(studentId)}&assignment_id=${encodeURIComponent(assignmentId)}`;
    const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await response.json();

    if (!data.success) {
        historyListEl.innerHTML = '<div class="notification is-danger is-light">Не удалось загрузить историю.</div>';
        modalEl.classList.add('is-active');
        return;
    }

    if (!data.history || data.history.length === 0) {
        historyListEl.innerHTML = '<div class="notification is-light">Попыток пока нет.</div>';
    } else {
        historyListEl.innerHTML = data.history.map(renderHistoryItem).join('');
    }
    modalEl.classList.add('is-active');
}

document.querySelectorAll('.ws-open-history').forEach((btn) => {
    btn.addEventListener('click', () => {
        openHistory(
            btn.dataset.studentId,
            btn.dataset.assignmentId,
            btn.dataset.studentName,
            btn.dataset.lessonTitle,
            btn.dataset.statusLabel
        ).catch(() => {
            historyListEl.innerHTML = '<div class="notification is-danger is-light">Ошибка загрузки.</div>';
            modalEl.classList.add('is-active');
        });
    });
});

document.getElementById('historyModalCloseTop').addEventListener('click', closeHistoryModal);
document.getElementById('historyModalCloseBottom').addEventListener('click', closeHistoryModal);
modalEl.querySelector('.modal-background').addEventListener('click', closeHistoryModal);
</script>

<?php include '../../includes/footer.php'; ?>
