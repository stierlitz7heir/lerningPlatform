<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../db/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit;
}

$teacher_id = $_SESSION['user_id'];

if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    if ($_GET['action'] === 'save') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            echo json_encode(['success' => false, 'message' => 'Нет данных']);
            exit;
        }

        $id = !empty($data['id']) ? (int)$data['id'] : null;
        $name = trim((string)($data['name'] ?? ''));
        $description = trim((string)($data['description'] ?? ''));
        $category = trim((string)($data['category'] ?? ''));
        $image_path = trim((string)($data['image_path'] ?? ''));
        $duration_hours = !empty($data['duration_hours']) ? (int)$data['duration_hours'] : 72;
        $status = in_array(($data['status'] ?? ''), ['Активный', 'Черновик', 'Архив'], true) ? $data['status'] : 'Активный';

        if ($name === '') {
            echo json_encode(['success' => false, 'message' => 'Укажите название программы']);
            exit;
        }

        try {
            if ($id) {
                $stmt = $db->prepare("
                    UPDATE courses
                    SET name = ?, description = ?, category = ?, image_path = ?, duration_hours = ?, status = ?
                    WHERE id = ? AND teacher_id = ?
                ");
                $stmt->execute([$name, $description, $category, $image_path ?: null, $duration_hours, $status, $id, $teacher_id]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO courses (name, description, category, image_path, teacher_id, duration_hours, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $description, $category, $image_path ?: null, $teacher_id, $duration_hours, $status]);
            }
            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
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

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        if (!isset($allowed[$mime])) {
            echo json_encode(['success' => false, 'message' => 'Разрешены только JPG/PNG/WEBP/GIF']);
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

    if (in_array($_GET['action'], ['archive', 'restore'], true) && isset($_GET['id'])) {
        $status = $_GET['action'] === 'archive' ? 'Архив' : 'Активный';
        $stmt = $db->prepare("UPDATE courses SET status = ? WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$status, (int)$_GET['id'], $teacher_id]);
        echo json_encode(['success' => true]);
        exit;
    }
}

$view = $_GET['view'] ?? 'active';
$q = trim($_GET['q'] ?? '');
$category_filter = trim($_GET['category'] ?? '');
$duration_filter = trim($_GET['duration'] ?? '');

$sql = "
    SELECT c.*,
           (
               SELECT COUNT(*)
               FROM course_subscriptions cs
               WHERE cs.course_id = c.id AND cs.status IN ('active', 'completed')
           ) as students_count
    FROM courses c
    WHERE c.teacher_id = ?
      AND " . ($view === 'archived' ? "c.status = 'Архив'" : "c.status != 'Архив'");
$params = [$teacher_id];

if ($q !== '') {
    $sql .= " AND (c.name LIKE ? OR c.description LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
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
$categories = $db->prepare("SELECT DISTINCT category FROM courses WHERE teacher_id = ? AND category IS NOT NULL AND category != '' ORDER BY category ASC");
$categories->execute([$teacher_id]);
$categoryRows = $categories->fetchAll();

include '../../includes/header.php';
?>

<section class="section">
    <div class="container">
        <div class="columns">
            <div class="column is-3">
                <?php include 'sidebar_teacher.php'; ?>
            </div>
            <div class="column">
                <div class="level mb-6">
                    <div class="level-left">
                        <div>
                            <h1 class="title is-4">Программы ДПО</h1>
                            <p class="is-size-6 has-text-grey">Управление только вашими программами и их статусами.</p>
                        </div>
                    </div>
                    <div class="level-right">
                        <button onclick="openModal()" class="button is-link is-medium">
                            <span class="icon"><i class="fas fa-plus"></i></span>
                            <span>Создать программу</span>
                        </button>
                    </div>
                </div>

                <div class="tabs is-toggle is-fullwidth mb-6">
                    <ul>
                        <li class="<?= $view !== 'archived' ? 'is-active' : '' ?>"><a href="?view=active">Активные</a></li>
                        <li class="<?= $view === 'archived' ? 'is-active' : '' ?>"><a href="?view=archived">Архив</a></li>
                    </ul>
                </div>

                <form method="GET" class="box mb-5">
                    <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
                    <div class="columns is-multiline">
                        <div class="column is-5">
                            <label class="label">Поиск по программам</label>
                            <input class="input" type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Название или описание">
                        </div>
                        <div class="column is-4">
                            <label class="label">Вид программы</label>
                            <div class="select is-fullwidth">
                                <select name="category">
                                    <option value="">Все</option>
                                    <?php foreach ($categoryRows as $cat): ?>
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

                <div class="columns is-multiline">
                    <?php foreach ($courses as $c): ?>
                        <div class="column is-6-tablet is-4-desktop">
                            <div class="card course-card admin-compact-card">
                                <div class="card-image">
                                    <figure class="image is-16by9">
                                        <?php if (!empty($c['image_path'])): ?>
                                            <img src="<?= htmlspecialchars($c['image_path']) ?>" alt="<?= htmlspecialchars($c['name']) ?>">
                                        <?php else: ?>
                                            <div class="has-background-light" style="height:100%;display:flex;align-items:center;justify-content:center;">
                                                <i class="fas fa-image fa-3x has-text-grey-lighter"></i>
                                            </div>
                                        <?php endif; ?>
                                    </figure>
                                </div>
                                <div class="card-content">
                                    <span class="tag <?= $c['status'] === 'Активный' ? 'is-success' : 'is-warning' ?>"><?= htmlspecialchars($c['status']) ?></span>
                                    <h3 class="title is-6 mt-2 mb-2"><?= htmlspecialchars($c['name']) ?></h3>
                                    <p class="has-text-grey is-size-7">Студентов: <strong><?= (int)$c['students_count'] ?></strong></p>
                                    <p class="has-text-grey is-size-7">Объём: <strong><?= (int)($c['duration_hours'] ?? 0) ?> ч.</strong></p>
                                    <p class="is-size-7 has-text-grey mt-2 line-clamp-2"><?= htmlspecialchars(substr((string)($c['description'] ?? ''), 0, 120)) ?>...</p>
                                    <div class="buttons mt-3">
                                        <button onclick='editCourse(<?= json_encode($c, JSON_UNESCAPED_UNICODE) ?>)' class="button is-info is-light is-fullwidth">Редактировать</button>
                                        <button onclick="<?= $c['status'] === 'Архив' ? "restoreCourse({$c['id']})" : "archiveCourse({$c['id']})" ?>" class="button <?= $c['status'] === 'Архив' ? 'is-success' : 'is-light' ?> is-fullwidth">
                                            <?= $c['status'] === 'Архив' ? 'Восстановить' : 'В архив' ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($courses)): ?>
                        <div class="column is-12">
                            <div class="notification is-light has-text-centered">По выбранным фильтрам программы не найдены.</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="modal" id="courseModal">
    <div class="modal-background" onclick="closeModal()"></div>
    <div class="modal-card">
        <header class="modal-card-head">
            <p class="modal-card-title" id="modalTitle">Новая программа</p>
            <button class="delete" onclick="closeModal()"></button>
        </header>
        <section class="modal-card-body">
            <input type="hidden" id="courseId">
            <div class="field">
                <label class="label">Название программы</label>
                <input class="input" type="text" id="courseName" required>
            </div>
            <div class="field">
                <label class="label">Описание</label>
                <textarea class="textarea" id="courseDesc" rows="4"></textarea>
            </div>
            <div class="field">
                <label class="label">Картинка курса</label>
                <input type="hidden" id="courseImagePath">
                <div class="mb-3">
                    <figure class="image is-16by9">
                        <img id="courseImagePreview" src="/images/courses/course_56e549e3e5d10d36.gif" alt="Превью курса" style="border-radius:10px; object-fit: cover;">
                    </figure>
                </div>
                <div class="file has-name is-fullwidth">
                    <label class="file-label">
                        <input class="file-input" type="file" id="courseImageFile" accept="image/*">
                        <span class="file-cta">
                            <span class="file-icon"><i class="fas fa-upload"></i></span>
                            <span class="file-label">Выбрать изображение</span>
                        </span>
                        <span class="file-name" id="courseImageFileName">Файл не выбран</span>
                    </label>
                </div>
            </div>
            <div class="field">
                <label class="label">Вид программы</label>
                <input class="input" type="text" id="courseCategory" placeholder="Повышение квалификации / Профпереподготовка">
            </div>
            <div class="field">
                <label class="label">Объём часов</label>
                <input class="input" type="number" id="courseHours" min="1" placeholder="72">
            </div>
            <div class="field">
                <label class="label">Статус</label>
                <div class="select is-fullwidth">
                    <select id="courseStatus">
                        <option value="Активный">Активный</option>
                        <option value="Черновик">Черновик</option>
                        <option value="Архив">Архив</option>
                    </select>
                </div>
            </div>
        </section>
        <footer class="modal-card-foot">
            <button onclick="saveCourse()" class="button is-link is-fullwidth">Сохранить программу</button>
        </footer>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('modalTitle').textContent = 'Новая программа';
    document.getElementById('courseId').value = '';
    document.getElementById('courseName').value = '';
    document.getElementById('courseDesc').value = '';
    document.getElementById('courseCategory').value = '';
    document.getElementById('courseImagePath').value = '';
    document.getElementById('courseImagePreview').src = '/images/courses/course_56e549e3e5d10d36.gif';
    document.getElementById('courseImageFile').value = '';
    document.getElementById('courseImageFileName').textContent = 'Файл не выбран';
    document.getElementById('courseHours').value = 72;
    document.getElementById('courseStatus').value = 'Активный';
    document.getElementById('courseModal').classList.add('is-active');
}

function closeModal() {
    document.getElementById('courseModal').classList.remove('is-active');
}

function editCourse(course) {
    document.getElementById('modalTitle').textContent = 'Редактирование программы';
    document.getElementById('courseId').value = course.id;
    document.getElementById('courseName').value = course.name || '';
    document.getElementById('courseDesc').value = course.description || '';
    document.getElementById('courseCategory').value = course.category || '';
    document.getElementById('courseImagePath').value = course.image_path || '';
    document.getElementById('courseImagePreview').src = course.image_path || '/images/courses/course_56e549e3e5d10d36.gif';
    document.getElementById('courseImageFile').value = '';
    document.getElementById('courseImageFileName').textContent = course.image_path ? 'Текущее изображение' : 'Файл не выбран';
    document.getElementById('courseHours').value = course.duration_hours || 72;
    document.getElementById('courseStatus').value = course.status || 'Активный';
    document.getElementById('courseModal').classList.add('is-active');
}

document.getElementById('courseImageFile').addEventListener('change', function (e) {
    const file = e.target.files && e.target.files[0] ? e.target.files[0] : null;
    document.getElementById('courseImageFileName').textContent = file ? file.name : 'Файл не выбран';
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function (evt) {
        document.getElementById('courseImagePreview').src = evt.target.result;
    };
    reader.readAsDataURL(file);
});

async function uploadCourseImageIfNeeded() {
    const fileInput = document.getElementById('courseImageFile');
    const file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
    if (!file) {
        return document.getElementById('courseImagePath').value || '';
    }
    const formData = new FormData();
    formData.append('course_image', file);
    const response = await fetch('teacher_courses.php?action=upload_image', {
        method: 'POST',
        body: formData
    });
    const json = await response.json();
    if (!json.success) {
        throw new Error(json.message || 'Ошибка загрузки изображения');
    }
    document.getElementById('courseImagePath').value = json.path;
    return json.path;
}

async function saveCourse() {
    let imagePath = '';
    try {
        imagePath = await uploadCourseImageIfNeeded();
    } catch (e) {
        alert('Ошибка загрузки изображения: ' + e.message);
        return;
    }

    const data = {
        id: document.getElementById('courseId').value || null,
        name: document.getElementById('courseName').value.trim(),
        description: document.getElementById('courseDesc').value.trim(),
        category: document.getElementById('courseCategory').value.trim(),
        image_path: imagePath,
        duration_hours: Number(document.getElementById('courseHours').value || 72),
        status: document.getElementById('courseStatus').value
    };

    const response = await fetch('teacher_courses.php?action=save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    const json = await response.json();
    if (json.success) {
        location.reload();
        return;
    }
    alert('Ошибка: ' + (json.message || 'Не удалось сохранить программу'));
}

async function archiveCourse(id) {
    if (!confirm('Отправить курс в архив?')) return;
    const response = await fetch(`teacher_courses.php?action=archive&id=${id}`);
    const json = await response.json();
    if (json.success) {
        location.reload();
    }
}

async function restoreCourse(id) {
    if (!confirm('Восстановить курс?')) return;
    const response = await fetch(`teacher_courses.php?action=restore&id=${id}`);
    const json = await response.json();
    if (json.success) {
        location.reload();
    }
}
</script>

<?php include '../../includes/footer.php'; ?>