<?php include '../includes/header.php'; ?>

<section class="section is-fullheight" style="padding: 1.5rem;">
    <div class="container">
        <div class="columns is-mobile is-centered">
            <div class="column is-12-mobile is-8-tablet is-5-desktop">

                <div class="box has-shadow" style="border-radius: 16px; padding: 2rem;">
                    <div class="has-text-centered mb-5">
                        <span class="icon has-text-link" style="font-size: 3rem;">
                            <i class="fas fa-graduation-cap"></i>
                        </span>
                        <h1 class="title is-4 mt-3" style="color: var(--primary-dark);">
                            Вход в систему
                        </h1>
                    </div>

                    <?php
                    if (isset($_GET['error'])):
                        if ($_GET['error'] === 'invalid') {
                            echo '<div class="notification is-danger is-light">Неверный логин или пароль</div>';
                        } elseif ($_GET['error'] === 'empty') {
                            echo '<div class="notification is-danger is-light">Заполните все поля</div>';
                        }
                    endif;
                    ?>

                    <form method="POST" action="functions/login_process.php" id="login-form">

                        <div class="field">
                            <label class="label is-size-7-mobile">Логин или Email</label>
                            <div class="control has-icons-left">
                                <input class="input is-medium" type="text" name="login" placeholder="Введите логин или email" required autofocus>
                                <span class="icon is-left">
                                    <i class="fas fa-user"></i>
                                </span>
                            </div>
                        </div>

                        <div class="field">
                            <label class="label is-size-7-mobile">Пароль</label>
                            <div class="control has-icons-left has-icons-right">
                                <input class="input is-medium" type="password" name="password" id="password-field" placeholder="••••••••" required>
                                <span class="icon is-left">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <span class="icon is-right is-clickable" id="toggle-password">
                                    <i class="fas fa-eye" id="eye-icon"></i>
                                </span>
                            </div>
                        </div>

                        <div class="field">
                            <label class="checkbox is-size-7-mobile">
                                <input type="checkbox" name="remember">
                                Запомнить меня
                            </label>
                        </div>

                        <div class="field mt-4">
                            <button class="button is-link is-fullwidth is-medium" type="submit">
                                Войти в систему
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</section>

<?php include '../includes/footer.php'; ?>