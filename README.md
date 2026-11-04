# Заказы

Учебный API заказа: Laravel 13, PHP 8.5, Octane/Swoole, Postgres, Redis, Kafka, строка лога в ClickHouse. Фронта нет.

Что строим и в каком порядке — [docs/plan.md](docs/plan.md). Что уже сделано — [docs/journal.md](docs/journal.md).

## Как запускать

Нужны Docker и Make. Compose-файл появится в корне, образ PHP лежит в `deploy/docker/dev/php`.

```bash
make up       # собрать и поднять
make down     # остановить
make logs     # логи
make ps       # статус
make shell    # оболочка контейнера app
```

Пока `docker-compose.yml` нет, `make up` завершится ошибкой. Это ожидаемо.

Artisan и Composer тоже через Make, аргумент в `cmd`:

```bash
make artisan cmd=migrate
make composer cmd=install
make test
```

`make test` заработает после установки Laravel. Тесты — штатный `php artisan test` (Pest или PHPUnit, как поставит каркас).

## Что не коммитить

`.env`, `vendor` и ключи из `storage` закрыты `.gitignore`. Пароли и токены в чат и в `docs/journal.md` не кладём. `.env.example` без значений — можно.
