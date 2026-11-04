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
- Каркас Laravel 13 в корне, его можно коммитить. Сервис `app` переводим на uid хоста (`HOST_UID`/`HOST_GID`, по умолчанию 1000), чтобы Composer не писал файлы от root. Контейнер ещё не пересоздавали, `laravel/octane` ещё не ставили.

## 2026-11-04 21:30

- Контейнер `app` пересоздан не от root. `composer require laravel/octane` прошёл, повторный `chown` не понадобился.
- `octane:install --server=swoole` прошёл. В compose команда контейнера заменена на `octane:start --host=0.0.0.0 --port=8000`. Прежняя `sleep infinity` осталась комментарием в compose и абзацем в README.
- Образ пересобран с `pcntl`. `curl` вернул `500`: Swoole жив, Postgres отвечает, таблицы `sessions` нет. Сессии в Laravel 13 по умолчанию в базе.
- `make migrate` прошёл: штатные таблицы каркаса созданы. `curl` на `http://127.0.0.1:8000` вернул `200`. Репозиторий можно публиковать: секретов в документации нет.
- Дальше Sanctum: `make artisan cmd="install:api --without-migration-prompt"`. Команду ещё не запускали.

## 2026-11-04 22:00

- `install:api --without-migration-prompt` прошёл: пакет `laravel/sanctum`, миграция `personal_access_tokens`, `routes/api.php`, api-маршруты в `bootstrap/app.php`. На `User` добавлен трейт `HasApiTokens`.
- `make migrate` прошёл. `octane:reload` в `storage/logs/swoole_http.log` отметил перезагрузку воркеров и сразу пачку SIGPIPE (broken pipe, сигнал 13) без обработчика. Контейнер `app` после этого не запущен: его главный процесс — `octane:start`, политики `restart` в compose нет. `composer require` из-за этого получил `service "app" is not running`.
- `app` поднят через `docker compose up -d app`. `spatie/laravel-model-states` ^2.14 встал. Дальше `make:model` для `Order` и `OrderLine`, команды ещё не запускали.
- Команды в чате пока пишем как `docker compose exec -T app php artisan ...`, без цели Make. Makefile остаётся.
- `make:model` для `Order` и `OrderLine` прошёл. Artisan создал пустые классы и миграции с `id` и `timestamps`. В миграции дописаны `user_id`, `status`, `total` и строки `order_id`, `name`, `quantity`. Связи: `User` hasMany `Order`, `Order` hasMany `OrderLine`. `migrate` ещё не запускали.

## 2026-11-04 22:30

- Миграции `orders` и `order_lines` прошли, таблицы видны в базе.
- В `.cursor/mcp.json` сервер `@microsoft/postgres-mcp`. Пароля в файле нет: его кладёт в связку ключей команда `connection set-password`.
- Сервер в Cursor включён, профиль `orders` находится. Первое подключение отказало: пароль пользователя `lab` не совпал. После `set-password` подключение к `test_laravel` прошло. Видны базы `postgres` и `test_laravel`, в публичной схеме есть `users`, `orders`, `order_lines`, `personal_access_tokens`.
- Дальше заготовки API: контроллер, два Form Request, ресурс, политика и сидер. Команды ещё не запускали.

## 2026-11-04 23:00

- `make:controller`, `make:resource`, `make:policy`, `make:seeder` прошли. Заполнены состояния Spatie (`draft` → `awaiting_payment` → `paid` → `shipped`, отмена из ранних, возврат из `paid` через `REFUNDS_ENABLED`), Policy владельца, создание заказа со строками, смена статуса в транзакции с `lockForUpdate`, маршруты `auth:sanctum`. Сидер пользователей Anna и Andrey ещё не запускали.
- Договор: новый ключ в `.env.example` агент сразу называет, пользователь сам дописывает его в `.env`. Для `REFUNDS_ENABLED=false` это было сказано с опозданием.
- В `.env.example` добавлен `OCTANE_SERVER=swoole`. У пользователя ключ уже был. Без него `config/octane.php` берёт `roadrunner`. В `.env` ничего менять не нужно.
- Сидер прошёл: в `users` две строки, Anna `anna@example.com` и Andrey `andrey@example.com`. Токены ещё не выпускали.

## 2026-11-04 23:30

- `POST /api/orders` из Postman вернул `500`: `authorizeResource()` в конструкторе зовёт `middleware()`, а базовый контроллер Laravel 13 этот метод не содержит. Вызов убран. `index` и `show` вызывают `authorize()` сами, `store` и `update` по-прежнему через Form Request.
- После перезапуска `app` создание заказа, список и просмотр своего заказа из Postman проходят. Незаконный переход статуса отвечает `422` и текстом `This status transition is not allowed.`
- Остановка на сегодня. Следующий шаг: `EXPLAIN` фильтра заказов по статусу, затем тысячи строк и индекс. В план записано в разделе «Где мы».


