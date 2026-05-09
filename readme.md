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

## Telegram уведомления в Docker
Для уведомлений аукционов через Telegram заполните переменные в `.env`:

```dotenv
TELEGRAM_BOT_TOKEN=123456:ABC...
TELEGRAM_BOT_USERNAME=your_bot_username
TELEGRAM_PROXY=socks5://user:pass@host:port
TELEGRAM_TIMEOUT=10
```

Polling выполняет сервис `scheduler` через `php artisan schedule:work`, отдельный webhook не нужен. После изменения Dockerfile пересоберите PHP-образы:

```bash
docker compose build php queue scheduler
docker compose up -d
```
