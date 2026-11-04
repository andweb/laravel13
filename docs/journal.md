# Журнал

Заголовок — дата и время, округлённые до получаса: `## 2026-11-04 20:00`, затем `20:30`. Пока получасье не сменилось, новые пункты дописываются в тот же блок, новый заголовок не открываем. Секреты, пароли и токены сюда не пишем.

## 2026-11-04 20:00

- Лаборатория: API заказа, Octane/Swoole, Postgres, Redis, Kafka, лог в ClickHouse. Фронта нет.
- Живой план `docs/plan.md`, правила агента `AGENTS.md`.
- Образ PHP: `deploy/docker/dev/php/Dockerfile`, база `phpswoole/swoole:6.2-php8.5`, расширения `pdo_pgsql`, `redis`, `rdkafka`, OPcache для CLI.
- Compose будет в корне. Образ не собирался.

## 2026-11-04 20:30

- Обвязка: `.gitignore` как у Laravel, `README.md`, `Makefile` (`up`, `down`, `test`, `artisan`), `.editorconfig`, `.gitattributes`.
- Taskfile не ставим.
- `make up` без `docker-compose.yml` падает, это нормально. Следующий файл — он.
- Репозиторий инициализирует и коммитит пользователь, агент git не трогает.
- Compose: `app` (пока `sleep infinity`), Postgres 16, Redis 7, Kafka KRaft `apache/kafka:4.1.0`, ClickHouse 24.8. Таблица `app_logs` — `deploy/docker/dev/clickhouse/init.sql`, только на пустой том.
- С хоста Kafka `localhost:9092`, из контейнера `app` — `kafka:19092`. Порт 8000 открыт, слушать его некому, пока нет Octane.
- Запуск не делали. Следующее действие пользователя — `make up`.
- Пароль базы убран из compose и README. Compose берёт `POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_PASSWORD` из `.env`. Пример без значений — `.env.example`. Файл `.env` пользователь создаёт сам.
- `.env` закрыт от агента: `.cursorignore` и правило `.cursor/rules/no-secrets.mdc`. `.env.example` читается.
