# Вход через ЛК Силаэдра

Приложение подключается к `https://lk.silaeder.ru` как конфиденциальный OpenID Connect-клиент. Вход доступен пользователям с ролями `student`, `teacher` и `admin`; внешняя роль `admin` всегда преобразуется в локальную роль `teacher`.

## Настройка клиента в CRM

Создайте OIDC-клиент в ЛК Силаэдра и разрешите ему:

- роли `student`, `teacher` и `admin`;
- scopes `openid`, `profile`, `email`, `roles`.

Зарегистрируйте точные URI production-приложения:

```text
Redirect URI:
https://example.ru/auth/silaeder/callback

Post-logout Redirect URI:
https://example.ru/auth/silaeder/logout/callback
```

## Переменные окружения

```dotenv
APP_URL=https://example.ru

CRM_OIDC_ENABLED=true
CRM_OIDC_ISSUER=https://lk.silaeder.ru
CRM_OIDC_CLIENT_ID=coding-projects
CRM_OIDC_CLIENT_SECRET=secret-из-CRM
CRM_OIDC_REDIRECT_URI=https://example.ru/auth/silaeder/callback
CRM_OIDC_POST_LOGOUT_REDIRECT_URI=https://example.ru/auth/silaeder/logout/callback
```

Для подтверждения совпавших email должна быть настроена отправка почты через стандартные переменные `MAIL_*` приложения.

После изменения конфигурации выполните миграции и очистите кэш конфигурации:

```bash
php artisan migrate --force
php artisan config:clear
```

## Связывание пользователей

Внешняя учётная запись идентифицируется только парой `(issuer, sub)`. Email не используется для автоматической привязки.

- Новый email создаёт новый локальный аккаунт.
- Если локальный аккаунт с таким email уже существует, приложение отправляет на этот адрес одноразовую ссылку. Аккаунты связываются только после перехода по ней; ссылка действует 30 минут.
- Пользователь также может заранее выполнить привязку через кнопку **Привязать ЛК Силаэдра** в настройках профиля.
- Роль, имя и доступный email обновляются из `userinfo` при каждом входе.
