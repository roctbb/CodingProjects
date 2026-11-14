# Изолированный стенд Coding Projects + GeekExam + GeekExecutor

Стенд проверяет реальный обмен трёх приложений, не использует боевые БД,
очереди, S3, ключи или платного AI-провайдера. Запускать из Coding Projects.
Все адреса привязаны к `127.0.0.1`, каждый запуск создаёт временные SQLite,
отдельный Redis и приватный S3 bucket в одноразовом Docker-контейнере.
Для GeekExecutor поднимаются два процесса Celery prefork; Laravel и GeekExam
получают отдельные workers. Подмена авторизации действует только во входной
точке стенда при `EXECUTOR_QA_MODE=1`, не в маршрутах приложения.

## Подготовка и запуск

Нужны установленные зависимости Coding Projects (`composer install`,
`npm install`, `npm run build`), PHP с SQLite, `redis-server`, Python с
зависимостями GeekExecutor, а также `eventlet`, `openpyxl` и Socket.IO-клиент
с WebSocket. Например, в отдельном Python-окружении:

```bash
python3 -m pip install -r /path/to/GeekExecutor/requirements.txt eventlet openpyxl 'python-socketio[client]'
```

GeekExam и GeekExecutor должны быть доступны как локальные checkout.
Docker daemon должен быть запущен. Явно выбрать тестовый context; скрипт
проверяет его через `docker --context … info`. Общие настройки Docker не меняет.
Используются образы `python:3.11`, `gcc:latest`, `dxflrs/garage:v2.5.0`.
Предварительная загрузка позволяет отделить сетевые ошибки registry от проверки:

```bash
docker --context YOUR_QA_CONTEXT pull python:3.11
docker --context YOUR_QA_CONTEXT pull gcc:latest
docker --context YOUR_QA_CONTEXT pull dxflrs/garage:v2.5.0
python3 scripts/integration/executor_smoke.py \
  --executor-dir /path/to/GeekExecutor \
  --exam-dir /path/to/GeekExam \
  --docker-context YOUR_QA_CONTEXT
```

На macOS проверено с отдельным профилем Colima:

```bash
colima start --profile codex-executor-qa --cpu 4 --memory 6 --disk 24 \
  --vm-type vz --mount-type virtiofs --activate=false
```

Его context — `colima-codex-executor-qa`. Передача файлов в контейнеры идёт
через `DOCKER_TRANSFER_MODE=cp`, без требования совпадающих путей хоста и VM.
S3 настроен как single-node Garage по [официальному quick start](https://garagehq.deuxfleurs.fr/documentation/quick-start/).
Production-конфигурация и `.env` проектов не переписываются.

## Проверяемые сценарии

| Сценарий | Проверяемый результат |
|---|---|
| Загрузка Python в CP и GeekExam одновременно | Реальные контейнеры; интервалы `testing` перекрываются на двух процессах Celery |
| Прямая подписка браузерного протокола | Только WebSocket, фазы и прогресс; переподключение возвращает состояние без HTTP polling |
| Группы тестов, частичный балл | CP получает 10/20, GeekExam — 3 балла; скрытые поля отсутствуют в ответах ученика |
| Покупка скрытого теста | Списывается 5 GC, повторная покупка бесплатна, данные становятся доступны владельцу |
| C++ из нескольких исходников | Событие `compiling`, успешный запуск и 20/20 |
| Повтор доставки | Первая доставка результата намеренно получает 503; реальный Celery повторяет её |
| Повтор подписанного callback | Число решений, транзакций и уведомлений о наградах не меняется |
| Оригинал материала в S3 | Точные бинарные байты; подписанная ссылка на 300 секунд; анонимный доступ запрещён |
| Замена материала и перезапуск S3 | Ранее сданное решение скачивает первоначальный оригинал |
| Изображение, Excel, notebook | Реальное извлечение материалов; локальный AI endpoint получает изображение, содержимое таблицы/notebook и приватную рубрику |
| Права доступа | Чужой ученик получает 403 на решение; ученик получает 403 на статус исполнителя |
| Вход администратора | CP подтверждает роль и state; GeekExecutor создаёт сессию и отдаёт статус с concurrency=2 |
| Старый callback GeekExam | 404, сохранённая оценка не меняется |
| Outbox исполнителя | Все результаты доставлены, временный payload очищен |

AI endpoint — локальная Responses-заглушка с фиксированной оценкой. Проверяется
транспорт, извлечение и применение ответа; качество реальной AI-проверки
требует отдельного прогона с выбранной моделью.

## Артефакты и завершение

Код скрипта: `scripts/integration/executor_smoke.py` и входные точки PHP
`scripts/integration/executor/`. При успехе создаётся
`output/integration/executor/result.json`, в любом случае сохраняются логи
процессов. Другой каталог можно выбрать через `--output`. Старый result удаляется
перед запуском: отсутствие нового `status=passed` означает, что прогон не завершён.
Каталог по умолчанию исключён из Git.

Скрипт в `finally` останавливает созданные процессы и удаляет S3-контейнер;
временные БД и файлы удаляются. Образы и VM остаются для следующего прогона.
Если запускали отдельный профиль Colima, остановить его после работы:

```bash
colima stop codex-executor-qa
```

## Зафиксированный результат 14 ноября 2026 года

- Общий стенд: `passed`, 21,87 с; пересечение интервалов проверки CP/GeekExam — 3,63 с.
- Coding Projects: 695 пройденных PHPUnit-тестов, 5679 проверок; один отдельный
  optional S3-тест пропущен в общем наборе. Реальный S3 проверен стендом выше.
- JavaScript Coding Projects: 22 теста; сборка, styles check и UX map проходят
  (216 из 216 маршрутов размечены).
- GeekExam: 82 теста и 57 subtests, включая конкурентные проверки на отдельном
  временном PostgreSQL. Сервер PostgreSQL остановлен после прогона.
- GeekExecutor: 170 тестов и 7 subtests; отдельный Docker smoke проходит для
  Python/C++ как с одним, так и с несколькими файлами.
- В браузере проверены валидация групп, исправление ошибки без блокировки
  сохранения, сохранение и повторное открытие настроек. Ширины 1440 и 390 px,
  горизонтального переполнения на мобильной странице нет. Снимки —
  `output/playwright/teacher-settings-desktop.png` и `teacher-settings-mobile-groups.png`.

Стенд выявил ошибку переноса скомпилированного C++ в контейнер без capabilities:
`tar` пытался восстановить владельца с хоста. Исправлено извлечением с
`--no-same-owner`, без добавления привилегий контейнеру.
