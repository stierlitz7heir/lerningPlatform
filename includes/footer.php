</main>

<footer class="footer" style="background: var(--primary-dark); color: #e0e0e0; margin-top: auto; padding: 3rem 1.5rem 2rem; border-top: 1px solid rgba(255,255,255,0.1);">
    <div class="container">
        <div class="columns is-vcentered is-multiline">

            <div class="column is-4-tablet is-12-mobile has-text-centered-mobile">
                <p class="has-text-weight-semibold mb-1" style="color: #fff;">Электронная образовательная платформа</p>
                <p class="is-size-7" style="opacity: 0.7;">
                    © <?= date("Y") ?> ГАПОУ СО «Энгельсский политехнический колледж»
                </p>
            </div>

            <div class="column is-4-tablet is-12-mobile">
                <div class="buttons is-centered">
                    <a href="https://politehnikum-eng.ru/" target="_blank" class="button is-small is-ghost has-text-light is-clickable">
                        <span class="icon is-small"><i class="fas fa-home"></i></span>
                        <span>Сайт колледжа</span>
                    </a>
                    <a href="https://politehnikum-eng.ru/index/raspisanie_zanjatij/0-409" target="_blank" class="button is-small is-ghost has-text-light is-clickable">
                        <span class="icon is-small"><i class="fas fa-calendar-days"></i></span>
                        <span>Расписание</span>
                    </a>
                </div>
            </div>

            <div class="column is-4-tablet is-12-mobile has-text-right-tablet has-text-centered-mobile">
                <a href="/pages/privacy.php" class="is-size-7 has-text-grey-light hover-underline">
                    Политика конфиденциальности
                </a>
            </div>

        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ru.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<script>
    // Настройка календарей по умолчанию на русский язык
    if (typeof flatpickr !== 'undefined') {
        flatpickr.setDefaults({
            locale: 'ru',
            dateFormat: 'Y-m-d'
        });
    }

    // Обработка закрытия уведомлений Bulma
    document.addEventListener('DOMContentLoaded', () => {
        (document.querySelectorAll('.notification .delete') || []).forEach(($delete) => {
            const $notification = $delete.parentNode;
            $delete.addEventListener('click', () => {
                $notification.style.display = 'none';
            });
        });
    });
</script>

<script src="/js/app.js"></script>

<style>
    /* Sticky Footer Helper */
    body {
        display: flex;
        flex-direction: column;
        min-height: 100vh;
    }
    .hover-underline:hover {
        text-decoration: underline;
        color: #fff !important;
    }
    /* Исправление для кнопок-призраков в темном футере */
    .button.is-ghost.has-text-light:hover {
        background-color: rgba(255,255,255,0.05);
        color: var(--primary-blue) !important;
    }
</style>

</body>
</html>