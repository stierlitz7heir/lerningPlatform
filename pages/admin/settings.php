<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../db/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success_msg = $error_msg = '';

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $display_name = $full_name;
    $new_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!empty($new_password) && $new_password !== $confirm_password) {
        $error_msg = "Пароли не совпадают";
    } else {
        try {
            if ($display_name === '') {
                $display_name = $_SESSION['login'] ?? 'admin';
            }
            $db->prepare("UPDATE users SET full_name = ? WHERE id = ?")
               ->execute([$display_name, $user_id]);
            $_SESSION['full_name'] = $display_name;

            if (!empty($new_password)) {
                $hash = password_hash($new_password, PASSWORD_BCRYPT);
                $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                   ->execute([$hash, $user_id]);
            }
            $success_msg = "Данные успешно обновлены!";
        } catch (Exception $e) {
            $error_msg = "Ошибка: " . $e->getMessage();
        }
    }
}

$stmt = $db->prepare("SELECT full_name, login, role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

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
                    <h1 class="title is-4 mb-5">Профиль оператора ДПО</h1>

                    <?php if ($success_msg): ?>
                        <div class="notification is-success is-light"><?= $success_msg ?></div>
                    <?php endif; ?>
                    <?php if ($error_msg): ?>
                        <div class="notification is-danger is-light"><?= $error_msg ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="field">
                            <label class="label">Псевдоним</label>
                            <div class="control">
                                <input class="input" type="text" name="full_name" 
                                       value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" placeholder="Если пусто, будет использован логин">
                            </div>
                        </div>

                        <div class="field">
                            <label class="label">Логин</label>
                            <div class="control">
                                <input class="input is-static" type="text" 
                                       value="<?= htmlspecialchars($user['login'] ?? '') ?>" readonly>
                            </div>
                        </div>

                        <div class="field">
                            <label class="label">Роль</label>
                            <div class="control">
                                <input class="input is-static" type="text" 
                                       value="<?= ucfirst($user['role'] ?? '') ?>" readonly>
                            </div>
                        </div>

                        <div class="field mt-5">
                            <label class="label">Новый пароль <small>(оставьте пустым, если не меняете)</small></label>
                            <div class="control">
                                <input class="input" type="password" name="password" placeholder="Новый пароль">
                            </div>
                        </div>

                        <div class="field">
                            <label class="label">Подтверждение пароля</label>
                            <div class="control">
                                <input class="input" type="password" name="confirm_password" placeholder="Повторите пароль">
                            </div>
                        </div>

                        <div class="field mt-6">
                            <button type="submit" class="button is-link is-medium">
                                <span class="icon"><i class="fas fa-save"></i></span>
                                <span>Сохранить изменения</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include '../../includes/footer.php'; ?>