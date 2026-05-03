<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../db/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $db->prepare("
    SELECT c.*, cs.status AS subscription_status, u.full_name AS teacher_name
    FROM courses c
    JOIN course_subscriptions cs ON c.id = cs.course_id
    LEFT JOIN users u ON u.id = c.teacher_id
    WHERE cs.user_id = ?
    ORDER BY c.name ASC
");
$stmt->execute([$user_id]);
$courses = $stmt->fetchAll();

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
                    <h1 class="title is-4">Моё обучение</h1>
                    <p class="is-size-6 has-text-grey">Текущие курсы и доступ к учебным материалам.</p>
                </div>

                <?php if (empty($courses)): ?>
                    <div class="notification is-light">
                        Вы ещё не записаны ни на один курс. 
                        <a href="courses.php" class="has-text-link">Перейти в каталог</a>
                    </div>
                <?php else: ?>
                    <div class="columns is-multiline">
                        <?php foreach ($courses as $c): ?>
                        <div class="column is-6-tablet is-4-desktop is-3-widescreen">
                            <article class="card course-card admin-compact-card">
                                <div class="card-image">
                                    <figure class="image is-16by9">
                                        <?php if (!empty($c['image_path'])): ?>
                                            <img src="<?= htmlspecialchars($c['image_path']) ?>" alt="<?= htmlspecialchars($c['name']) ?>">
                                        <?php else: ?>
                                            <div class="course-image-placeholder">
                                                <span class="icon is-large"><i class="fas fa-image fa-2x"></i></span>
                                                <span class="is-size-7">Изображение курса не добавлено</span>
                                            </div>
                                        <?php endif; ?>
                                    </figure>
                                </div>
                                <div class="card-content">
                                    <?php
                                    $statusMap = [
                                        'pending' => ['is-warning', 'На рассмотрении'],
                                        'active' => ['is-success', 'В обучении'],
                                        'rejected' => ['is-danger', 'Отклонен'],
                                        'completed' => ['is-link', 'Завершен'],
                                    ];
                                    [$tagClass, $tagText] = $statusMap[$c['subscription_status']] ?? ['is-light', 'Статус не указан'];
                                    ?>
                                    <span class="tag <?= $tagClass ?>"><?= $tagText ?></span>
                                    <h3 class="title is-6 mt-2 mb-2"><?= htmlspecialchars($c['name']) ?></h3>
                                    <p class="has-text-grey is-size-7">
                                        Преподаватель: <strong><?= htmlspecialchars($c['teacher_name'] ?: 'Не назначен') ?></strong>
                                    </p>
                                    <p class="has-text-grey is-size-7">
                                        Категория: <strong><?= htmlspecialchars($c['category'] ?: 'Профессиональное обучение') ?></strong>
                                    </p>
                                </div>
                                <div class="card-content pt-0">
                                    <div class="buttons">
                                        <?php if ($c['subscription_status'] === 'active' || $c['subscription_status'] === 'completed'): ?>
                                            <a href="student_tasks.php?course_id=<?= $c['id'] ?>" class="button is-link is-fullwidth">К заданиям</a>
                                        <?php else: ?>
                                            <button class="button is-light is-fullwidth" disabled>Ожидает доступа</button>
                                        <?php endif; ?>
                                        <a href="course_details.php?id=<?= $c['id'] ?>" class="button is-light is-fullwidth">Подробнее</a>
                                    </div>
                                </div>
                            </article>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php include '../../includes/footer.php'; ?>