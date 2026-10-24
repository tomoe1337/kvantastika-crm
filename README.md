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

Счётчик `rate_limit` автоматически сбрасывается, когда операция синхронизации в этом режиме достигает конечного статуса `succeeded` или `failed`. Поэтому сценарий `429, 429, 200` можно повторять из интерфейса без ручных команд. Ручной `POST /mock-api/deals/{id}/reset` по-прежнему доступен для прямых запросов к mock API.

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

## Бизнес-кейс

См. [docs/business-case.md](docs/business-case.md). В документе перечислены вопросы бизнесу, предложен ограниченный первый процесс назначения МВУ и описаны статусы встречи, границы данных между сервисом и Bitrix24, поведение при ошибке синхронизации и метрики пилота. Предложенные правила — гипотезы для согласования, поскольку дополнительного бизнес-контекста не предоставлено.

## Production-улучшения

Для реальной эксплуатации проекту потребуются:

1. Интеграция с Bitrix24: аутентификация, явное сопоставление полей, версионирование контракта и обработка ошибок API.
2. Надёжная исходящая синхронизация через transactional outbox, автоматическое восстановление очередей и dead-letter обработка.
3. Пользовательская аутентификация, роли и аудит ручных изменений.
4. Настраиваемые правила распределения, подключение реальных расписаний и отдельная защита от пересечения резервов.
5. Метрики и оповещения по очередям, повторным попыткам, ошибкам синхронизации и времени назначения.
6. Управление секретами и их ротация, TLS, webhook rate limiting и защита от повторного воспроизведения вне идемпотентности по `event_id`.
7. Резервное копирование PostgreSQL, проверка восстановления, CI/CD и план отката миграций.

## Использованные ИИ-инструменты и время

- **OpenCode, модель `opencode-go/gpt-6-luna`** — использовалась для планирования, реализации backend и frontend, подготовки тестов и документации. Код проверен запуском тестов и сборки.
- **Фактически затраченное время:** примерно 5 часов.

## Границы реализации

Это демонстрационное приложение для тестового задания, не production-интеграция. Docker Compose использует локальные учётные данные; для других сред задавайте свои секреты и настройки отдельно.
