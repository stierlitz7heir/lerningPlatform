<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Если пользователь не залогинен, но есть кука "запомнить меня"
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    $token = $_COOKIE['remember_me'];
    // Здесь предполагается, что $db уже определен в файле, который подключает header
    if (isset($db)) {
        $stmt = $db->prepare("SELECT id, login, role, full_name FROM users WHERE remember_token = ? LIMIT 1");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['login']     = $user['login'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['full_name'] = !empty($user['full_name']) ? $user['full_name'] : $user['login'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru" class="has-navbar-fixed-top">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Электронная образовательная платформа ГАПОУ СО «Энгельсский политехнический колледж»">
    <title>УПК | Энгельсский политехнический колледж</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="/css/style.css">
    <style>
        :root {
            --primary-blue: #3366cc;
            --primary-dark: #1e293b;
        }

        .navbar {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .navbar-item.has-text-white:hover {
            background-color: rgba(255, 255, 255, 0.1) !important;
            color: #fff !important;
        }

        .user-badge {
            background: rgba(255, 255, 255, 0.15);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-right: 0.5rem;
        }
    </style>
</head>

<body>

    <nav class="navbar is-fixed-top" style="background: var(--primary-dark);" role="navigation" aria-label="main navigation">
        <div class="container">
            <div class="navbar-brand">
                <a class="navbar-item has-text-white is-size-5" href="/index.php" style="font-weight: 700;">
                    <span class="icon mr-2"><i class="fas fa-graduation-cap"></i></span> УПК
                </a>
                <a role="button" class="navbar-burger burger has-text-white" data-target="navbarMenu">
                    <span aria-hidden="true"></span><span aria-hidden="true"></span><span aria-hidden="true"></span>
                </a>
            </div>

            <div id="navbarMenu" class="navbar-menu">
                <div class="navbar-start">
                    <a class="navbar-item has-text-white" href="/index.php">Главная</a>

                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'student'): ?>
                        <a class="navbar-item has-text-white" href="/pages/schedule.php">Расписание</a>
                    <?php endif; ?>
                </div>

                <div class="navbar-end">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <a class="navbar-item has-text-white" href="/pages/admin/admin.php">Панель управления</a>
                        <?php elseif ($_SESSION['role'] === 'teacher'): ?>
                            <a class="navbar-item has-text-white" href="/pages/teacher/teacher_index.php">Кабинет</a>
                        <?php elseif ($_SESSION['role'] === 'student'): ?>
                            <a class="navbar-item has-text-white" href="/pages/student/student.php">Обучение</a>
                            <a class="navbar-item has-text-white" href="/pages/schedule.php">Расписание</a>
                        <?php endif; ?>

                        <div class="navbar-item">
                            <div class="user-badge has-text-white">
                                <span class="icon is-small"><i class="fas fa-user-circle mr-2"></i></span>
                                <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['login'] ?? 'Пользователь') ?>
                            </div>
                            <a class="button is-danger is-light is-small" href="/pages/logout.php">
                                <i class="fas fa-sign-out-alt"></i>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="navbar-item">
                            <a class="button is-link is-light" href="/pages/login.php">
                                <strong>Войти в систему</strong>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const $navbarBurgers = Array.prototype.slice.call(document.querySelectorAll('.navbar-burger'), 0);
            $navbarBurgers.forEach(el => {
                el.addEventListener('click', () => {
                    const target = el.dataset.target;
                    const $target = document.getElementById(target);
                    el.classList.toggle('is-active');
                    $target.classList.toggle('is-active');
                });
            });
        });
    </script>

    <main style="padding-top: 0.5rem; min-height: 80vh;">