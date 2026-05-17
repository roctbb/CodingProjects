# Learning Avatar Room

Комната программиста использует один актуальный runtime-набор `room-system`.
Старый прототип и промежуточные генерационные материалы не входят в репозиторий.

## Runtime-Ассеты

В продакшене нужны только:

- готовые комнаты с прозрачными вырезами: `public/images/avatar-layers/room-system/rooms`;
- погода и сезоны: `public/images/avatar-layers/room-system/weather`;
- дефолтный постер: `public/images/avatar-layers/room-system/posters/default.png`;
- предметы на стол: `public/images/avatar-layers/room-system/items`;
- питомцы: `public/images/avatar-layers/room-system/pets`;
- персонажи: `public/images/avatar-layers/room-system/characters`;
- состояния сейфа с монетами: `public/images/avatar-layers/room-system/safes`;
- описание ассетов и координаты слотов: `config.json`, `layouts.json`.

Постеры программ и кубки достижений генерируются динамически через ChatGPT
proxy и сохраняются отдельно в media-хранилище. Если у ученика нет активной
программы с постером, показывается `posters/default.png`.

## Слои

Порядок отображения:

1. погода;
2. постер программы или дефолтный постер;
3. комната;
4. сейф/монеты;
5. предмет на столе;
6. до трех динамических кубков достижений;
7. персонаж;
8. питомец.

## Проверка

```bash
rtk npm run avatar:room-system:check
```

Команда проверяет runtime-ассеты комнат, погоду, персонажей, предметы, питомцев,
сейфы и запускает focused Laravel-тесты для комнаты.
