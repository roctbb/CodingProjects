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
