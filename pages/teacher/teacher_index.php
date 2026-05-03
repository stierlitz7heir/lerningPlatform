<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../db/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit;
}

$teacher_id = $_SESSION['user_id'];
$stmt_courses = $db->prepare("SELECT id, name FROM courses WHERE teacher_id = ? ORDER BY name ASC");
$stmt_courses->execute([$teacher_id]);
$my_courses = $stmt_courses->fetchAll();

$stmt_students = $db->prepare("
    SELECT COUNT(DISTINCT cs.user_id)
    FROM course_subscriptions cs
    JOIN courses c ON c.id = cs.course_id
    WHERE c.teacher_id = ? AND cs.status IN ('active', 'completed')
");
$stmt_students->execute([$teacher_id]);
$studentsTotal = (int)$stmt_students->fetchColumn();

include '../../includes/header.php';
?>

<section class="section">
    <div class="container">
        <div class="columns">
            <div class="column is-3">
                <?php include 'sidebar_teacher.php'; ?>
            </div>
            <div class="column">
                <div class="box panel-card panel-heading">
                    <h1 class="title is-4">
                        Добро пожаловать, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Преподаватель') ?>! 👋
                    </h1>
                    <p class="is-size-6 has-text-grey">Панель управления преподавателя</p>
                </div>

                <div class="columns">
                    <div class="column">
                        <div class="box">
                            <h2 class="title is-5">
                                <i class="fas fa-book mr-2"></i>Мои дисциплины
                            </h2>
                            <hr>
                            <?php if (!empty($my_courses)): ?>
                                <div class="tags">
                                    <?php foreach($my_courses as $c): ?>
                                        <span class="tag is-info is-medium"><?= htmlspecialchars($c['name']) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="has-text-grey">Дисциплины ещё не назначены</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="column">
                        <div class="box">
                            <h2 class="title is-5">
                                <i class="fas fa-users mr-2"></i>Мои студенты
                            </h2>
                            <hr>
                            <?php if ($studentsTotal > 0): ?>
                                <p class="title is-3 mb-2"><?= $studentsTotal ?></p>
                                <p class="has-text-grey">Активных слушателей по вашим дисциплинам</p>
                                <a href="teacher_groups.php" class="button is-light mt-4">Открыть список</a>
                            <?php else: ?>
                                <p class="has-text-grey">Пока нет активных слушателей</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include '../../includes/footer.php'; ?>