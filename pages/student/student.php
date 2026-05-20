<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../db/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

if (!function_exists('dpo_catalog_initials')) {
    function dpo_catalog_initials(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '?';
        }
        $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts !== false && count($parts) >= 2) {
            $a = mb_substr($parts[0], 0, 1, 'UTF-8');
            $b = mb_substr($parts[count($parts) - 1], 0, 1, 'UTF-8');
            return mb_strtoupper($a . $b, 'UTF-8');
        }
        return mb_strtoupper(mb_substr($name, 0, min(2, mb_strlen($name, 'UTF-8')), 'UTF-8'), 'UTF-8');
    }
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

<section class="section dpo-catalog dpo-catalog--my-learning">
    <div class="container">
        <div class="columns">
            <div class="column is-3">
                <?php include 'sidebar_student.php'; ?>
            </div>
            <div class="column">
                <header class="dpo-catalog-head">
                    <div class="dpo-catalog-head-text">
                        <h1 class="title is-4 mb-2">Моё обучение</h1>
                        <p class="dpo-catalog-lead">Программы, на которые вы поданы или уже обучаетесь. Переход к заданиям открыт после одобрения заявки.</p>
                    </div>
                    <div class="dpo-catalog-head-meta">
                        <span class="dpo-catalog-count">Программ: <strong><?= count($courses) ?></strong></span>
                    </div>
                </header>

                <?php if (empty($courses)): ?>
                    <div class="dpo-catalog-empty box has-text-centered">
                        <span class="icon is-large has-text-grey-light mb-3"><i class="fas fa-book-open fa-2x"></i></span>
                        <p class="title is-5">Пока нет программ</p>
                        <p class="has-text-grey mb-4">Выберите курс в каталоге и подайте заявку.</p>
                        <a href="courses.php" class="button is-link">Открыть каталог ДПО</a>
                    </div>
                <?php else: ?>
                    <div class="columns is-multiline dpo-catalog-grid">
                        <?php foreach ($courses as $c): ?>
                            <?php
                            $teacherLabel = $c['teacher_name'] ?: 'Не назначен';
                            $initials = dpo_catalog_initials($teacherLabel === 'Не назначен' ? '?' : $teacherLabel);
                            $category = $c['category'] ?: 'Профессиональное обучение';
                            $hours = (int)($c['duration_hours'] ?: 72);
                            $descPlain = trim(preg_replace('/\s+/u', ' ', strip_tags((string)($c['description'] ?? ''))));
                            if ($descPlain === '') {
                                $descHtml = '<span class="has-text-grey">Описание появится позже.</span>';
                            } else {
                                $descHtml = htmlspecialchars(mb_strimwidth($descPlain, 0, 150, '…', 'UTF-8'), ENT_QUOTES, 'UTF-8');
                            }
                            $sub = (string)($c['subscription_status'] ?? '');
                            $statusClass = 'dpo-status--open';
                            $statusText = 'Доступна запись';
                            if ($sub === 'pending') {
                                $statusClass = 'dpo-status--pending';
                                $statusText = 'На рассмотрении';
                            } elseif ($sub === 'active') {
                                $statusClass = 'dpo-status--active';
                                $statusText = 'В обучении';
                            } elseif ($sub === 'rejected') {
                                $statusClass = 'dpo-status--rejected';
                                $statusText = 'Заявка отклонена';
                            } elseif ($sub === 'completed') {
                                $statusClass = 'dpo-status--done';
                                $statusText = 'Завершено';
                            }
                            ?>
                            <div class="column is-12-mobile is-6-tablet is-4-desktop is-3-widescreen">
                                <article class="dpo-card">
                                    <a href="course_details.php?id=<?= (int)$c['id'] ?>" class="dpo-card-media">
                                        <?php if (!empty($c['image_path'])): ?>
                                            <img src="<?= htmlspecialchars($c['image_path']) ?>" alt="" loading="lazy">
                                        <?php else: ?>
                                            <div class="dpo-card-media-placeholder">
                                                <i class="fas fa-graduation-cap"></i>
                                            </div>
                                        <?php endif; ?>
                                        <span class="dpo-card-status <?= htmlspecialchars($statusClass) ?>"><?= htmlspecialchars($statusText) ?></span>
                                    </a>
                                    <div class="dpo-card-body">
                                        <a href="course_details.php?id=<?= (int)$c['id'] ?>" class="dpo-card-author">
                                            <span class="dpo-card-avatar" aria-hidden="true"><?= htmlspecialchars($initials) ?></span>
                                            <span class="dpo-card-author-text">
                                                <span class="dpo-card-author-label">Преподаватель</span>
                                                <span class="dpo-card-author-name"><?= htmlspecialchars($teacherLabel) ?></span>
                                            </span>
                                        </a>
                                        <h2 class="dpo-card-title">
                                            <a href="course_details.php?id=<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></a>
                                        </h2>
                                        <div class="dpo-card-chips">
                                            <span class="dpo-chip"><?= htmlspecialchars($category) ?></span>
                                            <span class="dpo-chip dpo-chip-muted"><?= $hours ?> ч.</span>
                                        </div>
                                        <p class="dpo-card-desc"><?= $descHtml ?></p>
                                        <div class="dpo-card-actions">
                                            <?php if ($sub === 'active' || $sub === 'completed'): ?>
                                                <a href="student_tasks.php?course_id=<?= (int)$c['id'] ?>" class="button is-link is-fullwidth dpo-card-cta">К заданиям</a>
                                            <?php else: ?>
                                                <button type="button" class="button is-light is-fullwidth" disabled>Ожидает доступа</button>
                                            <?php endif; ?>
                                            <a href="course_details.php?id=<?= (int)$c['id'] ?>" class="dpo-card-link">Подробнее о программе</a>
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
