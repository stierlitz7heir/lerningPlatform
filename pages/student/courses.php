<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../db/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $db->prepare(
    "SELECT c.*, u.full_name as teacher_name, cs.id as sub_id, cs.status as sub_status
     FROM courses c
     LEFT JOIN users u ON c.teacher_id = u.id
     LEFT JOIN course_subscriptions cs ON cs.course_id = c.id AND cs.user_id = ?
     WHERE c.status = 'Активный'
     ORDER BY c.id DESC"
);
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
                <div class="level mb-5">
                    <div class="level-left">
                        <div>
                            <h1 class="title is-4">Каталог программ</h1>
                            <p class="panel-description is-size-6 has-text-grey">Выберите программу и подайте заявку на обучение.</p>
                        </div>
                    </div>
                    <div class="level-right">
                        <span class="tag is-info is-medium">Всего курсов: <?= count($courses) ?></span>
                    </div>
                </div>

                <?php if (isset($_GET['success']) && $_GET['success'] === 'pending'): ?>
                    <div class="notification is-success is-light">Заявка создана и отправлена на рассмотрение.</div>
                <?php endif; ?>

                <div class="columns is-multiline">
            <?php foreach ($courses as $c): ?>
            <div class="column is-6-tablet is-4-desktop ">
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
                        $statusClass = 'is-light';
                        $statusText = 'Доступен';
                        if ($c['sub_id']) {
                            switch ($c['sub_status']) {
                                case 'pending':
                                    $statusClass = 'is-warning';
                                    $statusText = 'На рассмотрении';
                                    break;
                                case 'active':
                                    $statusClass = 'is-success';
                                    $statusText = 'Записан';
                                    break;
                                case 'rejected':
                                    $statusClass = 'is-danger';
                                    $statusText = 'Отклонена';
                                    break;
                                case 'completed':
                                    $statusClass = 'is-link';
                                    $statusText = 'Завершен';
                                    break;
                            }
                        }
                        ?>
                        <span class="tag <?= $statusClass ?>"><?= $statusText ?></span>
                        <h3 class="title is-6 mt-2 mb-2"><?= htmlspecialchars($c['name']) ?></h3>
                        <p class="has-text-grey is-size-7">
                            Преподаватель: <strong><?= htmlspecialchars($c['teacher_name'] ?: 'ЭПК') ?></strong>
                        </p>
                        <p class="has-text-grey is-size-7">
                            Вид программы: <strong><?= htmlspecialchars($c['category'] ?: 'Профессиональное обучение') ?></strong>
                        </p>
                        <p class="has-text-grey is-size-7">
                            Объем: <strong><?= htmlspecialchars($c['duration_hours'] ?: 72) ?> ч.</strong>
                        </p>
                        <p class="is-size-7 has-text-grey mt-2 line-clamp-2">
                            <?= htmlspecialchars(mb_strimwidth((string)($c['description'] ?? ''), 0, 120, '...')) ?>
                        </p>
                        <div class="buttons mt-3">
                            <a href="course_details.php?id=<?= $c['id'] ?>" class="button is-light is-fullwidth">Подробнее</a>
                            <?php if ($c['sub_id'] && $c['sub_status'] === 'active'): ?>
                                <a href="student_tasks.php?course_id=<?= $c['id'] ?>" class="button is-success is-fullwidth">Перейти к обучению</a>
                            <?php elseif ($c['sub_id'] && $c['sub_status'] === 'completed'): ?>
                                <a href="student_grades.php" class="button is-link is-fullwidth">Посмотреть результаты</a>
                            <?php elseif ($c['sub_id'] && $c['sub_status'] === 'pending'): ?>
                                <button class="button is-warning is-fullwidth" disabled>На рассмотрении</button>
                            <?php elseif ($c['sub_id'] && $c['sub_status'] === 'rejected'): ?>
                                <button class="button is-danger is-fullwidth" disabled>Заявка отклонена</button>
                            <?php else: ?>
                                <form action="subscribe.php" method="POST" style="width:100%;margin:0;">
                                    <input type="hidden" name="course_id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="button is-link is-fullwidth">Записаться</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            </div>
            <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include '../../includes/footer.php'; ?>