# Генерация AI-контента

## Зачем

Код на Laravel для вакансии: команда работает с ИИ, стек Laravel 10–11, код смотрят на GitHub. Фича живёт в ветке `ai-demo` того же приложения. Kafka и остальной учебный контур заказов на паузе, заказы не трогаем.

Что показываем ревьюеру: тонкий контроллер, Form Request, Action на ручку, Policy, Job в очереди Redis, идемпотентность без дублей при гонке, обработку сбоя модели, свою интеграцию с LLM через `Manager` и тесты без сети.

## Решения

- Раскладка — как у `artisan make`, без своих модулей. Граница фичи — подкаталог `AiContent` внутри типовых каталогов. На собесе: «проект небольшой, поэтому стандартная раскладка, на крупном разбил бы на модули».
- Репозитория нет. Повторяющиеся запросы — scope на модели.
- Пакет `laravel/ai` не берём: он требует Laravel 12+, а у работодателя 10–11. Своя интеграция на `Illuminate\Support\Manager` и фасаде `Http` переносится на 10–11 без изменений. Это же пишем в README.
- В каждом новом PHP-файле фичи `declare(strict_types=1);`. Файлы заказов не трогаем.
- Префикс `/api/v1` только у новой фичи. Маршруты заказов остаются на `/api`.

## Раскладка

```
app/Actions/AiContent/RequestStatement.php
app/Actions/AiContent/RequestHoroscope.php
app/Enums/AiGenerationKind.php           // statement | horoscope, промпт и склейка текста
app/Enums/AiGenerationStatus.php         // pending | done | failed
app/Http/Controllers/AiContent/StatementController.php
app/Http/Controllers/AiContent/HoroscopeController.php
app/Http/Requests/AiContent/StoreStatementRequest.php
app/Http/Requests/AiContent/ShowHoroscopeRequest.php
app/Http/Resources/AiGenerationResource.php
app/Jobs/GenerateAiContent.php
app/Models/AiGeneration.php
app/Policies/AiGenerationPolicy.php
app/Services/Llm/LlmDriver.php           // interface: complete(string $system, string $user): string
app/Services/Llm/LlmManager.php          // extends Illuminate\Support\Manager
app/Services/Llm/DeepSeekDriver.php
app/Services/Llm/FakeDriver.php
config/ai-content.php
resources/prompts/statement/system.md
resources/prompts/horoscope/system.md
routes/ai-content.php
tests/Unit/Llm/...
tests/Feature/AiContent/...
```

`LlmManager` регистрируется singleton в `AppServiceProvider`, там же лимитер `ai-content`. Отдельного service provider у фичи нет.

## Поток

1. Контроллер берёт данные из Form Request и вызывает Action. Своей логики в контроллере нет.
2. Action находит или создаёт строку `ai_generations`. Job ставится только если строка создана этим запросом (`wasRecentlyCreated`), через `->afterCommit()`.
3. Job `GenerateAiContent` берёт system из md через `AiGenerationKind`, собирает user-текст из `request` строки, вызывает `LlmManager`. В `response` пишет текст модели как есть и ставит `status = done`.
4. Клиент опрашивает ручку: `pending` — `202`, `done` — `200` с текстом, `failed` — `502`.

HTTP-запрос не ждёт модель 15–30 секунд. Воркер очереди запускается отдельно: `docker compose exec app php artisan queue:work redis`. Пока он не запущен, строка остаётся `pending`.

## Таблица `ai_generations`

- `user_id` — nullable, внешний ключ на `users`. У заявления владелец, у гороскопа `null`: он общий.
- `kind` — строка, cast в `AiGenerationKind`.
- `status` — строка, по умолчанию `pending`, cast в `AiGenerationStatus`.
- `for_date` — date, nullable. Только у гороскопа.
- `idempotency_key` — nullable. Только у заявления.
- `request` — json: `problem` у заявления, дата у гороскопа.
- `response` — text, nullable.
- `error` — короткая строка причины сбоя, без ключа и тела ответа провайдера.
- `timestamps`.

Уникальные индексы `(user_id, idempotency_key)` и `(kind, for_date)`. `NULL` в Postgres и SQLite не конфликтуют, поэтому заявления не мешают гороскопу и наоборот. Миграция без особенностей Postgres: тесты идут на SQLite в памяти.

Гонка двух одинаковых запросов закрыта индексом и `firstOrCreate`: в Laravel он при нарушении уникальности перечитывает строку (`createOrFirst`). Второй job не ставится.

## LLM: `Manager` и драйверы

`config/ai-content.php`:

- `driver` — `env('AI_CONTENT_DRIVER', 'fake')`. Без ключа всё работает на фиксированном тексте: кто склонирует репозиторий, запустит фичу без DeepSeek.
- `drivers.deepseek`: `key` из `DEEPSEEK_API_KEY`, `url` из `DEEPSEEK_URL` со значением по умолчанию `https://api.deepseek.com`, `model` из `DEEPSEEK_MODEL` со значением по умолчанию `deepseek-flash`, `timeout` 60 секунд, `max_tokens`.
- `timezone` — пояс, в котором считается «сегодня» для гороскопа. Не зависит от `app.timezone`.
- `horoscope.days_range` — на сколько дней назад и вперёд можно просить гороскоп.

`DeepSeekDriver` шлёт `POST {url}/chat/completions` фасадом `Http`: `withToken`, `timeout`, `retry` на соединение, `throw()` на 4xx/5xx. Текст берётся из `choices.0.message.content`. Пустой ответ — исключение, job уходит в повтор.

Тело запроса:

```json
{
    "model": "deepseek-flash",
    "messages": [
        { "role": "system", "content": "<resources/prompts/horoscope/system.md>" },
        { "role": "user", "content": "Гороскоп на 10 ноября 2026 (вторник)." }
    ],
    "thinking": { "type": "disabled" },
    "max_tokens": 2000,
    "stream": false
}
```

У `deepseek-flash` режим thinking включён по умолчанию: ответ дольше и дороже. Для этих текстов он не нужен. Поле сверить с документацией DeepSeek при ручном вызове.

`FakeDriver` возвращает фиксированный текст и не ходит в сеть.

## Job `GenerateAiContent`

- `$tries = 3`, `$backoff = [10, 30]`, `$timeout = 75`. Таймаут HTTP (60) меньше таймаута job (75), а тот меньше `retry_after` очереди Redis (90).
- Если строка уже `done`, job выходит: повторная доставка ничего не портит.
- `failed()` ставит `status = failed` и пишет в `error` короткую причину.

## Octane и воркер очереди

HTTP-сторона под Octane только пишет строку и читает кэш. Вызов модели идёт в воркере очереди. Оба процесса живут долго. `LlmManager` — singleton: он держит только конфиг и созданные драйверы, данных запроса в нём нет, поэтому утечки между запросами нет. Текст запроса приходит аргументом.

После правки `.env` нужен `docker compose restart app` для Octane и `php artisan queue:restart` для воркера.

## API v1

Префикс `/api/v1`, middleware `api`, `auth:sanctum`, `throttle:ai-content`. Ответ — `AiGenerationResource` в обёртке `data`, как у ресурсов заказов: `id`, `status`, `text`.

- `POST /api/v1/statements`. Заголовок `Idempotency-Key` обязателен. Тело `{ "problem": "..." }`, от 20 до 2000 символов.
  - Новая строка или ещё `pending` — `202`, заголовки `Location: /api/v1/statements/{id}` и `Retry-After`.
  - `done` — `200`, текст модели целиком. Первая строка — «Степень проблемы: N». Шапка и подпись в ответ не дописываются.
  - `failed` — `502`. Повтор — с новым ключом.
  - Тот же ключ с другим `problem` — `422`.
- `GET /api/v1/statements/{id}`. Те же `202`, `200`, `502`. Чужая строка — `403` через Policy.
- `GET /api/v1/horoscope?date=YYYY-MM-DD`. Дата необязательна, иначе сегодня в поясе `ai-content.timezone`. Дата вне `days_range` — `422`: иначе любой пользователь с токеном перебором дат запускает платные вызовы.
  - Готовый текст лежит в Redis под ключом `ai-content:horoscope:{date}` до конца текущих суток. Есть в кэше — `200` без запроса в базу.
  - Нет в кэше — `firstOrCreate` по `(kind, for_date)`. Строка новая — job и `202`. `pending` — `202`. `done` — кладём в кэш и `200`.
  - `failed` — атомарный `update ... where status = failed` обратно в `pending`. Если строку перевёл этот запрос, ставим job. Ответ `202`.
  - `GET` со стороны клиента идемпотентен: повтор не меняет результат, побочный эффект — только ленивая генерация.

Общие коды: `401` без токена, `429` сверх лимита.

## Промпты

- `statement/system.md` — модель оценивает проблему ЖКХ от 1 до 5 и пишет тело заявления. Первая строка ответа — «Степень проблемы: N». Шапка, слово «ЗАЯВЛЕНИЕ», ФИО, адрес и подпись в текст не входят: их не пишет ни модель, ни код.
- `horoscope/system.md` — гороскоп для канала: живая первая строка с датой из запроса, затем двенадцать знаков от Овна до Рыб. У каждого знака свой эмодзи и два предложения.

Полный текст промптов — только в этих файлах.

## Тесты

PHPUnit, как в остальном проекте. `Http::preventStrayRequests()` в базовом `TestCase`: живой запрос в тестах невозможен. Тестам с конфигом и `Http::fake()` нужен `Tests\TestCase`, а не голый PHPUnit: заглушка `make:test --unit` правится.

Unit:

- `LlmManager` берёт драйвер из конфига. Неизвестный драйвер — исключение.
- `DeepSeekDriver` под `Http::fake()`: адрес `/chat/completions`, Bearer, `model`, два сообщения, `stream: false`. Ответ 500 и пустой `content` — исключение.
- `FakeDriver` возвращает свой текст.
- `AiGenerationKind`: system читается из md. Текст модели сохраняется как есть, пустой ответ отклоняется.

Feature (`RefreshDatabase`, `Queue::fake()`: в `phpunit.xml` очередь `sync`, без фейка `POST` сразу вернёт `200`):

- Заявление: `401` без токена. `422` без `problem` и без ключа. Первый `POST` — `202`, `Location`, один job. Повтор с тем же ключом — тот же `id`, job по-прежнему один. Тот же ключ с другим текстом — `422`. Чужой `GET` — `403`. После `handle()` job с `FakeDriver` — `200`, в тексте ответ модели без шапки. После `failed()` — `502`.
- Гороскоп: первый `GET` — `202` и один job, повтор — без второго. После job — `200`, ключ в кэше. Дата вне диапазона — `422`. После `failed()` следующий `GET` ставит job заново.

## OpenAPI

`dedoc/scramble` `^0.13` (Laravel 13 поддерживается с `0.13.15`). Страница `/docs/api` собирается из маршрутов, Form Request и Resource, открыта в `local`. Коды `202` и `502` дописываются PHPDoc в контроллере, если генератор их не вывел. Для GitHub выгрузка `php artisan scramble:export --path=docs/openapi.json` лежит в репозитории: ревьюер видит контракт без запуска.

## README

Раздел фичи: что делает, как запустить без ключа (`AI_CONTENT_DRIVER=fake`) и с ключом, воркер очереди, два `curl` (заявление с `Idempotency-Key` и опрос, гороскоп), почему не `laravel/ai`, чем отличается подключение маршрутов в Laravel 10 (`RouteServiceProvider` вместо `bootstrap/app.php`).

## `.env`

В `.env.example` появятся `AI_CONTENT_DRIVER=fake` и пустой `DEEPSEEK_API_KEY=`. `DEEPSEEK_URL` и `DEEPSEEK_MODEL` туда не пишем: пустая строка из `.env` перебьёт значение по умолчанию в конфиге. Свой `.env` пользователь правит сам, значения в чат и журнал не попадают.

## Шаги

Команды `make:*` запускает пользователь, тела файлов пишет агент.

1. LLM. `make:interface Services/Llm/LlmDriver`, `make:class Services/Llm/LlmManager`, `make:class Services/Llm/DeepSeekDriver`, `make:class Services/Llm/FakeDriver`, `config/ai-content.php`, `.env.example`, unit-тесты драйверов.
2. Данные. `make:enum Enums/AiGenerationKind --string`, `make:enum Enums/AiGenerationStatus --string`, `make:model AiGeneration -m`, промпты, unit-тест `AiGenerationKind`. Пользователь запускает `migrate`.
3. Заявление. `make:request AiContent/StoreStatementRequest`, `make:class Actions/AiContent/RequestStatement`, `make:controller AiContent/StatementController`, `make:resource AiGenerationResource`, `make:policy AiGenerationPolicy --model=AiGeneration`, `make:job GenerateAiContent`, `routes/ai-content.php` и подключение в `bootstrap/app.php` через `then:`, лимитер, feature-тесты.
4. Гороскоп. `make:request AiContent/ShowHoroscopeRequest`, `make:class Actions/AiContent/RequestHoroscope`, `make:controller AiContent/HoroscopeController --invokable`, кэш, feature-тесты.
5. Scramble и выгрузка `docs/openapi.json`.
6. README, Pint по файлам фичи, один ручной вызов с ключом: заявление и гороскоп через `curl` при запущенном воркере.
7. По желанию: GitHub Actions с `php artisan test` на SQLite. Зелёный значок в README ревьюер видит сразу.
