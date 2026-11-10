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

## 2026-11-10 10:30

- На двух заказах `EXPLAIN` показал `Seq Scan` и одну отброшенную строку. Команда: `docker compose exec -T postgres psql -U lab -d test_laravel -c "EXPLAIN (ANALYZE, BUFFERS) SELECT * FROM orders WHERE status = 'awaiting_payment' ORDER BY created_at DESC;"`.
- Пять тысяч заказов Анны и повторный план одной командой: `INSERT` из `generate_series(1, 5000)`, затем `ANALYZE orders`, затем тот же `EXPLAIN`. План тот же по форме: вся таблица, фильтр оставил 1001 строку, отдельно `Sort`. Индекса ещё не было.
- Написана миграция `orders_status_created_at_index` по `(status, created_at)`. `migrate` ещё не запускали.

## 2026-11-10 11:00

- Индекс применён командой `docker compose exec -T app php artisan migrate`. Повторный `EXPLAIN` той же командой, что утром: ушёл с `Seq Scan` на `Bitmap Index Scan` по `orders_status_created_at_index` и `Bitmap Heap Scan`. Отдельный `Sort` остался: bitmap не сохраняет порядок `created_at`. Время 0,478 мс против 0,344 мс без индекса, 8 страниц индекса читались с диска.
- Вывод шага: индекс оставляем. На 5000 горячих строках он не ускорил запрос, потому что совпадает каждая пятая и куча уже в кэше. Форма плана показывает, что фильтр ушёл в индекс. Снимать его из-за одного холодного прогона не нужно.

## 2026-11-10 12:00

- К созданию заказа добавлены кэш статуса на 60 секунд, `throttle:orders` (5 в минуту на пользователя) и повтор с заголовком `Idempotency-Key`. В `.env.example` кэш и очередь переключены на `redis`. В свой `.env` пользователь поставил `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `CACHE_PREFIX=tst_` и выполнил `docker compose restart app`.
- После просмотра заказа `1` ключ нашёлся так: `docker compose exec -T redis redis-cli -n 1 --scan --pattern '*orders:*:status'`. Имя: `laravel-database-tst_orders:1:status`. Первый прогон скана был пустым, до запроса в API. Позже `TTL` этого ключа вернул `-2`: 60 секунд уже прошли, ключ удалён. После нового просмотра заказа `1` тот же `TTL` показал 56, затем 48 и 46. Два `POST /api/orders` с `Idempotency-Key: demo-1` вернули один заказ `5003`: сначала `201`, повтор `200`. Ключ статуса этого заказа: `laravel-database-tst_orders:5003:status`, `TTL` 22. Следующие `POST` упёрлись в лимит: `429`, `Too Many Attempts.`, `ThrottleRequestsException`. Job после оплаты ещё не создавали. Через 10 минут повтор с `Idempotency-Key: demo-1` создал новый заказ `5005`.

## 2026-11-10 16:30

- Кэш статуса раньше только писался в Redis, а `GET` отвечал из Postgres. Теперь `show` пишет результат `Cache::remember` в атрибут `status` перед ресурсом. `PATCH` по-прежнему перезаписывает ключ. Воркеры ещё не перезапускали.
- Содержимое ключа смотреть так: `docker compose exec -T redis redis-cli -n 1 GET "laravel-database-tst_orders:5005:status"`. Строка в Redis сериализована PHP, число id заказа в ключе идемпотентности лежит как есть.

## 2026-11-10 17:30

- `docker compose exec -T redis redis-cli -n 1 GET "laravel-database-tst_orders:5002:status"`. Первый раз ключа не было. После просмотра заказа значение `s:5:"draft";`. После смены статуса `s:16:"awaiting_payment";`.

## 2026-11-10 18:00

- После перехода заказа в `paid` контроллер ставит в очередь Redis job `RecordOrderPaid`. Тело пишет в лог id заказа. Воркер ещё не запускали, `app` после этой правки не перезапускали.
- Очередь смотрели так: `docker compose exec -T redis redis-cli -n 0 LLEN "laravel-database-queues:default"`. До оплаты было `0`, после перехода в `paid` стало `1`. `docker compose exec -T app php artisan queue:work redis --once -v` забрал `App\Jobs\RecordOrderPaid` за 44.05ms и оставил список пустым. В логе строка `[2026-11-10 14:21:20] local.INFO: order paid {"order_id":8}`. Команда лога: `docker compose exec -T app tail -n 20 storage/logs/laravel.log | grep 8`.

## 2026-11-10 18:30

- Миграция `create_outbox_table` заполнена: `order_id`, `type`, `payload`, `published_at`. Пользователь выполнил `docker compose exec -T app php artisan migrate`.
- Смена статуса в той же транзакции пишет строку `outbox` с типом `order.status_changed`. Воркер `app` после этой правки ещё не перезапускали.

## 2026-11-10 20:30

- `docker compose exec -T postgres psql -U lab -d test_laravel -c "SELECT id, order_id, type, payload, published_at FROM outbox ORDER BY id;"`. Первый прогон был пустым, до смены статуса. После перехода заказа `13` из `awaiting_payment` в `paid` появилась строка `1`, `published_at` пустой. Релей в топик `order.events` ещё не создавали.

## 2026-11-10 22:00

- Ветка `ai-demo`. Лаборатория заказов остаётся на месте: outbox без релея в Kafka. План фичи «генерация AI-контента» — [ai-content.md](ai-content.md). Кода фичи ещё нет.

## 2026-11-10 22:30

- План [ai-content.md](ai-content.md) переписан. Вызов DeepSeek — свой `LlmManager` на `Illuminate\Support\Manager`: пакет `laravel/ai` требует Laravel 12+, у работодателя 10–11. Добавлены статус генерации и обработка сбоя, уникальные индексы против гонки, Policy на чужое заявление, ограничение дат гороскопа и throttle, сквозные feature-тесты. Кода фичи ещё нет.

## 2026-11-10 23:00

- Шаг LLM фичи AI-контента. Пользователь создал заготовки:

```bash
docker compose exec app php artisan make:interface Services/Llm/LlmDriver --no-interaction
docker compose exec app php artisan make:class Services/Llm/LlmManager --no-interaction
docker compose exec app php artisan make:class Services/Llm/DeepSeekDriver --no-interaction
docker compose exec app php artisan make:class Services/Llm/FakeDriver --no-interaction
docker compose exec app php artisan make:test --unit Llm/LlmManagerTest --no-interaction
docker compose exec app php artisan make:test --unit Llm/DeepSeekDriverTest --no-interaction
docker compose exec app php artisan make:test --unit Llm/FakeDriverTest --no-interaction
```

- Заполнены `LlmDriver`, `LlmManager`, `DeepSeekDriver`, `FakeDriver`. Менеджер — singleton в `AppServiceProvider`. Конфиг `config/ai-content.php`, в `.env.example` — `AI_CONTENT_DRIVER=fake` и пустой `DEEPSEEK_API_KEY`. В базовом `tests/TestCase.php` включён `Http::preventStrayRequests()`.
- `docker compose exec app php artisan test --compact tests/Unit/Llm` — 8 passed, 11 assertions, 0.43s.
- Заготовки данных. `make:enum Enums/AiGenerationKind` попал в `app/Enums`: каталога ещё не было, и Artisan взял путь из аргумента. `make:enum Enums/AiGenerationStatus` после этого ушёл в `app/Enums/Enums`, потому что каталог `app/Enums` уже стал пространством по умолчанию. Лишний файл убран, статус лежит в `app/Enums/AiGenerationStatus.php`.

```bash
docker compose exec app php artisan make:enum Enums/AiGenerationKind --string --no-interaction
docker compose exec app php artisan make:enum Enums/AiGenerationStatus --string --no-interaction
docker compose exec app php artisan make:model AiGeneration -m --no-interaction
docker compose exec app php artisan make:test --unit AiContent/AiGenerationKindTest --no-interaction
```

- Заполнены enum'ы, модель `AiGeneration`, миграция `ai_generations`, промпты в `resources/prompts` и unit-тест склейки заявления.
- `docker compose exec app php artisan migrate` — `2026_11_10_191942_create_ai_generations_table`, 11.55ms. `docker compose exec app php artisan test --compact tests/Unit/AiContent` — 5 passed, 13 assertions, 0.26s.
- Заготовки заявления. Пользователь выполнил `make:request AiContent/StoreStatementRequest`, `make:class Actions/AiContent/RequestStatement`, `make:controller AiContent/StatementController`, `make:resource AiGenerationResource`, `make:policy AiGenerationPolicy --model=AiGeneration`, `make:job GenerateAiContent`, `make:test AiContent/StatementTest`. Заполнены Action, контроллер, Form Request, Resource, Policy, job, `routes/ai-content.php`, подключение в `bootstrap/app.php` через `then:` и лимитер `ai-content` (5 в минуту).

## 2026-11-10 23:30

- `docker compose exec app php artisan test --compact tests/Feature/AiContent/StatementTest.php` — сначала 4 failed, 4 passed. `status` на верхнем уровне JSON был `null`: `JsonResource` оборачивает ответ в `data`, как и заказы. Из-за пустого `id` чтение чужого заявления ушло на `GET /api/v1/statements` и получило `405`, а `findOrFail` не нашёл строку. Тест переведён на `data.id` и `data.status`. Повторный прогон: 8 passed, 48 assertions, 0.45s.
- Заготовки гороскопа. Пользователь выполнил `make:request AiContent/ShowHoroscopeRequest`, `make:class Actions/AiContent/RequestHoroscope`, `make:controller AiContent/HoroscopeController --invokable`, `make:test AiContent/HoroscopeTest`. Заполнены Form Request, Action, контроллер и `GET /api/v1/horoscope`. В Redis на ключ `ai-content:horoscope:{date}` кладётся `id` и текст до конца текущих суток.
- `docker compose exec app php artisan test --compact tests/Feature/AiContent/HoroscopeTest.php` — сначала 5 failed, 1 passed. Повтор на ту же дату не находил строку: cast `date` пишет в SQLite `2026-11-10 00:00:00`, а `firstOrCreate` искал `2026-11-10`. Поиск заменён на `whereDate`. Следующий прогон: 5 passed, 1 failed. Дата `10.11.2026` доходила до проверки диапазона, Carbon бросал `InvalidFormatException`, ответ был `500`. У правила `date` теперь `bail`. Повторный прогон: 6 passed, 41 assertions, 0.45s.
- Пользователь поставил `dedoc/scramble` `v0.13.53` командой `docker compose exec app composer require dedoc/scramble:^0.13 --no-interaction`. На `store` и `show` добавлены атрибуты `Response` для `202`, `200` и `502`, на гороскоп — для `202` и `200`. После падения `app` на недописанном автозагрузчике контейнер подняли снова.

## 2026-11-11 00:00

- `docker compose exec app php artisan scramble:export --path=docs/openapi.json`. В файле есть `/v1/statements`, `/v1/statements/{aiGeneration}` и `/v1/horoscope`, у заявления коды `202` и `502`.
- README переписан как обзор учебного бэкенда: заказ, транзакция и outbox, Redis, интеграция с ИИ. Релей Kafka, дыра Octane и ClickHouse в тексте остаются следующими шагами. Pint и ручной вызов ещё не делали.
- В [plan.md](plan.md) чек-лист: после ClickHouse пункт OpenAPI (выгрузка уже лежит в репозитории), последним — интеграция с ИИ.
- Pint по файлам фичи: 26 файлов, одно исправление в `tests/Feature/AiContent/StatementTest.php` — импорт `TestResponse` вместо полного имени в docblock.
- Ручной вызов заявления. Токен через tinker для `andrey@example.com`, в журнал значение не кладём. `POST /api/v1/statements` с `Idempotency-Key: demo-1` вернул `202`, `Retry-After: 2`, лимит `5`, осталось `4`. В теле `{"data":{"id":2,"status":"pending","text":null}}`, заголовок `Location` при этом указывает на `/api/v1/statements/1`. Читать нужно `/api/v1/statements/2`. Ответов `GET` заявления и гороскопа в выводе не было.

```bash
docker compose exec -T app php artisan tinker --execute 'echo App\Models\User::query()->where("email", "andrey@example.com")->firstOrFail()->createToken("ai-demo")->plainTextToken;'
curl -sS -D - -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" -H "Idempotency-Key: demo-1" -d '{"problem":"На кухне прорвало трубу, вода течёт второй час."}' http://localhost:8000/api/v1/statements
curl -sS -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" http://localhost:8000/api/v1/statements/1
curl -sS -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" "http://localhost:8000/api/v1/horoscope?date=2026-11-11"
```

- Воркер очереди уже был запущен: в `20:13:01` он добил старый `RecordOrderPaid` за 97.75ms. Сразу после `POST` заявления, в `20:16:05`, два `GenerateAiContent` завершились за 6.85ms и 2.50ms. Сеть до DeepSeek за такое время не ходит, воркер взял драйвер `fake`. Второй job — гороскоп из третьего `curl`. Тексты уже должны быть в `GET /api/v1/statements/2` и в `GET /api/v1/horoscope?date=2026-11-11`. Живой DeepSeek этот процесс не подхватит: он держит конфиг с запуска, его нужно остановить и поднять заново после `AI_CONTENT_DRIVER=deepseek`.
- Ручные кейсы API вынесены в [api-check.md](api-check.md), со ссылкой на README. В README оставлены команды, примеры дальше гоняем по одному. В примере токена адрес заменён на `andrey@example.com`.

## 2026-11-11 00:30

- Промпт заявления заменён: модель ставит степень проблемы от 1 до 5, первая строка «Степень проблемы: N», дальше только тело. `statement/header.md` и `statement/footer.md` удалены. `compose()` сохраняет текст модели как есть. Ручные кейсы в [api-check.md](api-check.md) пользователь сбросил, следующий `POST` пишем заново.
- Промпт гороскопа собран заново: тон астролога-психолога для канала и прежний каркас. Первая строка — «Гороскоп на» с датой из запроса, дальше знаки от Овна до Рыб, у каждого свой эмодзи и два предложения. О себе модель не пишет. Файл читается при каждом job, перезапуск воркера для этой правки не нужен.
- `docker compose exec app php artisan test --compact tests/Unit/AiContent tests/Feature/AiContent/StatementTest.php` — 13 passed, 56 assertions, 0.45s.


