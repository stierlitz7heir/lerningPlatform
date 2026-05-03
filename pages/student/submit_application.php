<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../db/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: courses.php");
    exit;
}

$course_id = (int)($_POST['course_id'] ?? 0);

if ($course_id <= 0) {
    header("Location: courses.php");
    exit;
}

header("Location: course_details.php?id=$course_id");
exit;
