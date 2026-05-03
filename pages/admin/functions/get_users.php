<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Доступ запрещён']);
    exit;
}

$search = $_GET['search'] ?? '';
$role   = $_GET['role'] ?? '';

$sql = "SELECT id, full_name, login, role, group_id,
               (SELECT name FROM groups_table WHERE id = users.group_id) as group_name 
        FROM users WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (full_name LIKE ? OR login LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($role !== '') {
    $sql .= " AND role = ?";
    $params[] = $role;
}

$sql .= " ORDER BY full_name ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($users);
exit;