## Posiflora Growth MVP — Telegram интеграция

Минимально рабочая версия из ТЗ:

- Backend: Symfony + PostgreSQL
- Frontend: React + TypeScript (Vite)
- Инфра: Docker + `docker-compose.yml`
- Фичи:
  - подключение Telegram-интеграции (token + chatId + enabled)
  - создание тестового заказа с фронта (без curl/Postman) с отправкой уведомления в Telegram
  - статус интеграции (enabled, masked chatId, lastSentAt, sent/failed за 7 дней)
  - идемпотентность отправки через `unique(shop_id, order_id)` в `telegram_send_log`
  - понятные статусы отправки в UI (`sent`, `failed`, `skipped`) и причина `skipped`

### Быстрый старт (backend + db в Docker)

Из корня репозитория:

```bash
docker compose up -d --build
docker compose exec -T backend php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec -T backend php bin/console app:seed
```

Backend будет доступен на `http://localhost:8000`.

### Frontend

```bash
cd frontend
npm install
npm run dev
```

Страница по ТЗ:

- `http://localhost:5173/shops/1/growth/telegram`

Vite настроен проксировать запросы `/shops/*` на `http://localhost:8000`, поэтому CORS не нужен.

### API (как в ТЗ)

- `POST /shops/{shopId}/telegram/connect`
- `POST /shops/{shopId}/orders`
- `GET /shops/{shopId}/telegram/status`

Ответ `POST /shops/{shopId}/orders` дополнительно содержит:

- `sendStatus`: `sent | failed | skipped`
- `skipReason`: `duplicate_order | integration_disabled | already_notified | null`

### Как прогнать тесты

Тесты запускаются в `APP_ENV=test` и используют SQLite (`var/test.db`) чтобы не зависеть от Postgres.

```bash
docker compose exec -T backend php bin/phpunit
```

### Реальная отправка в Telegram vs мок

По умолчанию включен мок-режим (ничего реально не отправляет).

Чтобы включить реальную отправку:

- в `docker-compose.yml` для сервиса `backend` поставьте `TELEGRAM_USE_REAL: "1"`

Отправка использует Telegram Bot API:
`POST https://api.telegram.org/bot{token}/sendMessage` с `{ chat_id, text }`.

Для проверки реального бота:

1. Создайте бота через [@BotFather](https://t.me/BotFather) и получите token.
2. Напишите боту в личку (`/start`).
3. Получите `chat_id`:

```bash
curl "https://api.telegram.org/bot<TOKEN>/deleteWebhook?drop_pending_updates=true"
curl "https://api.telegram.org/bot<TOKEN>/getUpdates"
```

4. Вставьте `botToken` + `chatId` на странице интеграции и сохраните.
5. Создайте тестовый заказ на фронте.

### Проверка на фронте

Страница `http://localhost:5173/shops/1/growth/telegram` позволяет:

- подключить/обновить Telegram-интеграцию;
- посмотреть статус и статистику отправок;
- создать тестовый заказ прямо в UI;
- увидеть итог отправки и понятные ошибки в toast-сообщениях.

Кнопка `Обновить статус` запрашивает `GET /shops/{shopId}/telegram/status` и показывает время последнего успешного обновления.

При `sendStatus=skipped` UI показывает причину:

- `duplicate_order` → заказ с таким номером уже существует
- `integration_disabled` → интеграция Telegram отключена
- `already_notified` → уведомление по этому заказу уже отправлялось

Поле `total` на фронте принимает только числовой формат (`1500` или `1500.50`), а backend дополнительно валидирует сумму и возвращает `422`, если формат неверный.

### Smoke-test за 1 минуту

1. Откройте `http://localhost:5173/shops/1/growth/telegram`, заполните `botToken`, `chatId`, включите `enabled=true`, нажмите `Сохранить`.
2. В блоке `Тестовый заказ` создайте заказ с новым `number` (например, `A-9001`) и суммой `1500`.
3. Проверьте результат:
   - в UI: toast и `sendStatus` (`sent|failed|skipped`) с причиной;
   - в Telegram: сообщение пришло (если включен real mode `TELEGRAM_USE_REAL=1`);
   - в блоке `Статус`: обновились `lastSentAt`, `sentCount/failedCount`.

### Если сообщение не пришло

Быстрый чеклист:

1. В `docker-compose.yml` для `backend` включен `TELEGRAM_USE_REAL: "1"`, и backend пересобран/перезапущен.
2. Токен валидный (`curl "https://api.telegram.org/bot<TOKEN>/getMe"` возвращает `ok: true`).
3. Боту отправлено хотя бы одно сообщение (`/start`), а `chatId` взят из `getUpdates`.
4. В интеграции сохранено `enabled=true`.
5. Для проверки используется новый `number` заказа (иначе получите `skipped` из-за дубликата).

| Симптом | Вероятная причина | Что сделать |
| --- | --- | --- |
| `sendStatus: skipped` + причина `duplicate_order` | Заказ с таким `number` уже существует | Использовать новый номер заказа |
| `sendStatus: skipped` + причина `integration_disabled` | Интеграция отключена | Включить `enabled=true` и сохранить |
| `sendStatus: failed` | Ошибка отправки в Telegram (token/chatId) | Проверить token через `getMe` и chatId через `getUpdates` |
| `sendStatus: sent`, но сообщения нет | Включен мок-режим | Поставить `TELEGRAM_USE_REAL: "1"` и перезапустить backend |
| `HTTP 422 total must be a valid number` | Неверный формат суммы | Передавать `total` как число (`1500` или `1500.50`) |
| `Сервер недоступен` на фронте | Backend не запущен/недоступен | Проверить `docker compose ps` и порт `localhost:8000` |

### Сиды

Команда `app:seed` создаёт минимум 1 магазин и 6 тестовых заказов.

### Допущения/упрощения

- Для удобной идемпотентности “повторного создания” заказа добавлено ограничение
  `unique(shop_id, number)` в таблицу `orders` (в ТЗ не было, но помогает не плодить дубли при повторном POST).
- UI намеренно простой, но с базовой UX-обработкой ошибок и статусов для удобной ручной проверки.

