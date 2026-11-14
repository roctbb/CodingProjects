# Завершение переноса B+ · 13 ноября 2026

Выполнен [план обновления](style-rollout-plan.md). Согласованная основа сохранена: нейтральные поверхности, тёмная основная навигация, компактные иконки курсов над названием, небольшие акценты настроенной темы. Сначала сняты исходные состояния, затем исправлены расхождения и повторно просмотрена вся система.

## Изменения этого прохода

| Семейство | Решение и результат |
| --- | --- |
| Общие компоненты | Геометрия полей, подписей и таблиц получила одного владельца в базе. Заголовки секций и кнопки меню перенесены из legacy в компоненты. Удалены заменённые правила; проверка стилей предотвращает их возвращение. Наведение строки использует нейтральную поверхность. Старые Bootstrap-модали дедлайнов, достижений и операций GC получили ту же поверхность, радиусы и интервалы, что нативные диалоги; старые заливки, линии и утилиты теней удалены. Общий токен затемнения сохраняет правильный фон диалога и в тёмном отображении. |
| Мои курсы | Карточки завершённых курсов приведены к тому же шаблону с маленькой иконкой, что активные и доступные. Отметка и доступность ссылки сохранены. На ширине 320 px заголовок, Пульс и действие перестраиваются без сжатия короткого названия в две строки. |
| Курс и ступень | У кнопки списка глав и таймлайна больше нет пересечения областей нажатия. На телефоне счётчик и точки расположены над названием. Исправлено растягивание верхней строки тетради на коротком конспекте: вкладки следуют сразу за заголовком. Серый рейл ступеней и белая рабочая область сохранены. |
| План занятий | Колонки плотной недели выбираются по доступной ширине, а не по числу дней. Они не сжимаются до нечитаемой полосы. У длинного графика нагрузки сохраняется минимальная ширина столбцов и локальная горизонтальная прокрутка. Каталог, комментарий к прошедшему занятию и меню переноса сохранены. |
| Журнал, отчёт, проверка | Повторно проверены выбранная глава, таблица задач одного урока, поиск, пустые результаты, выбор ученика и мобильная очередь. Общая геометрия таблиц применяется без отдельной палитры рабочего экрана. Вложенная вертикальная прокрутка таблиц не добавлена. |
| Профиль, сообщество, Пульс | Длинное имя на телефоне получает полную ширину под портретом; короткое остаётся рядом с компактным портретом. Проверены единая визитка с комнатой, открытые контакты и наклейки, длинная биография, галерея и поиск участников, обычные и длинные списки событий. Сохранены публичные рамки и обычные аватары в преподавательских рабочих списках. |
| Магазин | Создание и редактирование используют один partial предпросмотра и один JS-модуль. Превью получило самостоятельную поверхность с заголовком внутри. Формы используют общий индикатор изменений и отправки. В диалоге акции Escape закрывает окно и из заполненного поиска, с возвратом фокуса. Проверены виды товаров, покупки, история, архив, заказы и выбор товаров акции. |
| Формы и настройки | Общие правила применены к редакторам материалов и настройкам на всю ширину контента. На сенсорных устройствах текст нативных полей не меньше 16 px, области действий не меньше 44 px. Редактор комнаты переведён с 660 строк встроенного CSS на общий слой с токенами, без изменения генерации и геометрии слоёв. |
| Вход и вторичные экраны | В подтверждении почты убрана повторная рамка шапки. Вход с фотографией на весь фон и белой формой по центру оставлен без изменения шаблона. Проверены регистрация, восстановление, открытая ступень, показ урока, блокировки и предпросмотр достижения. |

Часть выбранных решений была перенесена в предыдущих итерациях. Здесь они проверены вместе с изменёнными общими компонентами; разделы без обнаруженных проблем не переписывались заново.

## Проверка

- Исходный срез: 142 снимка при 1440 и 390 px.
- Итоговый срез: 284 снимка — 71 набор экрана, роли и данных при 320, 390, 768 и 1440 px. Покрыты все 41 страница UX-карты и дополнительные состояния. Все ответы успешны; ошибок JavaScript, выхода страницы за ширину окна, обрезанных выбранных вкладок и уменьшенных сенсорных контролов в этом срезе нет.
- После настройки длинных имён повторно проверены 20 состояний профиля на тех же четырёх ширинах; соответствующие итоговые снимки обновлены.
- Интерактивный основной проход: 102 проверки при 1440, 390 и 320 px. Поиск и возврат фокуса списка глав, теория/задачи, видимая навигация ступеней, фильтры журнала и отчёта, модальный каталог планировщика, галерея, предпросмотр товара, состояние формы и применимость полей задачи.
- Дополнительный проход: **86 проверок**, успешно. Вкладки и фильтры магазина, поиск товаров акции и Escape, шаги регистрации и показ пароля, навигация глав с клавиатуры, открытые наклейки и контакты, предпросмотр редактирования. Регистрация и GET-переход главы также проверены без JavaScript.
- Палитры в Chromium: **112 проверок**, успешно. Светлое и тёмное отображение, действующая синяя/оранжевая палитра и две дополнительные, включая светлый основной цвет. Проверены контраст текста навигации и карточек, выбор иконки, мобильное меню и учебные переходы.
- Дополнительный WebKit: **40 снимков** критических экранов при 1440 и 390 px и **112 проверок** палитр и учебных переходов — успешно. Использован установленный в проекте Playwright 1.59.1; сбой нажатий в новом WebKit CLI воспроизводился на пустой странице с одной кнопкой и не устранялся изменением приложения.
- Интерактивные проходы в WebKit повторены после готовности шрифтов и MathJax: **102 + 86 проверок**, успешно, без ошибок JavaScript.
- Диалоги: **66 снимков** при 1440, 390 и 320 px. Настройка и предпросмотр расписания, провайдеры, история, занятие, запись, перенос и разделение материалов, связь с уроком, исключение даты, комментарий, дедлайны, пересказ решений, GC, достижение, комната и рамка. Открытие и закрытие проверены без применения изменений к данным. Диалоги повторно проверены в WebKit, тёмном отображении и двух дополнительных палитрах. Область кнопки закрытия на сенсорном устройстве — 44 px.
- `php artisan test --compact`: **596 тестов, 4844 проверки**, успешно. Две старые проверки наличия CSS в HTML редактора комнаты удалены после переноса правил в общий пакет; проверки структуры редактора и поведения остались.
- `npm run styles:check`, `npm run build`, `npm run ux:check`, компиляция Blade и `git diff --check` — успешно. Карта покрывает 173 из 173 маршрутов, 41 страницу и 251 переход.

Контроль включает обычные и дополнительные данные: 40 глав, плотную неделю, длинные материалы, несколько глав журнала, длинный профиль и коллекцию наклеек, пустой профиль, акцию, конспект с кодом/таблицами и Notebook. Результаты отдельных проходов содержат точные роли, fixture и ширину.

## Артефакты

Папка: `output/playwright/ui-round-19/`.

| Файл | Содержание |
| --- | --- |
| `baseline.json`, `before/` | Исходный срез |
| `responsive-pass.json`, `after/` | Итоговый полный срез четырёх ширин |
| `flows.json` | Основные интерактивные проверки |
| `secondary-flows.json` | Вкладки магазина, регистрация, модальный выбор акции, главы с клавиатуры, открытые контакты/наклейки и формы без JavaScript |
| `webkit-stable-webkit-pass.json`, `webkit/` | Контрольные снимки WebKit |
| `webkit-stable-verified-flows.json` | Основные интерактивные сценарии WebKit: 102 проверки |
| `webkit-stable-verified-secondary-flows.json` | Дополнительные сценарии магазина и регистрации в WebKit: 86 проверок |
| `webkit-stable-verified-webkit-themes.json` | Проверка палитр и учебных переходов WebKit |
| `dialog-themes.json`, `dialog-themed-*.png` | Диалоги в тёмном отображении и дополнительных палитрах |
| `webkit-stable-dialogs.json` | Дополнительная проверка диалогов WebKit |
| `dialogs.json`, `dialog-*.png` | Проверка и снимки диалогов |
| `profile-final.json` | Повторная проверка профилей после настройки длинных имён |
| `final-sheet-1.png` … `final-sheet-5.png` | Контактные листы всех 41 страниц компьютера |

Общая матрица снимков приведена ниже; полный набор состояний находится в JSON. Визуальная оценка выполнена по контактным листам и полным снимкам проблемных экранов, отдельно просмотрены мобильные семейства.

## Границы

Браузерные проверки используют отдельные копии SQLite, тестовые аккаунты и подменённые внешние запросы/уведомления. Это позволяет проверять интерфейс без отправки сообщений и изменения учебных данных. Генерация изображений, ответы реальных внешних провайдеров и доставка Telegram не входили в этот проход.

Фотографии входа, цепочка `BRAND_*` из окружения/YAML, сохранённые оценки, права доступа и расчёт XP/GC сохранены. После этого среза ручное закрепление урока и дополнительная настройка отчёта реализованы в [итерации 20](ui-round-20.md). Уведомления о наградах, интеграция GeekPaste, ведомость и бот остаются отдельными этапами из [follow-ups.md](follow-ups.md).

Legacy всё ещё содержит оформление старых схем; перенос общих примитивов не объявляет весь прежний Sass переписанным. Для новых изменений действуют [общие правила](../ui-style.md) и [границы JS-модулей](../frontend.md).

## Матрица экранов

| Экран | Роли в срезе | Компьютер | Телефон |
| --- | --- | --- | --- |
| Вход | гость | [1440](../../output/playwright/ui-round-19/after/login-guest-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/login-guest-phone-390.png) |
| Регистрация | гость | [1440](../../output/playwright/ui-round-19/after/register-guest-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/register-guest-phone-390.png) |
| Восстановление доступа | гость | [1440](../../output/playwright/ui-round-19/after/password-request-guest-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/password-request-guest-phone-390.png) |
| Новый пароль | гость | [1440](../../output/playwright/ui-round-19/after/password-reset-guest-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/password-reset-guest-phone-390.png) |
| Подтверждение почты | ученик, преподаватель | [1440](../../output/playwright/ui-round-19/after/verify-student-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/verify-student-phone-390.png) |
| Мои курсы | ученик, преподаватель | [1440](../../output/playwright/ui-round-19/after/courses-student-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/courses-student-phone-390.png) |
| Страница курса | ученик, преподаватель | [1440](../../output/playwright/ui-round-19/after/course-student-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/course-student-phone-390.png) |
| Дедлайны курса | ученик, преподаватель | [1440](../../output/playwright/ui-round-19/after/deadlines-student-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/deadlines-student-phone-390.png) |
| Ступень | ученик, преподаватель | [1440](../../output/playwright/ui-round-19/after/step-student-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/step-student-phone-390.png) |
| Открытая ступень | ученик, преподаватель | [1440](../../output/playwright/ui-round-19/after/public-step-student-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/public-step-student-phone-390.png) |
| Пульс | ученик, преподаватель | [1440](../../output/playwright/ui-round-19/after/pulse-student-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/pulse-student-phone-390.png) |
| Создание курса | преподаватель | [1440](../../output/playwright/ui-round-19/after/course-create-teacher-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/course-create-teacher-phone-390.png) |
| Настройки курса | преподаватель | [1440](../../output/playwright/ui-round-19/after/course-edit-teacher-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/course-edit-teacher-phone-390.png) |
| Добавление главы | преподаватель | [1440](../../output/playwright/ui-round-19/after/chapter-create-teacher-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/chapter-create-teacher-phone-390.png) |
| Редактирование главы | преподаватель | [1440](../../output/playwright/ui-round-19/after/chapter-edit-teacher-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/chapter-edit-teacher-phone-390.png) |
| Добавление урока | преподаватель | [1440](../../output/playwright/ui-round-19/after/lesson-create-teacher-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/lesson-create-teacher-phone-390.png) |
| Редактирование урока | преподаватель | [1440](../../output/playwright/ui-round-19/after/lesson-edit-teacher-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/lesson-edit-teacher-phone-390.png) |
| Добавление ступени | преподаватель | [1440](../../output/playwright/ui-round-19/after/step-create-teacher-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/step-create-teacher-phone-390.png) |
| Редактирование ступени | преподаватель | [1440](../../output/playwright/ui-round-19/after/step-edit-teacher-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/step-edit-teacher-phone-390.png) |
| Редактирование задачи | преподаватель | [1440](../../output/playwright/ui-round-19/after/task-edit-teacher-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/task-edit-teacher-phone-390.png) |
| Режим показа | преподаватель | [1440](../../output/playwright/ui-round-19/after/perform-teacher-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/perform-teacher-phone-390.png) |
| Очередь проверки | преподаватель | [1440](../../output/playwright/ui-round-19/after/reviews-teacher-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/reviews-teacher-phone-390.png) |
| Проверка решений ученика | преподаватель | [1440](../../output/playwright/ui-round-19/after/review-teacher-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/review-teacher-phone-390.png) |
| Предпросмотр достижения | преподаватель | [1440](../../output/playwright/ui-round-19/after/achievement-preview-teacher-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/achievement-preview-teacher-phone-390.png) |
| Отчёт по курсу | преподаватель | [1440](../../output/playwright/ui-round-19/after/report-teacher-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/report-teacher-phone-390.png) |
| Журнал опыта | преподаватель | [1440](../../output/playwright/ui-round-19/after/assessments-teacher-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/assessments-teacher-phone-390.png) |
| Заблокированные задачи | преподаватель | [1440](../../output/playwright/ui-round-19/after/blocked-teacher-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/blocked-teacher-phone-390.png) |
| План занятий | преподаватель | [1440](../../output/playwright/ui-round-19/after/planner-teacher-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/planner-teacher-phone-390.png) |
| Сообщество | ученик, преподаватель | [1440](../../output/playwright/ui-round-19/after/community-student-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/community-student-phone-390.png) |
| Профиль | ученик, преподаватель | [1440](../../output/playwright/ui-round-19/after/profile-student-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/profile-student-phone-390.png) |
| Личные настройки | ученик, преподаватель | [1440](../../output/playwright/ui-round-19/after/profile-edit-student-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/profile-edit-student-phone-390.png) |
| Магазин | ученик, преподаватель | [1440](../../output/playwright/ui-round-19/after/market-student-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/market-student-phone-390.png) |
| Добавление товара | администратор | [1440](../../output/playwright/ui-round-19/after/market-create-admin-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/market-create-admin-phone-390.png) |
| Редактирование товара | администратор | [1440](../../output/playwright/ui-round-19/after/market-edit-admin-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/market-edit-admin-phone-390.png) |
| Заказы | преподаватель | [1440](../../output/playwright/ui-round-19/after/orders-teacher-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/orders-teacher-phone-390.png) |
| Массовое начисление GC | администратор | [1440](../../output/playwright/ui-round-19/after/credit-admin-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/credit-admin-phone-390.png) |
| Настройки ИИ | администратор | [1440](../../output/playwright/ui-round-19/after/settings-ai-admin-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/settings-ai-admin-phone-390.png) |
| Цены и акции | администратор | [1440](../../output/playwright/ui-round-19/after/settings-store-admin-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/settings-store-admin-phone-390.png) |
| Настройки Telegram | администратор | [1440](../../output/playwright/ui-round-19/after/settings-telegram-admin-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/settings-telegram-admin-phone-390.png) |
| Редактор комнаты | администратор | [1440](../../output/playwright/ui-round-19/after/room-debug-admin-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/room-debug-admin-phone-390.png) |
| Создание задания | преподаватель | [1440](../../output/playwright/ui-round-19/after/task-create-teacher-desktop-1440.png) | [390](../../output/playwright/ui-round-19/after/task-create-teacher-phone-390.png) |
