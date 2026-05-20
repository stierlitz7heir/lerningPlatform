<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../db/db.php';

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$role = $_SESSION['role'];
$uid = (int)$_SESSION['user_id'];
$course_id = (int)($_GET['id'] ?? 0);

if ($course_id <= 0) {
    http_response_code(404);
    echo 'Программа не найдена';
    exit;
}

if (!isset($_SESSION['csrf_course'])) {
    $_SESSION['csrf_course'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_course'];
$saveError = null;

$stmt = $db->prepare("
    SELECT c.*, COALESCE(u.full_name, 'Преподаватель будет назначен') AS teacher
    FROM courses c
    LEFT JOIN users u ON c.teacher_id = u.id
    WHERE c.id = ?
");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) {
    http_response_code(404);
    echo 'Программа не найдена';
    exit;
}

$canEdit = ($role === 'admin')
    || ($role === 'teacher' && (int)($course['teacher_id'] ?? 0) === $uid);

$canView = false;
if ($role === 'admin') {
    $canView = true;
} elseif ($role === 'teacher') {
    $canView = (int)($course['teacher_id'] ?? 0) === $uid;
} elseif ($role === 'student') {
    if (($course['status'] ?? '') === 'Активный') {
        $canView = true;
    } else {
        $subChk = $db->prepare('SELECT id FROM course_subscriptions WHERE user_id = ? AND course_id = ? LIMIT 1');
        $subChk->execute([$uid, $course_id]);
        $canView = (bool)$subChk->fetch();
    }
}

if (!$canView) {
    http_response_code(403);
    echo 'Доступ к этой странице запрещён';
    exit;
}

$editMode = $canEdit && (($_GET['edit'] ?? '') === '1');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_course_page') {
    if (!$canEdit || !hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        echo 'Недостаточно прав или устарела форма';
        exit;
    }

    $name = trim((string)($_POST['name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $syllabus = trim((string)($_POST['syllabus'] ?? ''));
    $category = trim((string)($_POST['category'] ?? ''));
    $duration_hours = max(1, (int)($_POST['duration_hours'] ?? 72));
    $status = (string)($_POST['status'] ?? $course['status']);
    if (!in_array($status, ['Активный', 'Черновик', 'Архив'], true)) {
        $status = $course['status'];
    }
    if ($role === 'teacher') {
        $status = $course['status'];
    }

    if ($name === '') {
        $saveError = 'Укажите название программы';
    } else {
        $imagePath = trim((string)($_POST['current_image_path'] ?? ''));
        if (!empty($_FILES['cover']['name']) && (int)($_FILES['cover']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $file = $_FILES['cover'];
            if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
                $saveError = 'Изображение больше 5 МБ';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                $allowed = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                    'image/gif' => 'gif',
                ];
                if (!isset($allowed[$mime]) || !is_uploaded_file($file['tmp_name'])) {
                    $saveError = 'Допустимы только JPG, PNG, WebP, GIF';
                } else {
                    $uploadDir = realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'courses';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0775, true);
                    }
                    $fileName = 'course_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
                    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
                    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                        $imagePath = '/images/courses/' . $fileName;
                    } else {
                        $saveError = 'Не удалось сохранить файл';
                    }
                }
            }
        }

        if (empty($saveError)) {
            try {
                if ($role === 'admin') {
                    $up = $db->prepare('
                        UPDATE courses
                        SET name = ?, description = ?, syllabus = ?, category = ?, duration_hours = ?, status = ?, image_path = ?
                        WHERE id = ?
                    ');
                    $up->execute([
                        $name,
                        $description,
                        $syllabus !== '' ? $syllabus : null,
                        $category,
                        $duration_hours,
                        $status,
                        $imagePath !== '' ? $imagePath : ($course['image_path'] ?? null),
                        $course_id,
                    ]);
                } else {
                    $up = $db->prepare('
                        UPDATE courses
                        SET name = ?, description = ?, syllabus = ?, category = ?, duration_hours = ?, image_path = ?
                        WHERE id = ? AND teacher_id = ?
                    ');
                    $up->execute([
                        $name,
                        $description,
                        $syllabus !== '' ? $syllabus : null,
                        $category,
                        $duration_hours,
                        $imagePath !== '' ? $imagePath : ($course['image_path'] ?? null),
                        $course_id,
                        $uid,
                    ]);
                }
                header('Location: course_details.php?id=' . $course_id . '&saved=1');
                exit;
            } catch (Throwable $e) {
                $saveError = 'Ошибка сохранения';
            }
        }
    }
}

$assignStmt = $db->prepare('SELECT id, title FROM assignments WHERE course_id = ? ORDER BY id ASC');
$assignStmt->execute([$course_id]);
$assignments = $assignStmt->fetchAll();

$sub = null;
if ($role === 'student') {
    $check = $db->prepare('SELECT status FROM course_subscriptions WHERE user_id = ? AND course_id = ?');
    $check->execute([$uid, $course_id]);
    $sub = $check->fetch();
}

$syllabusText = trim((string)($course['syllabus'] ?? ''));
$syllabusLines = array_values(array_filter(
    preg_split('/\r\n|\r|\n/', $syllabusText) ?: [],
    static fn ($line) => trim($line) !== ''
));

include __DIR__ . '/../../includes/header.php';

$backHref = $role === 'admin'
    ? '../admin/manage_courses.php'
    : ($role === 'teacher' ? '../teacher/teacher_courses.php' : 'courses.php');
$backLabel = $role === 'admin'
    ? 'К каталогу (админка)'
    : ($role === 'teacher' ? 'Мои программы' : 'Каталог программ');
?>

<section class="section course-details-page">
    <div class="container">
        <div class="columns">
            <div class="column is-3">
                <?php if ($role === 'admin'): ?>
                    <?php include __DIR__ . '/../admin/sidebar.php'; ?>
                <?php elseif ($role === 'teacher'): ?>
                    <?php include __DIR__ . '/../teacher/sidebar_teacher.php'; ?>
                <?php else: ?>
                    <?php include __DIR__ . '/sidebar_student.php'; ?>
                <?php endif; ?>
            </div>
            <div class="column">
                <nav class="breadcrumb mb-4" aria-label="breadcrumbs">
                    <ul>
                        <li><a href="<?= htmlspecialchars($backHref) ?>"><?= htmlspecialchars($backLabel) ?></a></li>
                        <li class="is-active"><a href="#" aria-current="page"><?= htmlspecialchars((string)$course['name']) ?></a></li>
                    </ul>
                </nav>

                <div class="level is-mobile mb-4">
                    <div class="level-left">
                        <div>
                            <h1 class="title is-4 mb-1"><?= htmlspecialchars((string)$course['name']) ?></h1>
                            <p class="is-size-6 has-text-grey">Карточка программы ДПО</p>
                        </div>
                    </div>
                    <div class="level-right">
                        <?php if ($canEdit && !$editMode): ?>
                            <a class="button is-link is-light" href="course_details.php?id=<?= $course_id ?>&amp;edit=1">
                                <span class="icon"><i class="fas fa-pen"></i></span>
                                <span>Редактировать страницу</span>
                            </a>
                        <?php elseif ($canEdit && $editMode): ?>
                            <a class="button is-light" href="course_details.php?id=<?= $course_id ?>">Закрыть редактор</a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (isset($_GET['saved'])): ?>
                    <div class="notification is-success is-light">Изменения сохранены.</div>
                <?php endif; ?>
                <?php if (!empty($saveError)): ?>
                    <div class="notification is-danger is-light"><?= htmlspecialchars($saveError) ?></div>
                <?php endif; ?>

                <?php if (isset($_GET['success']) && $_GET['success'] === 'pending' && $role === 'student'): ?>
                    <div class="notification is-success is-light">Заявка отправлена и ожидает подтверждения.</div>
                <?php endif; ?>

                <?php if ($canEdit && $editMode): ?>
                    <div class="box course-details-edit-box">
                        <h2 class="title is-5">Редактирование</h2>
                        <form method="post" enctype="multipart/form-data" class="course-details-edit-form">
                            <input type="hidden" name="action" value="save_course_page">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="current_image_path" value="<?= htmlspecialchars((string)($course['image_path'] ?? '')) ?>">

                            <div class="field">
                                <label class="label">Название</label>
                                <input class="input" type="text" name="name" required maxlength="255" value="<?= htmlspecialchars((string)$course['name']) ?>">
                            </div>
                            <div class="columns">
                                <div class="column is-6">
                                    <div class="field">
                                        <label class="label">Вид программы</label>
                                        <input class="input" type="text" name="category" maxlength="100" value="<?= htmlspecialchars((string)($course['category'] ?? '')) ?>">
                                    </div>
                                </div>
                                <div class="column is-6">
                                    <div class="field">
                                        <label class="label">Объём, часов</label>
                                        <input class="input" type="number" name="duration_hours" min="1" max="2000" value="<?= (int)($course['duration_hours'] ?? 72) ?>">
                                    </div>
                                </div>
                            </div>
                            <?php if ($role === 'admin'): ?>
                                <div class="field">
                                    <label class="label">Статус в каталоге</label>
                                    <div class="select is-fullwidth">
                                        <select name="status">
                                            <option value="Активный" <?= ($course['status'] ?? '') === 'Активный' ? 'selected' : '' ?>>Активный</option>
                                            <option value="Черновик" <?= ($course['status'] ?? '') === 'Черновик' ? 'selected' : '' ?>>Черновик</option>
                                            <option value="Архив" <?= ($course['status'] ?? '') === 'Архив' ? 'selected' : '' ?>>Архив</option>
                                        </select>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="field">
                                <label class="label">Описание</label>
                                <textarea class="textarea" name="description" rows="10"><?= htmlspecialchars((string)($course['description'] ?? '')) ?></textarea>
                            </div>
                            <div class="field">
                                <label class="label">Модули программы (каждая строка — пункт списка)</label>
                                <textarea class="textarea" name="syllabus" rows="8" placeholder="Модуль 1: …"><?= htmlspecialchars($syllabusText) ?></textarea>
                            </div>
                            <div class="field">
                                <label class="label">Новая обложка (необязательно)</label>
                                <input class="input" type="file" name="cover" accept="image/jpeg,image/png,image/webp,image/gif">
                                <p class="help">JPG, PNG, WebP, GIF, до 5 МБ. Пустое поле — оставить текущее фото.</p>
                            </div>
                            <div class="field is-grouped">
                                <div class="control">
                                    <button type="submit" class="button is-link">Сохранить</button>
                                </div>
                                <div class="control">
                                    <a class="button is-light" href="course_details.php?id=<?= $course_id ?>">Отмена</a>
                                </div>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="box">
                    <div class="columns is-variable is-6">
                        <div class="column is-8">
                            <div class="content">
                                <h2 class="title is-5">О программе</h2>
                                <?php if (trim((string)($course['description'] ?? '')) !== ''): ?>
                                    <div class="course-details-prose"><?= nl2br(htmlspecialchars((string)$course['description'])) ?></div>
                                <?php else: ?>
                                    <p class="has-text-grey">Описание пока не заполнено<?= $canEdit ? ' — нажмите «Редактировать страницу».' : '.' ?></p>
                                <?php endif; ?>
                            </div>

                            <hr>

                            <?php if (!empty($assignments)): ?>
                                <h2 class="title is-5">Уроки на платформе</h2>
                                <ol class="course-details-lesson-list ml-4">
                                    <?php foreach ($assignments as $a): ?>
                                        <li><?= htmlspecialchars((string)$a['title']) ?></li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php endif; ?>

                            <?php if (!empty($syllabusLines)): ?>
                                <h2 class="title is-5 mt-5"><?= !empty($assignments) ? 'Дополнительно: программа' : 'Программа (модули)' ?></h2>
                                <ul class="course-details-lesson-list ml-4">
                                    <?php foreach ($syllabusLines as $line): ?>
                                        <li><?= htmlspecialchars(trim($line)) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php elseif (empty($assignments)): ?>
                                <h2 class="title is-5 mt-5">Структура программы</h2>
                                <p class="has-text-grey is-size-6">
                                    Список модулей появится здесь, когда автор программы заполнит блок «Модули»<?= $canEdit ? ' (кнопка «Редактировать страницу»)' : '' ?> или будут добавлены уроки в курсе.
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="column is-4">
                            <div class="box has-background-light course-details-aside">
                                <figure class="image course-details-cover mb-4">
                                    <?php if (!empty($course['image_path'])): ?>
                                        <img src="<?= htmlspecialchars((string)$course['image_path']) ?>" alt="" style="border-radius: 14px; object-fit: cover; width:100%; aspect-ratio:4/3;">
                                    <?php else: ?>
                                        <div class="course-cover-placeholder is-4by3">
                                            <span class="icon is-large"><i class="fas fa-image fa-2x"></i></span>
                                            <span class="is-size-7">Обложка не загружена</span>
                                        </div>
                                    <?php endif; ?>
                                </figure>
                                <p class="is-size-6 mb-2"><strong>Преподаватель:</strong> <?= htmlspecialchars((string)$course['teacher']) ?></p>
                                <p class="is-size-6 mb-2"><strong>Длительность:</strong> <?= (int)($course['duration_hours'] ?? 72) ?> ч.</p>
                                <p class="is-size-6 mb-4"><strong>Категория:</strong> <?= htmlspecialchars((string)($course['category'] ?: 'Профессиональное обучение')) ?></p>
                                <p class="is-size-7 has-text-grey mb-4">Статус: <strong><?= htmlspecialchars((string)($course['status'] ?? '')) ?></strong></p>

                                <?php if ($role === 'student'): ?>
                                    <?php if (!$sub): ?>
                                        <form action="subscribe.php" method="POST">
                                            <input type="hidden" name="course_id" value="<?= $course_id ?>">
                                            <button type="submit" class="button is-link is-fullwidth is-medium"<?= ($course['status'] ?? '') !== 'Активный' ? ' disabled title="Запись только для активных программ"' : '' ?>>Записаться</button>
                                        </form>
                                    <?php elseif ($sub['status'] === 'pending'): ?>
                                        <button type="button" class="button is-warning is-fullwidth" disabled>Заявка на рассмотрении</button>
                                    <?php elseif ($sub['status'] === 'active'): ?>
                                        <a href="student_tasks.php?course_id=<?= $course_id ?>" class="button is-success is-fullwidth">Перейти к обучению</a>
                                    <?php elseif ($sub['status'] === 'completed'): ?>
                                        <a href="student_grades.php" class="button is-link is-fullwidth">Результаты обучения</a>
                                    <?php elseif ($sub['status'] === 'rejected'): ?>
                                        <div class="notification is-danger is-light">Заявка отклонена</div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
