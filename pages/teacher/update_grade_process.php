<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../db/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit;
}

$enrollment_id = (int)($_POST['enrollment_id'] ?? 0);
$grade = (int)($_POST['grade'] ?? 0);
$teacher_id = $_SESSION['user_id'];

if (!$enrollment_id || $grade < 2 || $grade > 5) {
    header('Location: grades.php?error=1');
    exit;
}

$stmt = $db->prepare(
    "UPDATE course_enrollments ce
     JOIN courses c ON ce.course_id = c.id
     SET ce.grade = ?
     WHERE ce.id = ? AND c.teacher_id = ?"
);
$stmt->execute([$grade, $enrollment_id, $teacher_id]);

if ($stmt->rowCount() === 0) {
    header('Location: grades.php?error=1');
    exit;
}

header('Location: grades.php?success=1');
exit;
