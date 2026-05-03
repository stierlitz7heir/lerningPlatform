<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../db/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success_msg = $error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $new_password = $_POST['password'] ?? '';

    if (empty($full_name)) {
        $error_msg = "ФИО обязательно";
    } else {
        try {
            $db->prepare("UPDATE users SET full_name = ? WHERE id = ?")
               ->execute([$full_name, $user_id]);
            $_SESSION['full_name'] = $full_name;

            if (!empty($new_password)) {
                $hash = password_hash($new_password, PASSWORD_BCRYPT);
                $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                   ->execute([$hash, $user_id]);
            }
            $success_msg = "Профиль успешно обновлён!";
        } catch (Exception $e) {
            $error_msg = "Ошибка обновления";
        }
    }
}

$stmt = $db->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

include '../../includes/header.php';
?>

<section class="section">
    <div class="container">
        <div class="columns">
            <div class="column is-3">
                <?php include 'sidebar_teacher.php'; ?>
            </div>
            <div class="column">
                <div class="box panel-card">
                    <div class="panel-heading">
                        <h1 class="title is-4">Настройки профиля</h1>
                        <p class="is-size-6 has-text-grey">Обновите свои данные и пароль</p>
                    </div>

                    <?php if ($success_msg): ?><div class="notification is-success is-light"><?= $success_msg ?></div><?php endif; ?>
                    <?php if ($error_msg): ?><div class="notification is-danger is-light"><?= $error_msg ?></div><?php endif; ?>

                    <form method="POST">
                        <div class="field">
                            <label class="label">Псевдоним</label>
                            <input class="input" type="text" name="full_name" 
                                   value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" required>
                        </div>

                        <div class="field mt-5">
                            <label class="label">Новый пароль <small>(оставьте пустым, если не меняете)</small></label>
                            <input class="input" type="password" name="password" placeholder="Новый пароль">
                        </div>

                        <button type="submit" class="button is-link mt-5">Сохранить изменения</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include '../../includes/footer.php'; ?>