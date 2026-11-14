# Покрытие маршрутов UX-картой

Генерируется `npm run ux:build`. GET-маршрут может изменять данные, отдавать файл или перенаправлять; он не считается экраном автоматически. Таблица — инвентаризация текущего кода, не аудит безопасности.

| Метод | URL | Тип | Владелец в UX | Middleware |
| --- | --- | --- | --- | --- |
| GET\|HEAD | `/` | redirect | [Вход](screens.md#login) | web |
| GET\|HEAD | `/1561test` | redirect | [Материал подключённого провайдера](screens.md#provider-resource) | web |
| GET\|HEAD | `/api/executor/artifacts/{artifact}` | api | [GeekExecutor: исполнение кода](screens.md#geekexecutor) | api, throttle:6000,1 |
| GET\|HEAD | `/api/executor/executions/{execution}/input` | api | [GeekExecutor: исполнение кода](screens.md#geekexecutor) | api, throttle:6000,1 |
| POST | `/api/executor/executions/{execution}/progress` | api | [GeekExecutor: исполнение кода](screens.md#geekexecutor) | api, throttle:6000,1 |
| POST | `/api/executor/executions/{execution}/result` | api | [GeekExecutor: исполнение кода](screens.md#geekexecutor) | api, throttle:6000,1 |
| POST | `/api/executor/test-artifacts/status` | api | [GeekExecutor: исполнение кода](screens.md#geekexecutor) | api, throttle:6000,1 |
| PUT | `/api/executor/test-artifacts/{artifact}` | api | [GeekExecutor: исполнение кода](screens.md#geekexecutor) | api, throttle:6000,1 |
| POST | `/api/executor/test-reports` | api | [GeekExecutor: исполнение кода](screens.md#geekexecutor) | api, throttle:6000,1 |
| GET\|HEAD | `/api/user` | api | [Профиль](screens.md#profile) | api, auth:api |
| GET\|HEAD | `/auth/silaeder` | callback | [Личный кабинет Силаэдра](screens.md#silaeder) | web, guest, throttle:30,1 |
| GET\|HEAD | `/auth/silaeder/callback` | callback | [Личный кабинет Силаэдра](screens.md#silaeder) | web, throttle:60,1 |
| GET\|HEAD | `/auth/silaeder/link` | callback | [Личный кабинет Силаэдра](screens.md#silaeder) | web, auth, throttle:30,1 |
| GET\|HEAD | `/auth/silaeder/link/confirm/{token}` | callback | [Личный кабинет Силаэдра](screens.md#silaeder) | web, signed, throttle:20,1 |
| GET\|HEAD | `/auth/silaeder/logout/callback` | callback | [Личный кабинет Силаэдра](screens.md#silaeder) | web, throttle:60,1 |
| POST | `/email/resend` | action | [Подтверждение почты](screens.md#verify) | web, auth, throttle:6,1 |
| GET\|HEAD | `/email/verify` | screen | [Подтверждение почты](screens.md#verify) | web, auth |
| GET\|HEAD | `/email/verify/{id}/{hash}` | action | [Подтверждение почты](screens.md#verify) | web, auth, signed, throttle:6,1 |
| GET\|HEAD | `/home` | redirect | [Вход](screens.md#login) | web |
| GET\|HEAD | `/insider` | redirect | [Мои курсы](screens.md#courses) | web, verified |
| POST | `/insider/authoring/preview` | api | [Редактирование ступени](screens.md#step-edit) | web, verified, auth, teacher |
| POST | `/insider/chatgpt/improve-text` | api | [Помощь ИИ в редакторе](screens.md#text-assist) | web, verified |
| GET\|HEAD | `/insider/code-submissions/{submission}` | screen | [Отправленное решение и проверки](screens.md#code-submission) | web, auth, verified |
| GET\|HEAD | `/insider/code-submissions/{submission}/compare` | api | [Отправленное решение и проверки](screens.md#code-submission) | web, auth, verified |
| GET\|HEAD | `/insider/code-submissions/{submission}/files/{file}` | download | [Отправленное решение и проверки](screens.md#code-submission) | web, auth, verified |
| PUT | `/insider/code-submissions/{submission}/flags` | api | [Отправленное решение и проверки](screens.md#code-submission) | web, auth, verified |
| PUT | `/insider/code-submissions/{submission}/grade` | api | [Проверка решений ученика](screens.md#review) | web, auth, verified |
| PUT | `/insider/code-submissions/{submission}/integrity/review` | api | [Проверка решений ученика](screens.md#review) | web, auth, verified |
| GET\|HEAD | `/insider/code-submissions/{submission}/legacy-source` | download | [Отправленное решение и проверки](screens.md#code-submission) | web, auth, verified |
| POST | `/insider/code-submissions/{submission}/manual-review` | api | [Проверка решений ученика](screens.md#review) | web, auth, verified |
| POST | `/insider/code-submissions/{submission}/recheck` | api | [Проверка решений ученика](screens.md#review) | web, auth, verified |
| GET\|HEAD | `/insider/code-submissions/{submission}/repository/archive` | download | [Отправленное решение и проверки](screens.md#code-submission) | web, auth, verified |
| POST | `/insider/code-submissions/{submission}/review-request` | api | [Отправленное решение и проверки](screens.md#code-submission) | web, auth, verified |
| GET\|HEAD | `/insider/code-submissions/{submission}/runs/{run}` | api | [Отправленное решение и проверки](screens.md#code-submission) | web, auth, verified |
| GET\|HEAD | `/insider/code-submissions/{submission}/runs/{run}/files/{file}` | download | [Отправленное решение и проверки](screens.md#code-submission) | web, auth, verified |
| GET\|HEAD | `/insider/code-submissions/{submission}/subscription` | api | [Отправленное решение и проверки](screens.md#code-submission) | web, auth, verified |
| GET\|HEAD | `/insider/code-submissions/{submission}/task-files/{file}` | download | [Отправленное решение и проверки](screens.md#code-submission) | web, auth, verified |
| GET\|HEAD | `/insider/code-test-reports/{run}` | api | [Отправленное решение и проверки](screens.md#code-submission) | web, auth, verified |
| POST | `/insider/code-test-reports/{run}/tests/{test}/purchase` | api | [Отправленное решение и проверки](screens.md#code-submission) | web, auth, verified |
| GET\|HEAD | `/insider/code-test-reports/{run}/tests/{test}/{field}` | download | [Отправленное решение и проверки](screens.md#code-submission) | web, auth, verified |
| GET\|HEAD | `/insider/community` | screen | [Сообщество](screens.md#community) | web, verified, auth |
| GET\|HEAD | `/insider/courses` | screen | [Мои курсы](screens.md#courses) | web, verified, auth |
| GET\|HEAD | `/insider/courses/create` | screen | [Создание курса](screens.md#course-create) | web, verified, auth, teacher |
| POST | `/insider/courses/create` | action | [Создание курса](screens.md#course-create) | web, verified, auth, teacher |
| POST | `/insider/courses/{course_id}/chapters/{chapter_id}/default` | action | [Страница курса](screens.md#course) | web, verified, auth, course, teacher, course.manage:chapter |
| GET\|HEAD | `/insider/courses/{course_id}/chapters/{chapter_id}/edit` | screen | [Редактирование главы](screens.md#chapter-edit) | web, verified, auth, course, teacher, course.manage:chapter |
| POST | `/insider/courses/{course_id}/chapters/{chapter_id}/edit` | action | [Редактирование главы](screens.md#chapter-edit) | web, verified, auth, course, teacher, course.manage:chapter |
| GET\|HEAD | `/insider/courses/{course_id}/chapters/{chapter_id}/export-points` | download | [Страница курса](screens.md#course) | web, verified, auth, course, teacher, course.manage |
| GET\|HEAD | `/insider/courses/{course_id}/chapters/{chapter_id}/lower` | action | [Страница курса](screens.md#course) | web, verified, auth, course.manage:chapter |
| GET\|HEAD | `/insider/courses/{course_id}/chapters/{chapter_id}/upper` | action | [Страница курса](screens.md#course) | web, verified, auth, course.manage:chapter |
| POST | `/insider/courses/{course_id}/current-lesson` | action | [Текущий урок курса](screens.md#course-current-lesson) | web, verified, auth, course |
| GET\|HEAD | `/insider/courses/{course_id}/deadline-policies/{type}/{target}` | api | [Дедлайны курса](screens.md#deadlines) | web, verified |
| POST | `/insider/courses/{course_id}/deadline-policies/{type}/{target}` | action | [Дедлайны курса](screens.md#deadlines) | web, verified |
| GET\|HEAD | `/insider/courses/{course_id}/lessons/{id}/create` | screen | [Добавление ступени](screens.md#step-create) | web, verified, auth, step |
| POST | `/insider/courses/{course_id}/lessons/{id}/create` | action | [Добавление ступени](screens.md#step-create) | web, verified, auth, step |
| POST | `/insider/courses/{course_id}/lessons/{id}/deadline` | action | [Дедлайн всего урока](screens.md#lesson-deadline) | web, verified, auth, course.manage:lesson, teacher |
| GET\|HEAD | `/insider/courses/{course_id}/lessons/{id}/delete` | action | [Страница курса](screens.md#course) | web, verified, auth, course.manage:lesson |
| POST | `/insider/courses/{course_id}/lessons/{id}/early-access` | action | [Ранний доступ к уроку](screens.md#early-access) | web, verified, auth |
| POST | `/insider/courses/{course_id}/lessons/{id}/early-access/grant` | action | [Ранний доступ к уроку](screens.md#early-access) | web, verified, auth, course.manage:lesson, teacher |
| GET\|HEAD | `/insider/courses/{course_id}/lessons/{id}/edit` | screen | [Редактирование урока](screens.md#lesson-edit) | web, verified, auth, course.manage:lesson, teacher |
| POST | `/insider/courses/{course_id}/lessons/{id}/edit` | action | [Редактирование урока](screens.md#lesson-edit) | web, verified, auth, course.manage:lesson, teacher |
| GET\|HEAD | `/insider/courses/{course_id}/lessons/{id}/export` | download | [Страница курса](screens.md#course) | web, verified, auth, course.manage:lesson, teacher |
| GET\|HEAD | `/insider/courses/{course_id}/lessons/{id}/export-md` | download | [Страница курса](screens.md#course) | web, verified, auth, course.manage:lesson, teacher |
| GET\|HEAD | `/insider/courses/{course_id}/lessons/{id}/export-points` | download | [Страница курса](screens.md#course) | web, verified, auth, course.manage:lesson, teacher |
| GET\|HEAD | `/insider/courses/{course_id}/lessons/{id}/lower` | action | [Страница курса](screens.md#course) | web, verified, auth, course.manage:lesson, teacher |
| GET\|HEAD | `/insider/courses/{course_id}/lessons/{id}/upper` | action | [Страница курса](screens.md#course) | web, verified, auth, course.manage:lesson, teacher |
| GET\|HEAD | `/insider/courses/{course_id}/lessons/{lesson_id}/statistics` | api | [Страница курса](screens.md#course) | web, verified, auth, course |
| GET\|HEAD | `/insider/courses/{course_id}/perform/{id}` | screen | [Режим показа](screens.md#perform) | web, verified, auth, step, teacher |
| GET\|HEAD | `/insider/courses/{course_id}/planner` | screen | [План занятий](screens.md#planner) | web, auth, verified |
| POST | `/insider/courses/{course_id}/planner/api/calendar/preview` | api | [План занятий](screens.md#planner) | web, auth, verified |
| POST | `/insider/courses/{course_id}/planner/api/commands/{operation}` | api | [План занятий](screens.md#planner) | web, auth, verified |
| GET\|HEAD | `/insider/courses/{course_id}/planner/api/entries/{link_id}` | api | [План занятий](screens.md#planner) | web, auth, verified |
| GET\|HEAD | `/insider/courses/{course_id}/planner/api/providers/{provider_key}/resources` | api | [План занятий](screens.md#planner) | web, auth, verified |
| GET\|HEAD | `/insider/courses/{course_id}/planner/api/state` | api | [План занятий](screens.md#planner) | web, auth, verified |
| GET\|HEAD | `/insider/courses/{course_id}/planner/api/summary/details` | api | [План занятий](screens.md#planner) | web, auth, verified |
| GET\|HEAD | `/insider/courses/{course_id}/questions/{id}/delete` | action | [Ступень](screens.md#step) | web, verified, auth, step |
| POST | `/insider/courses/{course_id}/solution/{id}` | action | [Оценка, комментарий, пропуск и блокировка](screens.md#review-tools) | web, verified, auth, task, teacher |
| GET\|HEAD | `/insider/courses/{course_id}/steps/{id}` | screen | [Ступень](screens.md#step) | web, verified, auth, step |
| GET\|HEAD | `/insider/courses/{course_id}/steps/{id}/delete` | action | [Ступень](screens.md#step) | web, verified, auth, step, teacher |
| GET\|HEAD | `/insider/courses/{course_id}/steps/{id}/edit` | screen | [Редактирование ступени](screens.md#step-edit) | web, verified, auth, step, teacher |
| POST | `/insider/courses/{course_id}/steps/{id}/edit` | action | [Редактирование ступени](screens.md#step-edit) | web, verified, auth, step, teacher |
| GET\|HEAD | `/insider/courses/{course_id}/steps/{id}/lower` | action | [Ступень](screens.md#step) | web, verified, auth, step, teacher |
| GET\|HEAD | `/insider/courses/{course_id}/steps/{id}/notebook` | download | [Теория / оглавление / Notebook](screens.md#step-theory) | web, verified, auth, step |
| POST | `/insider/courses/{course_id}/steps/{id}/question` | action | [Ступень](screens.md#step) | web, verified, auth, step |
| POST | `/insider/courses/{course_id}/steps/{id}/task` | action | [Создание задания](screens.md#task-create) | web, verified, auth, task, teacher |
| GET\|HEAD | `/insider/courses/{course_id}/steps/{id}/task/create` | screen | [Создание задания](screens.md#task-create) | web, verified, teacher, auth, task |
| GET\|HEAD | `/insider/courses/{course_id}/steps/{id}/upper` | action | [Ступень](screens.md#step) | web, verified, auth, step, teacher |
| POST | `/insider/courses/{course_id}/tasks/{id}/ai-summary` | action | [ИИ-пересказ решений](screens.md#task-summary) | web, verified, auth, task, teacher |
| POST | `/insider/courses/{course_id}/tasks/{id}/block/{student_id}` | action | [Оценка, комментарий, пропуск и блокировка](screens.md#review-tools) | web, verified, auth, task, teacher |
| POST | `/insider/courses/{course_id}/tasks/{id}/deadline` | action | [Дедлайн задачи](screens.md#task-deadline) | web, verified, auth, task |
| GET\|HEAD | `/insider/courses/{course_id}/tasks/{id}/delete` | action | [Задача: текст, код или квиз](screens.md#step-task) | web, verified, auth, task, teacher |
| GET\|HEAD | `/insider/courses/{course_id}/tasks/{id}/down` | action | [Задача: текст, код или квиз](screens.md#step-task) | web, verified, auth, task |
| GET\|HEAD | `/insider/courses/{course_id}/tasks/{id}/edit` | screen | [Редактирование задачи](screens.md#task-edit) | web, verified, auth, task, teacher |
| POST | `/insider/courses/{course_id}/tasks/{id}/edit` | action | [Редактирование задачи](screens.md#task-edit) | web, verified, auth, task, teacher |
| POST | `/insider/courses/{course_id}/tasks/{id}/geekpaste-extra-attempt` | action | [Повторная проверка, штраф и бустер](screens.md#solution-options) | web, verified, auth, task |
| GET\|HEAD | `/insider/courses/{course_id}/tasks/{id}/left` | action | [Задача: текст, код или квиз](screens.md#step-task) | web, verified, auth, task |
| GET\|HEAD | `/insider/courses/{course_id}/tasks/{id}/phantom` | action | [Задача: текст, код или квиз](screens.md#step-task) | web, verified, auth, task, teacher |
| GET\|HEAD | `/insider/courses/{course_id}/tasks/{id}/recheck-all` | action | [Задача: текст, код или квиз](screens.md#step-task) | web, verified, auth, task |
| GET\|HEAD | `/insider/courses/{course_id}/tasks/{id}/right` | action | [Задача: текст, код или квиз](screens.md#step-task) | web, verified, auth, task |
| POST | `/insider/courses/{course_id}/tasks/{id}/solution` | action | [Попытки и обратная связь](screens.md#task-solutions) | web, verified, auth, task |
| POST | `/insider/courses/{course_id}/tasks/{id}/solution/{solution_id}/achievement` | action | [Предпросмотр достижения](screens.md#achievement-preview) | web, verified, auth, task, teacher |
| GET\|HEAD | `/insider/courses/{course_id}/tasks/{id}/solution/{solution_id}/achievement-preview` | screen | [Предпросмотр достижения](screens.md#achievement-preview) | web, verified, auth, task, teacher |
| POST | `/insider/courses/{course_id}/tasks/{id}/solution/{solution_id}/achievement-preview` | action | [Предпросмотр достижения](screens.md#achievement-preview) | web, verified, auth, task, teacher |
| POST | `/insider/courses/{course_id}/tasks/{id}/solution/{solution_id}/deadline-penalty` | action | [Повторная проверка, штраф и бустер](screens.md#solution-options) | web, verified, auth, task |
| POST | `/insider/courses/{course_id}/tasks/{id}/solution/{solution_id}/recheck` | action | [Повторная проверка, штраф и бустер](screens.md#solution-options) | web, verified, auth, task |
| POST | `/insider/courses/{course_id}/tasks/{id}/solution/{solution_id}/skip-review` | action | [Оценка, комментарий, пропуск и блокировка](screens.md#review-tools) | web, verified, auth, task, teacher |
| POST | `/insider/courses/{course_id}/tasks/{id}/solution/{solution_id}/waive-deadline-penalty` | action | [Оценка, комментарий, пропуск и блокировка](screens.md#review-tools) | web, verified, auth, task, teacher |
| POST | `/insider/courses/{course_id}/tasks/{id}/solution/{solution_id}/xp-booster` | action | [Повторная проверка, штраф и бустер](screens.md#solution-options) | web, verified, auth, task |
| GET\|HEAD | `/insider/courses/{course_id}/tasks/{id}/student/{student_id}` | screen | [Проверка решений ученика](screens.md#review) | web, verified, auth, task, teacher |
| POST | `/insider/courses/{course_id}/tasks/{id}/student/{student_id}/skip-review` | action | [Оценка, комментарий, пропуск и блокировка](screens.md#review-tools) | web, verified, auth, task, teacher |
| POST | `/insider/courses/{course_id}/tasks/{id}/unblock/{student_id}` | action | [Оценка, комментарий, пропуск и блокировка](screens.md#review-tools) | web, verified, auth, task |
| GET\|HEAD | `/insider/courses/{course_id}/tasks/{id}/up` | action | [Задача: текст, код или квиз](screens.md#step-task) | web, verified, auth, task |
| GET\|HEAD | `/insider/courses/{course}/tasks/{task}/checker` | api | [Ступень](screens.md#step) | web, auth, verified |
| POST | `/insider/courses/{course}/tasks/{task}/checker/extra-attempt` | api | [Ступень](screens.md#step) | web, auth, verified |
| GET\|POST\|HEAD | `/insider/courses/{course}/tasks/{task}/checker/files` | api | [Редактирование задачи](screens.md#task-edit) | web, auth, verified |
| GET\|DELETE\|HEAD | `/insider/courses/{course}/tasks/{task}/checker/files/{attachment}` | api | [Редактирование задачи](screens.md#task-edit) | web, auth, verified |
| POST | `/insider/courses/{course}/tasks/{task}/checker/files/{attachment}/replace` | api | [Редактирование задачи](screens.md#task-edit) | web, auth, verified |
| POST | `/insider/courses/{course}/tasks/{task}/checker/fixtures` | api | [Редактирование задачи](screens.md#task-edit) | web, auth, verified |
| GET\|HEAD | `/insider/courses/{course}/tasks/{task}/checker/fixtures/{artifact}` | download | [Редактирование задачи](screens.md#task-edit) | web, auth, verified |
| GET\|HEAD | `/insider/courses/{course}/tasks/{task}/checker/legacy` | api | [Редактирование задачи](screens.md#task-edit) | web, auth, verified |
| GET\|PUT\|HEAD | `/insider/courses/{course}/tasks/{task}/checker/private` | api | [Редактирование задачи](screens.md#task-edit) | web, auth, verified |
| GET\|PUT\|DELETE\|HEAD | `/insider/courses/{course}/tasks/{task}/code-draft` | api | [Ступень](screens.md#step) | web, auth, verified |
| GET\|HEAD | `/insider/courses/{course}/tasks/{task}/code-draft/files/{file}` | download | [Ступень](screens.md#step) | web, auth, verified |
| GET\|POST\|HEAD | `/insider/courses/{course}/tasks/{task}/code-submissions` | api | [Ступень](screens.md#step) | web, auth, verified |
| POST | `/insider/courses/{course}/tasks/{task}/code-submissions/recheck-latest` | api | [Проверка решений ученика](screens.md#review) | web, auth, verified |
| GET\|HEAD | `/insider/courses/{id}` | screen | [Страница курса](screens.md#course) | web, verified, course |
| GET\|HEAD | `/insider/courses/{id}/assessments` | screen | [Журнал опыта](screens.md#assessments) | web, verified, auth, course, teacher, course.manage |
| GET\|HEAD | `/insider/courses/{id}/blocked` | screen | [Заблокированные задачи](screens.md#blocked) | web, verified, auth |
| GET\|HEAD | `/insider/courses/{id}/chapter` | screen | [Добавление главы](screens.md#chapter-create) | web, verified, auth, course, teacher, course.manage |
| POST | `/insider/courses/{id}/chapter` | action | [Добавление главы](screens.md#chapter-create) | web, verified, auth, course, teacher, course.manage |
| GET\|HEAD | `/insider/courses/{id}/chapter-tasks` | screen | [Задачи главы ученика](screens.md#chapter-tasks) | web, verified, auth, course |
| GET\|HEAD | `/insider/courses/{id}/create` | screen | [Добавление урока](screens.md#lesson-create) | web, verified, auth, course.manage:lesson, teacher |
| POST | `/insider/courses/{id}/create` | action | [Добавление урока](screens.md#lesson-create) | web, verified, auth, course.manage:lesson, teacher |
| GET\|HEAD | `/insider/courses/{id}/deadlines` | screen | [Дедлайны курса](screens.md#deadlines) | web, verified, auth, course |
| GET\|HEAD | `/insider/courses/{id}/edit` | screen | [Настройки курса](screens.md#course-edit) | web, verified, auth, course, teacher, course.manage |
| POST | `/insider/courses/{id}/edit` | action | [Настройки курса](screens.md#course-edit) | web, verified, auth, course, teacher, course.manage |
| GET\|HEAD | `/insider/courses/{id}/enroll` | action | [Код приглашения / запись на курс](screens.md#join-course) | web, verified, auth |
| GET\|HEAD | `/insider/courses/{id}/export` | download | [Страница курса](screens.md#course) | web, verified, auth, course.manage |
| GET\|HEAD | `/insider/courses/{id}/export-md` | download | [Страница курса](screens.md#course) | web, verified, auth, course, teacher, course.manage |
| GET\|HEAD | `/insider/courses/{id}/notifications` | screen | [Уведомления курса](screens.md#course-notifications) | web, verified |
| POST | `/insider/courses/{id}/notifications` | action | [Уведомления курса](screens.md#course-notifications) | web, verified |
| POST | `/insider/courses/{id}/notifications/chats/{chat}` | action | [Уведомления курса](screens.md#course-notifications) | web, verified |
| POST | `/insider/courses/{id}/notifications/deliveries/{delivery}/retry` | action | [Уведомления курса](screens.md#course-notifications) | web, verified |
| POST | `/insider/courses/{id}/notifications/link` | action | [Уведомления курса](screens.md#course-notifications) | web, verified |
| POST | `/insider/courses/{id}/notifications/preference` | action | [Дедлайны курса](screens.md#deadlines) | web, verified |
| POST | `/insider/courses/{id}/poster` | action | [Настройки курса](screens.md#course-edit) | web, verified, auth, course, teacher, course.manage |
| GET\|HEAD | `/insider/courses/{id}/report` | screen | [Отчёт по курсу](screens.md#report) | web, verified, auth, course, teacher, course.manage |
| POST | `/insider/courses/{id}/report/students/{student_id}/geekpaste-warning/reset` | action | [Отчёт по курсу](screens.md#report) | web, verified, auth, course, teacher, course.manage |
| GET\|HEAD | `/insider/courses/{id}/start` | action | [Страница курса](screens.md#course) | web, verified, auth, course, teacher, course.manage |
| GET\|HEAD | `/insider/courses/{id}/stop` | action | [Страница курса](screens.md#course) | web, verified, auth, course, teacher, course.manage |
| GET\|HEAD | `/insider/invite` | action | [Код приглашения / запись на курс](screens.md#join-course) | web, verified, auth |
| GET\|HEAD | `/insider/jwt` | redirect | [GeekPaste](screens.md#geekpaste) | web, verified, auth |
| GET\|HEAD | `/insider/market` | screen | [Магазин](screens.md#market) | web, verified, auth |
| POST | `/insider/market/cancel/{id}` | action | [Заказы](screens.md#orders) | web, verified, auth, teacher |
| GET\|HEAD | `/insider/market/create` | screen | [Добавление товара](screens.md#market-create) | web, verified, auth, admin |
| POST | `/insider/market/create` | action | [Добавление товара](screens.md#market-create) | web, verified, auth, admin |
| GET\|HEAD | `/insider/market/credit` | screen | [Массовое начисление GC](screens.md#credit) | web, verified, auth, admin |
| POST | `/insider/market/credit` | action | [Массовое начисление GC](screens.md#credit) | web, verified, auth, admin |
| POST | `/insider/market/digital/{itemKey}/buy` | action | [Цифровые товары](screens.md#market-digital) | web, verified, auth |
| GET\|HEAD | `/insider/market/orders` | screen | [Заказы](screens.md#orders) | web, verified, auth, teacher |
| POST | `/insider/market/ship/{id}` | action | [Заказы](screens.md#orders) | web, verified, auth, teacher |
| POST | `/insider/market/{id}/archive` | action | [Архив товаров](screens.md#market-archive) | web, verified, auth, admin |
| POST | `/insider/market/{id}/bid` | action | [Аукционы](screens.md#market-auctions) | web, verified, auth |
| POST | `/insider/market/{id}/buy` | action | [Физические товары](screens.md#market-goods) | web, verified, auth |
| GET\|HEAD | `/insider/market/{id}/edit` | screen | [Редактирование товара](screens.md#market-edit) | web, verified, auth, admin |
| POST | `/insider/market/{id}/edit` | action | [Редактирование товара](screens.md#market-edit) | web, verified, auth, admin |
| POST | `/insider/market/{id}/finish-auction` | action | [Аукционы](screens.md#market-auctions) | web, verified, auth, admin |
| POST | `/insider/market/{id}/restore` | action | [Архив товаров](screens.md#market-archive) | web, verified, auth, admin |
| GET\|HEAD | `/insider/profile/delete-course/{id}` | action | [Добавление завершённого курса](screens.md#completed-course) | web, verified, auth, self, admin |
| GET\|HEAD | `/insider/profile/{id?}` | screen | [Профиль](screens.md#profile) | web, verified, auth |
| POST | `/insider/profile/{id}/avatar-frame` | action | [Цифровые товары](screens.md#market-digital) | web, verified, auth, self |
| POST | `/insider/profile/{id}/course` | action | [Добавление завершённого курса](screens.md#completed-course) | web, verified, auth, self, admin |
| POST | `/insider/profile/{id}/custom-title` | action | [Цифровые товары](screens.md#market-digital) | web, verified, auth, self |
| GET\|HEAD | `/insider/profile/{id}/edit` | screen | [Личные настройки](screens.md#profile-edit) | web, verified, auth, self |
| POST | `/insider/profile/{id}/edit` | action | [Личные настройки](screens.md#profile-edit) | web, verified, auth, self |
| POST | `/insider/profile/{id}/learning-avatar` | action | [Настройка комнаты по кнопке](screens.md#room-editor) | web, verified, auth, self |
| POST | `/insider/profile/{id}/password` | action | [Аккаунт и уведомления](screens.md#account-preferences) | web, verified, auth, self |
| GET\|HEAD | `/insider/profile/{id}/telegram-link` | action | [Привязка Telegram](screens.md#telegram-link) | web, verified, auth, self |
| POST | `/insider/profile/{id}/telegram-unlink` | action | [Привязка Telegram](screens.md#telegram-link) | web, verified, auth, self |
| POST | `/insider/profile/{user_id}/achievement/{achievement_id}` | action | [Редактирование достижения](screens.md#achievement-edit) | web, verified, auth |
| POST | `/insider/profile/{user_id}/achievement/{achievement_id}/delete` | action | [Редактирование достижения](screens.md#achievement-edit) | web, verified, auth |
| GET\|HEAD | `/insider/profile/{user_id}/delete-course/{course_id}` | action | [Добавление завершённого курса](screens.md#completed-course) | web, verified, auth, self, admin |
| POST | `/insider/profile/{user_id}/money` | action | [Операция с GC](screens.md#profile-money) | web, verified, auth, teacher |
| POST | `/insider/profile/{user_id}/room-character` | api | [Создание своего персонажа](screens.md#room-generation) | web, verified, throttle:10,1, auth |
| GET\|HEAD | `/insider/profile/{user_id}/room-character/{generation_id}` | api | [Создание своего персонажа](screens.md#room-generation) | web, verified, auth |
| GET\|HEAD | `/insider/pulse` | screen | [Пульс](screens.md#pulse) | web, verified, auth |
| GET\|HEAD | `/insider/reviews` | screen | [Очередь проверки](screens.md#reviews) | web, verified, auth, teacher |
| POST | `/insider/reviews/reset-pending` | action | [Очередь проверки](screens.md#reviews) | web, verified, auth, teacher |
| POST | `/insider/rewards/claim` | api | [Уведомления о наградах](screens.md#reward-notices) | web, verified, throttle:60,1 |
| GET\|HEAD | `/insider/room-debug` | screen | [Редактор комнаты](screens.md#room-debug) | web, verified, admin |
| POST | `/insider/room-debug/layout` | action | [Редактор комнаты](screens.md#room-debug) | web, verified, admin |
| GET\|HEAD | `/insider/settings/ai` | screen | [Настройки ИИ](screens.md#settings-ai) | web, verified, auth, admin |
| PUT | `/insider/settings/ai` | action | [Настройки ИИ](screens.md#settings-ai) | web, verified, auth, admin |
| DELETE | `/insider/settings/ai` | action | [Настройки ИИ](screens.md#settings-ai) | web, verified, auth, admin |
| GET\|HEAD | `/insider/settings/code-executor/legacy-exports/{export}/files/{file}` | download | [GeekExecutor: исполнение кода](screens.md#geekexecutor) | web, auth, verified |
| GET\|HEAD | `/insider/settings/code-executor/login` | redirect | [GeekExecutor: исполнение кода](screens.md#geekexecutor) | web, auth, verified |
| GET\|HEAD | `/insider/settings/code-executor/status` | api | [GeekExecutor: исполнение кода](screens.md#geekexecutor) | web, auth, verified |
| GET\|HEAD | `/insider/settings/system-store` | screen | [Цены и акции](screens.md#settings-store) | web, verified, auth, admin |
| PUT | `/insider/settings/system-store` | action | [Цены и акции](screens.md#settings-store) | web, verified, auth, admin |
| GET\|HEAD | `/insider/settings/system-store/targets` | api | [Выбор товаров акции](screens.md#market-promotion-picker) | web, verified, auth, admin |
| GET\|HEAD | `/insider/settings/telegram` | screen | [Настройки Telegram](screens.md#settings-telegram) | web, verified, auth, admin |
| PUT | `/insider/settings/telegram` | action | [Настройки Telegram](screens.md#settings-telegram) | web, verified, auth, admin |
| DELETE | `/insider/settings/telegram` | action | [Настройки Telegram](screens.md#settings-telegram) | web, verified, auth, admin |
| GET\|HEAD | `/insider/testmail` | maintenance | [Ошибки и обратная связь](screens.md#feedback) | web, verified |
| POST | `/insider/yandexgpt/improve-text` | api | [Помощь ИИ в редакторе](screens.md#text-assist) | web, verified |
| GET\|HEAD | `/legacy/geekpaste/{legacy}/{action?}` | redirect | [Отправленное решение и проверки](screens.md#code-submission) | web |
| GET\|HEAD | `/login` | screen | [Вход](screens.md#login) | web, guest |
| POST | `/login` | action | [Вход](screens.md#login) | web, guest |
| POST | `/logout` | action | [Меню пользователя](screens.md#user-menu) | web |
| GET\|HEAD | `/media/room-character-previews/{generation_id}` | media | [Комната участника](screens.md#profile-room) | web |
| GET\|HEAD | `/media/{dir}/{name}` | media | [Комната участника](screens.md#profile-room) | web |
| GET\|HEAD | `/open/steps/{id}` | screen | [Открытая ступень](screens.md#public-step) | web |
| GET\|HEAD | `/open/steps/{id}/notebook` | download | [Открытая ступень](screens.md#public-step) | web |
| POST | `/password/email` | action | [Восстановление доступа](screens.md#password-request) | web, guest |
| GET\|HEAD | `/password/reset` | screen | [Восстановление доступа](screens.md#password-request) | web, guest |
| POST | `/password/reset` | action | [Новый пароль](screens.md#password-reset) | web, guest |
| GET\|HEAD | `/password/reset/{token}` | screen | [Новый пароль](screens.md#password-reset) | web, guest |
| GET\|HEAD | `/register` | screen | [Регистрация](screens.md#register) | web, guest |
| POST | `/register` | action | [Регистрация](screens.md#register) | web, guest |
| GET\|HEAD | `/shared/code-submissions/{submission}/files/{file}` | download | [Отправленное решение и проверки](screens.md#code-submission) | web |
| GET\|HEAD | `/shared/code-submissions/{submission}/{action?}` | screen | [Отправленное решение и проверки](screens.md#code-submission) | web |
| GET\|HEAD | `/start-exam` | redirect | [Открытая ступень](screens.md#public-step) | web |
| GET\|HEAD | `/telegram-bot` | redirect | [Открытая ступень](screens.md#public-step) | web |
| POST | `/telegram/webhook/{secret?}` | api | [Telegram](screens.md#telegram) | web |
