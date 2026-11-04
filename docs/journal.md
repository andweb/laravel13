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
- Первый `make up` оборвался на скачивании образов. Сертификат на соединении с `registry-1.docker.io` выписан на `*.allureagro.com.br`, не на Docker Hub. Образы `postgres`, `redis`, `kafka`, `clickhouse` локально так и не появились. К compose это не относится.
- С оболочки `openssl` к тому же реестру получает нормальный сертификат `*.docker.com` (Amazon). Расходятся путь демона Docker и путь обычной команды.
- `daemon.json` нет, в `docker info` нет proxy и registry mirror. `EnableUserlandProxy` к реестру не относится.
- Повторный `make up` прошёл. Живы `orders-app` (sleep), `orders-postgres` (healthy), `orders-redis`, `orders-kafka` (healthy), `orders-clickhouse` (healthy). Порт 8000 открыт, слушать его некому.
- Команда установки Laravel 13 записана в README и в план: `composer create-project` в `/tmp/laravel` внутри `app`. В git каркаса ещё нет. `/tmp` не на томе, после установки сразу переносим в корень.

## 2026-11-04 21:00

- `create-project` — только скачивание во временный каталог. Каркас приложения собираем в этом же репозитории позже, отдельный репозиторий под него не заводим.
- Скачивание прошло. Дальше перенос из `/tmp/laravel` в корень тома, без `.env` и без нашего `README.md`. Команду ещё не запускали.
- Перенос прошёл: в корне `artisan`, `app`, `vendor`. Файлы принадлежали root. После `chown` вернули `AGENTS.md`, поправили `.gitignore` (секреты и `.cursor/rules/`) и `.env.example` на pgsql без значений. Случайный sqlite-файл `test_laravel` удалён. В `.env` ещё не дописаны `DB_CONNECTION` и `APP_KEY`.
- Попытка `docker compose exec ... > .env` оборвалась: оболочка сначала обнулила `.env`, Compose не нашёл `POSTGRES_*`. Пароль в чат не попадал. Файл фактически не сменился, в нём по-прежнему три строки `POSTGRES_*`. Обход — `docker exec` в отдельный файл.
- Пароль базы хранится в `.env` в штатных переменных Laravel `DB_*`. Образ Postgres своих имён не понимает, поэтому compose при запуске копирует `DB_DATABASE`, `DB_USERNAME` и `DB_PASSWORD` в `POSTGRES_*` только внутрь контейнера базы. В файле одной копии достаточно. Пользователь привёл `.env` к этому виду.
- `docker exec -T` на этой версии Docker не принимает флаг `-T`. Ключ сгенерирован так: `docker exec orders-app php artisan key:generate`. Artisan записал `APP_KEY` в `.env`. В чат ключ не копировали.

