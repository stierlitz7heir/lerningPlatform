<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../db/db.php';

// Проверка прав доступа
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'teacher')) {
    header("Location: ../login.php");
    exit;
}

// --- AJAX обработчики (delete, get, save) ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if ($_GET['action'] === 'delete' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        if ($id === (int)($_SESSION['user_id'] ?? 0)) {
            echo json_encode(['success' => false, 'message' => 'Нельзя удалить свой аккаунт']);
            exit;
        }
        try {
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_GET['action'] === 'get' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $stmt = $db->prepare("SELECT id, full_name, login, role, group_id FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        echo json_encode($user ?: ['success' => false]);
        exit;
    }
    
    if ($_GET['action'] === 'save') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) exit(json_encode(['success' => false, 'message' => 'Нет данных']));
        
        $id = $data['id'] ?? null;
        $full_name = trim($data['full_name'] ?? '');
        $login = trim($data['login']);
        $role = $data['role'];
        $group_id = null;
        $password = $data['password'] ?? '';

        if ($login === '') {
            echo json_encode(['success' => false, 'message' => 'Логин обязателен']);
            exit;
        }
        if ($full_name === '') {
            $full_name = $login;
        }

        try {
            if ($id) {
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $db->prepare("UPDATE users SET full_name=?, login=?, role=?, group_id=?, password_hash=? WHERE id=?");
                    $stmt->execute([$full_name, $login, $role, $group_id, $hash, $id]);
                } else {
                    $stmt = $db->prepare("UPDATE users SET full_name=?, login=?, role=?, group_id=? WHERE id=?");
                    $stmt->execute([$full_name, $login, $role, $group_id, $id]);
                }
            } else {
                if (empty($password)) $password = '123456'; // дефолтный пароль для новых
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("INSERT INTO users (full_name, login, role, group_id, password_hash) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$full_name, $login, $role, $group_id, $hash]);
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// Обычная загрузка страницы
$search = trim($_GET['search'] ?? '');
$roleFilter = $_GET['role'] ?? '';

$queryStr = "SELECT u.* FROM users u WHERE 1=1";
$params = [];

if ($search !== '') {
    $queryStr .= " AND (u.full_name LIKE ? OR u.login LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($roleFilter !== '') {
    $queryStr .= " AND u.role = ?";
    $params[] = $roleFilter;
}

$queryStr .= " ORDER BY u.full_name ASC";
$stmt = $db->prepare($queryStr);
$stmt->execute($params);
$users = $stmt->fetchAll();

include '../../includes/header.php';
?>

<section class="section">
    <div class="container">
        <div class="columns">
            <div class="column is-3">
                <?php include 'sidebar.php'; ?>
            </div>
            <div class="column">
                <div class="box">
                    <div class="level mb-5">
                        <div class="level-left">
                            <h1 class="title is-4">Контингент ДПО</h1>
                        </div>
                        <div class="level-right">
                            <button class="button is-link is-rounded" onclick="openUserModal()">
                                <i class="fas fa-user-plus mr-2"></i> Добавить пользователя
                            </button>
                        </div>
                    </div>

                    <!-- Фильтры -->
                    <form method="GET" class="mb-5">
                        <div class="field is-grouped">
                            <div class="control is-expanded">
                                <input class="input" type="text" name="search" placeholder="Поиск по псевдониму или логину" value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="control">
                                <div class="select">
                                    <select name="role">
                                        <option value="">Все роли</option>
                                        <option value="student" <?= $roleFilter === 'student' ? 'selected' : '' ?>>Слушатели</option>
                                        <option value="teacher" <?= $roleFilter === 'teacher' ? 'selected' : '' ?>>Преподаватели</option>
                                        <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Операторы</option>
                                    </select>
                                </div>
                            </div>
                            <div class="control">
                                <button type="submit" class="button is-info">Найти</button>
                            </div>
                        </div>
                    </form>

                    <table class="table is-fullwidth is-hoverable">
                        <thead>
                            <tr>
                                <th>Псевдоним</th>
                                <th>Логин</th>
                                <th>Роль</th>
                                <th class="has-text-right">Действия</th>
                            </tr>
                        </thead>
                        <tbody id="users-table">
                            <?php foreach ($users as $u): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($u['full_name']) ?></strong></td>
                                <td><code><?= htmlspecialchars($u['login']) ?></code></td>
                                <td>
                                    <span class="tag <?= $u['role']==='admin'?'is-danger':($u['role']==='teacher'?'is-warning':'is-light') ?>">
                                        <?= ucfirst($u['role']) ?>
                                    </span>
                                </td>
                                <td class="has-text-right">
                                    <button class="button is-small is-info" onclick='editUser(<?= json_encode($u) ?>)'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="button is-small is-danger" onclick="deleteUser(<?= $u['id'] ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Модальное окно -->
<div class="modal" id="userModal">
    <div class="modal-background" onclick="closeUserModal()"></div>
    <div class="modal-card">
        <header class="modal-card-head">
            <p class="modal-card-title" id="modalTitle">Новый пользователь</p>
            <button class="delete" onclick="closeUserModal()"></button>
        </header>
        <section class="modal-card-body">
            <input type="hidden" id="userId">
            
            <div class="field">
                <label class="label">Псевдоним</label>
                <input class="input" type="text" id="full_name" placeholder="Можно оставить пустым">
            </div>
            <div class="field">
                <label class="label">Логин</label>
                <input class="input" type="text" id="login" required>
            </div>
            <div class="field">
                <label class="label">Пароль <small>(оставьте пустым при редактировании)</small></label>
                <input class="input" type="password" id="password">
            </div>
            <div class="field">
                <label class="label">Роль</label>
                <div class="select is-fullwidth">
                    <select id="role">
                        <option value="student">Слушатель</option>
                        <option value="teacher">Преподаватель</option>
                        <option value="admin">Оператор ДПО</option>
                    </select>
                </div>
            </div>
        </section>
        <footer class="modal-card-foot">
            <button class="button is-link" onclick="saveUser()">Сохранить</button>
            <button class="button" onclick="closeUserModal()">Отмена</button>
        </footer>
    </div>
</div>

<script>
// AJAX функции
function openUserModal() {
    document.getElementById('modalTitle').textContent = 'Новый пользователь';
    document.getElementById('userId').value = '';
    document.getElementById('full_name').value = '';
    document.getElementById('login').value = '';
    document.getElementById('password').value = '';
    document.getElementById('role').value = 'student';
    document.getElementById('userModal').classList.add('is-active');
}

function editUser(user) {
    document.getElementById('modalTitle').textContent = 'Редактирование пользователя';
    document.getElementById('userId').value = user.id;
    document.getElementById('full_name').value = user.full_name;
    document.getElementById('login').value = user.login;
    document.getElementById('password').value = '';
    document.getElementById('role').value = user.role;
    document.getElementById('userModal').classList.add('is-active');
}

async function saveUser() {
    const data = {
        id: document.getElementById('userId').value || null,
        full_name: document.getElementById('full_name').value.trim(),
        login: document.getElementById('login').value.trim(),
        role: document.getElementById('role').value,
        password: document.getElementById('password').value
    };

    if (!data.login) {
        alert('Введите логин');
        return;
    }

    const res = await fetch('manage_users.php?action=save', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    });
    
    const json = await res.json();
    if (json.success) {
        alert('Успешно сохранено!');
        location.reload();
    } else {
        alert('Ошибка: ' + (json.message || 'Неизвестная ошибка'));
    }
}

async function deleteUser(id) {
    if (!confirm('Удалить пользователя?')) return;
    
    const res = await fetch(`manage_users.php?action=delete&id=${id}`);
    const json = await res.json();
    
    if (json.success) {
        alert('Пользователь удалён');
        location.reload();
    } else {
        alert('Ошибка удаления');
    }
}

function closeUserModal() {
    document.getElementById('userModal').classList.remove('is-active');
}
</script>

<?php include '../../includes/footer.php'; ?>