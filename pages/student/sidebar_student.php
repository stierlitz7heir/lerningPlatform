<?php
$current_page = basename($_SERVER['PHP_SELF']);
$sidebarLessons = $sidebar_lessons ?? [];
$sidebarCurrentAssignmentId = (int)($sidebar_current_assignment_id ?? 0);
$sidebarCourseId = (int)($sidebar_course_id ?? 0);
?>
<aside class="menu box">
    <p class="menu-label">Кабинет слушателя ДПО</p>
    <ul class="menu-list">
        <li>
            <a href="student.php" class="<?= $current_page === 'student.php' ? 'is-active' : '' ?>">
                <span class="icon"><i class="fas fa-layer-group"></i></span>
                <span>Мои программы</span>
            </a>
        </li>
        <li>
            <a href="courses.php" class="<?= in_array($current_page, ['courses.php', 'course_details.php', 'apply.php'], true) ? 'is-active' : '' ?>">
                <span class="icon"><i class="fas fa-search"></i></span>
                <span>Каталог программ</span>
            </a>
        </li>
        <li>
            <a href="student_tasks.php" class="<?= $current_page === 'student_tasks.php' ? 'is-active' : '' ?>">
                <span class="icon"><i class="fas fa-tasks"></i></span>
                <span>Учебные задания</span>
            </a>
        </li>
        <li>
            <a href="student_grades.php" class="<?= $current_page === 'student_grades.php' ? 'is-active' : '' ?>">
                <span class="icon"><i class="fas fa-graduation-cap"></i></span>
                <span>Результаты обучения</span>
            </a>
        </li>
        <li>
            <a href="student_profile.php" class="<?= $current_page === 'student_profile.php' ? 'is-active' : '' ?>">
                <span class="icon"><i class="fas fa-user-circle"></i></span>
                <span>Профиль</span>
            </a>
        </li>
    </ul>
    <?php if ($current_page === 'student_tasks.php' && !empty($sidebarLessons)): ?>
        <hr class="sidebar-divider">
        <p class="menu-label">Уроки модуля</p>
        <ul class="menu-list ws-lessons-menu">
            <?php foreach ($sidebarLessons as $lesson): ?>
                <?php
                $lessonId = (int)$lesson['assignment_id'];
                $lessonStatus = (string)($lesson['submission_status'] ?? '');
                $isLocked = (bool)$lesson['is_locked'];
                $statusIcon = '🔒';
                if (!$isLocked) {
                    if ($lessonStatus === 'accepted') {
                        $statusIcon = '✅';
                    } elseif ($lessonStatus === 'revision') {
                        $statusIcon = '❌';
                    } elseif ($lessonStatus === 'on_review' || $lessonStatus === 'review' || $lessonStatus === 'submitted') {
                        $statusIcon = '⏳';
                    } else {
                        $statusIcon = '⏳';
                    }
                }
                ?>
                <li>
                    <?php if ($isLocked): ?>
                        <span class="ws-lesson-link is-locked" title="Сначала завершите предыдущий урок">
                            <span class="ws-lesson-icon"><?= $statusIcon ?></span>
                            <span class="ws-lesson-title"><?= htmlspecialchars($lesson['title']) ?></span>
                        </span>
                    <?php else: ?>
                        <a
                            href="student_tasks.php?course_id=<?= $sidebarCourseId ?>&assignment_id=<?= $lessonId ?>"
                            class="ws-lesson-link <?= $sidebarCurrentAssignmentId === $lessonId ? 'is-active' : '' ?>"
                        >
                            <span class="ws-lesson-icon"><?= $statusIcon ?></span>
                            <span class="ws-lesson-title"><?= htmlspecialchars($lesson['title']) ?></span>
                        </a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</aside>

<style>
.menu.box { border-radius: 16px; box-shadow: 0 8px 24px rgba(15,23,42,0.06); border: 1px solid #eef2f7; padding: 1.1rem; background: #ffffff; }
.menu-label { color: #64748b; font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; margin-bottom: 0.75rem; }
.menu-list a { border-radius: 8px; padding: 0.65rem 0.85rem; color: #475569; display: flex; align-items: center; gap: 10px; transition: all 0.2s; }
.menu-list a:hover { background-color: #f3f6fc; color: #335fbe; }
.menu-list a.is-active { background-color: #eaf0ff !important; color: #1d4ed8 !important; font-weight: 600; }
.menu-list a .icon { width: 20px; }
.sidebar-divider { border: none; border-top: 1px solid #e2e8f0; margin: 0.85rem 0; }
.ws-lessons-menu .ws-lesson-link { width: 100%; display: flex; align-items: center; gap: 8px; border-radius: 8px; padding: 0.55rem 0.7rem; color: #1f2937; }
.ws-lessons-menu .ws-lesson-link.is-active { background: #eef2ff; color: #1d4ed8; font-weight: 600; }
.ws-lessons-menu .ws-lesson-link.is-locked { color: #9ca3af; cursor: not-allowed; background: #f8fafc; }
.ws-lesson-icon { width: 20px; text-align: center; }
.ws-lesson-title { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>