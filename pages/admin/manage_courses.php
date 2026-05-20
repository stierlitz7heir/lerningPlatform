<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../db/db.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'teacher'])) {
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

// AJAX обработчики
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    // Получить курс для редактирования
    if ($_GET['action'] === 'get' && isset($_GET['id'])) {
        $stmt = $db->prepare("SELECT * FROM courses WHERE id = ?");
        $stmt->execute([(int)$_GET['id']]);
        echo json_encode($stmt->fetch() ?: ['success' => false]);
        exit;
    }

    // Сохранить курс (создать или обновить)
    if ($_GET['action'] === 'save') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) exit(json_encode(['success' => false, 'message' => 'Нет данных']));

        $id = $data['id'] ?? null;
        $name = trim($data['name']);
        $description = trim($data['description']);
        $syllabus = trim((string)($data['syllabus'] ?? ''));
        $category = trim($data['category'] ?? '');
        $image_path = trim($data['image_path'] ?? '');
        $teacher_id = !empty($data['teacher_id']) ? (int)$data['teacher_id'] : null;
        $duration_hours = !empty($data['duration_hours']) ? (int)$data['duration_hours'] : 72;
        $status = $data['status'] ?? 'Активный';

        try {
            if ($id) {
                $stmt = $db->prepare("UPDATE courses SET name=?, description=?, syllabus=?, category=?, image_path=?, teacher_id=?, duration_hours=?, status=? WHERE id=?");
                $stmt->execute([$name, $description, $syllabus !== '' ? $syllabus : null, $category, $image_path ?: null, $teacher_id, $duration_hours, $status, $id]);
            } else {
                $stmt = $db->prepare("INSERT INTO courses (name, description, syllabus, category, image_path, teacher_id, duration_hours, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $description, $syllabus !== '' ? $syllabus : null, $category, $image_path ?: null, $teacher_id, $duration_hours, $status]);
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($_GET['action'] === 'upload_image') {
        if (!isset($_FILES['course_image']) || !is_uploaded_file($_FILES['course_image']['tmp_name'])) {
            echo json_encode(['success' => false, 'message' => 'Файл не получен']);
            exit;
        }

        $file = $_FILES['course_image'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Ошибка загрузки файла']);
            exit;
        }

        $maxBytes = 5 * 1024 * 1024;
        if (($file['size'] ?? 0) > $maxBytes) {
            echo json_encode(['success' => false, 'message' => 'Файл больше 5MB']);
            exit;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        if (!isset($allowed[$mime])) {
            echo json_encode(['success' => false, 'message' => 'Разрешены только изображения JPG/PNG/WEBP/GIF']);
            exit;
        }

        $uploadDir = realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'courses';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $fileName = 'course_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
        $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
        $webPath = '/images/courses/' . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            echo json_encode(['success' => false, 'message' => 'Не удалось сохранить файл']);
            exit;
        }

        echo json_encode(['success' => true, 'path' => $webPath]);
        exit;
    }

    // Архивация / восстановление
    if ($_GET['action'] === 'archive' && isset($_GET['id'])) {
        $stmt = $db->prepare("UPDATE courses SET status = 'Архив' WHERE id = ?");
        $stmt->execute([(int)$_GET['id']]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_GET['action'] === 'restore' && isset($_GET['id'])) {
        $stmt = $db->prepare("UPDATE courses SET status = 'Активный' WHERE id = ?");
        $stmt->execute([(int)$_GET['id']]);
        echo json_encode(['success' => true]);
        exit;
    }
}

// Обычная загрузка страницы
$view = $_GET['view'] ?? 'active';
$q = trim($_GET['q'] ?? '');
$teacher_filter = trim($_GET['teacher_id'] ?? '');
$category_filter = trim($_GET['category'] ?? '');
$duration_filter = trim($_GET['duration'] ?? '');

$sql = "SELECT c.*, u.full_name as teacher_name,
               (
                   SELECT COUNT(*) 
                   FROM course_subscriptions cs 
                   WHERE cs.course_id = c.id AND cs.status IN ('active', 'completed')
               ) as students_count
        FROM courses c 
        LEFT JOIN users u ON c.teacher_id = u.id 
        WHERE " . ($view === 'archived' ? "c.status = 'Архив'" : "c.status != 'Архив'");
$params = [];

if ($q !== '') {
    $sql .= " AND (c.name LIKE ? OR c.description LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($teacher_filter !== '') {
    $sql .= " AND c.teacher_id = ?";
    $params[] = (int)$teacher_filter;
}
if ($category_filter !== '') {
    $sql .= " AND c.category = ?";
    $params[] = $category_filter;
}
if ($duration_filter === 'short') {
    $sql .= " AND c.duration_hours <= 36";
} elseif ($duration_filter === 'medium') {
    $sql .= " AND c.duration_hours BETWEEN 37 AND 108";
} elseif ($duration_filter === 'long') {
    $sql .= " AND c.duration_hours >= 109";
}

$sql .= " ORDER BY c.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$courses = $stmt->fetchAll();

$teachers = $db->query("SELECT id, full_name FROM users WHERE role='teacher' ORDER BY full_name ASC")->fetchAll();
$categories = $db->query("SELECT DISTINCT category FROM courses WHERE category IS NOT NULL AND category != '' ORDER BY category ASC")->fetchAll();

include '../../includes/header.php';
?>

<section class="section dpo-catalog dpo-catalog--admin">
    <div class="container">
        <div class="columns">
            <div class="column is-3">
                <?php include 'sidebar.php'; ?>
            </div>
            <div class="column">
                <header class="dpo-catalog-head dpo-catalog-head--admin">
                    <div class="dpo-catalog-head-text">
                        <h1 class="title is-4 mb-2">Каталог программ ДПО</h1>
                        <p class="dpo-catalog-lead">Создание и редактирование программ, назначение преподавателя, архив.</p>
                    </div>
                    <div class="dpo-catalog-head-actions">
                        <span class="dpo-catalog-count">В списке: <strong><?= count($courses) ?></strong></span>
                        <button type="button" onclick="openModal()" class="button is-link">
                            <span class="icon"><i class="fas fa-plus"></i></span>
                            <span>Новая программа</span>
                        </button>
                    </div>
                </header>

                <div class="tabs is-toggle is-fullwidth mb-5">
                    <ul>
                        <li class="<?= $view !== 'archived' ? 'is-active' : '' ?>"><a href="?view=active">Активные</a></li>
                        <li class="<?= $view === 'archived' ? 'is-active' : '' ?>"><a href="?view=archived">Архив</a></li>
                    </ul>
                </div>

                <form method="GET" class="box mb-5">
                    <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
                    <div class="columns is-multiline">
                        <div class="column is-4">
                            <label class="label">Поиск по программам</label>
                            <input class="input" type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Название или описание">
                        </div>
                        <div class="column is-2">
                            <label class="label">Преподаватель</label>
                            <div class="select is-fullwidth">
                                <select name="teacher_id">
                                    <option value="">Все преподаватели</option>
                                    <?php foreach ($teachers as $t): ?>
                                        <option value="<?= $t['id'] ?>" <?= $teacher_filter === (string)$t['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($t['full_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="column is-3">
                            <label class="label">Вид программы</label>
                            <div class="select is-fullwidth">
                                <select name="category">
                                    <option value="">Все</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat['category']) ?>" <?= $category_filter === $cat['category'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['category']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="column is-3">
                            <label class="label">Объём программы</label>
                            <div class="select is-fullwidth">
                                <select name="duration">
                                    <option value="">Любой</option>
                                    <option value="short" <?= $duration_filter === 'short' ? 'selected' : '' ?>>До 36 часов</option>
                                    <option value="medium" <?= $duration_filter === 'medium' ? 'selected' : '' ?>>37-108 часов</option>
                                    <option value="long" <?= $duration_filter === 'long' ? 'selected' : '' ?>>109+ часов</option>
                                </select>
                            </div>
                        </div>
                        <div class="column is-12">
                            <div class="buttons is-justify-content-flex-end">
                                <button type="submit" class="button is-info">Фильтровать</button>
                                <a href="?view=<?= htmlspecialchars($view) ?>" class="button is-light">Сбросить</a>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="columns is-multiline dpo-catalog-grid">
                    <?php foreach ($courses as $c): ?>
                    <?php
                        $teacherLabel = $c['teacher_name'] ?: 'Не назначен';
                        $initials = dpo_catalog_initials($teacherLabel === 'Не назначен' ? '?' : $teacherLabel);
                        $category = $c['category'] ?: 'Вид не указан';
                        $hours = (int)($c['duration_hours'] ?? 0);
                        $studentsN = (int)($c['students_count'] ?? 0);
                        $descPlain = trim(preg_replace('/\s+/u', ' ', strip_tags((string)($c['description'] ?? ''))));
                        if ($descPlain === '') {
                            $descHtml = '<span class="has-text-grey">Нет описания.</span>';
                        } else {
                            $descHtml = htmlspecialchars(mb_strimwidth($descPlain, 0, 150, '…', 'UTF-8'), ENT_QUOTES, 'UTF-8');
                        }
                        $st = (string)($c['status'] ?? '');
                        if ($st === 'Активный') {
                            $statusClass = 'dpo-admin-status--on';
                            $statusShort = 'Активна';
                        } elseif ($st === 'Черновик') {
                            $statusClass = 'dpo-admin-status--draft';
                            $statusShort = 'Черновик';
                        } elseif ($st === 'Архив') {
                            $statusClass = 'dpo-admin-status--arch';
                            $statusShort = 'В архиве';
                        } else {
                            $statusClass = 'dpo-admin-status--draft';
                            $statusShort = $st !== '' ? htmlspecialchars($st, ENT_QUOTES, 'UTF-8') : '—';
                        }
                    ?>
                    <div class="column is-12-mobile is-6-tablet is-4-desktop is-3-widescreen">
                        <article class="dpo-card">
                            <div class="dpo-card-media">
                                <?php if (!empty($c['image_path'])): ?>
                                    <img src="<?= htmlspecialchars($c['image_path']) ?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <div class="dpo-card-media-placeholder">
                                        <i class="fas fa-graduation-cap"></i>
                                    </div>
                                <?php endif; ?>
                                <span class="dpo-card-status <?= $statusClass ?>"><?= $statusShort ?></span>
                            </div>
                            <div class="dpo-card-body">
                                <div class="dpo-card-author dpo-card-author--static">
                                    <span class="dpo-card-avatar" aria-hidden="true"><?= htmlspecialchars($initials) ?></span>
                                    <span class="dpo-card-author-text">
                                        <span class="dpo-card-author-label">Преподаватель</span>
                                        <span class="dpo-card-author-name"><?= htmlspecialchars($teacherLabel) ?></span>
                                    </span>
                                </div>
                                <h2 class="dpo-card-title"><span><?= htmlspecialchars($c['name']) ?></span></h2>
                                <div class="dpo-card-chips">
                                    <span class="dpo-chip"><?= htmlspecialchars($category) ?></span>
                                    <span class="dpo-chip dpo-chip-muted"><?= $hours ?> ч.</span>
                                    <span class="dpo-chip dpo-chip-muted"><?= $studentsN ?> <?= $studentsN === 1 ? 'студент' : ($studentsN > 1 && $studentsN < 5 ? 'студента' : 'студентов') ?></span>
                                </div>
                                <p class="dpo-card-desc"><?= $descHtml ?></p>
                                    <div class="dpo-card-actions dpo-card-actions--admin">
                                        <a href="../student/course_details.php?id=<?= (int)$c['id'] ?>" class="button is-light is-fullwidth">Страница курса</a>
                                        <button type="button" onclick='editCourse(<?= json_encode($c, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>)' class="button is-info is-light is-fullwidth">Редактировать</button>
                                    <button type="button" onclick="<?= $c['status'] === 'Архив' ? 'restoreCourse(' . (int)$c['id'] . ')' : 'archiveCourse(' . (int)$c['id'] . ')' ?>" class="button <?= $c['status'] === 'Архив' ? 'is-success' : 'is-light' ?> is-fullwidth">
                                        <?= $c['status'] === 'Архив' ? 'Восстановить' : 'В архив' ?>
                                    </button>
                                </div>
                            </div>
                        </article>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($courses)): ?>
                        <div class="column is-12">
                            <div class="dpo-catalog-empty box has-text-centered">
                                <span class="icon is-large has-text-grey-light mb-3"><i class="fas fa-search fa-2x"></i></span>
                                <p class="title is-5">Ничего не найдено</p>
                                <p class="has-text-grey">Измените фильтры или вкладку «Архив».</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
$courseEditorApi = 'manage_courses.php';
$courseEditorShowTeacherPicker = true;
$courseEditorTeachers = $teachers;
include __DIR__ . '/../../includes/course_editor_modal.php';
?>

<script>
// Архивация курса
async function archiveCourse(id) {
    if (!confirm('Отправить курс в архив?')) return;
    const res = await fetch(`manage_courses.php?action=archive&id=${id}`);
    const json = await res.json();
    if (json.success) location.reload();
}

// Восстановление курса
async function restoreCourse(id) {
    if (!confirm('Восстановить курс?')) return;
    const res = await fetch(`manage_courses.php?action=restore&id=${id}`);
    const json = await res.json();
    if (json.success) location.reload();
}
</script>

<?php include '../../includes/footer.php'; ?>