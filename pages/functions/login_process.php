<?php
session_start();
require_once '../../db/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../login.php");
    exit;
}

$login = trim($_POST['login']);
$password = $_POST['password'];
$remember = isset($_POST['remember']); // Проверяем галочку

if (empty($login) || empty($password)) {
    header("Location: ../login.php?error=empty");
    exit;
}

// ВАЖНО: Проверь, что в БД колонка называется password_hash или password
$stmt = $db->prepare("SELECT id, login, password_hash, role, full_name FROM users WHERE login = ?");
$stmt->execute([$login]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    header("Location: ../login.php?error=invalid");
    exit;
}

// Регенерация ID сессии для защиты от фиксации сессии
session_regenerate_id(true);

$_SESSION['user_id']   = $user['id'];
$_SESSION['login']     = $user['login'];
$_SESSION['role']      = $user['role'];
$_SESSION['full_name'] = !empty($user['full_name']) ? $user['full_name'] : $user['login'];

// --- ЛОГИКА "ЗАПОМНИТЬ МЕНЯ" ---
if ($remember) {
    // 1. Генерируем криптографически стойкий токен
    $token = bin2hex(random_bytes(32));
    
    // 2. Сохраняем его в базу данных для этого пользователя
    // Убедись, что ты выполнил: ALTER TABLE users ADD remember_token VARCHAR(255) NULL;
    $updateStmt = $db->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
    $updateStmt->execute([$token, $user['id']]);

    // 3. Устанавливаем куку на 30 дней
    // Параметры: имя, значение, время жизни, путь, домен, secure, httponly
    setcookie(
        'remember_me', 
        $token, 
        time() + (86400 * 30), 
        "/", 
        "", 
        false, // установи true, если используешь HTTPS
        true   // httponly защита
    );
}
// ------------------------------

// Перенаправление по ролям
if ($user['role'] === 'admin') {
    header("Location: ../admin/admin.php");
} elseif ($user['role'] === 'teacher') {
    header("Location: ../teacher/teacher_index.php");
} else {
    header("Location: ../student/student.php");
}
exit;