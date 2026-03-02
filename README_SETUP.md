# 🚀 Laravel Theme Moderation System - Полная инструкция по запуску

## 📋 Системные требования
- Docker & Docker Compose
- Git
- SQLite (используется вместо PostgreSQL для упрощения)

## 🛠️ Полная инструкция по запуску

### 1. Клонирование репозитория и сборка
```bash
# Перейти в папку проекта
cd /Users/stephan/Downloads/CodingProjects

# Собрать Docker образ
docker build -t codingprojects -f conf/Dockerfile.dev .
```

### 2. Создание и запуск контейнера с сохранением базы данных
```bash
# Создать volume для постоянного хранения базы данных
docker volume create codingprojects-db

# Запустить контейнер с volume
docker run -d \
  --name coding-dev-server \
  -v codingprojects-db:/var/www/html/database \
  -v $(pwd):/var/www/html \
  -p 8001:8000 \
  codingprojects
```

### 3. Настройка Laravel приложения
```bash
# Зайти в контейнер
docker exec -it coding-dev-server bash

# Установить права на директории
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache

# Сгенерировать ключ приложения
php artisan key:generate

# Выполнить миграции
php artisan migrate

# Создать базовые ранги (если нужно)
php artisan db:seed --class=RanksSeeder

# Очистить кеш
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
php artisan route:cache

# Выйти из контейнера
exit
```

### 4. Запуск Laravel сервера
```bash
# Запустить сервер в фоне
docker exec -d coding-dev-server php artisan serve --host=0.0.0.0 --port=8000
```

### 5. Создание пользователей для тестирования
```bash
# Создать админа
docker exec coding-dev-server php -r "
require_once 'bootstrap/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

\$admin = new App\User();
\$admin->name = 'Admin';
\$admin->email = 'admin@example.com';
\$admin->password = bcrypt('password');
\$admin->role = 'admin';
\$admin->email_verified_at = now();
\$admin->save();

for(\$i = 1; \$i <= 10; \$i++) {
    \$user = new App\User();
    \$user->name = 'user' . \$i;
    \$user->email = 'user' . \$i . '@example.com';
    \$user->password = bcrypt('password');
    \$user->role = 'student';
    \$user->email_verified_at = now();
    \$user->save();
}

echo 'Пользователи созданы!' . PHP_EOL;
echo 'Админ: admin@example.com / password' . PHP_EOL;
echo 'Пользователи: user1-user10@example.com / password' . PHP_EOL;
"
```

### 6. Назначение модератора тем (опционально)
```bash
# Назначить user1 модератором тем
docker exec coding-dev-server php -r "
require_once 'bootstrap/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

\$user = App\User::where('email', 'user1@example.com')->first();
if(\$user) {
    \$user->role = 'theme_moderator';
    \$user->save();
    echo 'user1 назначен модератором тем!' . PHP_EOL;
}
"
```

## 🌐 Доступ к приложению

### Основные URL:
- **Главная страница:** http://localhost:8001/
- **Регистрация/Вход:** http://localhost:8001/login
- **Темы оформления:** http://localhost:8001/insider/themes
- **Модерация тем:** http://localhost:8001/insider/themes/moderation (только для модераторов)

### Тестовые аккаунты:
| Роль | Email | Пароль |
|------|-------|--------|
| Admin | admin@example.com | password |
| Theme Moderator | user1@example.com | password |
| Student | user2@example.com | password |
| ... | user3-10@example.com | password |

## 🔧 Управление Docker контейнером

### Остановка контейнера:
```bash
docker stop coding-dev-server
```

### Запуск существующего контейнера:
```bash
docker start coding-dev-server
docker exec -d coding-dev-server php artisan serve --host=0.0.0.0 --port=8000
```

### Удаление контейнера (без потери данных):
```bash
docker rm coding-dev-server
```

### Пересоздание контейнера с сохранением данных:
```bash
docker run -d \
  --name coding-dev-server \
  -v codingprojects-db:/var/www/html/database \
  -v $(pwd):/var/www/html \
  -p 8001:8000 \
  codingprojects

docker exec -d coding-dev-server php artisan serve --host=0.0.0.0 --port=8000
```

## 🎨 Система модерации тем

### Роли пользователей:
- **Admin:** Полный доступ ко всем функциям
- **Theme Moderator:** Модерация тем, просмотр кода, бан/разбан пользователей
- **Teacher:** Создание и редактирование тем
- **Student:** Создание тем (если не забанен)

### Функции модерации:
1. **Просмотр тем на модерацию** (`/insider/themes/moderation`)
2. **Одобрение/Бан тем**
3. **Бан/Разбан пользователей** от создания тем
4. **Изменение решений** о бане тем
5. **Просмотр истории модерации**

### Статусы тем:
- **pending** - Ожидает модерации
- **approved** - Одобрена модератором
- **banned** - Забанена модератором

## 🐛 Устранение неполадок

### Сервер не запускается:
```bash
# Проверить статус контейнера
docker ps -a | grep coding

# Проверить логи
docker logs coding-dev-server

# Перезапустить сервер
docker exec -d coding-dev-server php artisan serve --host=0.0.0.0 --port=8000
```

### База данных повреждена:
```bash
# Удалить volume и пересоздать
docker volume rm codingprojects-db
docker volume create codingprojects-db

# Пересоздать контейнер и выполнить миграции заново
docker rm -f coding-dev-server
docker run -d --name coding-dev-server -v codingprojects-db:/var/www/html/database -v $(pwd):/var/www/html -p 8001:8000 codingprojects

docker exec -it coding-dev-server bash
php artisan migrate
exit

docker exec -d coding-dev-server php artisan serve --host=0.0.0.0 --port=8000
```

### Ошибки прав доступа:
```bash
# Исправить права в контейнере
docker exec coding-dev-server chown -R www-data:www-data /var/www/html/storage
docker exec coding-dev-server chown -R www-data:www-data /var/www/html/bootstrap/cache
```

## 📁 Структура проекта

```
├── app/                    # Исходный код Laravel
│   ├── Http/Controllers/   # Контроллеры
│   ├── User.php           # Модель пользователя
│   ├── Theme.php          # Модель темы
│   └── ...
├── database/              # Миграции и сиды
├── resources/views/       # Blade шаблоны
├── routes/web.php         # Маршруты
├── conf/                  # Docker конфигурация
└── docker-compose.yml     # (если используется)
```

---

## 🐛 Исправления после релиза

### Миграция для бана пользователей (2026-01-14)
Если возникает ошибка `SQLSTATE[HY000]: General error: 1 no such column: theme_banned`, выполните:

```bash
# Создать миграцию (если не существует)
php artisan make:migration add_theme_banned_to_users_table --table=users

# Или скопировать готовую миграцию в контейнер
docker cp database/migrations/2026_01_14_120000_add_theme_banned_to_users_table.php coding-dev-server:/var/www/html/database/migrations/

# Выполнить миграцию
docker exec coding-dev-server php artisan migrate
```

## ✨ Готово к работе!

Приложение запущено и готово к использованию. Все данные сохраняются между перезапусками благодаря Docker volume. Наслаждайтесь системой модерации тем! 🎉