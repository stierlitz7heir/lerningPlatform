<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit;
}
header('Location: journal.php' . (!empty($_GET['course_id']) ? '?course_id=' . (int)$_GET['course_id'] : ''));
exit;