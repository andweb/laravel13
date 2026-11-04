# Заказы

Учебный API заказа: Laravel 13, PHP 8.5, Octane/Swoole, Postgres, Redis, Kafka, строка лога в ClickHouse. Фронта нет.

Что строим и в каком порядке — [docs/plan.md](docs/plan.md). Что уже сделано — [docs/journal.md](docs/journal.md).

## Как запускать

Нужны Docker и Make. Образ PHP лежит в `deploy/docker/dev/php`, остальные сервисы — готовые образы в `docker-compose.yml`.

```bash
make up       # собрать и поднять
make down     # остановить, тома с данными остаются
make logs     # логи
make ps       # статус
make shell    # оболочка контейнера app
```

Первый `make up` долго собирает `rdkafka`. Контейнер `app` пока только спит: Laravel ещё нет, порт 8000 наружу открыт, но слушать его некому. Octane включим отдельной командой позже.

Перед первым запуском скопируй `.env.example` в `.env` и впиши имя базы, пользователя и пароль. Без этого `make up` остановится сам: Compose не подставляет пустой пароль. Файл `.env` не коммитится.

С хоста: Postgres `localhost:5432`, Redis `localhost:6379`, Kafka `localhost:9092`, ClickHouse `http://localhost:8123`. Из контейнера `app` брокер другой: `kafka:19092`.

Artisan и Composer тоже через Make, аргумент в `cmd`:

```bash
make artisan cmd=migrate
make composer cmd=install
make test
```

`make test` заработает после установки Laravel. Тесты — штатный `php artisan test` (Pest или PHPUnit, как поставит каркас).

## Поставить Laravel

Каталог репозитория уже занят, поэтому каркас сначала собирается во временный каталог контейнера. Это ещё не файлы приложения в git: `/tmp` пропадёт, если контейнер пересоздать. После установки напиши в чат, перенесём каркас в корень и настроим.

Контейнер `app` должен быть запущен (`make up`).

```bash
docker compose exec app composer create-project laravel/laravel:^13.0 /tmp/laravel --no-interaction
```

## Ключ приложения

`APP_KEY` генерирует Artisan внутри уже запущенного контейнера и сам дописывает его в `.env`. Команда `docker exec`, без флага `-T`: у этого клиента такого флага нет.

```bash
docker exec orders-app php artisan key:generate
```

В терминале остаётся строка «Application key set successfully». Сам ключ не печатается и в чат его не копируют. Повторный запуск затирает старый ключ новым.

## Что не коммитить

`.env`, `vendor` и ключи из `storage` закрыты `.gitignore`. Пароли и токены в чат и в `docs/journal.md` не кладём. `.env.example` без значений — можно.
