-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1:3307
-- Время создания: Апр 28 2026 г., 10:21
-- Версия сервера: 8.0.30
-- Версия PHP: 8.1.9

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `learningPlatform`
--

-- --------------------------------------------------------

--
-- Структура таблицы `assignments`
--

CREATE TABLE `assignments` (
  `id` int NOT NULL,
  `course_id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `due_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `assignments`
--

INSERT INTO `assignments` (`id`, `course_id`, `title`, `file_path`, `description`, `due_date`, `created_at`) VALUES
(24, 19, 'WS-1: Настройка проекта', NULL, 'Подготовить структуру проекта и проверить авторизацию.', '2026-05-10 23:59:59', '2026-04-28 06:14:46'),
(25, 19, 'WS-2: Форма отправки решения', NULL, 'Собрать форму отправки, валидацию и загрузку файла.', '2026-05-13 23:59:59', '2026-04-28 06:14:46'),
(26, 19, 'WS-3: Журнал преподавателя', NULL, 'Сделать матрицу статусов и модальное окно истории попыток.', '2026-05-16 23:59:59', '2026-04-28 06:14:46'),
(27, 19, 'WS-4: Sidebar и блокировки', NULL, 'Реализовать линейность уроков и статусы доступа.', '2026-05-18 23:59:59', '2026-04-28 06:14:46'),
(28, 20, 'SQL-1: Агрегации и отчеты', NULL, 'Сформировать отчеты по попыткам и прогрессу студентов.', '2026-05-12 23:59:59', '2026-04-28 06:14:46'),
(29, 20, 'SQL-2: Оптимизация запросов', NULL, 'Добавить индексы и проверить план выполнения.', '2026-05-15 23:59:59', '2026-04-28 06:14:46'),
(30, 21, 'FP-1: ТЗ и архитектура', NULL, 'Подготовить ТЗ и схему модулей проекта.', '2026-05-20 23:59:59', '2026-04-28 06:14:46');

-- --------------------------------------------------------

--
-- Структура таблицы `courses`
--

CREATE TABLE `courses` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `teacher_id` int DEFAULT NULL,
  `description` text,
  `image_path` varchar(255) DEFAULT NULL,
  `status` enum('Активный','Архив','Черновик') NOT NULL DEFAULT 'Черновик',
  `category` varchar(100) DEFAULT 'Профессиональное обучение',
  `duration_hours` int DEFAULT '72',
  `price` decimal(10,2) DEFAULT '0.00',
  `project_name` varchar(120) DEFAULT NULL,
  `learning_format` varchar(120) DEFAULT NULL,
  `training_type` varchar(120) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `courses`
--

INSERT INTO `courses` (`id`, `name`, `teacher_id`, `description`, `image_path`, `status`, `category`, `duration_hours`, `price`, `project_name`, `learning_format`, `training_type`) VALUES
(19, 'WorldSkills: Веб-разработка PHP', 3, 'Практический трек по PHP, PDO и модульной платформе обучения с поэтапной сдачей уроков.', NULL, 'Активный', 'Профессиональное обучение', 72, '0.00', NULL, NULL, NULL),
(20, 'WorldSkills: SQL и аналитика', 3, 'Отработка SQL-запросов, проектирование схемы данных и анализ учебной статистики.', NULL, 'Активный', 'Профессиональное обучение', 48, '0.00', NULL, NULL, NULL),
(21, 'WorldSkills: Финальный проект', 3, 'Итоговый курс с защитой мини-проекта и проверкой по критериям эксперта.', '/images/courses/course_692af2b1dd73f07e.png', 'Черновик', 'Профессиональное обучение', 36, '0.00', NULL, NULL, NULL),
(22, '', NULL, '', '/images/courses/course_b417febf24e45e19.png', 'Архив', '', 72, '0.00', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `course_enrollments`
--

CREATE TABLE `course_enrollments` (
  `id` int NOT NULL,
  `course_id` int NOT NULL,
  `student_id` int NOT NULL,
  `enrolled_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `grade` int DEFAULT NULL,
  `last_accepted_lesson_id` int DEFAULT NULL,
  `last_completed_lesson_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `course_enrollments`
--

INSERT INTO `course_enrollments` (`id`, `course_id`, `student_id`, `enrolled_at`, `grade`, `last_accepted_lesson_id`, `last_completed_lesson_id`) VALUES
(8, 19, 2, '2026-04-28 06:14:46', 84, 24, 24),
(9, 20, 2, '2026-04-28 06:14:46', 91, NULL, NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `course_subscriptions`
--

CREATE TABLE `course_subscriptions` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `course_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('pending','active','rejected','completed') COLLATE utf8mb4_general_ci DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `course_subscriptions`
--

INSERT INTO `course_subscriptions` (`id`, `user_id`, `course_id`, `created_at`, `status`) VALUES
(6, 2, 19, '2026-04-28 06:14:46', 'active'),
(7, 2, 20, '2026-04-28 06:14:46', 'completed'),
(8, 2, 21, '2026-04-28 06:14:46', 'pending');

-- --------------------------------------------------------

--
-- Структура таблицы `groups_table`
--

CREATE TABLE `groups_table` (
  `id` int NOT NULL,
  `course_id` int DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `program` varchar(255) DEFAULT NULL,
  `student_count` int NOT NULL DEFAULT '0',
  `status` enum('Активная','Завершена') NOT NULL DEFAULT 'Активная'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `groups_table`
--

INSERT INTO `groups_table` (`id`, `course_id`, `name`, `program`, `student_count`, `status`) VALUES
(6, NULL, 'ИС-414/22', 'Веб-разработка и программирование', 1, 'Активная');

-- --------------------------------------------------------

--
-- Структура таблицы `schedule`
--

CREATE TABLE `schedule` (
  `id` int NOT NULL,
  `day_number` int NOT NULL,
  `specialization_id` int NOT NULL,
  `lesson` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `schedule`
--

INSERT INTO `schedule` (`id`, `day_number`, `specialization_id`, `lesson`) VALUES
(52, 1, 108, '14:00\r\nкаб.15\r\nБашкова И.О.'),
(53, 1, 113, '13:30\r\nкаб. 15\r\nБарашкова О.Ю.'),
(54, 2, 110, '13:30\r\nкаб.61\r\n\r\nБелоусова М.К.'),
(55, 2, 111, '13:30\r\nкаб. 52\r\nЭКЗАМЕН\r\nАлахвердиева И.И.'),
(56, 2, 120, '13:30\r\nЛаборатория БАС\r\nЖивотиковская, д.13\r\nКитаев А.А.');

-- --------------------------------------------------------

--
-- Структура таблицы `specializations`
--

CREATE TABLE `specializations` (
  `id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `specializations`
--

INSERT INTO `specializations` (`id`, `name`) VALUES
(108, 'Водитель кат. «В» '),
(109, 'Облицовщик-плиточник'),
(110, 'Сварщик РД'),
(111, 'Электромонтажник'),
(112, 'Слесарь по ремонту автомобилей'),
(113, 'Основы вокала'),
(114, 'Парикмахер'),
(115, 'Коммерция и осуществление интернет-маркетинга'),
(116, 'Портной'),
(117, 'Официант'),
(118, 'Бармен'),
(119, 'Кондитер'),
(120, 'Оператор БАС');

-- --------------------------------------------------------

--
-- Структура таблицы `submissions`
--

CREATE TABLE `submissions` (
  `id` int NOT NULL,
  `assignment_id` int NOT NULL,
  `student_id` int NOT NULL,
  `attempt_number` int NOT NULL DEFAULT '1',
  `status` enum('on_review','revision','accepted','submitted','review') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'on_review',
  `text_content` text COLLATE utf8mb4_general_ci,
  `file_url` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `teacher_feedback` text COLLATE utf8mb4_general_ci,
  `answer_text` text COLLATE utf8mb4_general_ci,
  `file_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `teacher_comment` text COLLATE utf8mb4_general_ci,
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `comment` text COLLATE utf8mb4_general_ci,
  `grade` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `submissions`
--

INSERT INTO `submissions` (`id`, `assignment_id`, `student_id`, `attempt_number`, `status`, `text_content`, `file_url`, `teacher_feedback`, `answer_text`, `file_path`, `teacher_comment`, `submitted_at`, `comment`, `grade`) VALUES
(12, 24, 2, 1, 'accepted', 'Стартовый проект собран, авторизация и подключение PDO работают.', 'uploads/submissions/ws_web_l1_a1.pdf', 'Принято. Архитектура аккуратная.', 'Стартовый проект собран, авторизация и подключение PDO работают.', 'uploads/submissions/ws_web_l1_a1.pdf', 'Принято. Архитектура аккуратная.', '2026-05-09 11:20:00', 'Принято. Архитектура аккуратная.', 92),
(13, 25, 2, 1, 'revision', 'Сделал форму отправки, но без полной валидации и истории попыток.', 'uploads/submissions/ws_web_l2_a1.docx', 'Вернуть на доработку: добавьте серверную проверку и обработку повторных попыток.', 'Сделал форму отправки, но без полной валидации и истории попыток.', 'uploads/submissions/ws_web_l2_a1.docx', 'Вернуть на доработку: добавьте серверную проверку и обработку повторных попыток.', '2026-05-11 08:00:00', 'Вернуть на доработку: добавьте серверную проверку и обработку повторных попыток.', 63),
(14, 25, 2, 2, 'on_review', 'Добавил серверную валидацию, инкремент попыток и обновил отображение истории.', 'uploads/submissions/ws_web_l2_a2.docx', NULL, 'Добавил серверную валидацию, инкремент попыток и обновил отображение истории.', 'uploads/submissions/ws_web_l2_a2.docx', NULL, '2026-05-12 13:35:00', NULL, NULL),
(15, 28, 2, 1, 'accepted', 'Сформирован отчет по студенту, уроку и статусам с использованием оконных функций.', 'uploads/submissions/ws_sql_l1_a1.sql', 'Принято. Запросы корректные.', 'Сформирован отчет по студенту, уроку и статусам с использованием оконных функций.', 'uploads/submissions/ws_sql_l1_a1.sql', 'Принято. Запросы корректные.', '2026-05-10 15:40:00', 'Принято. Запросы корректные.', 95),
(16, 29, 2, 1, 'on_review', 'Добавлены индексы и объяснение оптимизации сложного запроса.', 'uploads/submissions/ws_sql_l2_a1.sql', NULL, 'Добавлены индексы и объяснение оптимизации сложного запроса.', 'uploads/submissions/ws_sql_l2_a1.sql', NULL, '2026-05-13 06:25:00', NULL, NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int NOT NULL,
  `param_key` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `param_value` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `system_settings`
--

INSERT INTO `system_settings` (`id`, `param_key`, `param_value`) VALUES
(1, 'site_name', 'ЭОП | Энгельсский политехнический колледж'),
(2, 'contact_email', 'admin@epk-edu.ru');

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `login` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('student','teacher','admin') NOT NULL DEFAULT 'student',
  `full_name` varchar(100) NOT NULL,
  `group_name` varchar(20) DEFAULT NULL,
  `group_id` int DEFAULT NULL,
  `remember_token` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `login`, `password_hash`, `role`, `full_name`, `group_name`, `group_id`, `remember_token`) VALUES
(1, 'admin', '$2a$12$eK8AKrHAGchz12UhgnCwweFNlSVEfs6UfmpzD/4u2UBm0xVr6..Le', 'admin', 'Администратор платформы', NULL, NULL, '4e0715d13700b6d26ca06061d3559d1361d5971e9074cc10142e05de06f794cc'),
(2, 'stierlitz7heir', '$2y$10$SJ.C97qnNvJQls2dtW46duOtfoK.5K6k68cDbsuf0S3LTcmZXeqkK', 'student', 'Кооль Валерий Павлович', 'ИС-414/22', 6, NULL),
(3, 'denlym', '$2y$10$D/utQTzU2HEfeiPjYSD4Rup0l1VxBKEw3eqxgZ/NBAEU0T.oKi332', 'teacher', 'Тугушев Денислям Умярович', NULL, NULL, '766d05786387e8d10a28c8b551b35875322576ff4d3080cf7cdb52c57b4ab510');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `assignments`
--
ALTER TABLE `assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`);

--
-- Индексы таблицы `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `course_enrollments`
--
ALTER TABLE `course_enrollments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Индексы таблицы `course_subscriptions`
--
ALTER TABLE `course_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_subscription` (`user_id`,`course_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Индексы таблицы `groups_table`
--
ALTER TABLE `groups_table`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_group_course` (`course_id`);

--
-- Индексы таблицы `schedule`
--
ALTER TABLE `schedule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `specialization_id` (`specialization_id`);

--
-- Индексы таблицы `specializations`
--
ALTER TABLE `specializations`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `submissions`
--
ALTER TABLE `submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `idx_submissions_assignment_student_attempt` (`assignment_id`,`student_id`,`attempt_number`);

--
-- Индексы таблицы `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `param_key` (`param_key`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `login` (`login`),
  ADD KEY `fk_user_group` (`group_id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `assignments`
--
ALTER TABLE `assignments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT для таблицы `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT для таблицы `course_enrollments`
--
ALTER TABLE `course_enrollments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT для таблицы `course_subscriptions`
--
ALTER TABLE `course_subscriptions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT для таблицы `groups_table`
--
ALTER TABLE `groups_table`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `schedule`
--
ALTER TABLE `schedule`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT для таблицы `specializations`
--
ALTER TABLE `specializations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=123;

--
-- AUTO_INCREMENT для таблицы `submissions`
--
ALTER TABLE `submissions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT для таблицы `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `assignments`
--
ALTER TABLE `assignments`
  ADD CONSTRAINT `assignments_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `course_enrollments`
--
ALTER TABLE `course_enrollments`
  ADD CONSTRAINT `course_enrollments_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `course_enrollments_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `course_subscriptions`
--
ALTER TABLE `course_subscriptions`
  ADD CONSTRAINT `course_subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `course_subscriptions_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `groups_table`
--
ALTER TABLE `groups_table`
  ADD CONSTRAINT `fk_group_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `schedule`
--
ALTER TABLE `schedule`
  ADD CONSTRAINT `schedule_ibfk_1` FOREIGN KEY (`specialization_id`) REFERENCES `specializations` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `submissions`
--
ALTER TABLE `submissions`
  ADD CONSTRAINT `submissions_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `submissions_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_group` FOREIGN KEY (`group_id`) REFERENCES `groups_table` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
