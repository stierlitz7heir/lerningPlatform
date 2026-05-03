<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../db/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

$course_id = (int)($_GET['course_id'] ?? 0);

$stmt = $db->prepare("SELECT id, name, category, duration_hours FROM courses WHERE id = ? AND status = 'Активный'");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) {
    header("Location: courses.php");
    exit;
}

include '../../includes/header.php';
?>

<section class="section">
    <div class="container">
        <div class="columns">
            <div class="column is-3">
                <?php include 'sidebar_student.php'; ?>
            </div>
            <div class="column">
                <div class="box panel-heading">
                    <h1 class="title is-4">Запись на программу</h1>
                    <p class="is-size-6 has-text-grey">Дополнительная форма больше не требуется.</p>
                </div>
                <div class="box">
                    <p class="mb-4">Для программы <strong><?= htmlspecialchars($course['name']) ?></strong> заявка оформляется автоматически по данным вашего профиля.</p>
                    <form action="subscribe.php" method="POST">
                        <input type="hidden" name="course_id" value="<?= (int)$course['id'] ?>">
                        <div class="buttons">
                            <button type="submit" class="button is-link">Подтвердить запись</button>
                            <a href="course_details.php?id=<?= (int)$course['id'] ?>" class="button is-light">Назад к курсу</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include '../../includes/footer.php'; ?>
