# GeekClass
Платформа для организации онлайн-курсов

## Как запустить DEV
Для запуска требуется `docker` и `postgres`

### Linux
```bash
docker build -f conf/Dockerfile.dev -t codingprojects . 
docker run --rm --network=host codingprojects
```

### Windows
```bash
copy windows.env.example .env
docker run --rm -v "%cd%\.env:/var/www/html/.env" codingprojects php artisan key:generate
start-dev-windows.bat
```

## Доступ к сайту DEV
Сайт будет доступен по адресу `localhost:8000`

### Регистрация учителя
Создайте новое поле в таблице `providers` с помощью `Adminer` и введите в поле `invite` любое значение, затем используйте его на регистрации

Также понадобится создать хотя бы один ранг в таблице `ranks`

### Вход через ЛК Силаэдра

Настройка OpenID Connect, redirect URI и переменных окружения описана в [docs/silaeder-oidc.md](docs/silaeder-oidc.md).

### Запросы к ИИ

Выбор собственного шлюза или прямого OpenAI API, ключей, эндпоинтов и моделей описан в [docs/ai.md](docs/ai.md).

## Telegram уведомления в Docker
Главный администратор может настроить бота в меню профиля → «Настройки» → «Telegram» (`/insider/settings/telegram`): ключ, имя бота, HTTP(S)/SOCKS5-прокси, адрес Bot API, таймаут и секрет вебхука. Настройки сохраняются в зашифрованном виде в `telegram_settings` и применяются к следующим запросам, в том числе из очереди, без перезапуска. Сохранение формы не отправляет тестовых сообщений и не регистрирует вебхук.

Для первого развёртывания выполните миграции. До миграции и при отсутствии сохранённых настроек используются переменные `.env`:

```dotenv
TELEGRAM_BOT_TOKEN=123456:ABC...
TELEGRAM_BOT_USERNAME=your_bot_username
TELEGRAM_PROXY=socks5://user:pass@host:port
TELEGRAM_TIMEOUT=10
TELEGRAM_API_URL=https://api.telegram.org
TELEGRAM_WEBHOOK_SECRET=
```

Для секретных полей доступны действия «оставить», «заменить», «очистить» и «использовать настройку сервера». Очистка ключа отключает запросы к боту, а «без прокси» отключает в том числе прокси из окружения. Сброс Telegram не меняет настройки ИИ. Имя и ключ должны принадлежать одному боту; после смены бота пользователям потребуется открыть его и подключить Telegram заново.

Polling выполняет сервис `scheduler` через `php artisan schedule:work`, отдельный webhook не нужен. Позиция чтения обновлений хранится отдельно для каждого бота и адреса API; ротация ключа того же бота её сохраняет. Если используется webhook, секрет из формы нужно также передать при регистрации в Telegram; пустой секрет сохраняет прежний режим без проверки. Контракт запросов и ответов: [Telegram Bot API](https://core.telegram.org/bots/api).

После изменения Dockerfile пересоберите PHP-образы:

```bash
docker compose build php queue scheduler
docker compose up -d
```
