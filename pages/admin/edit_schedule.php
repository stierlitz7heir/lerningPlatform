<?php include '../../includes/header.php'; ?>

<section class="section">
    <div class="container">
        <h1 class="title" style="color: var(--primary-dark);">Редактирование расписания</h1>
        <p class="is-size-6 has-text-grey">ГАПОУ СО «Энгельсский политехнический колледж»</p>

        <div class="box mb-6">
            <div class="field is-horizontal">
                <div class="field-label is-normal">
                    <label class="label">Выберите программу / группу</label>
                </div>
                <div class="field-body">
                    <div class="select is-medium">
                        <select id="group-select">
                            <option value="">— Выберите программу —</option>
                            <option value="driver_b">Водитель категории "В"</option>
                            <option value="tiler">Облицовщик-плиточник</option>
                            <option value="welder">Сварщик Р.Д.</option>
                            <option value="electrician">Электромонтажник</option>
                            <option value="auto_repair">Слесарь по ремонту автомобилей</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="box">
            <div class="level">
                <div class="level-left">
                    <h3 class="title is-5">Расписание на неделю</h3>
                </div>
                <div class="level-right">
                    <div class="buttons">
                        <button onclick="addRow()" class="button is-info is-small">
                            <span class="icon"><i class="fas fa-plus"></i></span>
                            Добавить пару
                        </button>
                        <button onclick="saveSchedule()" class="button is-success is-small">
                            <span class="icon"><i class="fas fa-save"></i></span>
                            Сохранить расписание
                        </button>
                    </div>
                </div>
            </div>

            <div class="table-container" style="overflow-x: auto;">
                <table class="table is-fullwidth is-bordered is-hoverable" id="schedule-table">
                    <thead>
                        <tr style="background: #f0f4ff;">
                            <th style="width: 140px;">День недели</th>
                            <th style="width: 80px;">Пара</th>
                            <th>Предмет</th>
                            <th>Преподаватель</th>
                            <th>Кабинет</th>
                            <th style="width: 80px;">Действия</th>
                        </tr>
                    </thead>
                    <tbody id="schedule-body">
                        <!-- Заполняется JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<script>
function addRow() {
    const tbody = document.getElementById('schedule-body');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td contenteditable="true" class="has-text-weight-medium">Понедельник</td>
        <td contenteditable="true" class="has-text-centered">1</td>
        <td contenteditable="true">—</td>
        <td contenteditable="true">—</td>
        <td contenteditable="true">—</td>
        <td>
            <button onclick="deleteRow(this)" class="button is-small is-danger">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
}

function deleteRow(btn) {
    if (confirm('Удалить эту пару?')) {
        btn.closest('tr').remove();
    }
}

function saveSchedule() {
    const rows = [];
    document.querySelectorAll('#schedule-body tr').forEach(tr => {
        const cells = tr.querySelectorAll('td[contenteditable]');
        rows.push({
            day: cells[0].innerText.trim(),
            pair: cells[1].innerText.trim(),
            subject: cells[2].innerText.trim(),
            teacher: cells[3].innerText.trim(),
            room: cells[4].innerText.trim()
        });
    });

    localStorage.setItem('scheduleData', JSON.stringify(rows));
    alert('✅ Расписание успешно сохранено!');
}

document.addEventListener('DOMContentLoaded', () => {
    const tbody = document.getElementById('schedule-body');
    tbody.innerHTML = `
        <tr>
            <td contenteditable="true" class="has-text-weight-medium">Понедельник</td>
            <td contenteditable="true" class="has-text-centered">1</td>
            <td contenteditable="true">Математика</td>
            <td contenteditable="true">Иванов И.И.</td>
            <td contenteditable="true">ауд. 203</td>
            <td><button onclick="deleteRow(this)" class="button is-small is-danger"><i class="fas fa-trash"></i></button></td>
        </tr>
        <tr>
            <td contenteditable="true" class="has-text-weight-medium">Понедельник</td>
            <td contenteditable="true" class="has-text-centered">2</td>
            <td contenteditable="true">Программирование</td>
            <td contenteditable="true">Кузнецов А.А.</td>
            <td contenteditable="true">лаб. 304</td>
            <td><button onclick="deleteRow(this)" class="button is-small is-danger"><i class="fas fa-trash"></i></button></td>
        </tr>
    `;
});
</script>

<?php include '../../includes/footer.php'; ?>