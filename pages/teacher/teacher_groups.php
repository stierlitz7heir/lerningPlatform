<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../db/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit;
}

$teacher_id = $_SESSION['user_id'];
$selectedCourseId = (int)($_GET['course_id'] ?? 0);

$stmt = $db->prepare("
    SELECT
        c.id,
        c.name,
        c.status,
        COUNT(DISTINCT CASE WHEN cs.status IN ('active', 'completed') THEN cs.user_id END) as total_students
    FROM courses c
    LEFT JOIN course_subscriptions cs ON cs.course_id = c.id
    WHERE c.teacher_id = ?
    GROUP BY c.id, c.name, c.status
    ORDER BY c.name ASC
");
$stmt->execute([$teacher_id]);
$courses = $stmt->fetchAll();

$students = [];
if ($selectedCourseId > 0) {
    $studentsStmt = $db->prepare("
        SELECT u.full_name, u.group_name, cs.status
        FROM course_subscriptions cs
        JOIN courses c ON c.id = cs.course_id
        JOIN users u ON u.id = cs.user_id
        WHERE c.teacher_id = ? AND c.id = ? AND cs.status IN ('active', 'completed')
        ORDER BY u.full_name ASC
    ");
    $studentsStmt->execute([$teacher_id, $selectedCourseId]);
    $students = $studentsStmt->fetchAll();
}

include '../../includes/header.php';
?>

<section class="section">
    <div class="container">
        <div class="columns">
            <div class="column is-3">
                <?php include 'sidebar_teacher.php'; ?>
            </div>
            <div class="column">
                <div class="box panel-card">
                    <div class="panel-heading">
                        <h1 class="title is-4">Мои дисциплины и студенты</h1>
                        <p class="is-size-6 has-text-grey">Сколько человек обучается по каждой дисциплине и кто сейчас зачислен.</p>
                    </div>

                    <table class="table is-fullwidth is-hoverable is-striped">
                        <thead>
                            <tr>
                                <th>Дисциплина</th>
                                <th>Статус</th>
                                <th class="has-text-centered">Студентов</th>
                                <th class="has-text-right">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $course): ?>
                            <tr>
                                <td>
                                    <span class="icon-text">
                                        <span class="icon has-text-link"><i class="fas fa-book"></i></span>
                                        <strong><?= htmlspecialchars($course['name']) ?></strong>
                                    </span>
                                </td>
                                <td><span class="tag is-light"><?= htmlspecialchars($course['status']) ?></span></td>
                                <td class="has-text-centered">
                                    <span class="tag is-info is-medium"><?= (int)$course['total_students'] ?></span>
                                </td>
                                <td class="has-text-right">
                                    <div class="buttons is-right are-small">
                                        <a href="teacher_groups.php?course_id=<?= (int)$course['id'] ?>" class="button is-light">Список</a>
                                        <a href="journal.php?course_id=<?= (int)$course['id'] ?>" class="button is-link is-light">Журнал</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                            <?php if (empty($courses)): ?>
                            <tr>
                                <td colspan="4" class="has-text-centered py-6 has-text-grey">
                                    Пока нет дисциплин, закрепленных за преподавателем.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <?php if ($selectedCourseId > 0): ?>
                        <hr>
                        <h2 class="title is-5 mb-3">Список студентов</h2>
                        <?php if (empty($students)): ?>
                            <div class="notification is-light">По выбранной дисциплине пока нет активных студентов.</div>
                        <?php else: ?>
                            <table class="table is-fullwidth is-hoverable">
                                <thead>
                                    <tr>
                                        <th>ФИО</th>
                                        <th>Группа</th>
                                        <th>Статус</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($student['full_name']) ?></strong></td>
                                            <td><?= htmlspecialchars($student['group_name'] ?: 'Не указана') ?></td>
                                            <td><span class="tag is-light"><?= htmlspecialchars($student['status']) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include '../../includes/footer.php'; ?>