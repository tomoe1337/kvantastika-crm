# CRM сделок

Учебный проект CRM с ручным редактированием сделок, приёмом webhook и имитацией внешней CRM.

## Стек

- PHP 8.4, Laravel 12 (`backend/composer.lock` закрепляет фактически установленные зависимости).
- Node.js 22, React 19, TypeScript 5.9, Vite 7 (`frontend/package-lock.json` закрепляет фактически установленные зависимости).
- PostgreSQL 16 и Redis 7.
- Docker Compose для локальной разработки.

## Локальный запуск

Требуются Docker Engine и Docker Compose.

```bash
cp backend/.env.example backend/.env
docker compose build
docker compose run --rm --no-deps app php artisan key:generate
docker compose up
```

Laravel применяет миграции при запуске контейнера `app`. После запуска:

- frontend: <http://localhost:5173>
- Laravel API: <http://localhost:8000>
- PostgreSQL и Redis доступны приложениям внутри Docker-сети Compose.

Загрузить воспроизводимую демонстрационную сделку:

```bash
docker compose exec app php artisan db:seed --force
```

Остановить сервисы: `docker compose down`. Данные PostgreSQL хранятся в именованном volume и переживают остановку контейнеров. Для полного сброса локальной базы используйте `docker compose down -v`.

Vite проксирует `/api` и `/mock-api` к Laravel-контейнеру, поэтому браузер использует один origin и локально не требует настройки CORS.

## Реализованные сценарии

### Сделки и webhook

- `GET /api/deals?q=...&status=...&page=...&per_page=...` — поиск по названию или внешнему ID, фильтр и пагинация.
- `POST /api/deals`, `GET /api/deals/{id}`, `PATCH /api/deals/{id}` — ручное создание, просмотр и редактирование.
- `POST /api/deals/{id}/refresh` — фоновая синхронизация. Принимает необязательный JSON `{"mode":"success|rate_limit|server_error"}`.
- `POST /api/webhooks/deals` — приём webhook с заголовком `X-Webhook-Token`. Секрет задаётся в `WEBHOOK_SECRET`.
- `php artisan operations:requeue-pending` — повторная постановка сохранённых, но не обработанных событий/операций и восстановление просроченных задач.

Версия внешней системы является порядком применения данных. Более старая версия игнорируется; ручная правка сохраняется при совпадающей версии; данные применяются только при получении новой версии. Детали и статусы описаны в [архитектурных решениях](docs/architecture.md).

### Повторяемые режимы mock CRM

Демо-снимок сделки `5678` создаётся сидером и начинает с версии 18.

```bash
# Успешный ответ
curl "http://localhost:8000/mock-api/deals/5678?mode=success"

# Сброс счётчика, затем два 429 и 200
curl -X POST "http://localhost:8000/mock-api/deals/5678/reset"
curl -i "http://localhost:8000/mock-api/deals/5678?mode=rate_limit"
curl -i "http://localhost:8000/mock-api/deals/5678?mode=rate_limit"
curl -i "http://localhost:8000/mock-api/deals/5678?mode=rate_limit"

# Постоянный 500
curl -i "http://localhost:8000/mock-api/deals/5678?mode=server_error"
```

Для задания другой версии внешних данных используйте `PUT /mock-api/deals/{id}` с JSON-полями `version`, `updated_at`, `title`, `status`, `amount`. Интерфейс позволяет запускать все три режима из карточки сделки.

## Проверки

```bash
docker compose exec app php artisan test
docker compose exec frontend npm run build
```

## Архитектурные решения

См. [docs/architecture.md](docs/architecture.md) — границы компонентов, политика внешних версий и локальных изменений, идемпотентность webhook и статусы фоновых операций.

## Границы реализации

Это демонстрационное приложение для тестового задания, не production-интеграция. Docker Compose использует локальные учётные данные; для других сред задавайте свои секреты и настройки отдельно.
