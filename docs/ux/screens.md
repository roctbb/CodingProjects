# Экраны, состояния и переходы

Генерируется из `system-map.json`. «Изучен код» не означает «визуально проверен». Роли описывают пользовательский сценарий; точные middleware перечислены в `routes.md`, дополнительные проверки — в контроллерах и policy.

<a id="login"></a>

## Вход

Тип: page. Роли сценария: Гость.

Войти по паролю или через Силаэдр

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: пустая форма; ошибка входа; ошибка SSO; доступность SSO.

Шаблон экрана: auth. Статус: implemented.

Маршруты:

- `GET|HEAD /` — redirect
- `GET|HEAD /home` — redirect
- `GET|HEAD /login` — screen
- `POST /login` — action

Переходы:

- Создать аккаунт → [Регистрация](#register) (navigate)
- Забыли пароль → [Восстановление доступа](#password-request) (navigate)
- Успешный вход → [Мои курсы](#courses) (action)
- Войти через Силаэдр → [Личный кабинет Силаэдра](#silaeder) (external)

Исходники:

- [resources/views/auth/login.blade.php](../../resources/views/auth/login.blade.php)

<a id="register"></a>

## Регистрация

Тип: page. Роли сценария: Гость.

Создать учётную запись; возможен контекст приглашения на курс

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: обычная регистрация; course_id; валидация; капча.

Шаблон экрана: auth. Статус: implemented.

Маршруты:

- `GET|HEAD /register` — screen
- `POST /register` — action

Переходы:

- Уже есть аккаунт → [Вход](#login) (navigate)
- Зарегистрироваться → [Подтверждение почты](#verify) (action)
- Далее: профиль → [Профиль при регистрации](#register-profile) (state)

Исходники:

- [resources/views/auth/register.blade.php](../../resources/views/auth/register.blade.php)

<a id="password-request"></a>

## Восстановление доступа

Тип: page. Роли сценария: Гость.

Получить ссылку сброса пароля

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: форма; ссылка отправлена; ошибка адреса.

Шаблон экрана: auth. Статус: implemented.

Маршруты:

- `POST /password/email` — action
- `GET|HEAD /password/reset` — screen

Переходы:

- Ссылка из письма → [Новый пароль](#password-reset) (external)

Исходники:

- [resources/views/auth/passwords/email.blade.php](../../resources/views/auth/passwords/email.blade.php)

<a id="password-reset"></a>

## Новый пароль

Тип: page. Роли сценария: Гость.

Задать пароль по токену

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: валидный токен; просроченный токен; валидация.

Шаблон экрана: auth. Статус: implemented.

Маршруты:

- `POST /password/reset` — action
- `GET|HEAD /password/reset/{token}` — screen

Переходы:

- Сохранить пароль → [Мои курсы](#courses) (action)

Исходники:

- [resources/views/auth/passwords/reset.blade.php](../../resources/views/auth/passwords/reset.blade.php)

<a id="verify"></a>

## Подтверждение почты

Тип: page. Роли сценария: Новый пользователь, Ученик, Преподаватель, Администратор.

Подтвердить почту и продолжить вход

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: письмо отправлено; повторная отправка; переход по подписанной ссылке.

Шаблон экрана: auth. Статус: implemented.

Маршруты:

- `POST /email/resend` — action
- `GET|HEAD /email/verify` — screen
- `GET|HEAD /email/verify/{id}/{hash}` — action

Переходы:

- Подтвердить почту → [Мои курсы](#courses) (action)

Исходники:

- [resources/views/auth/verify.blade.php](../../resources/views/auth/verify.blade.php)

<a id="courses"></a>

## Мои курсы

Тип: page. Роли сценария: Новый пользователь, Ученик, Преподаватель, Администратор.

Выбрать курс, продолжить работу или присоединиться

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: ученик; преподаватель; активные; черновики; архив; нет курсов; дедлайны; дни рождения; преподаватель: один счётчик GC и XP; сегодняшние дни рождения; группы будущих дат; все даты ближайших семи дней; пустой пульс; больше восьми именинников; переход года; скрытая дата рождения; единая сетка активных и пройденных курсов ученика; карточка курса: наведение и клавиатурный фокус; аватар на ширину личной карточки, переход в профиль; SVG-иконки: автовыбор и ручной выбор; пульс между заголовком и действиями / под заголовком; именинники без аватаров.

Шаблон экрана: course-list. Статус: implemented.

Маршруты:

- `GET|HEAD /insider` — redirect
- `GET|HEAD /insider/courses` — screen

Переходы:

- Активные / черновики / архив → [Активные / черновики / архив](#course-tabs) (state)
- Код приглашения / запись на курс → [Код приглашения / запись на курс](#join-course) (state)
- Открыть курс → [Страница курса](#course) (navigate)
- Создать курс → [Создание курса](#course-create) (navigate)
- Число и график пульса в шапке → [Пульс](#pulse) (navigate)
- Открыть свой профиль по аватару → [Профиль](#profile) (navigate)

Исходники:

- [resources/views/home.blade.php](../../resources/views/home.blade.php)
- [resources/views/courses/partials/home_profile_panel.blade.php](../../resources/views/courses/partials/home_profile_panel.blade.php)
- [resources/views/courses/partials/birthday_rows.blade.php](../../resources/views/courses/partials/birthday_rows.blade.php)
- [resources/views/components/gc-deadline-preview.blade.php](../../resources/views/components/gc-deadline-preview.blade.php)
- [resources/views/courses/partials/home_course_card.blade.php](../../resources/views/courses/partials/home_course_card.blade.php)
- [resources/views/courses/partials/home_completed_course_card.blade.php](../../resources/views/courses/partials/home_completed_course_card.blade.php)
- [app/Queries/CourseDeadlineList.php](../../app/Queries/CourseDeadlineList.php)
- [resources/views/courses/partials/home_activity_feed.blade.php](../../resources/views/courses/partials/home_activity_feed.blade.php)
- [resources/views/components/gc-page-header.blade.php](../../resources/views/components/gc-page-header.blade.php)
- [resources/views/components/gc-tab-strip.blade.php](../../resources/views/components/gc-tab-strip.blade.php)
- [resources/assets/js/components/tab-strip.js](../../resources/assets/js/components/tab-strip.js)

<a id="course"></a>

## Страница курса

Тип: page. Роли сценария: Новый пользователь, Ученик, Преподаватель, Администратор.

Найти главу, урок и следующую ступень

Условия: Участник курса либо администратор; материалы зависят от доступности урока.

Проверка: Исправлены контекст главы, статусы продолжения и награды, завершение обучения, мобильные сроки и доступность статистики больших групп. Графики и подробная статистика загружаются по запросу. Проверки — ui-round-23.md.

Состояния для макета и проверки: ученик / преподаватель; пустая глава; закрытый урок; ранний доступ; статистика урока; стикер за выполнение; 95% и возможность улучшения; завершённая глава / завершённый курс; группа из 70 учеников / страницы статистики; ошибка загрузки / повтор; сохранение главы между разделами.

Шаблон экрана: course-workspace. Статус: implemented.

Маршруты:

- `POST /insider/courses/{course_id}/chapters/{chapter_id}/default` — action
- `GET|HEAD /insider/courses/{course_id}/chapters/{chapter_id}/export-points` — download
- `GET|HEAD /insider/courses/{course_id}/chapters/{chapter_id}/lower` — action
- `GET|HEAD /insider/courses/{course_id}/chapters/{chapter_id}/upper` — action
- `GET|HEAD /insider/courses/{course_id}/lessons/{id}/delete` — action
- `GET|HEAD /insider/courses/{course_id}/lessons/{id}/export` — download
- `GET|HEAD /insider/courses/{course_id}/lessons/{id}/export-md` — download
- `GET|HEAD /insider/courses/{course_id}/lessons/{id}/export-points` — download
- `GET|HEAD /insider/courses/{course_id}/lessons/{id}/lower` — action
- `GET|HEAD /insider/courses/{course_id}/lessons/{id}/upper` — action
- `GET|HEAD /insider/courses/{course_id}/lessons/{lesson_id}/statistics` — api
- `GET|HEAD /insider/courses/{id}` — screen
- `GET|HEAD /insider/courses/{id}/export` — download
- `GET|HEAD /insider/courses/{id}/export-md` — download
- `GET|HEAD /insider/courses/{id}/start` — action
- `GET|HEAD /insider/courses/{id}/stop` — action

Переходы:

- Глава и список уроков → [Глава и список уроков](#course-chapter) (state)
- Статистика урока → [Статистика урока](#lesson-stats) (state)
- Дедлайн всего урока → [Дедлайн всего урока](#lesson-deadline) (open)
- Ранний доступ к уроку → [Ранний доступ к уроку](#early-access) (state)
- Дедлайны · общая навигация курса → [Дедлайны курса](#deadlines) (navigate)
- Открыть ступень → [Ступень](#step) (navigate)
- Изменить курс → [Настройки курса](#course-edit) (navigate)
- Добавить главу → [Добавление главы](#chapter-create) (navigate)
- Изменить главу → [Редактирование главы](#chapter-edit) (navigate)
- Добавить урок → [Добавление урока](#lesson-create) (navigate)
- Изменить урок → [Редактирование урока](#lesson-edit) (navigate)
- План занятий · общая навигация курса → [План занятий](#planner) (navigate)
- Отчёт · общая навигация курса → [Отчёт по курсу](#report) (navigate)
- Журнал · общая навигация курса → [Журнал опыта](#assessments) (navigate)
- Блокировки → [Заблокированные задачи](#blocked) (navigate)
- Ученик / преподаватель → [Профиль](#profile) (navigate)
- Продолжить обучение → [Ступень](#step) (navigate)
- Выбрать текущий урок в меню карточки → [Текущий урок курса](#course-current-lesson) (open)
- Задачи главы → [Задачи главы ученика](#chapter-tasks) (navigate)
- Уведомления курса · меню действий → [Уведомления курса](#course-notifications) (navigate)
- Подгруппы → [Подгруппы курса](#course-subgroups) (open)

Исходники:

- [resources/views/courses/details.blade.php](../../resources/views/courses/details.blade.php)
- [resources/views/components/gc-chapter-timeline.blade.php](../../resources/views/components/gc-chapter-timeline.blade.php)
- [resources/assets/js/components/chapter-timeline.js](../../resources/assets/js/components/chapter-timeline.js)
- [app/Support/CourseChapterNavigation.php](../../app/Support/CourseChapterNavigation.php)
- [resources/views/components/gc-course-header.blade.php](../../resources/views/components/gc-course-header.blade.php)
- [resources/views/components/gc-deadline-preview.blade.php](../../resources/views/components/gc-deadline-preview.blade.php)
- [app/Queries/CourseDeadlineList.php](../../app/Queries/CourseDeadlineList.php)
- [resources/views/components/gc-tab-strip.blade.php](../../resources/views/components/gc-tab-strip.blade.php)
- [resources/assets/js/components/tab-strip.js](../../resources/assets/js/components/tab-strip.js)
- [resources/views/components/gc-lesson-reward.blade.php](../../resources/views/components/gc-lesson-reward.blade.php)
- [resources/views/components/gc-lesson-outline.blade.php](../../resources/views/components/gc-lesson-outline.blade.php)
- [resources/assets/js/course-stats.js](../../resources/assets/js/course-stats.js)
- [app/Support/CourseLessonView.php](../../app/Support/CourseLessonView.php)
- [app/Support/CourseStudyOutcome.php](../../app/Support/CourseStudyOutcome.php)
- [app/Support/CourseLessonStatistics.php](../../app/Support/CourseLessonStatistics.php)
- [resources/assets/js/components/course-context.js](../../resources/assets/js/components/course-context.js)
- [resources/assets/js/components/charts.js](../../resources/assets/js/components/charts.js)
- [resources/views/courses/partials/lesson-statistics.blade.php](../../resources/views/courses/partials/lesson-statistics.blade.php)
- [resources/views/courses/partials/due-summary.blade.php](../../resources/views/courses/partials/due-summary.blade.php)

<a id="deadlines"></a>

## Дедлайны курса

Тип: page. Роли сценария: Новый пользователь, Ученик, Преподаватель, Администратор.

Посмотреть сроки по всем главам и перейти к задаче

Условия: Участник курса либо администратор.

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: ближайшие дедлайны; пропущенные дедлайны с доступной сдачей; переход в пропущенные после последнего срока; пусто; ученик / преподаватель.

Шаблон экрана: timeline. Статус: implemented.

Маршруты:

- `GET|HEAD /insider/courses/{course_id}/deadline-policies/{type}/{target}` — api
- `POST /insider/courses/{course_id}/deadline-policies/{type}/{target}` — action
- `GET|HEAD /insider/courses/{id}/deadlines` — screen
- `POST /insider/courses/{id}/notifications/preference` — action

Переходы:

- Открыть задачу с ближайшим или пропущенным сроком → [Ступень](#step) (navigate)
- Программа · общая навигация курса → [Страница курса](#course) (navigate)
- План занятий · общая навигация курса → [План занятий](#planner) (navigate)
- Журнал · общая навигация курса → [Журнал опыта](#assessments) (navigate)
- Отчёт · общая навигация курса → [Отчёт по курсу](#report) (navigate)

Исходники:

- [resources/views/courses/deadlines.blade.php](../../resources/views/courses/deadlines.blade.php)
- [resources/views/components/gc-course-header.blade.php](../../resources/views/components/gc-course-header.blade.php)
- [app/Queries/CourseDeadlineList.php](../../app/Queries/CourseDeadlineList.php)
- [resources/assets/js/components/deadline-workspace.js](../../resources/assets/js/components/deadline-workspace.js)
- [resources/views/components/gc-tab-strip.blade.php](../../resources/views/components/gc-tab-strip.blade.php)
- [resources/assets/js/components/tab-strip.js](../../resources/assets/js/components/tab-strip.js)

<a id="step"></a>

## Ступень

Тип: page. Роли сценария: Новый пользователь, Ученик, Преподаватель, Администратор.

Прочитать теорию, решить задачи, увидеть обратную связь

Условия: Участник курса; для ученика учитываются состояние курса, урок и видимость задачи.

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: теория; текст; код; квиз; пустая ступень; сдано / проверено / блокировка; ученик / преподаватель; список ступеней слева с текущим пунктом; мобильный список раскрыт / скрыт; одна ступень без боковой колонки; вкладка задачи по прямой ссылке видна на узком экране; загрузка готовых файлов и вставка кода; неизменяемая отправленная версия; WebSocket: очередь / компиляция / тесты / результат; покупка скрытого теста; реальный прирост XP / новое звание / GC без перезагрузки; чат до первой посылки; ответ преподавателя на выбранную посылку; журнал конкретной проверки в диалоге.

Шаблон экрана: learning-workspace. Статус: implemented.

Маршруты:

- `GET|HEAD /insider/courses/{course_id}/questions/{id}/delete` — action
- `GET|HEAD /insider/courses/{course_id}/steps/{id}` — screen
- `GET|HEAD /insider/courses/{course_id}/steps/{id}/delete` — action
- `GET|HEAD /insider/courses/{course_id}/steps/{id}/lower` — action
- `POST /insider/courses/{course_id}/steps/{id}/question` — action
- `POST /insider/courses/{course_id}/steps/{id}/tutor` — api
- `GET|HEAD /insider/courses/{course_id}/steps/{id}/upper` — action
- `GET|POST|HEAD /insider/courses/{course}/tasks/{task}/chat/{student}` — api
- `GET|HEAD /insider/courses/{course}/tasks/{task}/checker` — api
- `POST /insider/courses/{course}/tasks/{task}/checker/extra-attempt` — api
- `GET|PUT|DELETE|HEAD /insider/courses/{course}/tasks/{task}/code-draft` — api
- `GET|HEAD /insider/courses/{course}/tasks/{task}/code-draft/files/{file}` — download
- `GET|POST|HEAD /insider/courses/{course}/tasks/{task}/code-submissions` — api

Переходы:

- Теория / оглавление / Notebook → [Теория / оглавление / Notebook](#step-theory) (state)
- Задача: текст, код или квиз → [Задача: текст, код или квиз](#step-task) (state)
- Чат задачи, посылки и результаты проверок → [Попытки и обратная связь](#task-solutions) (state)
- Прогресс учеников и поиск → [Прогресс учеников и поиск](#student-progress) (state)
- Создать задание → [Создание задания](#task-create) (navigate)
- Дедлайн задачи → [Дедлайн задачи](#task-deadline) (open)
- ИИ-пересказ решений → [ИИ-пересказ решений](#task-summary) (open)
- Повторная проверка, штраф и бустер → [Повторная проверка, штраф и бустер](#solution-options) (state)
- К курсу → [Страница курса](#course) (navigate)
- Соседняя ступень → [Ступень](#step) (navigate)
- Изменить ступень → [Редактирование ступени](#step-edit) (navigate)
- Новый этап → [Добавление ступени](#step-create) (navigate)
- Изменить задачу → [Редактирование задачи](#task-edit) (navigate)
- Режим показа → [Режим показа](#perform) (navigate)
- Оглавление конспекта → [Оглавление конспекта](#reading-outline) (state)
- Ссылка на отправленную работу → [Отправленное решение и проверки](#code-submission) (navigate)
- Условие при сдаче → [Условие на момент сдачи](#material-history) (navigate)

Исходники:

- [resources/views/steps/details.blade.php](../../resources/views/steps/details.blade.php)
- [resources/views/steps/partials/notes.blade.php](../../resources/views/steps/partials/notes.blade.php)
- [resources/assets/js/nbv.js](../../resources/assets/js/nbv.js)
- [resources/assets/js/components/reading-workspace.js](../../resources/assets/js/components/reading-workspace.js)
- [resources/assets/js/components/content-media.js](../../resources/assets/js/components/content-media.js)
- [resources/views/steps/partials/nav.blade.php](../../resources/views/steps/partials/nav.blade.php)
- [resources/assets/js/components/workbook-navigation.js](../../resources/assets/js/components/workbook-navigation.js)
- [resources/views/components/gc-tab-strip.blade.php](../../resources/views/components/gc-tab-strip.blade.php)
- [resources/assets/js/components/tab-strip.js](../../resources/assets/js/components/tab-strip.js)
- [resources/views/steps/partials/task_header.blade.php](../../resources/views/steps/partials/task_header.blade.php)
- [resources/views/components/gc-task-state.blade.php](../../resources/views/components/gc-task-state.blade.php)
- [resources/assets/js/code-checking/workspace.js](../../resources/assets/js/code-checking/workspace.js)
- [resources/assets/js/components/reward-notices.js](../../resources/assets/js/components/reward-notices.js)
- [docs/ux/code-submissions-ux.mmd](../../docs/ux/code-submissions-ux.mmd)

<a id="public-step"></a>

## Открытая ступень

Тип: page. Роли сценария: Гость, Новый пользователь, Ученик, Преподаватель, Администратор.

Прочитать доступный без входа учебный материал

Условия: Публичен только урок с is_open; закрытая ступень сейчас возвращает 503.

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: гость; авторизованный; открытый урок; урок закрыт; список ступеней слева с текущим пунктом; мобильный список раскрыт / скрыт; одна ступень без боковой колонки.

Шаблон экрана: reading. Статус: implemented.

Маршруты:

- `GET|HEAD /open/steps/{id}` — screen
- `GET|HEAD /open/steps/{id}/notebook` — download
- `GET|HEAD /start-exam` — redirect
- `GET|HEAD /telegram-bot` — redirect

Исходники:

- [resources/views/steps/details.blade.php](../../resources/views/steps/details.blade.php)
- [resources/views/steps/partials/nav.blade.php](../../resources/views/steps/partials/nav.blade.php)
- [resources/assets/js/components/workbook-navigation.js](../../resources/assets/js/components/workbook-navigation.js)
- [resources/views/components/gc-tab-strip.blade.php](../../resources/views/components/gc-tab-strip.blade.php)
- [resources/assets/js/components/tab-strip.js](../../resources/assets/js/components/tab-strip.js)

<a id="pulse"></a>

## Пульс

Тип: page. Роли сценария: Новый пользователь, Ученик, Преподаватель, Администратор.

Увидеть события и перейти к их контексту

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: пусто; фильтр курса; начисления; достижения; новые уроки.

Шаблон экрана: feed. Статус: implemented.

Маршруты:

- `GET|HEAD /insider/pulse` — screen

Переходы:

- Событие урока → [Страница курса](#course) (navigate)
- Событие решения: преподаватель → [Проверка решений ученика](#review) (navigate)
- Событие достижения → [Достижения и просмотр кубка](#profile-achievements) (navigate)

Исходники:

- [resources/views/courses/pulse.blade.php](../../resources/views/courses/pulse.blade.php)

<a id="course-create"></a>

## Создание курса

Тип: page. Роли сценария: Преподаватель, Администратор.

Выбрать программу и создать курс

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: новый курс; импорт; нет программ; валидация.

Шаблон экрана: form. Статус: implemented.

Маршруты:

- `GET|HEAD /insider/courses/create` — screen
- `POST /insider/courses/create` — action

Переходы:

- Сохранить → [Мои курсы](#courses) (action)

Исходники:

- [resources/views/courses/create.blade.php](../../resources/views/courses/create.blade.php)

<a id="course-edit"></a>

## Настройки курса

Тип: page. Роли сценария: Преподаватель, Администратор.

Изменить курс, участников, доступ и описание

Условия: Преподаватель с доступом к курсу либо администратор.

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: ученики; преподаватели для администратора; видимость статистики; постер; валидация; выбор и сохранение иконки; преподаватель без поля инвайта.

Шаблон экрана: form. Статус: implemented.

Маршруты:

- `GET|HEAD /insider/courses/{id}/edit` — screen
- `POST /insider/courses/{id}/edit` — action
- `POST /insider/courses/{id}/poster` — action

Переходы:

- Сохранить → [Страница курса](#course) (action)
- Исправить / улучшить текст → [Помощь ИИ в редакторе](#text-assist) (state)

Исходники:

- [resources/views/courses/edit.blade.php](../../resources/views/courses/edit.blade.php)
- [resources/views/components/gc-course-icon-picker.blade.php](../../resources/views/components/gc-course-icon-picker.blade.php)
- [app/Support/CourseIcons.php](../../app/Support/CourseIcons.php)

<a id="chapter-create"></a>

## Добавление главы

Тип: page. Роли сценария: Преподаватель, Администратор.

Добавить главу программы

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: форма; ошибки; возврат к главе.

Шаблон экрана: form. Статус: implemented.

Маршруты:

- `GET|HEAD /insider/courses/{id}/chapter` — screen
- `POST /insider/courses/{id}/chapter` — action

Переходы:

- Сохранить → [Страница курса](#course) (action)

Исходники:

- [resources/views/courses/create_chapter.blade.php](../../resources/views/courses/create_chapter.blade.php)

<a id="chapter-edit"></a>

## Редактирование главы

Тип: page. Роли сценария: Преподаватель, Администратор.

Изменить главу и ограничения

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: текущие значения; блокировки по времени / прогрессу; ошибки.

Шаблон экрана: form. Статус: implemented.

Маршруты:

- `GET|HEAD /insider/courses/{course_id}/chapters/{chapter_id}/edit` — screen
- `POST /insider/courses/{course_id}/chapters/{chapter_id}/edit` — action

Переходы:

- Сохранить → [Страница курса](#course) (action)

Исходники:

- [resources/views/courses/edit_chapter.blade.php](../../resources/views/courses/edit_chapter.blade.php)

<a id="lesson-create"></a>

## Добавление урока

Тип: page. Роли сценария: Преподаватель, Администратор.

Создать урок и стартовую ступень либо импортировать

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: форма; импорт; валидация.

Шаблон экрана: form. Статус: implemented.

Маршруты:

- `GET|HEAD /insider/courses/{id}/create` — screen
- `POST /insider/courses/{id}/create` — action

Переходы:

- Сохранить → [Ступень](#step) (action)

Исходники:

- [resources/views/lessons/create.blade.php](../../resources/views/lessons/create.blade.php)

<a id="lesson-edit"></a>

## Редактирование урока

Тип: page. Роли сценария: Преподаватель, Администратор.

Изменить название, главу, доступ и стикер

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: параметры; доступ; превью стикера; валидация.

Шаблон экрана: form. Статус: implemented.

Маршруты:

- `GET|HEAD /insider/courses/{course_id}/lessons/{id}/edit` — screen
- `POST /insider/courses/{course_id}/lessons/{id}/edit` — action

Переходы:

- Сохранить → [Страница курса](#course) (action)
- Каталог наклеек → [Каталог наклеек](#settings-stickers) (navigate)

Исходники:

- [resources/views/lessons/edit.blade.php](../../resources/views/lessons/edit.blade.php)

<a id="step-create"></a>

## Добавление ступени

Тип: page. Роли сценария: Преподаватель, Администратор.

Добавить теорию и материалы ступени

Условия: В интерфейсе — действие преподавателя; у createView/create в списке middleware только auth/verified. Роли здесь обозначают UX-сценарий, а не утверждение о полноте серверной авторизации.

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: Markdown; Notebook; валидация.

Шаблон экрана: editor. Статус: implemented.

Маршруты:

- `GET|HEAD /insider/courses/{course_id}/lessons/{id}/create` — screen
- `POST /insider/courses/{course_id}/lessons/{id}/create` — action

Переходы:

- Сохранить → [Ступень](#step) (action)

Исходники:

- [resources/views/steps/create.blade.php](../../resources/views/steps/create.blade.php)

<a id="step-edit"></a>

## Редактирование ступени

Тип: page. Роли сценария: Преподаватель, Администратор.

Редактировать теорию, заметки и видео

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: Markdown; Notebook; видео; предпросмотр; ошибки.

Шаблон экрана: editor. Статус: implemented.

Маршруты:

- `POST /insider/authoring/preview` — api
- `GET|HEAD /insider/courses/{course_id}/steps/{id}/edit` — screen
- `POST /insider/courses/{course_id}/steps/{id}/edit` — action

Переходы:

- Сохранить → [Ступень](#step) (action)
- Исправить / улучшить текст → [Помощь ИИ в редакторе](#text-assist) (state)

Исходники:

- [resources/views/steps/edit.blade.php](../../resources/views/steps/edit.blade.php)

<a id="task-edit"></a>

## Редактирование задачи

Тип: page. Роли сценария: Преподаватель, Администратор.

Изменить условие, проверку и параметры задачи

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: текст; автопроверка; шаблон кода; авторское решение; ИИ-достижение; валидация.

Шаблон экрана: editor. Статус: implemented.

Маршруты:

- `GET|HEAD /insider/courses/{course_id}/tasks/{id}/edit` — screen
- `POST /insider/courses/{course_id}/tasks/{id}/edit` — action
- `GET|POST|HEAD /insider/courses/{course}/tasks/{task}/checker/files` — api
- `GET|DELETE|HEAD /insider/courses/{course}/tasks/{task}/checker/files/{attachment}` — api
- `POST /insider/courses/{course}/tasks/{task}/checker/files/{attachment}/replace` — api
- `POST /insider/courses/{course}/tasks/{task}/checker/fixtures` — api
- `GET|HEAD /insider/courses/{course}/tasks/{task}/checker/fixtures/{artifact}` — download
- `GET|HEAD /insider/courses/{course}/tasks/{task}/checker/legacy` — api
- `GET|PUT|HEAD /insider/courses/{course}/tasks/{task}/checker/private` — api

Переходы:

- Сохранить → [Ступень](#step) (action)
- Исправить / улучшить текст → [Помощь ИИ в редакторе](#text-assist) (state)

Исходники:

- [resources/views/steps/edit_task.blade.php](../../resources/views/steps/edit_task.blade.php)
- [resources/assets/js/code-checking/authoring.js](../../resources/assets/js/code-checking/authoring.js)

<a id="perform"></a>

## Режим показа

Тип: page. Роли сценария: Преподаватель, Администратор.

Показывать теорию и задания всему классу

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: теория; задача; предыдущая / следующая ступень.

Шаблон экрана: presentation. Статус: implemented.

Маршруты:

- `GET|HEAD /insider/courses/{course_id}/perform/{id}` — screen

Переходы:

- Вернуться → [Ступень](#step) (navigate)
- Соседняя ступень → [Режим показа](#perform) (navigate)

Исходники:

- [resources/views/perform/details.blade.php](../../resources/views/perform/details.blade.php)
- [resources/views/components/gc-tab-strip.blade.php](../../resources/views/components/gc-tab-strip.blade.php)
- [resources/assets/js/components/tab-strip.js](../../resources/assets/js/components/tab-strip.js)

<a id="reviews"></a>

## Очередь проверки

Тип: page. Роли сценария: Преподаватель, Администратор.

Выбрать непроверенное решение

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: очередь; пагинация; пусто; пропустить одно / все.

Шаблон экрана: review-queue. Статус: implemented.

Маршруты:

- `GET|HEAD /insider/reviews` — screen
- `POST /insider/reviews/reset-pending` — action

Переходы:

- Открыть попытку → [Проверка решений ученика](#review) (navigate)

Исходники:

- [resources/views/courses/reviews.blade.php](../../resources/views/courses/reviews.blade.php)
- [resources/views/steps/partials/review-queue.blade.php](../../resources/views/steps/partials/review-queue.blade.php)
- [app/Services/ReviewWorkspace.php](../../app/Services/ReviewWorkspace.php)

<a id="review"></a>

## Проверка решений ученика

Тип: page. Роли сценария: Преподаватель, Администратор.

Изучить попытки, оценить и дать обратную связь

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: несколько попыток; нет решений; подозрения GeekPaste; повторная проверка; блокировка; достижение; ответ виден до раскрытия условия; условие открыто при отсутствии решений; полное длинное название задачи.

Шаблон экрана: review-workspace. Статус: implemented.

Маршруты:

- `PUT /insider/code-submissions/{submission}/grade` — api
- `PUT /insider/code-submissions/{submission}/integrity/review` — api
- `POST /insider/code-submissions/{submission}/manual-review` — api
- `POST /insider/code-submissions/{submission}/recheck` — api
- `GET|HEAD /insider/courses/{course_id}/tasks/{id}/student/{student_id}` — screen
- `POST /insider/courses/{course}/tasks/{task}/code-submissions/recheck-latest` — api

Переходы:

- Оценка, комментарий, пропуск и блокировка → [Оценка, комментарий, пропуск и блокировка](#review-tools) (state)
- К задаче → [Ступень](#step) (navigate)
- Профиль ученика → [Профиль](#profile) (navigate)
- Создать достижение → [Предпросмотр достижения](#achievement-preview) (navigate)
- Следующее решение этого курса → [Проверка решений ученика](#review) (navigate)
- Очередь курса → [Очередь проверки](#reviews) (navigate)

Исходники:

- [resources/views/steps/review.blade.php](../../resources/views/steps/review.blade.php)
- [resources/views/steps/partials/review-queue.blade.php](../../resources/views/steps/partials/review-queue.blade.php)
- [app/Services/ReviewWorkspace.php](../../app/Services/ReviewWorkspace.php)

<a id="achievement-preview"></a>

## Предпросмотр достижения

Тип: page. Роли сценария: Преподаватель, Администратор.

Проверить сгенерированное достижение перед публикацией

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: генерация; готово; ошибка; опубликовать; вернуться.

Шаблон экрана: preview. Статус: implemented.

Маршруты:

- `POST /insider/courses/{course_id}/tasks/{id}/solution/{solution_id}/achievement` — action
- `GET|HEAD /insider/courses/{course_id}/tasks/{id}/solution/{solution_id}/achievement-preview` — screen
- `POST /insider/courses/{course_id}/tasks/{id}/solution/{solution_id}/achievement-preview` — action

Переходы:

- Опубликовать → [Достижения и просмотр кубка](#profile-achievements) (navigate)
- Вернуться → [Проверка решений ученика](#review) (navigate)
- Выбрать идею и заполнить форму → [Выбор идеи достижения](#achievement-choice) (state)

Исходники:

- [resources/views/steps/achievement_preview.blade.php](../../resources/views/steps/achievement_preview.blade.php)

<a id="report"></a>

## Отчёт по курсу

Тип: page. Роли сценария: Преподаватель, Администратор.

Найти отставание, открыть ученика и задачу

Проверка: Итерация 20: текущий урок, наклейки и отчёт только по урокам с заданиями; проверка 320/390/768/1440 px — ui-round-20.md.

Состояния для макета и проверки: выбор ученика; главы; уроки; графики; сигналы GeekPaste; нет статистики; поиск и смена ученика в мобильной панели; выбор главы и восстановление из URL; пустой поиск без устаревшего результата; задачи появляются при раскрытии.

Шаблон экрана: report. Статус: implemented.

Маршруты:

- `GET|HEAD /insider/courses/{id}/report` — screen
- `POST /insider/courses/{id}/report/students/{student_id}/geekpaste-warning/reset` — action

Переходы:

- Открыть задачу ученика → [Проверка решений ученика](#review) (navigate)
- Решения на проверке в этом курсе → [Очередь проверки](#reviews) (navigate)
- Программа · общая навигация курса → [Страница курса](#course) (navigate)
- План занятий · общая навигация курса → [План занятий](#planner) (navigate)
- Журнал · общая навигация курса → [Журнал опыта](#assessments) (navigate)
- Дедлайны · общая навигация курса → [Дедлайны курса](#deadlines) (navigate)
- Выбрать ученика на телефоне → [Выбор ученика в отчёте](#report-student-picker) (open)

Исходники:

- [resources/views/courses/report.blade.php](../../resources/views/courses/report.blade.php)
- [resources/views/components/gc-course-header.blade.php](../../resources/views/components/gc-course-header.blade.php)
- [resources/views/courses/partials/report-activity.blade.php](../../resources/views/courses/partials/report-activity.blade.php)
- [resources/assets/js/components/charts.js](../../resources/assets/js/components/charts.js)
- [resources/views/components/gc-tab-strip.blade.php](../../resources/views/components/gc-tab-strip.blade.php)
- [resources/assets/js/components/tab-strip.js](../../resources/assets/js/components/tab-strip.js)
- [resources/assets/js/components/report-workspace.js](../../resources/assets/js/components/report-workspace.js)
- [app/Support/CourseReportLessons.php](../../app/Support/CourseReportLessons.php)

<a id="assessments"></a>

## Журнал опыта

Тип: page. Роли сценария: Преподаватель, Администратор.

Сопоставить учеников и баллы за задачи

Существующий журнал XP; не новая итоговая ведомость, отложенная на следующий этап.

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: пусто; много задач; блокированные задачи; переход к проверке; текущая глава по умолчанию; смена главы; фильтры по состояниям и поиск; локальная прокрутка таблицы на телефоне; мобильный выбор состояния работ; прокрутка колонок кнопками; переход к разбору и возврат к итогам; 32 ученика и длинная глава.

Шаблон экрана: matrix. Статус: implemented.

Маршруты:

- `GET|HEAD /insider/courses/{id}/assessments` — screen

Переходы:

- Выбрать ячейку → [Проверка решений ученика](#review) (navigate)
- Программа · общая навигация курса → [Страница курса](#course) (navigate)
- План занятий · общая навигация курса → [План занятий](#planner) (navigate)
- Отчёт · общая навигация курса → [Отчёт по курсу](#report) (navigate)
- Дедлайны · общая навигация курса → [Дедлайны курса](#deadlines) (navigate)

Исходники:

- [resources/views/courses/assessments.blade.php](../../resources/views/courses/assessments.blade.php)
- [resources/views/components/gc-course-header.blade.php](../../resources/views/components/gc-course-header.blade.php)
- [app/Http/Controllers/CoursesController.php](../../app/Http/Controllers/CoursesController.php)
- [resources/assets/js/components/assessment-workspace.js](../../resources/assets/js/components/assessment-workspace.js)
- [resources/views/courses/partials/assessment-detail.blade.php](../../resources/views/courses/partials/assessment-detail.blade.php)
- [resources/views/components/gc-tab-strip.blade.php](../../resources/views/components/gc-tab-strip.blade.php)
- [resources/assets/js/components/tab-strip.js](../../resources/assets/js/components/tab-strip.js)
- [resources/assets/js/components/table-navigation.js](../../resources/assets/js/components/table-navigation.js)
- [resources/views/components/gc-table-navigation.blade.php](../../resources/views/components/gc-table-navigation.blade.php)

<a id="blocked"></a>

## Заблокированные задачи

Тип: page. Роли сценария: Преподаватель, Администратор.

Найти блокировку ученика и снять её

Условия: Контроллер: администратор либо преподаватель данного курса.

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: нет блокировок; группы по ученикам; разблокировка.

Шаблон экрана: table. Статус: implemented.

Маршруты:

- `GET|HEAD /insider/courses/{id}/blocked` — screen

Переходы:

- Открыть блокировку → [Проверка решений ученика](#review) (navigate)
- Профиль ученика → [Профиль](#profile) (navigate)

Исходники:

- [resources/views/courses/blocked.blade.php](../../resources/views/courses/blocked.blade.php)

<a id="planner"></a>

## План занятий

Тип: page. Роли сценария: Ученик, Преподаватель, Администратор.

Планировать занятия и фиксировать факт

Условия: Участники курса читают свои и общие занятия. Редактирование — только преподаватель курса или администратор.

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: календарь; обзор; без даты; нет расписания; каталог недоступен; конфликт версии; нативный выбор календаря / обзора; план / факт; независимые расписания подгрупп; общие занятия только для чтения в подгруппе; копирование материалов; ученический календарь.

Шаблон экрана: planner. Статус: implemented.

Маршруты:

- `GET|HEAD /insider/courses/{course_id}/planner` — screen
- `POST /insider/courses/{course_id}/planner/api/calendar/preview` — api
- `POST /insider/courses/{course_id}/planner/api/commands/{operation}` — api
- `GET|HEAD /insider/courses/{course_id}/planner/api/copy-targets` — api
- `GET|HEAD /insider/courses/{course_id}/planner/api/entries/{link_id}` — api
- `GET|HEAD /insider/courses/{course_id}/planner/api/providers/{provider_key}/resources` — api
- `GET|HEAD /insider/courses/{course_id}/planner/api/state` — api
- `GET|HEAD /insider/courses/{course_id}/planner/api/summary/details` — api

Переходы:

- Материалы и комментарии прошедших занятий → [Прошедшие занятия и комментарии](#planner-journal) (state)
- Обзор покрытия и нагрузки → [Обзор покрытия и нагрузки](#planner-summary) (state)
- Каталог провайдера → [Каталог провайдера](#planner-catalog) (state)
- Расписание курса → [Расписание курса](#planner-schedule) (open)
- Предпросмотр новой сетки → [Предпросмотр новой сетки](#planner-preview) (open)
- Подключение провайдеров → [Подключение провайдеров](#planner-providers) (open)
- Редактор занятия → [Редактор занятия](#planner-session) (open)
- Произвольная запись → [Произвольная запись](#planner-note) (open)
- Перенос материала → [Перенос материала](#planner-move) (open)
- Разделение урока по занятиям → [Разделение урока по занятиям](#planner-split) (open)
- Связь материала с уроками → [Связь материала с уроками](#planner-relations) (open)
- Дедлайн работы → [Дедлайн работы](#planner-deadline) (open)
- Исключение дат → [Исключение дат](#planner-exclude) (open)
- Комментарий к завершившемуся занятию → [Комментарий к занятию](#planner-record) (open)
- История изменений → [История изменений](#planner-history) (open)
- Программа · общая навигация курса → [Страница курса](#course) (navigate)
- Журнал · общая навигация курса → [Журнал опыта](#assessments) (navigate)
- Отчёт · общая навигация курса → [Отчёт по курсу](#report) (navigate)
- Дедлайны · общая навигация курса → [Дедлайны курса](#deadlines) (navigate)

Исходники:

- [resources/views/course-planner/index.blade.php](../../resources/views/course-planner/index.blade.php)
- [resources/views/components/gc-course-header.blade.php](../../resources/views/components/gc-course-header.blade.php)
- [resources/assets/js/course-planner/index.js](../../resources/assets/js/course-planner/index.js)
- [resources/assets/js/course-planner/deadline-strip.js](../../resources/assets/js/course-planner/deadline-strip.js)
- [resources/views/components/gc-tab-strip.blade.php](../../resources/views/components/gc-tab-strip.blade.php)
- [resources/assets/js/components/tab-strip.js](../../resources/assets/js/components/tab-strip.js)
- [resources/views/course-planner/student.blade.php](../../resources/views/course-planner/student.blade.php)

<a id="community"></a>

## Сообщество

Тип: page. Роли сценария: Новый пользователь, Ученик, Преподаватель, Администратор.

Найти знакомого по имени и внешнему виду, увидеть аватар, рамку и звание, открыть профиль.

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: поиск; фильтр роли; нет результатов; свой / чужой профиль; скрытые участники; обычные и пользовательские рамки; длинные имена и звания; мобильная сетка; первая / средняя / последняя страница; поиск вне первой страницы; сброс страницы при изменении фильтра; страница за пределами результатов; глобальные лидеры на разных страницах; звание без прогресс-бара; 80 px и персональные рамки.

Шаблон экрана: directory. Статус: implemented.

Маршруты:

- `GET|HEAD /insider/community` — screen

Переходы:

- Открыть человека → [Профиль](#profile) (navigate)
- Поиск и роль → первая страница → [Сообщество](#community) (state)
- Следующая / предыдущая страница → [Сообщество](#community) (state)

Исходники:

- [resources/views/profile/index.blade.php](../../resources/views/profile/index.blade.php)
- [app/Queries/CommunityDirectory.php](../../app/Queries/CommunityDirectory.php)
- [resources/views/components/gc-person-card.blade.php](../../resources/views/components/gc-person-card.blade.php)
- [resources/views/components/gc-page-navigation.blade.php](../../resources/views/components/gc-page-navigation.blade.php)
- [app/Support/CommunityNavigation.php](../../app/Support/CommunityNavigation.php)

<a id="profile"></a>

## Профиль

Тип: page. Роли сценария: Новый пользователь, Ученик, Преподаватель, Администратор.

Увидеть человека, прогресс, комнату и достижения

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: свой / чужой; ученик / преподаватель; пустые секции; комната; история GC; приватные поля; открытые контакты; почта: владелец/преподаватель/ученик; скрытая дата рождения; наклейки владельца и посетителя; длинное имя и платное звание на телефоне; нет публичных контактов; длинные коллекции; прямая ссылка на свёрнутое достижение; служебный комментарий только уполномоченным ролям; визитка и контакты / комната на широком экране; три курса и раскрытие остальных; мобильный порядок: контакты → комната → обучение.

Шаблон экрана: profile. Статус: implemented.

Маршруты:

- `GET|HEAD /api/user` — api
- `GET|HEAD /insider/profile/{id?}` — screen

Переходы:

- Комната и купленные предметы → [Комната участника](#profile-room) (state)
- История GC ученика, по правам → [История GC ученика](#profile-history) (state)
- Достижения и просмотр кубка → [Достижения и просмотр кубка](#profile-achievements) (state)
- Редактирование достижения → [Редактирование достижения](#achievement-edit) (open)
- Операция с GC → [Операция с GC](#profile-money) (open)
- Добавление завершённого курса → [Добавление завершённого курса](#completed-course) (open)
- Редактировать профиль → [Личные настройки](#profile-edit) (navigate)
- Текущий / завершённый курс → [Страница курса](#course) (navigate)
- Настроить комнату → [Настройка комнаты по кнопке](#room-editor) (open)
- Баланс GC → [История GC в магазине](#wallet) (navigate)
- Коллекции и завершённые курсы → [Коллекции и завершённые курсы](#profile-collections) (state)

Исходники:

- [resources/views/profile/details.blade.php](../../resources/views/profile/details.blade.php)
- [resources/views/profile/partials/contacts.blade.php](../../resources/views/profile/partials/contacts.blade.php)
- [app/Support/ContactLinks.php](../../app/Support/ContactLinks.php)
- [resources/views/profile/partials/about.blade.php](../../resources/views/profile/partials/about.blade.php)
- [resources/views/profile/partials/courses.blade.php](../../resources/views/profile/partials/courses.blade.php)
- [resources/views/profile/partials/stickers.blade.php](../../resources/views/profile/partials/stickers.blade.php)
- [resources/views/profile/partials/achievement.blade.php](../../resources/views/profile/partials/achievement.blade.php)
- [resources/views/profile/partials/course-row.blade.php](../../resources/views/profile/partials/course-row.blade.php)
- [resources/views/components/gc-contact.blade.php](../../resources/views/components/gc-contact.blade.php)
- [app/Support/CommunityNavigation.php](../../app/Support/CommunityNavigation.php)

<a id="profile-edit"></a>

## Личные настройки

Тип: page. Роли сценария: Новый пользователь, Ученик, Преподаватель, Администратор.

Изменить данные профиля, пароль, Telegram и связь с Силаэдром.

Условия: Владелец профиля либо администратор.

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: свой / администратор; аватар; почта; привязка SSO; валидация.

Шаблон экрана: form. Статус: implemented.

Маршруты:

- `GET|HEAD /insider/profile/{id}/edit` — screen
- `POST /insider/profile/{id}/edit` — action

Переходы:

- Сохранить / отменить → [Профиль](#profile) (navigate)
- Аккаунт и уведомления → [Аккаунт и уведомления](#account-preferences) (state)

Исходники:

- [resources/views/profile/edit.blade.php](../../resources/views/profile/edit.blade.php)
- [resources/views/components/gc-tab-strip.blade.php](../../resources/views/components/gc-tab-strip.blade.php)
- [resources/assets/js/components/tab-strip.js](../../resources/assets/js/components/tab-strip.js)

<a id="market"></a>

## Магазин

Тип: page. Роли сценария: Новый пользователь, Ученик, Преподаватель, Администратор.

Выбрать физический или цифровой товар, участвовать в аукционе

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: товары; цифровые; аукционы; архив; заказы; нет товаров; не хватает GC; преподаватель / администратор; компактная покупка справа; цена слева; недостаточный баланс; контраст обычной и наведённой кнопки доставки; монеты в цвете акцента темы; текущая вкладка видна на телефоне; компактный заказ с ценой и действиями на телефоне.

Шаблон экрана: catalog. Статус: implemented.

Маршруты:

- `GET|HEAD /insider/market` — screen

Переходы:

- Физические товары → [Физические товары](#market-goods) (state)
- Цифровые товары → [Цифровые товары](#market-digital) (state)
- Аукционы → [Аукционы](#market-auctions) (state)
- Архив товаров → [Архив товаров](#market-archive) (state)
- Заказы внутри магазина → [Заказы внутри магазина](#market-orders) (state)
- Настройка своей рамки → [Настройка своей рамки](#custom-frame) (open)
- Добавить товар → [Добавление товара](#market-create) (navigate)
- Изменить товар → [Редактирование товара](#market-edit) (navigate)
- Начислить GC → [Массовое начисление GC](#credit) (navigate)
- Управление заказами → [Заказы](#orders) (navigate)
- Мои покупки → [Мои покупки](#personal-purchases) (state)
- История GC → [История GC в магазине](#wallet) (state)

Исходники:

- [resources/views/market/index.blade.php](../../resources/views/market/index.blade.php)
- [resources/views/components/gc-purchase-footer.blade.php](../../resources/views/components/gc-purchase-footer.blade.php)
- [resources/views/market/partials/orders_table.blade.php](../../resources/views/market/partials/orders_table.blade.php)
- [resources/views/components/gc-tab-strip.blade.php](../../resources/views/components/gc-tab-strip.blade.php)
- [resources/assets/js/components/tab-strip.js](../../resources/assets/js/components/tab-strip.js)

<a id="market-create"></a>

## Добавление товара

Тип: page. Роли сценария: Администратор.

Создать товар, программу или аукцион

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: товар / аукцион; количество; цена; изображение; программа; валидация.

Шаблон экрана: form. Статус: implemented.

Маршруты:

- `GET|HEAD /insider/market/create` — screen
- `POST /insider/market/create` — action

Переходы:

- Сохранить → [Магазин](#market) (action)

Исходники:

- [resources/views/market/create.blade.php](../../resources/views/market/create.blade.php)

<a id="market-edit"></a>

## Редактирование товара

Тип: page. Роли сценария: Администратор.

Изменить товар или параметры аукциона

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: текущие значения; предпросмотр; ошибки.

Шаблон экрана: form. Статус: implemented.

Маршруты:

- `GET|HEAD /insider/market/{id}/edit` — screen
- `POST /insider/market/{id}/edit` — action

Переходы:

- Сохранить → [Магазин](#market) (action)

Исходники:

- [resources/views/market/edit.blade.php](../../resources/views/market/edit.blade.php)

<a id="orders"></a>

## Заказы

Тип: page. Роли сценария: Преподаватель, Администратор.

Выдать заказ либо отменить, посмотреть историю

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: активные; доставленные; пагинация; пусто; контраст кнопки доставки; альтернативная палитра.

Шаблон экрана: table. Статус: implemented.

Маршруты:

- `POST /insider/market/cancel/{id}` — action
- `GET|HEAD /insider/market/orders` — screen
- `POST /insider/market/ship/{id}` — action

Переходы:

- Покупатель → [Профиль](#profile) (navigate)

Исходники:

- [resources/views/market/orders.blade.php](../../resources/views/market/orders.blade.php)
- [resources/views/market/partials/orders_table.blade.php](../../resources/views/market/partials/orders_table.blade.php)

<a id="credit"></a>

## Массовое начисление GC

Тип: page. Роли сценария: Администратор.

Выбрать получателей, сумму и комментарий

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: получатели; сумма; комментарий; валидация; успех.

Шаблон экрана: form. Статус: implemented.

Маршруты:

- `GET|HEAD /insider/market/credit` — screen
- `POST /insider/market/credit` — action

Переходы:

- Сохранить → [Магазин](#market) (action)

Исходники:

- [resources/views/market/credit.blade.php](../../resources/views/market/credit.blade.php)

<a id="settings-ai"></a>

## Настройки ИИ

Тип: page. Роли сценария: Администратор.

Выбрать одно подключение и две общие модели

Условия: Только глобальный администратор. Секреты не возвращаются в HTML и не попадают в old input.

Проверка: Два режима подключения: встроенный шлюз или OpenAI-совместимый API. Видны только параметры выбранного режима, единый базовый адрес и две модели. RouterAI поддерживается по его адресу без отдельного выбора сервиса. Сохранение включает общие модели, неактивные ключи сохраняются. Проверка и конфигурация — docs/ai.md.

Состояния для макета и проверки: серверные / переопределённые; секрет скрыт; проверка значений; сброс; встроенный шлюз / OpenAI-совместимый API; один базовый адрес / старые отдельные адреса; переключение без удаления неактивных ключей; две модели вместо переопределений операций.

Шаблон экрана: settings. Статус: implemented.

Маршруты:

- `GET|HEAD /insider/settings/ai` — screen
- `PUT /insider/settings/ai` — action
- `DELETE /insider/settings/ai` — action

Переходы:

- Расход ИИ → [Расход токенов ИИ](#settings-ai-usage) (navigate)
- Цены товаров → [Цены и акции](#settings-store) (navigate)
- Telegram → [Настройки Telegram](#settings-telegram) (navigate)
- Наклейки → [Каталог наклеек](#settings-stickers) (navigate)

Исходники:

- [resources/views/settings/ai.blade.php](../../resources/views/settings/ai.blade.php)
- [resources/views/components/gc-tab-strip.blade.php](../../resources/views/components/gc-tab-strip.blade.php)
- [resources/assets/js/components/tab-strip.js](../../resources/assets/js/components/tab-strip.js)
- [resources/assets/js/components/ai-settings.js](../../resources/assets/js/components/ai-settings.js)
- [app/Services/Ai/Connection.php](../../app/Services/Ai/Connection.php)
- [app/Services/Ai/AiSettings.php](../../app/Services/Ai/AiSettings.php)
- [app/Services/Ai/ImagesClient.php](../../app/Services/Ai/ImagesClient.php)
- [app/Http/Requests/UpdateAiSettingsRequest.php](../../app/Http/Requests/UpdateAiSettingsRequest.php)

<a id="settings-ai-usage"></a>

## Расход токенов ИИ

Тип: page. Роли сценария: Администратор.

Посмотреть суммарный расход токенов и уточнить его по назначению, модели, пользователю и периоду

Условия: Только глобальный администратор.

Проверка: Для вызовов с частичным или отсутствующим счётчиком показано число таких вызовов.

Состояния для макета и проверки: общий отчёт; фильтр по пользователю и модели; частичный счётчик; пустой период.

Маршруты:

- `GET|HEAD /insider/settings/ai/usage` — screen

Переходы:

- ИИ → [Настройки ИИ](#settings-ai) (navigate)
- Уточнить фильтры отчёта → [Расход токенов ИИ](#settings-ai-usage) (state)

Исходники:

- [resources/views/settings/ai-usage.blade.php](../../resources/views/settings/ai-usage.blade.php)
- [app/Http/Controllers/AiUsageController.php](../../app/Http/Controllers/AiUsageController.php)
- [app/Services/Ai/TokenUsage.php](../../app/Services/Ai/TokenUsage.php)

<a id="settings-store"></a>

## Цены и акции

Тип: page. Роли сценария: Администратор.

Настроить цены и акцию

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: цены; акция; валидация; сохранено.

Шаблон экрана: settings. Статус: implemented.

Маршруты:

- `GET|HEAD /insider/settings/system-store` — screen
- `PUT /insider/settings/system-store` — action

Переходы:

- ИИ → [Настройки ИИ](#settings-ai) (navigate)
- Telegram → [Настройки Telegram](#settings-telegram) (navigate)
- Выбрать товары акции → [Выбор товаров акции](#market-promotion-picker) (open)

Исходники:

- [resources/views/settings/system-store.blade.php](../../resources/views/settings/system-store.blade.php)
- [resources/views/settings/partials/store-promotion.blade.php](../../resources/views/settings/partials/store-promotion.blade.php)
- [app/Services/SystemStoreSettings.php](../../app/Services/SystemStoreSettings.php)
- [app/Http/Controllers/SystemStoreSettingsController.php](../../app/Http/Controllers/SystemStoreSettingsController.php)
- [resources/views/components/gc-tab-strip.blade.php](../../resources/views/components/gc-tab-strip.blade.php)
- [resources/assets/js/components/tab-strip.js](../../resources/assets/js/components/tab-strip.js)

<a id="settings-telegram"></a>

## Настройки Telegram

Тип: page. Роли сценария: Администратор.

Настроить существующего Telegram-бота платформы

Существующая привязка Telegram; бот сопровождения курса из обсуждения ещё не реализуется.

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: секрет скрыт; webhook; сохранение; сброс.

Шаблон экрана: settings. Статус: implemented.

Маршруты:

- `GET|HEAD /insider/settings/telegram` — screen
- `PUT /insider/settings/telegram` — action
- `DELETE /insider/settings/telegram` — action

Переходы:

- ИИ → [Настройки ИИ](#settings-ai) (navigate)
- Цены товаров → [Цены и акции](#settings-store) (navigate)

Исходники:

- [resources/views/settings/telegram.blade.php](../../resources/views/settings/telegram.blade.php)
- [resources/views/components/gc-tab-strip.blade.php](../../resources/views/components/gc-tab-strip.blade.php)
- [resources/assets/js/components/tab-strip.js](../../resources/assets/js/components/tab-strip.js)

<a id="room-debug"></a>

## Редактор комнаты

Тип: page. Роли сценария: Администратор.

Настроить положение слоёв комнаты

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Состояния для макета и проверки: фон; слои; предпросмотр; сохранение layout.

Шаблон экрана: editor. Статус: implemented.

Маршруты:

- `GET|HEAD /insider/room-debug` — screen
- `POST /insider/room-debug/layout` — action

Исходники:

- [resources/views/admin/room-debug.blade.php](../../resources/views/admin/room-debug.blade.php)

<a id="course-tabs"></a>

## Активные / черновики / архив

Тип: state. Роли сценария: Новый пользователь, Ученик, Преподаватель, Администратор.

Для преподавателя — вкладки; ученик видит свои, доступные и завершённые курсы.

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Исходники:

- [resources/views/home.blade.php](../../resources/views/home.blade.php)

<a id="join-course"></a>

## Код приглашения / запись на курс

Тип: state. Роли сценария: Новый пользователь, Ученик, Преподаватель, Администратор.

Форма кода и запись в открытый курс возвращают в учебный контекст.

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Маршруты:

- `GET|HEAD /insider/courses/{id}/enroll` — action
- `GET|HEAD /insider/invite` — action

Переходы:

- Запись в открытый курс → [Страница курса](#course) (action)
- Код приглашения принят: вернуться к списку → [Мои курсы](#courses) (action)

Исходники:

- [resources/views/home.blade.php](../../resources/views/home.blade.php)

<a id="course-chapter"></a>

## Глава и список уроков

Тип: state. Роли сценария: Новый пользователь, Ученик, Преподаватель, Администратор.

Глава и список уроков

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Состояния для макета и проверки: точка и перетаскивание на телефоне; выбор после отпускания; вертикальная прокрутка без перехода; Home/End и Escape; ранний доступ; одна и последняя глава; без JavaScript.

Исходники:

- [resources/views/courses/details.blade.php](../../resources/views/courses/details.blade.php)

<a id="lesson-stats"></a>

## Статистика урока

Тип: state. Роли сценария: Преподаватель, Администратор.

Статистика урока

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Исходники:

- [resources/views/courses/details.blade.php](../../resources/views/courses/details.blade.php)

<a id="lesson-deadline"></a>

## Дедлайн всего урока

Тип: dialog. Роли сценария: Преподаватель, Администратор.

Дедлайн всего урока

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Маршруты:

- `POST /insider/courses/{course_id}/lessons/{id}/deadline` — action

Переходы:

- Закрыть / отменить → [Страница курса](#course) (close)

Исходники:

- [resources/views/courses/partials/lesson_deadline_modal.blade.php](../../resources/views/courses/partials/lesson_deadline_modal.blade.php)

<a id="early-access"></a>

## Ранний доступ к уроку

Тип: state. Роли сценария: Новый пользователь, Ученик, Преподаватель, Администратор.

Покупка учеником и выдача преподавателем — разные действия в карточке урока.

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Маршруты:

- `POST /insider/courses/{course_id}/lessons/{id}/early-access` — action
- `POST /insider/courses/{course_id}/lessons/{id}/early-access/grant` — action

Исходники:

- [resources/views/courses/details.blade.php](../../resources/views/courses/details.blade.php)

<a id="step-theory"></a>

## Теория / оглавление / Notebook

Тип: state. Роли сценария: Новый пользователь, Ученик, Преподаватель, Администратор.

Теория / оглавление / Notebook

Проверка: Итерация 10, 12.11.2026: обе роли, 1440/768/390/320 px. Программа с адаптивной сводкой, видимый список ступеней, переход к практике, состояния, сроки и история попыток; проверки длинного содержимого и краевых состояний. Данные изолированы; подробности и ограничения — ui-round-10.md.

Маршруты:

- `GET|HEAD /insider/courses/{course_id}/steps/{id}/notebook` — download

Исходники:

- [resources/views/steps/details.blade.php](../../resources/views/steps/details.blade.php)

<a id="step-task"></a>

## Задача: текст, код или квиз

Тип: state. Роли сценария: Новый пользователь, Ученик, Преподаватель, Администратор.

Задача: текст, код или квиз

Проверка: Итерация 10, 12.11.2026: обе роли, 1440/768/390/320 px. Программа с адаптивной сводкой, видимый список ступеней, переход к практике, состояния, сроки и история попыток; проверки длинного содержимого и краевых состояний. Данные изолированы; подробности и ограничения — ui-round-10.md.

Маршруты:

- `GET|HEAD /insider/courses/{course_id}/tasks/{id}/delete` — action
- `GET|HEAD /insider/courses/{course_id}/tasks/{id}/down` — action
- `GET|HEAD /insider/courses/{course_id}/tasks/{id}/left` — action
- `GET|HEAD /insider/courses/{course_id}/tasks/{id}/phantom` — action
- `GET|HEAD /insider/courses/{course_id}/tasks/{id}/recheck-all` — action
- `GET|HEAD /insider/courses/{course_id}/tasks/{id}/right` — action
- `GET|HEAD /insider/courses/{course_id}/tasks/{id}/up` — action

Переходы:

- Написать / проверить код → [GeekPaste](#geekpaste) (external)
- Отправить решение → [Попытки и обратная связь](#task-solutions) (action)

Исходники:

- [resources/views/steps/details.blade.php](../../resources/views/steps/details.blade.php)

<a id="task-solutions"></a>

## Попытки и обратная связь

Тип: state. Роли сценария: Новый пользователь, Ученик, Преподаватель, Администратор.

Попытки и обратная связь

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Маршруты:

- `POST /insider/courses/{course_id}/tasks/{id}/solution` — action

Переходы:

- Действия с попыткой → [Повторная проверка, штраф и бустер](#solution-options) (open)

Исходники:

- [resources/views/steps/partials/task_solutions_list.blade.php](../../resources/views/steps/partials/task_solutions_list.blade.php)
- [resources/views/steps/solution_partial.blade.php](../../resources/views/steps/solution_partial.blade.php)

<a id="student-progress"></a>

## Прогресс учеников и поиск

Тип: state. Роли сценария: Преподаватель, Администратор.

Прогресс учеников и поиск

Проверка: Итерация 10, 12.11.2026: обе роли, 1440/768/390/320 px. Программа с адаптивной сводкой, видимый список ступеней, переход к практике, состояния, сроки и история попыток; проверки длинного содержимого и краевых состояний. Данные изолированы; подробности и ограничения — ui-round-10.md.

Переходы:

- Выбрать ученика → [Проверка решений ученика](#review) (navigate)

Исходники:

- [resources/views/steps/details.blade.php](../../resources/views/steps/details.blade.php)

<a id="task-create"></a>

## Создание задания

Тип: page. Роли сценария: Преподаватель, Администратор.

Создать условие, выбрать способ проверки и настроить награды

Проверка: Итерация 19: общий перенос B+ и повторный срез всех 41 экранов. Chromium 320/390/768/1440 px, применимые роли, обычные и дополнительные данные; сценарии и границы проверки — ui-round-19.md.

Маршруты:

- `POST /insider/courses/{course_id}/steps/{id}/task` — action
- `GET|HEAD /insider/courses/{course_id}/steps/{id}/task/create` — screen

Переходы:

- Сохранить / отменить → [Ступень](#step) (navigate)

Исходники:

- [resources/views/steps/create_task.blade.php](../../resources/views/steps/create_task.blade.php)
- [resources/views/steps/partials/task-form.blade.php](../../resources/views/steps/partials/task-form.blade.php)
- [resources/assets/js/code-checking/authoring.js](../../resources/assets/js/code-checking/authoring.js)

<a id="task-deadline"></a>

## Дедлайн задачи

Тип: dialog. Роли сценария: Преподаватель, Администратор.

Дедлайн задачи

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Маршруты:

- `POST /insider/courses/{course_id}/tasks/{id}/deadline` — action

Переходы:

- Закрыть / отменить → [Ступень](#step) (close)

Исходники:

- [resources/views/steps/partials/deadline_modal.blade.php](../../resources/views/steps/partials/deadline_modal.blade.php)

<a id="task-summary"></a>

## ИИ-пересказ решений

Тип: dialog. Роли сценария: Преподаватель, Администратор.

ИИ-пересказ решений

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Маршруты:

- `POST /insider/courses/{course_id}/tasks/{id}/ai-summary` — action

Переходы:

- Закрыть / отменить → [Ступень](#step) (close)

Исходники:

- [resources/views/steps/details.blade.php](../../resources/views/steps/details.blade.php)

<a id="solution-options"></a>

## Повторная проверка, штраф и бустер

Тип: state. Роли сценария: Новый пользователь, Ученик, Преподаватель, Администратор.

Повторная проверка, штраф и бустер

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Маршруты:

- `POST /insider/courses/{course_id}/tasks/{id}/geekpaste-extra-attempt` — action
- `POST /insider/courses/{course_id}/tasks/{id}/solution/{solution_id}/deadline-penalty` — action
- `POST /insider/courses/{course_id}/tasks/{id}/solution/{solution_id}/recheck` — action
- `POST /insider/courses/{course_id}/tasks/{id}/solution/{solution_id}/xp-booster` — action

Исходники:

- [resources/views/steps/partials/deadline_penalty.blade.php](../../resources/views/steps/partials/deadline_penalty.blade.php)
- [resources/views/steps/partials/xp_booster.blade.php](../../resources/views/steps/partials/xp_booster.blade.php)
- [resources/views/steps/solution_partial.blade.php](../../resources/views/steps/solution_partial.blade.php)

<a id="review-tools"></a>

## Оценка, комментарий, пропуск и блокировка

Тип: state. Роли сценария: Преподаватель, Администратор.

Оценка, комментарий, пропуск и блокировка Удаление конкретной сдачи с подтверждением и пересчётом прогресса.

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Маршруты:

- `POST /insider/courses/{course_id}/solution/{id}` — action
- `POST /insider/courses/{course_id}/tasks/{id}/block/{student_id}` — action
- `POST /insider/courses/{course_id}/tasks/{id}/solution/{solution_id}/skip-review` — action
- `POST /insider/courses/{course_id}/tasks/{id}/solution/{solution_id}/waive-deadline-penalty` — action
- `POST /insider/courses/{course_id}/tasks/{id}/student/{student_id}/skip-review` — action
- `POST /insider/courses/{course_id}/tasks/{id}/unblock/{student_id}` — action
- `DELETE /insider/courses/{course}/tasks/{task}/attempts/{attempt}` — action

Исходники:

- [resources/views/steps/review.blade.php](../../resources/views/steps/review.blade.php)
- [app/Http/Controllers/SolutionRemovalController.php](../../app/Http/Controllers/SolutionRemovalController.php)
- [app/Services/SolutionRemoval.php](../../app/Services/SolutionRemoval.php)
- [resources/views/components/gc-remove-attempt.blade.php](../../resources/views/components/gc-remove-attempt.blade.php)

<a id="planner-journal"></a>

## Прошедшие занятия и комментарии

Тип: state. Роли сценария: Преподаватель, Администратор.

Посмотреть материалы и комментарии по датам

Проверка: Единый список занятий; комментарий показывает содержание прошедшего занятия рядом с материалами. Отдельного переключателя факта нет. Проверено в браузере на изолированных данных на 1440 и 390 px.

Переходы:

- Добавить / изменить комментарий → [Комментарий к занятию](#planner-record) (navigate)

Исходники:

- [resources/views/course-planner/index.blade.php](../../resources/views/course-planner/index.blade.php)

<a id="planner-summary"></a>

## Обзор покрытия и нагрузки

Тип: state. Роли сценария: Преподаватель, Администратор.

Связь уроков с материалами провайдеров и дедлайнами.

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Переходы:

- Открыть занятие из сводки → [План занятий](#planner) (navigate)

Исходники:

- [resources/views/course-planner/index.blade.php](../../resources/views/course-planner/index.blade.php)

<a id="planner-catalog"></a>

## Каталог провайдера

Тип: dialog. Роли сценария: Преподаватель, Администратор.

Выбрать материалы и добавить к занятию без дублей

Проверка: Нативный модальный каталог: серверный поиск, типы, дочерние материалы, страницы, выбор нескольких элементов; уже добавленные заблокированы. Проверено в браузере на изолированных данных на 1440 и 390 px.

Переходы:

- Открыть материал → [Материал подключённого провайдера](#provider-resource) (navigate)
- Добавить выбранные материалы → [Редактор занятия](#planner-session) (close)

Исходники:

- [resources/views/course-planner/index.blade.php](../../resources/views/course-planner/index.blade.php)

<a id="planner-schedule"></a>

## Расписание курса

Тип: dialog. Роли сценария: Преподаватель, Администратор.

Расписание курса

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Переходы:

- Закрыть / отменить → [План занятий](#planner) (close)
- Рассчитать → [Предпросмотр новой сетки](#planner-preview) (navigate)

Исходники:

- [resources/assets/js/course-planner/index.js](../../resources/assets/js/course-planner/index.js)

<a id="planner-preview"></a>

## Предпросмотр новой сетки

Тип: dialog. Роли сценария: Преподаватель, Администратор.

Предпросмотр новой сетки

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Переходы:

- Закрыть / отменить → [План занятий](#planner) (close)
- Применить сетку → [План занятий](#planner) (navigate)

Исходники:

- [resources/assets/js/course-planner/index.js](../../resources/assets/js/course-planner/index.js)

<a id="planner-providers"></a>

## Подключение провайдеров

Тип: dialog. Роли сценария: Преподаватель, Администратор.

Подключение провайдеров

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Переходы:

- Закрыть / отменить → [План занятий](#planner) (close)

Исходники:

- [resources/assets/js/course-planner/index.js](../../resources/assets/js/course-planner/index.js)

<a id="planner-session"></a>

## Редактор занятия

Тип: dialog. Роли сценария: Преподаватель, Администратор.

Редактор занятия

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Переходы:

- Закрыть / отменить → [План занятий](#planner) (close)
- Выбрать материалы → [Каталог провайдера](#planner-catalog) (navigate)

Исходники:

- [resources/assets/js/course-planner/index.js](../../resources/assets/js/course-planner/index.js)

<a id="planner-note"></a>

## Произвольная запись

Тип: dialog. Роли сценария: Преподаватель, Администратор.

Произвольная запись

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Переходы:

- Закрыть / отменить → [План занятий](#planner) (close)

Исходники:

- [resources/assets/js/course-planner/index.js](../../resources/assets/js/course-planner/index.js)

<a id="planner-move"></a>

## Перенос материала

Тип: dialog. Роли сценария: Преподаватель, Администратор.

Перенос материала

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Переходы:

- Закрыть / отменить → [План занятий](#planner) (close)

Исходники:

- [resources/assets/js/course-planner/index.js](../../resources/assets/js/course-planner/index.js)

<a id="planner-split"></a>

## Разделение урока по занятиям

Тип: dialog. Роли сценария: Преподаватель, Администратор.

Разделение урока по занятиям

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Переходы:

- Закрыть / отменить → [План занятий](#planner) (close)

Исходники:

- [resources/assets/js/course-planner/index.js](../../resources/assets/js/course-planner/index.js)

<a id="planner-relations"></a>

## Связь материала с уроками

Тип: dialog. Роли сценария: Преподаватель, Администратор.

Связь материала с уроками

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Переходы:

- Закрыть / отменить → [План занятий](#planner) (close)

Исходники:

- [resources/assets/js/course-planner/index.js](../../resources/assets/js/course-planner/index.js)

<a id="planner-deadline"></a>

## Дедлайн работы

Тип: dialog. Роли сценария: Преподаватель, Администратор.

Дедлайн работы

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Переходы:

- Закрыть / отменить → [План занятий](#planner) (close)

Исходники:

- [resources/assets/js/course-planner/index.js](../../resources/assets/js/course-planner/index.js)

<a id="planner-exclude"></a>

## Исключение дат

Тип: dialog. Роли сценария: Преподаватель, Администратор.

Исключение дат

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Переходы:

- Закрыть / отменить → [План занятий](#planner) (close)

Исходники:

- [resources/assets/js/course-planner/index.js](../../resources/assets/js/course-planner/index.js)

<a id="planner-record"></a>

## Комментарий к занятию

Тип: dialog. Роли сценария: Преподаватель, Администратор.

Запомнить, что успели на завершившемся занятии

Проверка: Комментарий к прошедшему занятию без подтверждения посещения и отдельного слоя материалов. Проверено в браузере на изолированных данных на 1440 и 390 px.

Переходы:

- Закрыть / отменить → [План занятий](#planner) (close)

Исходники:

- [resources/assets/js/course-planner/index.js](../../resources/assets/js/course-planner/index.js)

<a id="planner-history"></a>

## История изменений

Тип: dialog. Роли сценария: Преподаватель, Администратор.

История изменений

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Переходы:

- Закрыть / отменить → [План занятий](#planner) (close)

Исходники:

- [resources/assets/js/course-planner/index.js](../../resources/assets/js/course-planner/index.js)

<a id="profile-room"></a>

## Комната участника

Тип: state. Роли сценария: Новый пользователь, Ученик, Преподаватель, Администратор.

Просмотр комнаты; редактирование открывается владельцем по кнопке.

Проверка: Итерация 11, 12.11.2026: профиль и галерея, обе роли, 1440/768/390/320 px. Проверены структура, контекст возврата, полные имена, широкие платные рамки, контакты, приватность, действия владельца и преподавателя, ссылки на достижения, палитра и сенсорные цели. Изолированные данные; подробности и ограничения — ui-round-11.md.

Маршруты:

- `GET|HEAD /media/room-character-previews/{generation_id}` — media
- `GET|HEAD /media/{dir}/{name}` — media

Исходники:

- [resources/views/profile/details.blade.php](../../resources/views/profile/details.blade.php)

<a id="room-generation"></a>

## Создание своего персонажа

Тип: state. Роли сценария: Новый пользователь, Ученик, Преподаватель, Администратор.

Цена → запуск → ожидание → результат / ошибка и возврат; без перехода на отдельную страницу.

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Маршруты:

- `POST /insider/profile/{user_id}/room-character` — api
- `GET|HEAD /insider/profile/{user_id}/room-character/{generation_id}` — api

Переходы:

- Готово / возврат при ошибке → [Комната участника](#profile-room) (state)

Исходники:

- [resources/views/profile/partials/room-character.blade.php](../../resources/views/profile/partials/room-character.blade.php)

<a id="profile-history"></a>

## История GC ученика

Тип: state. Роли сценария: Преподаватель, Администратор.

Последние 20 операций чужого профиля в диалоге для уполномоченного преподавателя или администратора. Личная история перенесена в магазин.

Проверка: Итерация 11, 12.11.2026: профиль и галерея, обе роли, 1440/768/390/320 px. Проверены структура, контекст возврата, полные имена, широкие платные рамки, контакты, приватность, действия владельца и преподавателя, ссылки на достижения, палитра и сенсорные цели. Изолированные данные; подробности и ограничения — ui-round-11.md.

Исходники:

- [resources/views/profile/details.blade.php](../../resources/views/profile/details.blade.php)

<a id="profile-achievements"></a>

## Достижения и просмотр кубка

Тип: state. Роли сценария: Новый пользователь, Ученик, Преподаватель, Администратор.

Достижения и просмотр кубка

Проверка: Итерация 11, 12.11.2026: профиль и галерея, обе роли, 1440/768/390/320 px. Проверены структура, контекст возврата, полные имена, широкие платные рамки, контакты, приватность, действия владельца и преподавателя, ссылки на достижения, палитра и сенсорные цели. Изолированные данные; подробности и ограничения — ui-round-11.md.

Исходники:

- [resources/views/profile/partials/achievements.blade.php](../../resources/views/profile/partials/achievements.blade.php)

<a id="achievement-edit"></a>

## Редактирование достижения

Тип: dialog. Роли сценария: Преподаватель, Администратор.

Редактирование достижения

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Маршруты:

- `POST /insider/profile/{user_id}/achievement/{achievement_id}` — action
- `POST /insider/profile/{user_id}/achievement/{achievement_id}/delete` — action

Переходы:

- Закрыть / отменить → [Профиль](#profile) (close)

Исходники:

- [resources/views/profile/partials/achievements.blade.php](../../resources/views/profile/partials/achievements.blade.php)
- [resources/views/profile/partials/achievement.blade.php](../../resources/views/profile/partials/achievement.blade.php)

<a id="profile-money"></a>

## Операция с GC

Тип: dialog. Роли сценария: Преподаватель, Администратор.

Операция с GC

Проверка: Итерация 11, 12.11.2026: профиль и галерея, обе роли, 1440/768/390/320 px. Проверены структура, контекст возврата, полные имена, широкие платные рамки, контакты, приватность, действия владельца и преподавателя, ссылки на достижения, палитра и сенсорные цели. Изолированные данные; подробности и ограничения — ui-round-11.md.

Маршруты:

- `POST /insider/profile/{user_id}/money` — action

Переходы:

- Закрыть / отменить → [Профиль](#profile) (close)

Исходники:

- [resources/views/profile/partials/management-dialogs.blade.php](../../resources/views/profile/partials/management-dialogs.blade.php)

<a id="completed-course"></a>

## Добавление завершённого курса

Тип: dialog. Роли сценария: Администратор.

Добавление завершённого курса

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Маршруты:

- `GET|HEAD /insider/profile/delete-course/{id}` — action
- `POST /insider/profile/{id}/course` — action
- `GET|HEAD /insider/profile/{user_id}/delete-course/{course_id}` — action

Переходы:

- Закрыть / отменить → [Профиль](#profile) (close)

Исходники:

- [resources/views/profile/partials/management-dialogs.blade.php](../../resources/views/profile/partials/management-dialogs.blade.php)

<a id="telegram-link"></a>

## Привязка Telegram

Тип: state. Роли сценария: Новый пользователь, Ученик, Преподаватель, Администратор.

Личная ссылка на существующего бота, ожидание привязки, отключение.

Условия: В интерфейсе подключение доступно только владельцу; административные настройки бота — отдельно.

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Маршруты:

- `GET|HEAD /insider/profile/{id}/telegram-link` — action
- `POST /insider/profile/{id}/telegram-unlink` — action

Переходы:

- Открыть личную ссылку → [Telegram](#telegram) (navigate)

Исходники:

- [resources/views/profile/partials/account-settings.blade.php](../../resources/views/profile/partials/account-settings.blade.php)

<a id="market-goods"></a>

## Физические товары

Тип: state. Роли сценария: Новый пользователь, Ученик, Преподаватель, Администратор.

Физические товары

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Маршруты:

- `POST /insider/market/{id}/buy` — action

Исходники:

- [resources/views/market/index.blade.php](../../resources/views/market/index.blade.php)

<a id="market-digital"></a>

## Цифровые товары

Тип: state. Роли сценария: Новый пользователь, Ученик, Преподаватель, Администратор.

Цифровые товары

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Маршруты:

- `POST /insider/market/digital/{itemKey}/buy` — action
- `POST /insider/profile/{id}/avatar-frame` — action
- `POST /insider/profile/{id}/custom-title` — action

Переходы:

- Купить и экипировать → [Комната участника](#profile-room) (navigate)

Исходники:

- [resources/views/market/index.blade.php](../../resources/views/market/index.blade.php)

<a id="market-auctions"></a>

## Аукционы

Тип: state. Роли сценария: Новый пользователь, Ученик, Преподаватель, Администратор.

Аукционы

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Маршруты:

- `POST /insider/market/{id}/bid` — action
- `POST /insider/market/{id}/finish-auction` — action

Исходники:

- [resources/views/market/index.blade.php](../../resources/views/market/index.blade.php)

<a id="market-archive"></a>

## Архив товаров

Тип: state. Роли сценария: Преподаватель, Администратор.

Архив товаров

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Маршруты:

- `POST /insider/market/{id}/archive` — action
- `POST /insider/market/{id}/restore` — action

Исходники:

- [resources/views/market/index.blade.php](../../resources/views/market/index.blade.php)

<a id="market-orders"></a>

## Заказы внутри магазина

Тип: state. Роли сценария: Преподаватель, Администратор.

Заказы внутри магазина

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Исходники:

- [resources/views/market/index.blade.php](../../resources/views/market/index.blade.php)

<a id="custom-frame"></a>

## Настройка своей рамки

Тип: dialog. Роли сценария: Новый пользователь, Ученик, Преподаватель, Администратор.

Настройка своей рамки

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Переходы:

- Закрыть / отменить → [Магазин](#market) (close)
- Купить рамку → [Профиль](#profile) (action)

Исходники:

- [resources/views/market/partials/digital_goods_grid.blade.php](../../resources/views/market/partials/digital_goods_grid.blade.php)

<a id="navigation"></a>

## Общая навигация

Тип: shell. Роли сценария: Новый пользователь, Ученик, Преподаватель, Администратор.

Перейти в основной раздел или контекст текущего курса.

Проверка: Активный пункт обозначен мягкой заливкой, цветом иконки и насыщенностью текста; убрана цветная скобка слева. Оранжевый акцент вкладок сохранён. Проверка: docs/ux/validation.md.

Переходы:

- Открыть раздел → [Мои курсы](#courses) (navigate)
- Открыть раздел → [Пульс](#pulse) (navigate)
- Открыть раздел → [Магазин](#market) (navigate)
- Открыть раздел → [Сообщество](#community) (navigate)
- Открыть раздел → [Профиль](#profile) (navigate)
- Проверка → [Очередь проверки](#reviews) (navigate)
- Меню профиля → [Меню пользователя](#user-menu) (open)

Исходники:

- [resources/views/layouts/left-menu.blade.php](../../resources/views/layouts/left-menu.blade.php)

<a id="user-menu"></a>

## Меню пользователя

Тип: state. Роли сценария: Новый пользователь, Ученик, Преподаватель, Администратор.

Профиль, настройки администратора, ресурсы, тема и выход.

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Маршруты:

- `POST /logout` — action

Переходы:

- Настройки → [Настройки ИИ](#settings-ai) (navigate)
- Мой профиль → [Профиль](#profile) (navigate)
- Открыть ресурс → [GeekPaste](#geekpaste) (external)
- Открыть ресурс → [GeekExam](#geekexam) (external)
- Открыть ресурс → [GeekBattle / CodeBattle](#geekbattle) (external)
- Открыть ресурс → [Storage](#storage) (external)
- Открыть ресурс → [Arena](#arena) (external)
- Выход → [Вход](#login) (action)
- Переключить тему → [Меню пользователя](#user-menu) (state)
- Личные настройки → [Аккаунт и уведомления](#account-preferences) (navigate)
- Ревью программ для администратора → [Ревью учебных материалов](#material-review) (navigate)

Исходники:

- [resources/views/layouts/partials/sidebar-user-resources.blade.php](../../resources/views/layouts/partials/sidebar-user-resources.blade.php)
- [resources/views/layouts/partials/sidebar-settings-link.blade.php](../../resources/views/layouts/partials/sidebar-settings-link.blade.php)

<a id="course-navigation"></a>

## Навигация внутри ступени

Тип: shell. Роли сценария: Гость, Новый пользователь, Ученик, Преподаватель, Администратор.

Вернуться к курсу или перейти к соседней ступени.

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Переходы:

- К курсу → [Страница курса](#course) (navigate)
- Другая ступень → [Ступень](#step) (navigate)

Исходники:

- [resources/views/steps/partials/nav.blade.php](../../resources/views/steps/partials/nav.blade.php)
- [resources/views/steps/partials/breadcrumb_widget.blade.php](../../resources/views/steps/partials/breadcrumb_widget.blade.php)

<a id="feedback"></a>

## Ошибки и обратная связь

Тип: state. Роли сценария: Гость, Новый пользователь, Ученик, Преподаватель, Администратор.

Валидация, 403/404/419/503, уведомления, ожидание запроса и пустые состояния.

Общий контракт, а не отдельный вручную нарисованный экран ошибки; кастомные error views не обнаружены.

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Маршруты:

- `GET|HEAD /insider/testmail` — maintenance

Исходники:

- [resources/views/layouts/partials/session-alert.blade.php](../../resources/views/layouts/partials/session-alert.blade.php)
- [app/Exceptions/Handler.php](../../app/Exceptions/Handler.php)

<a id="silaeder"></a>

## Личный кабинет Силаэдра

Тип: external. Роли сценария: Гость, Новый пользователь, Ученик, Преподаватель, Администратор.

Личный кабинет Силаэдра

Проверка: Внешний сервис: его внутренние экраны не входят в аудит CodingProjects.

Маршруты:

- `GET|HEAD /auth/silaeder` — callback
- `GET|HEAD /auth/silaeder/callback` — callback
- `GET|HEAD /auth/silaeder/link` — callback
- `GET|HEAD /auth/silaeder/link/confirm/{token}` — callback
- `GET|HEAD /auth/silaeder/logout/callback` — callback

Переходы:

- Успешный callback → [Мои курсы](#courses) (redirect)
- Привязка / заполнение профиля → [Личные настройки](#profile-edit) (redirect)

Исходники:

- Внешняя граница системы.

<a id="geekpaste"></a>

## GeekPaste

Тип: external. Роли сценария: Гость, Новый пользователь, Ученик, Преподаватель, Администратор.

GeekPaste

Проверка: Внешний сервис: его внутренние экраны не входят в аудит CodingProjects.

Маршруты:

- `GET|HEAD /insider/jwt` — redirect

Исходники:

- Внешняя граница системы.

<a id="geekexam"></a>

## GeekExam

Тип: external. Роли сценария: Гость, Новый пользователь, Ученик, Преподаватель, Администратор.

GeekExam

Проверка: Внешний сервис: его внутренние экраны не входят в аудит CodingProjects.

Маршруты:

- `POST /api/geekexam/wifi-disconnect` — callback

Исходники:

- Внешняя граница системы.

<a id="geekbattle"></a>

## GeekBattle / CodeBattle

Тип: external. Роли сценария: Гость, Новый пользователь, Ученик, Преподаватель, Администратор.

GeekBattle / CodeBattle

Проверка: Внешний сервис: его внутренние экраны не входят в аудит CodingProjects.

Исходники:

- Внешняя граница системы.

<a id="ai-code-battle"></a>

## AI Code Battle

Тип: external. Роли сценария: Гость, Новый пользователь, Ученик, Преподаватель, Администратор.

AI Code Battle

Проверка: Внешний сервис: его внутренние экраны не входят в аудит CodingProjects.

Исходники:

- Внешняя граница системы.

<a id="storage"></a>

## Storage

Тип: external. Роли сценария: Гость, Новый пользователь, Ученик, Преподаватель, Администратор.

Storage

Проверка: Внешний сервис: его внутренние экраны не входят в аудит CodingProjects.

Исходники:

- Внешняя граница системы.

<a id="arena"></a>

## Arena

Тип: external. Роли сценария: Гость, Новый пользователь, Ученик, Преподаватель, Администратор.

Arena

Проверка: Внешний сервис: его внутренние экраны не входят в аудит CodingProjects.

Исходники:

- Внешняя граница системы.

<a id="telegram"></a>

## Telegram

Тип: external. Роли сценария: Гость, Новый пользователь, Ученик, Преподаватель, Администратор.

Telegram

Проверка: Внешний сервис: его внутренние экраны не входят в аудит CodingProjects.

Маршруты:

- `POST /telegram/webhook/{secret?}` — api

Исходники:

- Внешняя граница системы.

<a id="provider-resource"></a>

## Материал подключённого провайдера

Тип: external. Роли сценария: Преподаватель, Администратор.

Открыть внешнюю единицу контента из привязки занятия.

Провайдеры расширяемые; тесты и баттлы не являются жёстко заданными типами планировщика.

Проверка: Изучены маршруты и шаблоны; новый визуальный проход ещё не проведён.

Маршруты:

- `GET|HEAD /1561test` — redirect

Исходники:

- [app/CoursePlanner/Providers/ProviderRegistry.php](../../app/CoursePlanner/Providers/ProviderRegistry.php)

<a id="text-assist"></a>

## Помощь ИИ в редакторе

Тип: state. Роли сценария: Преподаватель, Администратор.

Исправить текст, дождаться ответа, вернуть результат в редактор.

Проверка: Состояние общего редактора, не отдельная страница.

Маршруты:

- `POST /insider/chatgpt/improve-text` — api
- `POST /insider/yandexgpt/improve-text` — api

Исходники:

- [resources/assets/js/components/markdown-editor.js](../../resources/assets/js/components/markdown-editor.js)

<a id="account-preferences"></a>

## Аккаунт и уведомления

Тип: state. Роли сценария: Новый пользователь, Ученик, Преподаватель, Администратор.

Telegram, почта и Силаэдр в личных настройках; не административные настройки.

Проверка: Перенесено в Blade; проверяется вместе с профилем, магазином и личными настройками.

Маршруты:

- `POST /insider/profile/{id}/password` — action

Переходы:

- Подключить / отключить Telegram → [Привязка Telegram](#telegram-link) (action)
- Привязать учётную запись → [Личный кабинет Силаэдра](#silaeder) (external)

Исходники:

- [resources/views/profile/partials/account-settings.blade.php](../../resources/views/profile/partials/account-settings.blade.php)

<a id="room-editor"></a>

## Настройка комнаты по кнопке

Тип: dialog. Роли сценария: Новый пользователь, Ученик, Преподаватель, Администратор.

Только купленные предметы и генерация; просмотр профиля не загружен формами.

Проверка: Перенесено в Blade; проверяется вместе с профилем, магазином и личными настройками.

Маршруты:

- `POST /insider/profile/{id}/learning-avatar` — action

Переходы:

- Сохранить и вернуться → [Комната участника](#profile-room) (close)
- Создать своего персонажа → [Создание своего персонажа](#room-generation) (action)
- Отменить настройки → [Профиль](#profile) (close)

Исходники:

- [resources/views/profile/partials/room-settings.blade.php](../../resources/views/profile/partials/room-settings.blade.php)

<a id="personal-purchases"></a>

## Мои покупки

Тип: state. Роли сценария: Новый пользователь, Ученик, Преподаватель, Администратор.

Личные заказы и доступные цифровые предметы владельца; отдельная пагинация по 25 заказов.

Проверка: Перенесено в Blade; проверяется вместе с профилем, магазином и личными настройками.

Исходники:

- [resources/views/market/partials/purchases.blade.php](../../resources/views/market/partials/purchases.blade.php)

<a id="wallet"></a>

## История GC в магазине

Тип: state. Роли сценария: Новый пользователь, Ученик, Преподаватель, Администратор.

Личная история операций текущего пользователя, по 25 на страницу; переход из баланса и совместимость со старым #gc-history.

Проверка: Перенесено в Blade; проверяется вместе с профилем, магазином и личными настройками.

Исходники:

- [resources/views/market/index.blade.php](../../resources/views/market/index.blade.php)
- [resources/views/components/gc-wallet-history.blade.php](../../resources/views/components/gc-wallet-history.blade.php)

<a id="register-profile"></a>

## Профиль при регистрации

Тип: state. Роли сценария: Гость.

Заполнить обязательные сведения ученика и при желании добавить контакты

Проверка: Проверено переключение состояния в изолированном браузере 11.11.2026.

Переходы:

- Назад без потери полей → [Регистрация](#register) (state)
- Зарегистрироваться → [Подтверждение почты](#verify) (action)

Исходники:

- [resources/views/auth/register.blade.php](../../resources/views/auth/register.blade.php)

<a id="achievement-choice"></a>

## Выбор идеи достижения

Тип: state. Роли сценария: Преподаватель, Администратор.

Выбрать один вариант и отредактировать его в общей форме

Проверка: Проверено переключение состояния в изолированном браузере 11.11.2026.

Исходники:

- [resources/views/steps/achievement_preview.blade.php](../../resources/views/steps/achievement_preview.blade.php)

<a id="profile-collections"></a>

## Коллекции и завершённые курсы

Тип: state. Роли сценария: Ученик, Преподаватель, Администратор.

Открыть второстепенные коллекции без перегрузки первого экрана

Проверка: Итерация 11, 12.11.2026: профиль и галерея, обе роли, 1440/768/390/320 px. Проверены структура, контекст возврата, полные имена, широкие платные рамки, контакты, приватность, действия владельца и преподавателя, ссылки на достижения, палитра и сенсорные цели. Изолированные данные; подробности и ограничения — ui-round-11.md.

Исходники:

- [resources/views/profile/partials/stickers.blade.php](../../resources/views/profile/partials/stickers.blade.php)

<a id="reading-outline"></a>

## Оглавление конспекта

Тип: state. Роли сценария: Ученик, Преподаватель, Администратор.

Выбрать раздел конспекта на телефоне или в боковом оглавлении

Проверка: Итерация 10, 12.11.2026: обе роли, 1440/768/390/320 px. Программа с адаптивной сводкой, видимый список ступеней, переход к практике, состояния, сроки и история попыток; проверки длинного содержимого и краевых состояний. Данные изолированы; подробности и ограничения — ui-round-10.md.

Исходники:

- [resources/views/steps/details.blade.php](../../resources/views/steps/details.blade.php)

<a id="market-promotion-picker"></a>

## Выбор товаров акции

Тип: dialog. Роли сценария: Администратор.

Найти и выбрать обычные и цифровые товары с сохранением выбора между страницами

Проверка: Проверены серверные страницы по 25, кириллица, выбор между страницами и страницы целиком, отмена, применение, удаление, повтор после ошибки, сохранение акции и баннер. Проверен сенсорный диалог 320×740.

Состояния для макета и проверки: поиск; пусто; загрузка; ошибка и повтор; постраничный выбор; отмена; телефон.

Маршруты:

- `GET|HEAD /insider/settings/system-store/targets` — api

Переходы:

- Применить выбор / отменить → [Цены и акции](#settings-store) (close)

Исходники:

- [resources/views/settings/partials/store-promotion.blade.php](../../resources/views/settings/partials/store-promotion.blade.php)
- [resources/assets/js/components/promotion-picker.js](../../resources/assets/js/components/promotion-picker.js)
- [app/Http/Controllers/SystemStoreSettingsController.php](../../app/Http/Controllers/SystemStoreSettingsController.php)

<a id="report-student-picker"></a>

## Выбор ученика в отчёте

Тип: dialog. Роли сценария: Преподаватель, Администратор.

Найти ученика на телефоне; список и сортировка общие с компьютерной колонкой

Проверка: Проверены поиск среди 32 учеников, пустой результат, сброс, выбор и закрытие панели. Компьютер использует тот же список слева.

Состояния для макета и проверки: поиск; пусто; сортировка; выбранный ученик; закрытие.

Переходы:

- Выбор / закрытие → [Отчёт по курсу](#report) (close)

Исходники:

- [resources/views/courses/report.blade.php](../../resources/views/courses/report.blade.php)
- [resources/assets/js/components/report-workspace.js](../../resources/assets/js/components/report-workspace.js)

<a id="course-current-lesson"></a>

## Текущий урок курса

Тип: state. Роли сценария: Преподаватель, Администратор.

Выбрать общий текущий урок и вернуть автоматическое личное продолжение ученика.

Условия: Администратор или преподаватель именно этого курса. Урок принадлежит программе курса и содержит ступени; отметка не меняет расписание и результаты.

Проверка: Итерации 20–21: общий текущий урок, доступность, возврат из другой главы и стикер; ui-round-21.md.

Состояния для макета и проверки: выбор и замена урока; автоматический выбор после сброса; выбор в другой главе; закрытый урок не открывает доступ; серая и полученная наклейка; нет наклейки.

Маршруты:

- `POST /insider/courses/{course_id}/current-lesson` — action

Переходы:

- Сохранить или сбросить общий текущий урок → [Страница курса](#course) (close)

Исходники:

- [resources/views/courses/details.blade.php](../../resources/views/courses/details.blade.php)
- [app/Http/Controllers/CoursesController.php](../../app/Http/Controllers/CoursesController.php)
- [app/Course.php](../../app/Course.php)
- [resources/views/components/gc-lesson-reward.blade.php](../../resources/views/components/gc-lesson-reward.blade.php)

<a id="chapter-tasks"></a>

## Задачи главы ученика

Тип: page. Роли сценария: Ученик.

Посмотреть собственные результаты задач выбранной главы и перейти к заданию.

Условия: Ученик, зачисленный на этот курс. Закрытые уроки и скрытые задачи без собственной попытки не раскрываются.

Проверка: Проверены выбор главы, фильтры, переход к заданию, телефон и компьютер.

Состояния для макета и проверки: одна / несколько глав; к выполнению / выполнено / на проверке; нет доступных задач; пустой фильтр; ранний доступ.

Маршруты:

- `GET|HEAD /insider/courses/{id}/chapter-tasks` — screen

Переходы:

- Открыть задание → [Ступень](#step) (navigate)

Исходники:

- [resources/views/courses/chapter-tasks.blade.php](../../resources/views/courses/chapter-tasks.blade.php)
- [resources/views/components/gc-course-header.blade.php](../../resources/views/components/gc-course-header.blade.php)
- [app/Http/Controllers/StudentCourseTasksController.php](../../app/Http/Controllers/StudentCourseTasksController.php)

<a id="geekexecutor"></a>

## GeekExecutor: исполнение кода

Тип: external. Роли сценария: Ученик, Преподаватель, Администратор.

Граница исполнения кода: передача запусков, результатов и файлов.

Проверка: Внешний исполнитель: контейнеры и ИИ, прямой WebSocket статуса. Исходники, отчёты и награды показываются в Coding Projects.

Маршруты:

- `GET|HEAD /api/executor/artifacts/{artifact}` — api
- `POST /api/executor/executions/{execution}/grade` — api
- `GET|HEAD /api/executor/executions/{execution}/input` — api
- `POST /api/executor/executions/{execution}/progress` — api
- `POST /api/executor/executions/{execution}/result` — api
- `POST /api/executor/test-artifacts/status` — api
- `PUT /api/executor/test-artifacts/{artifact}` — api
- `POST /api/executor/test-reports` — api
- `GET|HEAD /insider/settings/code-executor/legacy-artifacts/{artifact}` — download
- `GET|HEAD /insider/settings/code-executor/legacy-exports` — api
- `GET|HEAD /insider/settings/code-executor/legacy-exports/{export}/files/{file}` — download
- `GET|HEAD /insider/settings/code-executor/login` — redirect
- `GET|HEAD /insider/settings/code-executor/status` — api

Исходники:

- [routes/code-checking.php](../../routes/code-checking.php)
- [docs/code-checking-backend.md](../../docs/code-checking-backend.md)

<a id="reward-notices"></a>

## Уведомления о наградах

Тип: state. Роли сценария: Ученик.

Увидеть фактически полученную наклейку или монеты без повторения после обновления.

Проверка: Проверены последовательная выдача, две вкладки, повторное открытие, закрытие и пауза при фокусе.

Маршруты:

- `POST /insider/rewards/claim` — api

Исходники:

- [resources/views/components/gc-reward-notices.blade.php](../../resources/views/components/gc-reward-notices.blade.php)
- [resources/assets/js/components/reward-notices.js](../../resources/assets/js/components/reward-notices.js)
- [app/Http/Controllers/RewardNoticesController.php](../../app/Http/Controllers/RewardNoticesController.php)

<a id="code-submission"></a>

## Отправленное решение и проверки

Тип: page. Роли сценария: Ученик, Преподаватель, Администратор.

Посмотреть неизменяемые исходники, результат, историю проверок и доступные тесты.

Условия: Автор решения либо преподаватель соответствующего курса.

Проверка: HTML-страница по обычной ссылке; тот же API отдаёт JSON для формы в уроке. AJAX, Socket.IO, тесты и награды проверены в изолированном сценарии.

Маршруты:

- `GET|HEAD /insider/code-submissions/{submission}` — screen
- `GET|HEAD /insider/code-submissions/{submission}/bundle` — download
- `GET|HEAD /insider/code-submissions/{submission}/compare` — api
- `GET|HEAD /insider/code-submissions/{submission}/files/{file}` — download
- `GET|HEAD /insider/code-submissions/{submission}/files/{file}/archive` — api
- `GET|HEAD /insider/code-submissions/{submission}/files/{file}/archive/entries/{entry}` — api
- `GET|HEAD /insider/code-submissions/{submission}/files/{file}/archive/entries/{entry}/download` — download
- `PUT /insider/code-submissions/{submission}/flags` — api
- `GET|HEAD /insider/code-submissions/{submission}/legacy-source` — download
- `GET|HEAD /insider/code-submissions/{submission}/repository/archive` — download
- `POST /insider/code-submissions/{submission}/retry` — api
- `POST /insider/code-submissions/{submission}/review-request` — api
- `GET|HEAD /insider/code-submissions/{submission}/runs/{run}` — api
- `GET|HEAD /insider/code-submissions/{submission}/runs/{run}/files/{file}` — download
- `GET|HEAD /insider/code-submissions/{submission}/runs/{run}/tests/{number}` — api
- `POST /insider/code-submissions/{submission}/runs/{run}/tests/{number}/purchase` — api
- `GET|HEAD /insider/code-submissions/{submission}/runs/{run}/tests/{number}/{field}` — download
- `GET|HEAD /insider/code-submissions/{submission}/subscription` — api
- `GET|HEAD /insider/code-submissions/{submission}/task-files/{file}` — download
- `GET|HEAD /insider/code-test-reports/{run}` — api
- `POST /insider/code-test-reports/{run}/tests/{test}/purchase` — api
- `GET|HEAD /insider/code-test-reports/{run}/tests/{test}/{field}` — download
- `GET|HEAD /insider/courses/{course}/tasks/{task}/code-attempts/{solution}` — api
- `GET|HEAD /legacy/geekpaste/{legacy}/{action?}` — redirect
- `GET|HEAD /shared/code-submissions/{submission}/files/{file}` — download
- `GET|HEAD /shared/code-submissions/{submission}/{action?}` — screen

Переходы:

- Вернуться к задаче → [Ступень](#step) (navigate)
- Условие сохранённой сдачи → [Условие на момент сдачи](#material-history) (navigate)

Исходники:

- [resources/views/steps/native-submission.blade.php](../../resources/views/steps/native-submission.blade.php)
- [resources/views/steps/partials/native-workspace.blade.php](../../resources/views/steps/partials/native-workspace.blade.php)
- [resources/assets/js/code-checking/workspace.js](../../resources/assets/js/code-checking/workspace.js)

<a id="course-notifications"></a>

## Уведомления курса

Тип: page. Роли сценария: Преподаватель, Администратор.

Настроить личные напоминания преподавателям и чатам курса, подтвердить подключение группы и проверить доставку.

Проверка: Цепочки сроков: общий стиль, компактные настройки, 390/1440 px, подмена Telegram в проверках.

Состояния для макета и проверки: группа ожидает подтверждения; подключённая тема; отключённый бот; очередь / ошибка / неизвестный результат; пустой журнал.

Маршруты:

- `GET|HEAD /insider/courses/{id}/notifications` — screen
- `POST /insider/courses/{id}/notifications` — action
- `POST /insider/courses/{id}/notifications/chats/{chat}` — action
- `POST /insider/courses/{id}/notifications/deliveries/{delivery}/retry` — action
- `POST /insider/courses/{id}/notifications/link` — action

Исходники:

- [resources/views/courses/notifications.blade.php](../../resources/views/courses/notifications.blade.php)
- [app/Http/Controllers/CourseNotificationsController.php](../../app/Http/Controllers/CourseNotificationsController.php)
- [app/Services/CourseTelegram.php](../../app/Services/CourseTelegram.php)
- [app/Services/DeadlineNotifications.php](../../app/Services/DeadlineNotifications.php)

<a id="material-review"></a>

## Ревью учебных материалов

Тип: page. Роли сценария: Администратор.

Выбрать редакцию урока, разобрать замысел, структуру и локальные правки, проверить итог и отдельно опубликовать.

Условия: Только администраторы; версия основы, принадлежность программе и версия черновика проверяются перед записью.

Проверка: Изолированный синтетический стенд, Chromium 1440/390, светлая и тёмная темы; конфликт с сохранением ввода, публикация и подготовка восстановления.

Состояния для макета и проверки: программы с поиском; урок без предложений; три уровня ревью; несколько редакций; сравнение; вопрос без готовой правки; конфликт; прогресс; предпросмотр публикации; опубликованная версия; светлая и тёмная темы; узкое окно.

Шаблон экрана: review-workspace. Статус: implemented.

Маршруты:

- `GET|HEAD /insider/material-review` — screen
- `POST /insider/material-review/drafts` — action
- `GET|HEAD /insider/material-review/drafts/{id}` — api
- `POST /insider/material-review/drafts/{id}/{action}` — action
- `POST /insider/material-review/preview` — api
- `GET|HEAD /material-resources/{id}` — media

Переходы:

- Редактировать материал или вариант предложения → [Ручная правка в ревью](#material-review-editor) (open)

Исходники:

- [resources/views/material-review/index.blade.php](../../resources/views/material-review/index.blade.php)
- [resources/views/material-review/document.blade.php](../../resources/views/material-review/document.blade.php)
- [public/css/material-review.css](../../public/css/material-review.css)
- [public/js/material-review.js](../../public/js/material-review.js)

<a id="material-review-editor"></a>

## Ручная правка в ревью

Тип: dialog. Роли сценария: Администратор.

Исправить текст или ячейки Jupyter, просмотреть результат, сохранить авторское предложение и сохранить ввод при конфликте.

Проверка: Браузер: ручная правка, предпросмотр, сохранение, конфликт и восстановление введённого текста на 1440/390.

Состояния для макета и проверки: Markdown; связанные части; ячейки Jupyter; предпросмотр; версия изменилась; восстановление ввода.

Переходы:

- Сохранить в черновик или закрыть редактор → [Ревью учебных материалов](#material-review) (close)

Исходники:

- [resources/views/material-review/index.blade.php](../../resources/views/material-review/index.blade.php)
- [public/js/material-review.js](../../public/js/material-review.js)

<a id="material-history"></a>

## Условие на момент сдачи

Тип: page. Роли сценария: Ученик, Преподаватель, Администратор.

Прочитать достоверное условие своей прежней работы после изменения или переноса задачи.

Условия: Только владелец работы и уполномоченный преподаватель курса/администратор; авторское решение видно только преподавателю.

Проверка: PHP-тесты: доступ владельца и преподавателя, запрет постороннему ученику, отсутствие авторского решения у ученика.

Состояния для макета и проверки: снимок сохранён; версия неизвестна; задача перенесена; приватное авторское решение.

Маршруты:

- `GET|HEAD /insider/material-code-history/{id}` — screen
- `GET|HEAD /insider/material-history/{id}` — screen

Исходники:

- [resources/views/material-review/attempt.blade.php](../../resources/views/material-review/attempt.blade.php)
- [resources/views/material-review/code-condition.blade.php](../../resources/views/material-review/code-condition.blade.php)

<a id="course-subgroups"></a>

## Подгруппы курса

Тип: page. Роли сценария: Преподаватель, Администратор.

Создать подгруппы, распределить учеников и открыть отдельное расписание.

Условия: Администратор или преподаватель именно этого курса. Один текущий состав, история переводов сохранена.

Проверка: Создание и переименование, массовый перевод, защита от устаревшего состава; проверки описаны в ../course-subgroups.md.

Состояния для макета и проверки: нет подгрупп; нераспределённые ученики; перевод одного или нескольких учеников; конфликт состава; ширина 320 px; создание по запросу; добавление оставшихся одним действием; поиск большого класса; закреплённая панель перевода.

Шаблон экрана: course-workspace. Статус: implemented.

Маршруты:

- `GET|HEAD /insider/courses/{course_id}/subgroups` — screen
- `POST /insider/courses/{course_id}/subgroups` — action
- `POST /insider/courses/{course_id}/subgroups/assign` — action
- `POST /insider/courses/{course_id}/subgroups/{subgroup_id}` — action

Переходы:

- Расписание подгруппы → [План занятий](#planner) (open)

Исходники:

- [resources/views/courses/subgroups.blade.php](../../resources/views/courses/subgroups.blade.php)
- [app/Http/Controllers/CourseSubgroupsController.php](../../app/Http/Controllers/CourseSubgroupsController.php)
- [app/Services/CourseSubgroups.php](../../app/Services/CourseSubgroups.php)
- [app/Support/CourseScope.php](../../app/Support/CourseScope.php)
- [resources/assets/js/components/course-subgroups.js](../../resources/assets/js/components/course-subgroups.js)

<a id="settings-stickers"></a>

## Каталог наклеек

Тип: page. Роли сценария: Администратор.

Добавить наклейку в коллекцию или изменить название и коллекцию

Проверка: Проверяется на локальном стенде в административных настройках.

Состояния для макета и проверки: Новая наклейка; Изменение коллекции; Повторяющееся изображение.

Маршруты:

- `GET|HEAD /insider/settings/stickers` — screen
- `POST /insider/settings/stickers` — action
- `PUT /insider/settings/stickers/{sticker}` — action

Переходы:

- ИИ → [Настройки ИИ](#settings-ai) (navigate)

Исходники:

- [resources/views/settings/stickers.blade.php](../../resources/views/settings/stickers.blade.php)
- [app/Http/Controllers/StickersController.php](../../app/Http/Controllers/StickersController.php)
- [app/Sticker.php](../../app/Sticker.php)
