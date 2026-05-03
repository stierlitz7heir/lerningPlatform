<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../db/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

$course_id = (int)($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];

$stmt = $db->prepare("
    SELECT c.*, COALESCE(u.full_name, 'Преподаватель будет назначен') as teacher
    FROM courses c
    LEFT JOIN users u ON c.teacher_id = u.id
    WHERE c.id = ?
");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) { echo "Курс не найден"; exit; }

$check = $db->prepare("SELECT status FROM course_subscriptions WHERE user_id = ? AND course_id = ?");
$check->execute([$user_id, $course_id]);
$sub = $check->fetch();

include '../../includes/header.php';
?>

<section class="section">
    <div class="container">
        <div class="columns">
            <div class="column is-3">
                <?php include 'sidebar_student.php'; ?>
            </div>
            <div class="column">
                <div class="box panel-heading">
                    <h1 class="title is-4"><?= htmlspecialchars($course['name']) ?></h1>
                    <p class="panel-description is-size-6 has-text-grey">Описание курса и условия участия</p>
                </div>

                <?php if (isset($_GET['success']) && $_GET['success'] === 'pending'): ?>
                    <div class="notification is-success is-light">Заявка отправлена и ожидает подтверждения.</div>
                <?php endif; ?>

                <div class="box">
                    <div class="columns">
                        <div class="column is-8">
                            <div class="content">
                                <h4 class="title is-5">О курсе</h4>
                                <p><?= nl2br(htmlspecialchars((string)($course['description'] ?? ''))) ?></p>
                            </div>
                            <hr>
                            <h4 class="title is-5">Модули программы</h4>
                            <div class="notification is-light">
                                <ul class="ml-4">
                                    <li>Модуль 1: Введение в специальность и основы теории</li>
                                    <li>Модуль 2: Практическая работа с инструментами</li>
                                    <li>Модуль 3: Итоговая аттестация и проект</li>
                                </ul>
                                <p class="is-size-7 has-text-grey mt-2">* Полный список материалов будет доступен после одобрения заявки.</p>
                            </div>
                        </div>
                        <div class="column is-4">
                            <div class="box has-background-light">
                                <figure class="image is-4by3 mb-4">
                                    <?php if (!empty($course['image_path'])): ?>
                                        <img src="<?= htmlspecialchars($course['image_path']) ?>" alt="<?= htmlspecialchars($course['name']) ?>" style="border-radius: 14px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="course-cover-placeholder is-4by3">
                                            <span class="icon is-large"><i class="fas fa-image fa-2x"></i></span>
                                            <span class="is-size-7">Изображение курса не добавлено</span>
                                        </div>
                                    <?php endif; ?>
                                </figure>
                                <p class="is-size-6 mb-2"><strong>Преподаватель:</strong> <?= htmlspecialchars((string)$course['teacher']) ?></p>
                                <p class="is-size-6 mb-3"><strong>Длительность:</strong> <?= htmlspecialchars($course['duration_hours'] ?: 72) ?> часов</p>
                                <p class="is-size-6 mb-5"><strong>Категория:</strong> <?= htmlspecialchars($course['category'] ?: 'Профессиональное обучение') ?></p>

                                <?php if (!$sub): ?>
                                    <form action="subscribe.php" method="POST">
                                        <input type="hidden" name="course_id" value="<?= $course_id ?>">
                                        <button class="button is-link is-fullwidth is-large">Записаться</button>
                                    </form>
                                <?php elseif ($sub['status'] === 'pending'): ?>
                                    <button class="button is-warning is-fullwidth" disabled>Заявка на рассмотрении</button>
                                <?php elseif ($sub['status'] === 'active'): ?>
                                    <a href="student_tasks.php?course_id=<?= $course_id ?>" class="button is-success is-fullwidth">Перейти к обучению</a>
                                <?php elseif ($sub['status'] === 'completed'): ?>
                                    <a href="student_tasks.php?course_id=<?= $course_id ?>" class="button is-link is-fullwidth">Открыть результаты</a>
                                <?php elseif ($sub['status'] === 'rejected'): ?>
                                    <div class="notification is-danger is-light">Заявка отклонена</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include '../../includes/footer.php'; ?>