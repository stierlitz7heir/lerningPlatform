<?php 
$current = basename($_SERVER['PHP_SELF']); 
?>
<aside class="menu box">
    <p class="menu-label">Панель ДПО</p>
    <ul class="menu-list">
        <li>
            <a href="/pages/admin/admin.php" class="<?= $current === 'admin.php' ? 'is-active' : '' ?>">
                <span class="icon"><i class="fas fa-chart-line"></i></span>
                <span>Сводка и аналитика</span>
            </a>
        </li>
        <li>
            <a href="/pages/schedule.php" class="<?= $current === 'schedule.php' ? 'is-active' : '' ?>">
                <span class="icon"><i class="fas fa-calendar-alt"></i></span>
                <span>Календарь занятий</span>
            </a>
        </li>
    </ul>

    <p class="menu-label">Программы</p>
    <ul class="menu-list">
        <li>
            <a href="/pages/admin/manage_courses.php" class="<?= $current === 'manage_courses.php' ? 'is-active' : '' ?>">
                <span class="icon"><i class="fas fa-book"></i></span>
                <span>Каталог программ ДПО</span>
            </a>
        </li>
        <li>
            <a href="/pages/admin/manage_requests.php" class="<?= $current === 'manage_requests.php' ? 'is-active' : '' ?>">
                <span class="icon"><i class="fas fa-user-check"></i></span>
                <span>Заявки студентов</span>
            </a>
        </li>
    </ul>

    <p class="menu-label">Контингент</p>
    <ul class="menu-list">
        <li>
            <a href="/pages/admin/manage_users.php" class="<?= $current === 'manage_users.php' ? 'is-active' : '' ?>">
                <span class="icon"><i class="fas fa-users"></i></span>
                <span>Студенты и сотрудники</span>
            </a>
        </li>
    </ul>

    <p class="menu-label">Настройки</p>
    <ul class="menu-list">
        <li>
            <a href="/pages/admin/settings.php" class="<?= $current === 'settings.php' ? 'is-active' : '' ?>">
                <span class="icon"><i class="fas fa-cog"></i></span>
                <span>Мои данные</span>
            </a>
        </li>
    </ul>
</aside>

<style>
.menu.box {
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    padding: 1.25rem;
}

.menu-label {
    color: #64748b;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
    margin-bottom: 0.75rem;
}

.menu-list a {
    border-radius: 8px;
    padding: 0.75rem 1rem;
    color: #475569;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.2s;
}

.menu-list a:hover {
    background-color: #f1f5f9;
    color: #3366cc;
}

.menu-list a.is-active {
    background-color: #3366cc !important;
    color: #fff !important;
    font-weight: 600;
}

.menu-list a .icon {
    width: 20px;
}
</style>