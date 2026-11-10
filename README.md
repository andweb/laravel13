# Учебный бэкенд на Laravel

Один API без фронта: несколько типовых задач бэкенда в одном приложении. Стек — Laravel 13, PHP 8.5, Octane/Swoole, Postgres, Redis. Рядом в Docker лежат Kafka и ClickHouse. Всё, что проверяем, ходим через `curl`.

План и ход работы — [docs/plan.md](docs/plan.md) и [docs/journal.md](docs/journal.md). Пройденные ручные кейсы — [docs/api-check.md](docs/api-check.md).

## Какие задачи закрывает проект

- **Заказ.** Пользователь, строки заказа, связи Eloquent, Form Request, API Resource, Policy «только свой заказ», токен Sanctum. Отдельного экрана входа нет: двух пользователей создаёт сидер.
- **Состояния и транзакция.** Статус заказа идёт через Spatie Model States: `draft` → `awaiting_payment` → `paid` → `shipped`, из ранних статусов можно `cancelled`. Возврат выключен конфигом `REFUNDS_ENABLED`. Смена статуса — в транзакции с `lockForUpdate`. В той же транзакции пишется строка `outbox`, чтобы событие не ушло, если заказ откатился.
- **Redis.** Кэш статуса, лимит на создание, повтор с заголовком `Idempotency-Key`, очередь и job после оплаты.
- **Запрос к базе.** Список заказов сначала показывает N+1, затем грузится через `with()`. Под фильтр статуса есть составной индекс.
- **Интеграция с ИИ.** Заявление в УК и гороскоп на дату. HTTP не ждёт модель: строка создаётся сразу, текст пишет job. Пока его нет — `202`, когда записан — `200`. Вызов DeepSeek — свой менеджер драйверов на `Illuminate\Support\Manager`, без пакета `laravel/ai`: тот требует Laravel 12+. Контракт — [docs/openapi.json](docs/openapi.json), в `local` та же страница на `/docs/api`.

Дальше по плану: релей outbox в Kafka, учебный маршрут про память воркера Octane и строка лога в ClickHouse. План фичи ИИ — [docs/ai-content.md](docs/ai-content.md).

## Как запустить

Нужны Docker и Make. Образ PHP — `deploy/docker/dev/php`, остальные сервисы — готовые образы в `docker-compose.yml`.

```bash
make up       # собрать и поднять
make down     # остановить, тома с данными остаются
make logs
make ps
make shell
```

Первый `make up` долго собирает `rdkafka`. Приложение слушает `0.0.0.0:8000` через Octane/Swoole. После правки PHP код подхватывает `docker compose restart app`.

Перед первым запуском скопируй `.env.example` в `.env` и впиши имя базы, пользователя и пароль. Без этого Compose остановится: пустой пароль он не подставляет. Файл `.env` не коммитится.

С хоста: Postgres `localhost:5432`, Redis `localhost:6379`, Kafka `localhost:9092`, ClickHouse `http://localhost:8123`. Из контейнера `app` брокер другой: `kafka:19092`.

```bash
make migrate
make artisan cmd=migrate:status
make composer cmd=install
make test
```

`make migrate` запускает `php artisan migrate --force`. `make test` — `php artisan test` на SQLite в памяти.

`APP_KEY` выпускается внутри контейнера и сам дописывается в `.env`. В терминале остаётся «Application key set successfully», сам ключ не печатается. Повторный запуск затирает старый ключ.

```bash
docker exec orders-app php artisan key:generate
```

## Заказы

Ручки под `auth:sanctum`, префикс `/api`: список, создание, просмотр, смена статуса. Чужой заказ — `403`. Токен берётся так же, как для ручек ИИ ниже, и в чат не копируется.

## Генерация текста

Префикс `/api/v1`. Заявление — `POST /api/v1/statements` с заголовком `Idempotency-Key` и полем `problem` от 20 символов. Гороскоп — `GET /api/v1/horoscope`, дата необязательна. Повтор на ту же дату или с тем же ключом второй job не ставит.

Код вызова модели переносится на Laravel 10 и 11. Меняется регистрация маршрутов: здесь `routes/ai-content.php` подключён в `bootstrap/app.php` через `then:`. В Laravel 10 тот же файл подключают в `RouteServiceProvider`.

Без ключа фича отвечает фиксированным текстом. В `.env`:

```
AI_CONTENT_DRIVER=fake
DEEPSEEK_API_KEY=
```

Для живого DeepSeek поставь `AI_CONTENT_DRIVER=deepseek` и впиши ключ. Адрес и модель уже заданы в конфиге: `https://api.deepseek.com` и `deepseek-flash`. После правки `.env` перезапусти контейнер и воркер: оба держат старый конфиг до перезапуска.

```bash
docker compose restart app
docker compose exec app php artisan queue:work redis
```

Воркер занимает терминал. Токен:

```bash
docker compose exec app php artisan tinker --execute 'echo App\Models\User::query()->where("email", "andrey@example.com")->firstOrFail()->createToken("ai-demo")->plainTextToken;'
```

Примеры ниже запускай по одному. Что ответил сервер, пишется в [docs/api-check.md](docs/api-check.md), не сюда. Пока в ответе `pending`, следующий GET бери по `data.id` из тела.

```bash
curl -sS -D - -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" -H "Idempotency-Key: demo-1" -d '{"problem":"На кухне прорвало трубу, вода течёт второй час."}' http://localhost:8000/api/v1/statements
curl -sS -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" http://localhost:8000/api/v1/statements/ID
curl -sS -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" "http://localhost:8000/api/v1/horoscope?date=2026-11-11"
```

## Что не коммитить

`.env`, `vendor` и ключи из `storage` закрыты `.gitignore`. Пароли и токены в чат и в журнал не кладём. `.env.example` без значений коммитить можно.
