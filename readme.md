# GeekClass
Платформа для организации онлайн-курсов

## Docker (production)
Для запуска требуется `docker` и настроенная БД (например PostgreSQL).

### Сборка production-образа
```bash
docker build -f conf/Dockerfile.prod -t codingprojects:prod .
```

### Запуск PHP-FPM контейнера
```bash
docker run --rm -p 9000:9000 --env-file .env codingprojects:prod
```

`conf/Dockerfile.prod` поднимает PHP-FPM (порт `9000`), поэтому для HTTP-доступа нужен отдельный Nginx/прокси.

### Регистрация учителя
Создайте новое поле в таблице `providers` с помощью `Adminer` и введите в поле `invite` любое значение, затем используйте его на регистрации

Также понадобится создать хотя бы один ранг в таблице `ranks`
