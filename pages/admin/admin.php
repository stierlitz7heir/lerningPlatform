<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../db/db.php';

// Проверка прав доступа
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Актуальная статистика
try {
    $stats = [
        'students'   => $db->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn(),
        'teachers'   => $db->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn(),
        'courses'    => $db->query("SELECT COUNT(*) FROM courses")->fetchColumn(),
        'groups'     => $db->query("SELECT COUNT(*) FROM groups_table")->fetchColumn(),
        'active_courses' => $db->query("SELECT COUNT(*) FROM courses WHERE status = 'Активный'")->fetchColumn(),
    ];
} catch (Exception $e) {
    $stats = ['students' => 0, 'teachers' => 0, 'courses' => 0, 'groups' => 0, 'active_courses' => 0];
}

include '../../includes/header.php';
?>

<section class="section">
    <div class="container">
        <div class="columns">
            <!-- Сайдбар -->
            <div class="column is-3">
                <?php include 'sidebar.php'; ?>
            </div>

            <!-- Основной контент -->
            <div class="column">
                <div class="box panel-card info-panel">
                    <div class="panel-heading">
                        <h1 class="title is-4 mb-1">Панель управления</h1>
                        <p class="is-6 has-text-grey">
                            Рады видеть вас, <strong><?= htmlspecialchars($_SESSION['full_name'] ?? 'Администратор') ?></strong>
                        </p>
                    </div>
                </div>

                <!-- Статистика -->
                <div class="columns is-multiline">
                    <div class="column is-6-tablet is-3-desktop">
                        <div class="box panel-card has-text-centered">
                            <p class="heading has-text-grey">Студенты</p>
                            <p class="title is-2 has-text-link"><?= $stats['students'] ?></p>
                            <span class="icon is-large has-text-grey-light"><i class="fas fa-user-graduate"></i></span>
                        </div>
                    </div>

                    <div class="column is-6-tablet is-3-desktop">
                        <div class="box panel-card has-text-centered">
                            <p class="heading has-text-grey">Преподаватели</p>
                            <p class="title is-2 has-text-info"><?= $stats['teachers'] ?></p>
                            <span class="icon is-large has-text-grey-light"><i class="fas fa-chalkboard-teacher"></i></span>
                        </div>
                    </div>

                    <div class="column is-6-tablet is-3-desktop">
                        <div class="box panel-card has-text-centered">
                            <p class="heading has-text-grey">Курсов всего</p>
                            <p class="title is-2 has-text-success"><?= $stats['courses'] ?></p>
                            <span class="icon is-large has-text-grey-light"><i class="fas fa-book"></i></span>
                        </div>
                    </div>

                    <div class="column is-6-tablet is-3-desktop">
                        <div class="box panel-card has-text-centered">
                            <p class="heading has-text-grey">Активных курсов</p>
                            <p class="title is-2 has-text-warning-dark"><?= $stats['active_courses'] ?></p>
                            <span class="icon is-large has-text-grey-light"><i class="fas fa-book-open"></i></span>
                        </div>
                    </div>

                    <div class="column is-6-tablet is-3-desktop">
                        <div class="box panel-card has-text-centered">
                            <p class="heading has-text-grey">Групп</p>
                            <p class="title is-2 has-text-warning-dark"><?= $stats['groups'] ?></p>
                            <span class="icon is-large has-text-grey-light"><i class="fas fa-users"></i></span>
                        </div>
                    </div>
                </div>

                <!-- Быстрые действия -->
                <div class="box panel-card mt-6">
                    <div class="panel-heading">
                        <h3 class="title is-5">Быстрые действия</h3>
                    </div>
                    <div class="buttons">
                        <a href="manage_users.php" class="button is-link">
                            <i class="fas fa-users mr-2"></i> Управление пользователями
                        </a>
                        <a href="manage_courses.php" class="button is-info">
                            <i class="fas fa-book mr-2"></i> Управление курсами
                        </a>
                        <a href="manage_requests.php" class="button is-success">
                            <i class="fas fa-user-check mr-2"></i> Заявки на обучение
                        </a>
                        <a href="../schedule.php" class="button is-warning">
                            <i class="fas fa-calendar-alt mr-2"></i> Расписание
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include '../../includes/footer.php'; ?>