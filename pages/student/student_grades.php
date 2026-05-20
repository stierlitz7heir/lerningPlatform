<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../db/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

$student_id = (int)$_SESSION['user_id'];

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
            'resume_assignment_id' => 0,
            'completed' => false,
            'grades_sum' => 0,
            'grades_n' => 0,
        ];
    }
    $courses[$courseId]['assignments'][] = $row;
}

$resumeStmt = $db->prepare("
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

$lastLessonStmt = $db->prepare("
    SELECT id FROM assignments WHERE course_id = ? ORDER BY id DESC LIMIT 1
");

foreach ($courses as $cid => &$course) {
    $n = count($course['assignments']);
    foreach ($course['assignments'] as $assignment) {
        if (($assignment['status'] ?? '') === 'accepted') {
            $course['accepted_count']++;
        }
        if ($assignment['grade'] !== null && $assignment['grade'] !== '') {
            $course['grades_sum'] += (int)$assignment['grade'];
            $course['grades_n']++;
        }
    }
    $course['completed'] = $n > 0 && $course['accepted_count'] >= $n;

    $resumeStmt->execute([$student_id, $cid]);
    $resumeId = (int)$resumeStmt->fetchColumn();
    if ($resumeId <= 0 && $n > 0) {
        $lastLessonStmt->execute([$cid]);
        $resumeId = (int)$lastLessonStmt->fetchColumn();
    }
    $course['resume_assignment_id'] = $resumeId;
}
unset($course);

include '../../includes/header.php';
?>

<section class="section student-grades-page">
    <div class="container">
        <div class="columns">
            <div class="column is-3">
                <?php include 'sidebar_student.php'; ?>
            </div>
            <div class="column">
                <div class="box panel-card">
                    <div class="panel-heading">
                        <h1 class="title is-4">Результаты обучения</h1>
                        <p class="is-size-6 has-text-grey">Статусы заданий и баллы совпадают с журналом преподавателя.</p>
                    </div>

                    <?php if (empty($courses)): ?>
                        <div class="has-text-centered py-6 has-text-grey">У вас пока нет зачисленных программ.</div>
                    <?php else: ?>
                        <div class="student-grades-grid">
                            <?php foreach ($courses as $course): ?>
                                <?php
                                $avg = $course['grades_n'] > 0
                                    ? round($course['grades_sum'] / $course['grades_n'], 1)
                                    : null;
                                ?>
                                <article class="box student-grades-course-card">
                                    <div class="student-grades-course-head">
                                        <h2 class="title is-5 mb-1"><?= htmlspecialchars($course['course_name']) ?></h2>
                                        <div class="student-grades-meta is-size-7">
                                            <span>Принято: <strong><?= (int)$course['accepted_count'] ?></strong> / <?= count($course['assignments']) ?></span>
                                            <?php if ($avg !== null): ?>
                                                <span class="student-grades-avg">Средний балл: <strong><?= htmlspecialchars((string)$avg) ?></strong></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php if ($course['completed']): ?>
                                        <div class="notification is-success is-light is-size-7 mb-3">Все задания приняты.</div>
                                    <?php elseif ($course['resume_assignment_id'] > 0): ?>
                                        <div class="notification is-link is-light is-size-7 mb-3">
                                            <a href="student_tasks.php?course_id=<?= (int)$course['course_id'] ?>&assignment_id=<?= (int)$course['resume_assignment_id'] ?>">Перейти к текущему заданию</a>
                                        </div>
                                    <?php endif; ?>

                                    <ul class="student-grades-list">
                                        <?php foreach ($course['assignments'] as $assignment): ?>
                                            <?php
                                            $aid = (int)$assignment['assignment_id'];
                                            $status = (string)($assignment['status'] ?? '');
                                            $resumeId = (int)$course['resume_assignment_id'];
                                            $tagClass = 'is-light';
                                            $label = 'Не начато';
                                            if ($status === 'accepted') {
                                                $tagClass = 'is-success';
                                                $label = 'Принято';
                                            } elseif ($status === 'revision') {
                                                $tagClass = 'is-danger';
                                                $label = 'На доработке';
                                            } elseif ($status === 'on_review' || $status === 'review' || $status === 'submitted') {
                                                $tagClass = 'is-warning';
                                                $label = 'На проверке';
                                            } elseif ($aid === $resumeId && !$course['completed']) {
                                                $tagClass = 'is-info';
                                                $label = 'Открыто для сдачи';
                                            } elseif ($status === '') {
                                                $tagClass = 'is-light';
                                                $label = 'Ожидает очереди';
                                            }
                                            $g = $assignment['grade'];
                                            $hasGrade = $g !== null && $g !== '';
                                            ?>
                                            <li class="student-grades-row">
                                                <div class="student-grades-row-main">
                                                    <span class="student-grades-title"><?= htmlspecialchars($assignment['assignment_title']) ?></span>
                                                    <span class="tag <?= $tagClass ?>"><?= htmlspecialchars($label) ?></span>
                                                </div>
                                                <?php if ($hasGrade): ?>
                                                    <span class="student-grades-ball"><?= (int)$g ?></span>
                                                <?php else: ?>
                                                    <span class="student-grades-ball is-muted">—</span>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
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
