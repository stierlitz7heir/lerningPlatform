<?php
require_once __DIR__ . '/db.php';

$usersTotal = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$usersAllowed = (int)$db->query("SELECT COUNT(*) FROM users WHERE login IN ('admin','stierlitz7heir','denlym')")->fetchColumn();
$courses = (int)$db->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$assignments = (int)$db->query("SELECT COUNT(*) FROM assignments")->fetchColumn();
$enrollments = (int)$db->query("SELECT COUNT(*) FROM course_enrollments")->fetchColumn();
$subscriptions = (int)$db->query("SELECT COUNT(*) FROM course_subscriptions")->fetchColumn();
$submissions = (int)$db->query("SELECT COUNT(*) FROM submissions")->fetchColumn();
$statusAccepted = (int)$db->query("SELECT COUNT(*) FROM submissions WHERE status = 'accepted'")->fetchColumn();
$statusRevision = (int)$db->query("SELECT COUNT(*) FROM submissions WHERE status = 'revision'")->fetchColumn();
$statusReview = (int)$db->query("SELECT COUNT(*) FROM submissions WHERE status = 'on_review'")->fetchColumn();

echo "users_total={$usersTotal}\n";
echo "users_allowed={$usersAllowed}\n";
echo "courses={$courses}\n";
echo "assignments={$assignments}\n";
echo "enrollments={$enrollments}\n";
echo "subscriptions={$subscriptions}\n";
echo "submissions={$submissions}\n";
echo "accepted={$statusAccepted}\n";
echo "revision={$statusRevision}\n";
echo "on_review={$statusReview}\n";
