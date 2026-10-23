# CRM сделок

Учебный проект CRM с ручным редактированием сделок и имитацией внешней CRM.
Сейчас подготовлены каркасы backend и frontend, архитектурные решения и локальная Docker-инфраструктура.

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

Остановить сервисы: `docker compose down`. Данные PostgreSQL хранятся в именованном volume и переживают остановку контейнеров. Для полного сброса локальной базы используйте `docker compose down -v`.

Vite проксирует `/api` и `/mock-api` к Laravel-контейнеру, поэтому браузер использует один origin и локально не требует настройки CORS.

## Архитектурные решения

См. [docs/architecture.md](docs/architecture.md) — границы компонентов, политика внешних версий и локальных изменений, идемпотентность webhook и статусы фоновых операций.

## Состояние реализации

На текущем этапе созданы каркасы приложений и инфраструктура. Функциональность CRM, webhook, mock API и проверки бизнес-сценариев будет добавлена отдельными этапами.
