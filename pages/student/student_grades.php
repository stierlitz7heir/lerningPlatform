<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../db/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit;
}

$student_id = $_SESSION['user_id'];
$query = "
    SELECT
        c.id AS course_id,
        c.name AS course_name,
        c.description,
        a.id AS assignment_id,
        a.title AS assignment_title,
        latest.status,
        latest.grade,
        latest.submitted_at
    FROM course_enrollments ce
    JOIN courses c ON c.id = ce.course_id
    JOIN assignments a ON a.course_id = c.id
    LEFT JOIN (
        SELECT ranked.assignment_id, ranked.student_id, ranked.status, ranked.grade, ranked.submitted_at
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
    ) latest ON latest.assignment_id = a.id AND latest.student_id = ce.student_id
    WHERE ce.student_id = ?
    ORDER BY c.name ASC, a.id ASC
";
$stmt = $db->prepare($query);
$stmt->execute([$student_id]);
$rows = $stmt->fetchAll();

$courses = [];
foreach ($rows as $row) {
    $courseId = (int)$row['course_id'];
    if (!isset($courses[$courseId])) {
        $courses[$courseId] = [
            'course_id' => $courseId,
            'course_name' => $row['course_name'],
            'description' => $row['description'],
            'assignments' => [],
            'accepted_count' => 0,
            'next_assignment_id' => 0,
            'completed' => false,
        ];
    }
    $courses[$courseId]['assignments'][] = $row;
}

foreach ($courses as &$course) {
    foreach ($course['assignments'] as $assignment) {
        if (($assignment['status'] ?? '') === 'accepted') {
            $course['accepted_count']++;
        } elseif ($course['next_assignment_id'] === 0) {
            $course['next_assignment_id'] = (int)$assignment['assignment_id'];
        }
    }
    if ($course['next_assignment_id'] === 0 && !empty($course['assignments'])) {
        $course['completed'] = true;
    }
}
unset($course);

include '../../includes/header.php';
?>

<style>
.box {
    border-radius: 12px !important;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.12);
    border: none;
}

.admin-header {
    margin-bottom: 2rem;
    border-bottom: 1px solid #f1f5f9;
    padding-bottom: 1rem;
}

.table thead th {
    color: #64748b;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
}

.course-divider {
    background-color: #f8fafc;
    font-weight: bold;
    color: #475569;
}

.avg-badge {
    background: var(--primary-blue);
    color: #fff;
    padding: 0.2rem 0.6rem;
    border-radius: 6px;
    font-size: 0.85rem;
}
.progress-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1rem;
}
.lesson-pill {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.7rem 0.85rem;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    background: #fff;
    margin-bottom: 0.6rem;
}
</style>

<section class="section">
    <div class="container">
        <div class="columns">

            <div class="column is-3">
                <?php include 'sidebar_student.php'; ?>
            </div>

            <div class="column">
                <div class="box panel-card">
                    <div class="panel-heading">
                        <h1 class="title is-4">Результаты обучения</h1>
                        <p class="is-size-6 has-text-grey">Прогресс по модулям, принятые задания и доступ к следующему шагу.</p>
                    </div>

                    <?php if (empty($courses)): ?>
                        <div class="has-text-centered py-6 has-text-grey">У вас пока нет доступных модулей.</div>
                    <?php else: ?>
                        <div class="progress-grid">
                            <?php foreach ($courses as $course): ?>
                                <article class="box">
                                    <h2 class="title is-5 mb-2"><?= htmlspecialchars($course['course_name']) ?></h2>
                                    <p class="has-text-grey is-size-7 mb-3">
                                        Принято заданий: <strong><?= $course['accepted_count'] ?></strong> из <strong><?= count($course['assignments']) ?></strong>
                                    </p>
                                    <?php if ($course['completed']): ?>
                                        <div class="notification is-success is-light">Модуль завершен. Все задания приняты.</div>
                                    <?php elseif ($course['next_assignment_id'] > 0): ?>
                                        <div class="notification is-link is-light">
                                            Следующее доступное задание:
                                            <a href="student_tasks.php?course_id=<?= (int)$course['course_id'] ?>&assignment_id=<?= (int)$course['next_assignment_id'] ?>">открыть шаг</a>
                                        </div>
                                    <?php endif; ?>

                                    <?php foreach ($course['assignments'] as $assignment): ?>
                                        <?php
                                        $status = $assignment['status'] ?? '';
                                        $icon = '🔒';
                                        $label = 'Заблокировано';
                                        if ($status === 'accepted') {
                                            $icon = '✅';
                                            $label = 'Принято';
                                        } elseif ($status === 'revision') {
                                            $icon = '❌';
                                            $label = 'На доработке';
                                        } elseif ($status === 'on_review' || $status === 'review' || $status === 'submitted') {
                                            $icon = '⏳';
                                            $label = 'На проверке';
                                        } elseif ((int)$assignment['assignment_id'] === (int)$course['next_assignment_id']) {
                                            $icon = '▶';
                                            $label = 'Доступно';
                                        }
                                        ?>
                                        <div class="lesson-pill">
                                            <div>
                                                <div><strong><?= htmlspecialchars($assignment['assignment_title']) ?></strong></div>
                                                <div class="is-size-7 has-text-grey"><?= $label ?></div>
                                            </div>
                                            <div class="has-text-right">
                                                <div><?= $icon ?></div>
                                                <?php if ($assignment['grade'] !== null): ?>
                                                    <div class="is-size-7 has-text-grey">Оценка: <?= (int)$assignment['grade'] ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</section>

<?php include '../../includes/footer.php'; ?>