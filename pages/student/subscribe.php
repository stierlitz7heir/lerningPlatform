<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../db/db.php';

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'student' ||
    !isset($_POST['course_id'])
) {
    header("Location: courses.php");
    exit;
}

$courseId = (int)$_POST['course_id'];
$userId = (int)$_SESSION['user_id'];

if ($courseId <= 0) {
    header("Location: courses.php");
    exit;
}

$courseStmt = $db->prepare("SELECT id FROM courses WHERE id = ? AND status = 'Активный' LIMIT 1");
$courseStmt->execute([$courseId]);
if (!$courseStmt->fetch()) {
    header("Location: courses.php");
    exit;
}

$existingStmt = $db->prepare("SELECT status FROM course_subscriptions WHERE user_id = ? AND course_id = ? LIMIT 1");
$existingStmt->execute([$userId, $courseId]);
$existing = $existingStmt->fetchColumn();

if ($existing === false) {
    $insertStmt = $db->prepare("INSERT INTO course_subscriptions (user_id, course_id, status) VALUES (?, ?, 'pending')");
    $insertStmt->execute([$userId, $courseId]);
    header("Location: course_details.php?id={$courseId}&success=pending");
    exit;
}

header("Location: course_details.php?id={$courseId}&status=" . urlencode((string)$existing));
exit;