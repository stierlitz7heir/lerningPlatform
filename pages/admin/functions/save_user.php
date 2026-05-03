<?php
header('Content-Type: application/json');
require_once '../../../db/db.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Нет данных']);
    exit;
}

$id         = $data['id'] ?? null;
$full_name  = trim($data['full_name'] ?? '');
$login      = trim($data['login'] ?? '');
$role       = $data['role'] ?? 'student';
$group_id   = !empty($data['group_id']) ? (int)$data['group_id'] : null;
$password   = $data['password'] ?? '';

if (empty($login)) {
    echo json_encode(['success' => false, 'message' => 'Логин обязателен']);
    exit;
}
if (empty($full_name)) {
    $full_name = $login;
}

try {
    if ($id) {
        // Редактирование
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE users SET full_name=?, login=?, role=?, group_id=?, password_hash=? WHERE id=?");
            $stmt->execute([$full_name, $login, $role, $group_id, $hash, $id]);
        } else {
            $stmt = $db->prepare("UPDATE users SET full_name=?, login=?, role=?, group_id=? WHERE id=?");
            $stmt->execute([$full_name, $login, $role, $group_id, $id]);
        }
    } else {
        // Создание нового
        if (empty($password)) $password = '123456'; // дефолтный пароль
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare("INSERT INTO users (full_name, login, role, group_id, password_hash) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$full_name, $login, $role, $group_id, $hash]);
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;