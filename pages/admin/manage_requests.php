<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../db/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// AJAX обработчики
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    $sub_id = (int)($_GET['sub_id'] ?? 0);

    if ($_GET['action'] === 'approve' && $sub_id) {
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT user_id, course_id FROM course_subscriptions WHERE id = ? LIMIT 1");
            $stmt->execute([$sub_id]);
            $subscription = $stmt->fetch();

            if (!$subscription) {
                throw new Exception('Заявка не найдена');
            }

            $stmt = $db->prepare("UPDATE course_subscriptions SET status = 'active' WHERE id = ?");
            $stmt->execute([$sub_id]);

            // Для ведомости оценок нужен enrollment-запись.
            $stmt = $db->prepare("
                INSERT INTO course_enrollments (course_id, student_id)
                SELECT ?, ?
                WHERE NOT EXISTS (
                    SELECT 1 FROM course_enrollments WHERE course_id = ? AND student_id = ?
                )
            ");
            $stmt->execute([
                $subscription['course_id'],
                $subscription['user_id'],
                $subscription['course_id'],
                $subscription['user_id']
            ]);

            $db->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($_GET['action'] === 'reject' && $sub_id) {
        $stmt = $db->prepare("UPDATE course_subscriptions SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$sub_id]);
        echo json_encode(['success' => true]);
        exit;
    }
}

// Фильтрация заявок по статусу
$status_filter = $_GET['status'] ?? 'all';
$allowed_statuses = ['all', 'pending', 'active', 'rejected', 'completed'];
if (!in_array($status_filter, $allowed_statuses, true)) {
    $status_filter = 'all';
}

// Получение всех заявок (включая обработанные)
$sql = "
    SELECT 
        cs.id,
        cs.status,
        cs.created_at,
        u.full_name as student_name,
        c.name as course_name,
        t.full_name as teacher_name
    FROM course_subscriptions cs
    JOIN users u ON cs.user_id = u.id
    JOIN courses c ON cs.course_id = c.id
    LEFT JOIN users t ON c.teacher_id = t.id
";

$params = [];
if ($status_filter !== 'all') {
    $sql .= " WHERE cs.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY cs.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

$stats = ['pending' => 0, 'active' => 0, 'rejected' => 0, 'completed' => 0];
foreach ($requests as $request) {
    if (isset($stats[$request['status']])) {
        $stats[$request['status']]++;
    }
}

include '../../includes/header.php';
?>

<section class="section">
    <div class="container">
        <div class="columns">
            <div class="column is-3">
                <?php include 'sidebar.php'; ?>
            </div>
            <div class="column">
                <div class="box">
                    <div class="level mb-5">
                        <div class="level-left">
                            <h1 class="title is-4">Заявки слушателей ДПО</h1>
                        </div>
                        <div class="level-right">
                            <span class="tag is-info">
                                По текущему фильтру: <?= count($requests) ?>
                            </span>
                        </div>
                    </div>

                    <div class="tags mb-4">
                        <span class="tag is-warning is-light">Ожидают: <?= $stats['pending'] ?></span>
                        <span class="tag is-success is-light">Одобрены: <?= $stats['active'] ?></span>
                        <span class="tag is-danger is-light">Отклонены: <?= $stats['rejected'] ?></span>
                        <span class="tag is-link is-light">Завершили: <?= $stats['completed'] ?></span>
                    </div>

                    <form method="GET" class="mb-4">
                        <div class="field is-grouped">
                            <div class="control">
                                <div class="select">
                                    <select name="status">
                                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>Все статусы</option>
                                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>На рассмотрении</option>
                                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Одобрены</option>
                                        <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Отклонены</option>
                                        <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Завершили обучение</option>
                                    </select>
                                </div>
                            </div>
                            <div class="control">
                                <button type="submit" class="button is-info">Применить фильтр</button>
                            </div>
                        </div>
                    </form>

                    <?php if (empty($requests)): ?>
                        <div class="box has-text-centered py-6 has-text-grey">
                            По выбранному фильтру нет заявок.
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="table is-fullwidth is-hoverable is-striped">
                                <thead>
                                    <tr>
                                        <th>Дата</th>
                                        <th>Студент</th>
                                        <th>Программа</th>
                                        <th>Преподаватель</th>
                                        <th>Статус</th>
                                        <th class="has-text-right">Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($requests as $r): ?>
                                    <tr>
                                        <td><?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></td>
                                        <td><strong><?= htmlspecialchars($r['student_name']) ?></strong></td>
                                        <td><?= htmlspecialchars($r['course_name']) ?></td>
                                        <td><?= htmlspecialchars($r['teacher_name'] ?? '—') ?></td>
                                        <td>
                                            <?php
                                            $statusClass = '';
                                            $statusText = '';
                                            switch($r['status']) {
                                                case 'pending':
                                                    $statusClass = 'is-warning';
                                                    $statusText = 'На рассмотрении';
                                                    break;
                                                case 'active':
                                                    $statusClass = 'is-success';
                                                    $statusText = 'Одобрена';
                                                    break;
                                                case 'rejected':
                                                    $statusClass = 'is-danger';
                                                    $statusText = 'Отклонена';
                                                    break;
                                                case 'completed':
                                                    $statusClass = 'is-link';
                                                    $statusText = 'Завершено';
                                                    break;
                                            }
                                            ?>
                                            <span class="tag <?= $statusClass ?>"><?= $statusText ?></span>
                                        </td>
                                        <td class="has-text-right">
                                            <?php if ($r['status'] === 'pending'): ?>
                                                <button onclick="approveRequest(<?= $r['id'] ?>)" 
                                                        class="button is-small is-success">
                                                    Одобрить
                                                </button>
                                                <button onclick="rejectRequest(<?= $r['id'] ?>)" 
                                                        class="button is-small is-danger">
                                                    Отклонить
                                                </button>
                                            <?php else: ?>
                                                <span class="has-text-grey is-size-7">Обработано</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
// Одобрить заявку
async function approveRequest(id) {
    if (!confirm('Одобрить заявку?')) return;
    
    const res = await fetch(`manage_requests.php?action=approve&sub_id=${id}`);
    const json = await res.json();
    
    if (json.success) {
        alert('Заявка одобрена!');
        location.reload();
    } else {
        alert('Ошибка при одобрении');
    }
}

// Отклонить заявку
async function rejectRequest(id) {
    if (!confirm('Отклонить заявку?')) return;
    
    const res = await fetch(`manage_requests.php?action=reject&sub_id=${id}`);
    const json = await res.json();
    
    if (json.success) {
        alert('Заявка отклонена!');
        location.reload();
    } else {
        alert('Ошибка при отклонении');
    }
}
</script>

<?php include '../../includes/footer.php'; ?>