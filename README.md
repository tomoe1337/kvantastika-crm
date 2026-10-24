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

Пять сценариев из задания сопоставлены с автоматическими тестами:

| № | Сценарий | Проверка |
|---|---|---|
| 1 | Дубликат `event_id` применяется один раз | `WebhookTest::test_duplicate_event_id_is_saved_and_applied_once` |
| 2 | Старая внешняя версия не перезаписывает новую | `WebhookTest::test_older_webhook_is_recorded_but_does_not_overwrite_newer_deal`, `ExternalSyncTest::test_stale_get_response_is_successfully_ignored` |
| 3 | `429, 429, 200` завершается успешной синхронизацией | `MockApiTest::test_rate_limit_mode_returns_two_429_responses_then_a_stable_success`, `ExternalSyncTest::test_two_rate_limits_then_success_updates_deal_on_third_attempt` |
| 4 | Постоянный `500` завершается конечной ошибкой после трёх попыток | `ExternalSyncTest::test_permanent_server_errors_end_in_a_terminal_failure_after_three_attempts` |
| 5 | Локальная правка сохраняется при конфликте с равной внешней версией | `WebhookTest::test_equal_version_with_different_values_preserves_local_edits`, `ExternalSyncTest::test_same_version_get_conflict_preserves_local_edits` |

## Архитектурные решения

См. [docs/architecture.md](docs/architecture.md) — границы компонентов, политика внешних версий и локальных изменений, идемпотентность webhook и статусы фоновых операций.

## Границы реализации

Это демонстрационное приложение для тестового задания, не production-интеграция. Docker Compose использует локальные учётные данные; для других сред задавайте свои секреты и настройки отдельно.
