<?php
$current = basename($_SERVER['PHP_SELF']);
?>
<aside class="menu box">
    <p class="menu-label">Кабинет ДПО</p>
    <ul class="menu-list">
        <li>
            <a href="teacher_index.php" class="<?= $current === 'teacher_index.php' ? 'is-active' : '' ?>">
                <span class="icon"><i class="fas fa-home"></i></span>
                <span>Сводка преподавателя</span>
            </a>
        </li>
        <li>
            <a href="teacher_courses.php" class="<?= $current === 'teacher_courses.php' ? 'is-active' : '' ?>">
                <span class="icon"><i class="fas fa-book"></i></span>
                <span>Программы ДПО</span>
            </a>
        </li>
        <li>
            <a href="journal.php" class="<?= in_array($current, ['journal.php', 'grades.php', 'grade_tasks.php'], true) ? 'is-active' : '' ?>">
                <span class="icon"><i class="fas fa-table-cells-large"></i></span>
                <span>Журнал и проверка</span>
            </a>
        </li>
        <li>
            <a href="teacher_groups.php" class="<?= $current === 'teacher_groups.php' ? 'is-active' : '' ?>">
                <span class="icon"><i class="fas fa-book-open-reader"></i></span>
                <span>Мои дисциплины и студенты</span>
            </a>
        </li>
        <li>
            <a href="teacher_profile.php" class="<?= $current === 'teacher_profile.php' ? 'is-active' : '' ?>">
                <span class="icon"><i class="fas fa-user-circle"></i></span>
                <span>Профиль</span>
            </a>
        </li>
    </ul>
</aside>

<style>
.menu.box { border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); padding: 1.25rem; }
.menu-label { color: #64748b; font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; margin-bottom: 0.75rem; }
.menu-list a { border-radius: 8px; padding: 0.75rem 1rem; color: #475569; display: flex; align-items: center; gap: 10px; transition: all 0.2s; }
.menu-list a:hover { background-color: #f1f5f9; color: #3366cc; }
.menu-list a.is-active { background-color: #3366cc !important; color: #fff !important; font-weight: 600; }
.menu-list a .icon { width: 20px; }
</style>