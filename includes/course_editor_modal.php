<?php
/** @var string $courseEditorApi относительный URL, напр. manage_courses.php */
/** @var bool $courseEditorShowTeacherPicker */
/** @var array<int, array{id:int, full_name:string}> $courseEditorTeachers */
$courseEditorApi = $courseEditorApi ?? 'manage_courses.php';
$courseEditorShowTeacherPicker = $courseEditorShowTeacherPicker ?? false;
$courseEditorTeachers = $courseEditorTeachers ?? [];
?>
<div class="modal course-editor-modal" id="courseModal">
    <div class="modal-background" onclick="closeCourseEditorModal()"></div>
    <div class="modal-card course-editor-modal-card">
        <header class="modal-card-head course-editor-modal-head">
            <div>
                <p class="modal-card-title mb-1" id="courseModalTitle">Программа ДПО</p>
                <p class="is-size-7 has-text-grey mb-0">Заполните карточку: текст для каталога, обложка, структура модулей.</p>
            </div>
            <button type="button" class="delete" onclick="closeCourseEditorModal()" aria-label="Закрыть"></button>
        </header>
        <section class="modal-card-body course-editor-modal-body">
            <input type="hidden" id="courseId" value="">

            <div class="columns is-multiline">
                <div class="column is-12-tablet is-5-desktop">
                    <label class="label">Обложка программы</label>
                    <input type="hidden" id="courseImagePath" value="">
                    <div class="course-editor-dropzone" id="courseImageDropzone">
                        <img id="courseImagePreview" class="course-editor-preview-img" src="" alt="">
                        <div class="course-editor-dropzone-placeholder" id="courseImagePlaceholder">
                            <span class="icon is-large has-text-grey"><i class="fas fa-cloud-upload-alt fa-2x"></i></span>
                            <p class="has-text-weight-semibold mt-2">Перетащите файл сюда</p>
                            <p class="is-size-7 has-text-grey">или нажмите «Выбрать» · JPG, PNG, WebP, GIF · до 5 МБ</p>
                        </div>
                        <div class="file is-centered mt-3">
                            <label class="file-label">
                                <input class="file-input" type="file" id="courseImageFile" accept="image/jpeg,image/png,image/webp,image/gif">
                                <span class="file-cta button is-light is-small">
                                    <span class="file-icon"><i class="fas fa-folder-open"></i></span>
                                    <span>Выбрать изображение</span>
                                </span>
                            </label>
                        </div>
                        <p class="is-size-7 has-text-grey mt-2" id="courseImageFileName">Файл не выбран</p>
                    </div>
                </div>
                <div class="column is-12-tablet is-7-desktop">
                    <div class="field">
                        <label class="label">Название программы <span class="has-text-danger">*</span></label>
                        <input class="input" type="text" id="courseName" required maxlength="255" placeholder="Как отображается в каталоге">
                    </div>
                    <div class="field">
                        <label class="label">Вид программы</label>
                        <input class="input" type="text" id="courseCategory" maxlength="100" placeholder="Например: Повышение квалификации">
                    </div>
                    <div class="columns is-mobile">
                        <div class="column is-6">
                            <div class="field">
                                <label class="label">Объём, часов</label>
                                <input class="input" type="number" id="courseHours" min="1" max="2000" step="1" placeholder="72">
                            </div>
                        </div>
                        <div class="column is-6">
                            <div class="field">
                                <label class="label">Статус</label>
                                <div class="select is-fullwidth">
                                    <select id="courseStatus">
                                        <option value="Активный">Активный — в каталоге</option>
                                        <option value="Черновик">Черновик — скрыт</option>
                                        <option value="Архив">Архив</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if ($courseEditorShowTeacherPicker): ?>
                    <div class="field">
                        <label class="label">Преподаватель</label>
                        <div class="select is-fullwidth">
                            <select id="teacherId">
                                <option value="">— Не назначен —</option>
                                <?php foreach ($courseEditorTeachers as $t): ?>
                                    <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php else: ?>
                    <input type="hidden" id="teacherId" value="">
                    <?php endif; ?>
                </div>
            </div>

            <hr class="course-editor-hr">

            <div class="field">
                <label class="label">Описание для страницы курса</label>
                <p class="help mb-2">Отображается студентам в блоке «О программе». Можно несколько абзацев.</p>
                <textarea class="textarea course-editor-textarea" id="courseDesc" rows="8" placeholder="Цели, аудитория, формат, требования…"></textarea>
                <p class="is-size-7 has-text-grey mt-1"><span id="courseDescCount">0</span> символов</p>
            </div>

            <div class="field">
                <label class="label">Структура программы (модули)</label>
                <p class="help mb-2">Каждая строка — отдельный пункт списка на странице курса. Если заданы уроки в системе, они показываются дополнительно.</p>
                <textarea class="textarea" id="courseSyllabus" rows="6" placeholder="Модуль 1: …&#10;Модуль 2: …&#10;Модуль 3: …"></textarea>
            </div>
        </section>
        <footer class="modal-card-foot course-editor-modal-foot">
            <button type="button" class="button" onclick="closeCourseEditorModal()">Отмена</button>
            <button type="button" class="button is-link" onclick="saveCourseFromEditor()">
                <span class="icon"><i class="fas fa-save"></i></span>
                <span>Сохранить</span>
            </button>
        </footer>
    </div>
</div>

<script>
(function () {
    const COURSE_EDITOR_API = <?= json_encode($courseEditorApi, JSON_UNESCAPED_UNICODE) ?>;
    const SHOW_TEACHER_PICKER = <?= $courseEditorShowTeacherPicker ? 'true' : 'false' ?>;

    let droppedCourseFile = null;

    function setPreview(src) {
        const img = document.getElementById('courseImagePreview');
        const ph = document.getElementById('courseImagePlaceholder');
        if (src && String(src).trim() !== '') {
            img.src = src;
            img.style.display = 'block';
            ph.style.display = 'none';
        } else {
            img.removeAttribute('src');
            img.style.display = 'none';
            ph.style.display = 'flex';
        }
    }

    window.openCourseEditorModal = function () {
        droppedCourseFile = null;
        document.getElementById('courseModalTitle').textContent = 'Новая программа';
        document.getElementById('courseId').value = '';
        document.getElementById('courseName').value = '';
        document.getElementById('courseDesc').value = '';
        document.getElementById('courseSyllabus').value = '';
        document.getElementById('courseCategory').value = '';
        document.getElementById('courseImagePath').value = '';
        document.getElementById('courseImageFile').value = '';
        document.getElementById('courseImageFileName').textContent = 'Файл не выбран';
        document.getElementById('courseHours').value = 72;
        document.getElementById('courseStatus').value = 'Активный';
        if (SHOW_TEACHER_PICKER) {
            document.getElementById('teacherId').value = '';
        }
        setPreview('');
        document.getElementById('courseModal').classList.add('is-active');
        updateDescCount();
    };

    window.closeCourseEditorModal = function () {
        document.getElementById('courseModal').classList.remove('is-active');
    };

    window.editCourseInEditor = function (course) {
        document.getElementById('courseModalTitle').textContent = 'Редактирование программы';
        document.getElementById('courseId').value = course.id || '';
        document.getElementById('courseName').value = course.name || '';
        document.getElementById('courseDesc').value = course.description || '';
        document.getElementById('courseSyllabus').value = course.syllabus || '';
        document.getElementById('courseCategory').value = course.category || '';
        document.getElementById('courseImagePath').value = course.image_path || '';
        document.getElementById('courseImageFile').value = '';
        document.getElementById('courseImageFileName').textContent = course.image_path ? 'Сохранённое изображение (загрузите новый для замены)' : 'Файл не выбран';
        document.getElementById('courseHours').value = course.duration_hours || 72;
        document.getElementById('courseStatus').value = course.status || 'Активный';
        if (SHOW_TEACHER_PICKER) {
            document.getElementById('teacherId').value = course.teacher_id ? String(course.teacher_id) : '';
        }
        setPreview(course.image_path || '');
        document.getElementById('courseModal').classList.add('is-active');
        updateDescCount();
    };

    function updateDescCount() {
        const el = document.getElementById('courseDesc');
        const n = el ? el.value.length : 0;
        const c = document.getElementById('courseDescCount');
        if (c) c.textContent = String(n);
    }

    document.getElementById('courseDesc').addEventListener('input', updateDescCount);

    document.getElementById('courseImageFile').addEventListener('change', function (e) {
        const file = e.target.files && e.target.files[0] ? e.target.files[0] : null;
        document.getElementById('courseImageFileName').textContent = file ? file.name : 'Файл не выбран';
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function (evt) {
            const img = document.getElementById('courseImagePreview');
            const ph = document.getElementById('courseImagePlaceholder');
            img.src = evt.target.result;
            img.style.display = 'block';
            ph.style.display = 'none';
        };
        reader.readAsDataURL(file);
    });

    const dz = document.getElementById('courseImageDropzone');
    ['dragenter', 'dragover'].forEach(function (ev) {
        dz.addEventListener(ev, function (e) {
            e.preventDefault();
            dz.classList.add('is-dragover');
        });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
        dz.addEventListener(ev, function (e) {
            e.preventDefault();
            dz.classList.remove('is-dragover');
        });
    });
    dz.addEventListener('drop', function (e) {
        const f = e.dataTransfer.files && e.dataTransfer.files[0];
        if (!f || !/^image\//.test(f.type)) return;
        droppedCourseFile = f;
        document.getElementById('courseImageFile').value = '';
        document.getElementById('courseImageFileName').textContent = f.name;
        const reader = new FileReader();
        reader.onload = function (evt) {
            document.getElementById('courseImagePreview').src = evt.target.result;
            document.getElementById('courseImagePlaceholder').style.display = 'none';
        };
        reader.readAsDataURL(f);
    });

    async function uploadCourseImageIfNeeded() {
        const fileInput = document.getElementById('courseImageFile');
        let file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
        if (!file && droppedCourseFile) {
            file = droppedCourseFile;
        }
        if (!file) {
            return document.getElementById('courseImagePath').value || '';
        }
        const formData = new FormData();
        formData.append('course_image', file);
        const response = await fetch(COURSE_EDITOR_API + '?action=upload_image', {
            method: 'POST',
            body: formData
        });
        const json = await response.json();
        droppedCourseFile = null;
        if (!json.success) {
            throw new Error(json.message || 'Ошибка загрузки изображения');
        }
        document.getElementById('courseImagePath').value = json.path;
        return json.path;
    }

    window.saveCourseFromEditor = async function () {
        let imagePath = '';
        try {
            imagePath = await uploadCourseImageIfNeeded();
        } catch (e) {
            alert('Ошибка загрузки изображения: ' + e.message);
            return;
        }

        const data = {
            id: document.getElementById('courseId').value || null,
            name: document.getElementById('courseName').value.trim(),
            description: document.getElementById('courseDesc').value.trim(),
            syllabus: document.getElementById('courseSyllabus').value.trim(),
            category: document.getElementById('courseCategory').value.trim(),
            image_path: imagePath,
            duration_hours: Number(document.getElementById('courseHours').value || 72),
            status: document.getElementById('courseStatus').value
        };
        if (SHOW_TEACHER_PICKER) {
            const tid = document.getElementById('teacherId').value;
            data.teacher_id = tid ? Number(tid) : null;
        }

        const res = await fetch(COURSE_EDITOR_API + '?action=save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const json = await res.json();
        if (json.success) {
            location.reload();
            return;
        }
        alert('Ошибка: ' + (json.message || 'Не удалось сохранить'));
    };

    window.openModal = window.openCourseEditorModal;
    window.closeModal = window.closeCourseEditorModal;
    window.editCourse = window.editCourseInEditor;
    window.saveCourse = window.saveCourseFromEditor;
})();
</script>
