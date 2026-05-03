<?php
session_start();
require_once '../db/db.php';

$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

// --- БЛОК ОБРАБОТКИ ДАННЫХ (ДО ВЫВОДА HTML) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    $action = $_POST['action'] ?? '';

    // 1. СОХРАНЕНИЕ ВСЕГО
    if ($action === 'save') {
        $db->beginTransaction();
        try {
            if (isset($_POST['specs'])) {
                $stUpdateSpec = $db->prepare("UPDATE specializations SET name = ? WHERE id = ?");
                foreach ($_POST['specs'] as $id => $name) {
                    $stUpdateSpec->execute([$name, $id]);
                }
            }
            $db->exec("DELETE FROM schedule");
            $stInsert = $db->prepare("INSERT INTO schedule (day_number, specialization_id, lesson) VALUES (?, ?, ?)");
            foreach (($_POST['rows'] ?? []) as $dayNum => $rowData) {
                foreach ($rowData['cells'] as $specId => $text) {
                    if (trim($text) !== "") {
                        $stInsert->execute([(int)$dayNum, (int)$specId, $text]);
                    }
                }
            }
            $db->commit();
        } catch (Exception $e) { $db->rollBack(); die($e->getMessage()); }
        header("Location: schedule.php"); exit;
    }

    // 2. ДОБАВЛЕНИЕ ГРУППЫ
    if ($action === 'add_spec') {
        $st = $db->prepare("INSERT INTO specializations (name) VALUES ('Новая группа')");
        $st->execute();
        header("Location: schedule.php"); exit;
    }

    // 3. УДАЛЕНИЕ ГРУППЫ
    if ($action === 'delete_spec') {
        $specId = (int)$_POST['delete_spec_id'];
        $st = $db->prepare("DELETE FROM specializations WHERE id = ?");
        $st->execute([$specId]);
        header("Location: schedule.php"); exit;
    }
}

// --- ПОДГОТОВКА ДАННЫХ ---
function getDayInfo($offset) {
    $days = ['Monday' => 'ПОНЕДЕЛЬНИК', 'Tuesday' => 'ВТОРНИК', 'Wednesday' => 'СРЕДА', 'Thursday' => 'ЧЕТВЕРГ', 'Friday' => 'ПЯТНИЦА'];
    $timestamp = strtotime("monday this week +$offset days");
    return ['name' => $days[date('l', $timestamp)] ?? '', 'date' => date('d.m.Y', $timestamp)];
}

$sData = $db->query("SELECT * FROM specializations ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$schData = $db->query("SELECT * FROM schedule")->fetchAll(PDO::FETCH_ASSOC);
$matrix = [];
foreach ($schData as $r) { $matrix[$r['day_number']][$r['specialization_id']] = $r['lesson']; }

include '../includes/header.php';
?>

<style>

    .sch-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        flex-wrap: wrap; /* Позволяет элементам переноситься */
        gap: 15px;
    }

    .sch-actions {
        display: flex;
        gap: 10px;
    }

    /* Адаптив для телефонов */
    @media screen and (max-width: 768px) {
        .sch-top {
            flex-direction: column; /* Текст сверху, кнопки снизу */
            align-items: flex-start; /* Выравнивание по левому краю */
        }

        .sch-actions {
            width: 100%; /* Кнопки растягиваются на всю ширину */
            justify-content: space-between;
        }

        .btn-save, .btn-add {
            flex: 1; /* Кнопки становятся одинакового размера */
            text-align: center;
            padding: 12px 5px; /* Увеличиваем область нажатия для пальца */
        }
    }
    .sch-wrapper { padding: 20px; font-family: sans-serif; color: #334155; }
    .sch-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
    .sch-actions { display: flex; gap: 10px; }
    .btn-save { background: #4a69bd; color: #fff; border: none; padding: 8px 20px; border-radius: 4px; cursor: pointer; font-weight: 600; }
    .btn-add { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-size: 0.85rem; }
    
    .table-scroll { border: 1px solid #e2e8f0; border-radius: 8px; overflow-x: auto; background: #fff; }
    .sch-table { width: 100%; border-collapse: collapse; table-layout: fixed; min-width: 1500px; }
    .sch-table th, .sch-table td { border: 1px solid #e2e8f0; vertical-align: middle !important; text-align: center !important; position: relative; }

    .col-info { width: 110px; background: #f8fafc; }
    .col-spec { width: 160px; background: #eff6ff; padding: 0 !important; }

    .btn-del-spec {
        position: absolute; top: 2px; right: 2px; background: #ff3860; border: none; color: #fff; 
        width: 18px; height: 18px; border-radius: 3px; cursor: pointer; font-size: 12px;
        display: flex; align-items: center; justify-content: center; opacity: 0; transition: 0.2s; z-index: 5;
    }
    .col-spec:hover .btn-del-spec { opacity: 1; }

    .edit-area { width: 100%; border: none; background: transparent; resize: none; font-size: 0.8rem; padding: 10px; text-align: center !important; outline: none; display: block; }
    .spec-input { font-weight: 700; color: #1e40af; min-height: 50px; padding-top: 15px; }
    .lesson-input { min-height: 100px; }
    
    .view-text { font-size: 0.8rem; padding: 10px; text-align: center !important; white-space: pre-line; min-height: 100px; display: flex; align-items: center; justify-content: center; }
    .day-label { display: block; font-size: 0.65rem; color: #3b82f6; font-weight: 800; }
    .date-label { font-size: 0.8rem; font-weight: 700; }
</style>

<div class="sch-wrapper container">
    <form method="POST" id="schForm">
        <input type="hidden" name="action" id="formAction" value="save">
        <input type="hidden" name="delete_spec_id" id="delId" value="">

        <div class="sch-top">
            <h1 class="title is-4">Расписание</h1>
            <div class="sch-actions">
                <?php if ($isAdmin): ?>
                    <button type="button" onclick="submitAction('add_spec')" class="btn-add">+ Группа</button>
                    <button type="button" onclick="submitAction('save')" class="btn-save">Сохранить</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-scroll">
            <table class="sch-table">
                <thead>
                    <tr>
                        <th class="col-info">Дата</th>
                        <?php foreach ($sData as $spec): ?>
                            <th class="col-spec">
                                <?php if ($isAdmin): ?>
                                    <button type="button" onclick="deleteSpec(<?= $spec['id'] ?>)" class="btn-del-spec">×</button>
                                    <textarea name="specs[<?= $spec['id'] ?>]" class="edit-area spec-input"><?= htmlspecialchars($spec['name']) ?></textarea>
                                <?php else: ?>
                                    <div class="view-text" style="font-weight:700"><?= htmlspecialchars($spec['name']) ?></div>
                                <?php endif; ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($i = 0; $i < 5; $i++): $dayNum = $i + 1; $info = getDayInfo($i); ?>
                        <tr>
                            <td class="col-info">
                                <span class="day-label"><?= $info['name'] ?></span>
                                <span class="date-label"><?= $info['date'] ?></span>
                            </td>
                            <?php foreach ($sData as $spec): $val = $matrix[$dayNum][$spec['id']] ?? ''; ?>
                                <td>
                                    <?php if ($isAdmin): ?>
                                        <textarea name="rows[<?= $dayNum ?>][cells][<?= $spec['id'] ?>]" class="edit-area lesson-input"><?= htmlspecialchars($val) ?></textarea>
                                    <?php else: ?>
                                        <div class="view-text"><?= htmlspecialchars($val) ?></div>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </form>
</div>

<script>
function submitAction(action) {
    document.getElementById('formAction').value = action;
    document.getElementById('schForm').submit();
}

function deleteSpec(id) {
    if (confirm('Удалить эту колонку и всё её содержимое?')) {
        document.getElementById('delId').value = id;
        submitAction('delete_spec');
    }
}
</script>

<?php include '../includes/footer.php'; ?>