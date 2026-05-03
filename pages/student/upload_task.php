<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../db/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: student_tasks.php');
    exit;
}

$student_id = $_SESSION['user_id'];
$assignment_id = (int)($_POST['assignment_id'] ?? 0);
$course_id = (int)($_POST['course_id'] ?? 0);
$textContent = trim($_POST['text_content'] ?? '');
$returnUrl = 'student_tasks.php';
if ($course_id > 0) {
    $returnUrl .= '?course_id=' . $course_id . '&assignment_id=' . $assignment_id;
}

if (!$assignment_id || empty($_FILES['solution']['name'])) {
    header('Location: ' . $returnUrl . (str_contains($returnUrl, '?') ? '&' : '?') . 'upload=fail');
    exit;
}

if ($_FILES['solution']['error'] !== UPLOAD_ERR_OK) {
    header('Location: ' . $returnUrl . (str_contains($returnUrl, '?') ? '&' : '?') . 'upload=fail');
    exit;
}

$allowedExtensions = ['pdf', 'doc', 'docx', 'txt', 'zip', 'rar', 'jpg', 'jpeg', 'png'];
$originalName = basename($_FILES['solution']['name']);
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if (!in_array($extension, $allowedExtensions, true)) {
    header('Location: ' . $returnUrl . (str_contains($returnUrl, '?') ? '&' : '?') . 'upload=invalid_type');
    exit;
}

$uploadDir = dirname(__DIR__, 2) . '/uploads/submissions';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$safeName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
$safeName = mb_substr($safeName, 0, 40);
$filename = sprintf('%s_%s_%s_%s.%s', $student_id, $assignment_id, $safeName, time(), $extension);
$filePath = 'uploads/submissions/' . $filename;
$destination = $uploadDir . '/' . $filename;

if (!move_uploaded_file($_FILES['solution']['tmp_name'], $destination)) {
    header('Location: ' . $returnUrl . (str_contains($returnUrl, '?') ? '&' : '?') . 'upload=fail');
    exit;
}

$attemptStmt = $db->prepare(
    "SELECT COALESCE(MAX(attempt_number), 0) + 1 AS next_attempt
     FROM submissions
     WHERE assignment_id = ? AND student_id = ?"
);
$attemptStmt->execute([$assignment_id, $student_id]);
$nextAttempt = (int)$attemptStmt->fetchColumn();
if ($nextAttempt < 1) {
    $nextAttempt = 1;
}

$insert = $db->prepare(
    "INSERT INTO submissions (
        assignment_id, student_id, attempt_number, status,
        text_content, file_url, teacher_feedback,
        answer_text, file_path, submitted_at, comment, teacher_comment, grade
    )
     VALUES (?, ?, ?, 'submitted', ?, ?, NULL, ?, ?, NOW(), NULL, NULL, NULL)"
);
$insert->execute([
    $assignment_id,
    $student_id,
    $nextAttempt,
    $textContent !== '' ? $textContent : null,
    $filePath,
    $textContent !== '' ? $textContent : null,
    $filePath
]);

header('Location: ' . $returnUrl . (str_contains($returnUrl, '?') ? '&' : '?') . 'upload=success');
exit;
