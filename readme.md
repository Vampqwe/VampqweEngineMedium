# VampqweEngineMedium

Модульное PHP-ядро (мини-CMS) для веб-приложения: DI-контейнер с сервис-провайдерами, безопасная конфигурация, шаблонизатор, HTTP-роутер и база данных. Без внешних Composer-зависимостей — всё ядро написано вручную.

## Политика драйверов БД: только MySQL/MariaDB

Движок работает **только с MySQL/MariaDB**. Это жёсткая политика проекта, реализуемая на уровне кода:

- `DataBaseDriver::fromString()` для значений `sqlite`/`pgsql` бросает `DataBaseException::inactiveDriver()` — боевое подключение к неактивному драйверу невозможно;
- SQLite и PostgreSQL сохранены в enum как **неактивные, зарезервированные** драйверы (`@deprecated`) — для будущего расширения, не для работы;
- мастер установки (`InstallService`) выполняет DDL/DML исключительно через MySQL PDO; на финальном шаге (step5) `enforceMysqlConfigPolicy()` принудительно приводит `config.env` к политике MySQL: `DB_DRIVER=mysql`, sqlite-параметры (`DB_PATH=storage/app.sqlite` из старых шаблонов) вычищаются **до** создания маркера `storage/.installed`;
- `InstalledGuard::checkDatabaseSchema()` проверяет готовность базы только через `information_schema` (MySQL/MariaDB); любой другой драйвер трактуется как «не установлено»;
- диагностический lenient-режим (`fromString($driver, lenient: true)`) существует только для инструментов анализа и никогда не используется рантаймом.

Попытка указать `DB_DRIVER=sqlite` даёт явную ошибку вместо тихой работы на неподдерживаемом драйвере.

## Состояния движка (EngineState)

Раньше статус сводился к boolean «установлен / не установлен», из-за чего рестарт MySQL выглядел для мониторинга и пользователя как сброс установки. Теперь введено единое трёхзначное состояние (`Core\System\Application\EngineState`), которое является **источником правды** и для шлюза установки, и для health-проб, и для мастера (`InstallService::isInstalled()` делегирует в `InstalledGuard`):

| Состояние | Признаки | Поведение |
|---|---|---|
| `installed` | метка `storage/.installed` + таблицы в MySQL есть | обычный serving |
| `not_installed` | нет метки и/или нет таблиц (первый запуск) | страница «Движок не установлен» со ссылкой на `/admin/install`; API — JSON 404 с `X-Engine-State: not_installed` |
| `db_unreachable` | метка есть, но соединение с MySQL не устанавливается | страница «База временно недоступна» (HTTP 503 + `Retry-After: 15`), **без** ссылки на мастер — защита от разрушительной переустановки живого сайта; API — JSON 503 |

Ключевое исправление bootstrap: `DataBaseServiceProvider::boot()` больше не бросает исключение при недоступной БД — приложение стартует всегда, а состояние определяется шлюзом per-request. Именно поэтому раньше при первом запуске (пустая БД, нет `.installed`) пользователь получал HTTP 500 вместо страницы установки: падение происходило до регистрации `InstallGateMiddleware` и error handler'а.

## Требования

- PHP 8.1+ (проверено на 8.2), расширения: `pdo_mysql`, `dom`, `mbstring`, `openssl`
- MySQL 5.7+/MariaDB 10.3+ — единственный активный драйвер
- Composer (автозагрузка PSR-4; сторонние пакеты не требуются)
- Apache 2.4.8+ с `mod_rewrite` и `mod_authz_core` (`mod_headers` опционально)
- Unix-окружение для строгой проверки прав `config.env` (`SECURITY_ENFORCE_CONFIG_PERMS`)

## Быстрый старт

1. клонировать и установить зависимости:

```bash
git clone <repo-url> VampqweEngineLight && cd VampqweEngineLight
composer install
```

2. создать локальный конфиг:

```bash
cp config.env.example config.env
chmod 640 config.env
```

В `config.env` обязательно задать:

```env
FS_BASE_PATH=/absolute/path/to/project   # корень проекта
DB_DRIVER=mysql                          # другое значение запрещено политикой
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=your_database                    # должна существовать (создаётся автоматически при правах)
DB_USER=your_db_user
DB_PASS=your_db_password
DB_CONNECT_TIMEOUT_S=5                   # таймаут подключения к MySQL, сек
INSTALL_KEY=                             # ключ доступа к мастеру установки (см. ниже)
```

3. запустить встроенный сервер:

```bash
php -S 127.0.0.1:8000 -t .
```

Открыть `http://127.0.0.1:8000/`. Если движок ещё не установлен (нет `storage/.installed` или в базе нет таблиц), приложение покажет страницу-заглушку «Движок не установлен» со ссылкой на мастер установки. Если же метка есть, но MySQL недоступен — страница «База временно недоступна» (503), а не приглашение переустанавливать сайт.

## Мастер установки (5 шагов)

Установка выполняется через админ-мастер `/admin/install` и защищён многоуровнево:

- **доступ до авторизации** — только при наличии `INSTALL_KEY` (параметр `?key=...` или заголовок `X-Install-Key`) либо активной админ-сессии; после установки POST-шаги блокируются;
- **CSRF** — все POST-шаги проходят обязательную валидацию токена;
- **rate limiting** — попытки доступа к мастеру ограничиваются;
- **шифрование** — реквизиты БД между шагами хранятся в сессии в зашифрованном виде (AES-256-CTR + HMAC), а не открытым текстом;
- **аудит** — обращения к мастеру пишутся в audit-лог.

Генерация/отзыв ключа активации:

```bash
php bin/install-key.php generate   # выводит INSTALL_KEY для config.env
php bin/install-key.php revoke     # отзывает текущий ключ
```

Шаги мастера:

1. **Проверка соединения** — тест подключения к MySQL по указанным реквизитам (с таймаутом `DB_CONNECT_TIMEOUT_S`).
2. **Структура БД** — выполнение `database/structure.sql` (чистый MySQL-дамп: InnoDB, AUTO_INCREMENT).
3. **Начальные данные** — сид страниц/меню и **создание учётной записи супер-администратора** (пароль валидируется и хэшируется bcrypt).
4. **Запись конфигурации** — обновление `config.env` (в т.ч. `DB_DRIVER=mysql`).
5. **Подтверждение** — POST-форма: сначала `enforceMysqlConfigPolicy()` вычищает из конфига унаследованные sqlite-параметры, затем создаётся маркер `storage/.installed` и сбрасывается кэш guard'а; повторные GET идемпотентны.

После успешной установки `INSTALL_KEY` рекомендуется отозвать и удалить из `config.env`.

## Структура проекта

```text
.
├── index.php                           # единственная публичная точка входа
├── .htaccess                           # front controller + запрет внутренних файлов
├── config.env.example                  # шаблон конфига (копируется в config.env)
├── config.env                          # локальный конфиг, НЕ хранится в Git
├── composer.json / composer.lock
├── readme.md              # этот документ
├── readme-migrate.md      # руководство: перенос движка на другой домен (сценарии A/B)
├── index.php
├── database/
│   └── structure.sql                   # MySQL-схема (pages, menu_items, redirects, users, ...)
├── bin/
│   └── install-key.php                 # CLI: генерация/отзыв INSTALL_KEY
├── assets/                             # публичные статические ресурсы
├── views/
│   ├── layouts/  pages/  partials/     # шаблоны сайта
│   └── admin/                          # шаблоны админки и мастера установки
├── storage/                            # кэш, маркер .installed, seed, runtime (в .gitignore)
├── logs/                               # app.log (в .gitignore)
└── core/
    ├── helpers.php                     # загрузочные хелперы
    └── classes/
        ├── system/                     # подсистемы ядра
        │   ├── Application/            # Application, InstalledGuard, EngineState, bootstrap
        │   ├── Config/                 # чтение/валидация config.env, проверка прав
        │   ├── Container/              # DI-контейнер (без автоворки)
        │   ├── DataBase/               # PDO-обёртка, DataBaseDriver (политика MySQL), исключения
        │   ├── Diagnostics/            # dev-preview панель проверок
        │   ├── Error/                  # ErrorHandler, production-safe тексты ошибок
        │   ├── FileSystem/             # пути, сканирование, base-path защита
        │   ├── Http/                   # Request/Response/Router/MiddlewareStack,
        │   │                           # InstallGateMiddleware, NotInstalledPage, DbUnavailablePage
        │   ├── Logger/                 # логгер + audit-лог
        │   ├── Map/                    # картография маршрутов/структуры
        │   ├── Security/               # CSRF, CSP, rate limiter, SqlSanitizer, шифрование
        │   ├── ServiceProvider/        # базовые классы провайдеров
        │   ├── Template/               # компилятор и кэш шаблонов
        │   ├── TimeDate/               # таймзоны, локаль
        │   └── Validator/              # валидация форм
        └── module/                     # бизнес-модули
            ├── Admin/                  # Controllers/, Middleware/, Services/ (мастер установки, импорт)
            ├── Page/                   # страницы, меню, редиректы, sitemap
            ├── Directory/
            └── File/
```

## Точки входа и маршруты

- `index.php` → `Application::bootstrap()` — конфиг, контейнер, сервис-провайдеры, роутер, install-gate, error handler; при фатальном падении bootstrap срабатывает emergency error handler (понятная 500 вместо белого экрана).
- `/api/health` — **liveness**: процесс жив? Никогда не трогает БД, отвечает 200 даже при недоступном MySQL; в теле — текущее `EngineState`.
- `/api/health/ready` — **readiness**: готов ли serving? Возвращает 200 только при состоянии `installed`; иначе 503 с машинно-читаемой причиной (`database_unavailable` | `engine_not_installed`) и заголовком `Retry-After`.
- `/admin/*` — админ-панель (AuthMiddleware → CSP → контроллеры).
- `/admin/install*` — мастер установки (InstallAccessMiddleware: ключ/сессия + CSRF + rate limit).
- прочие URL → `PageController` (страницы из БД).

`InstalledGuard` перед отдачей любого ответа проверяет: маркер `storage/.installed` + реальное наличие таблиц движка в MySQL (с таймаутом подключения). При несостыковке (маркер удалён, база пустая/другая) сайт считается неустановленным и показывает заглушку; при недоступной базе с целым маркером — 503 без приглашения переустановиться. Whitelist шлюза: `/api/health*`, `/admin/install*`.

## Конфигурация (основные ключи `config.env`)

| Ключ | Назначение |
|---|---|
| `FS_BASE_PATH` | абсолютный путь корня проекта |
| `DB_DRIVER` | всегда `mysql` (политика; sqlite/pgsql запрещены) |
| `DB_HOST/PORT/NAME/USER/PASS/CHARSET` | реквизиты MySQL/MariaDB |
| `DB_CONNECT_TIMEOUT_S` | таймаут установки соединения PDO, сек (default 5) |
| `DB_PDO_ERRMODE/FETCH_MODE/PERSISTENT` | поведение PDO |
| `DB_CHECK_ON_BOOT` | проверять соединение при bootstrap (ошибка не валит приложение) |
| `DB_ENFORCE_PREPARED` | запрет неподготовленных запросов |
| `APP_BASE_URL` / `APP_NAME` | базовый URL и имя приложения |
| `APP_DEBUG` | `true` только локально; в проде `false` |
| `DEV_PREVIEW_ENABLED` | локальная preview-панель диагностики |
| `INSTALL_KEY` | ключ доступа к мастеру установки (пусто = мастер закрыт) |
| `SECURITY_ENFORCE_CONFIG_PERMS` | требовать режим прав ≤ 0640 на config.env |

Параметр `DB_PATH` (sqlite-наследие) из `config.env` / `config.env.example` удалён; если он остался в старом конфиге, шаг 5 мастера удалит его автоматически.

## Безопасность

Реализовано:

- CSRF-токены на всех изменяющих запросах (для мастера установки ротация отключена намеренно — многошаговая POST-цепочка использует один токен);
- CSP/HSTS заголовки, отдельный CSP для админки;
- rate limiting (в т.ч. на мастер установки);
- `SqlSanitizer` + обязательные prepared statements;
- строгая проверка прав на `config.env`, защита base-path;
- шифрование конфиденциального payload в сессии установки (AES-256-CTR + HMAC, seed в `storage/.install_seed`, chmod 600);
- audit-логирование обращений к установке;
- защита от ложной переустановки: состояние `db_unreachable` никогда не предлагает мастер установки;
- production-safe сообщения об ошибках (`APP_DEBUG=false`).

Обязательно перед продакшеном:

- **секреты остались в истории Git** (старые `config.env`, `logs/app.log`, `storage/.installed` отслеживались ранее) — выполнить **ротацию** `DB_PASS` и пароля супер-админа; при необходимости очистить историю (`git filter-repo` / BFG) согласованным force-push;
- `config.env`, `storage/.installed`, `storage/.install_key`, `storage/.install_seed`, `logs/*` не должны попадать в коммиты (контролируется `.gitignore`);
- `APP_DEBUG=false`, `DEV_PREVIEW_ENABLED=false`, `INSTALL_KEY=` (пусто) или отозван;
- предпочтительный документ-рут — отдельный каталог `public/` (в текущей структуре корнем served является весь проект, защиту обеспечивает `.htaccess`).

## Apache

Корень проекта используется как document root; `.htaccess` запрещает HTTP-доступ к `config.env`, скрытым файлам, `core/`, `vendor/`, `views/`, `storage/`, `logs/`, `bin/` и перенаправляет остальные URL в `index.php`. Доступен путь `/.well-known/acme-challenge/` для HTTP-01. Пример vhost:

```apache
<Directory /path/to/VampqweEngineLight>
    AllowOverride FileInfo AuthConfig Indexes Options
    Require all granted
</Directory>
```

`php -S` игнорирует `.htaccess` — ограничения доступа проверяйте только на Apache.

## Локальная диагностика

При `APP_DEBUG=true` и `DEV_PREVIEW_ENABLED=true` с loopback-подключения доступна панель `http://127.0.0.1:8000/__dev/preview`: проверки PHP-runtime, bootstrap, соединения и схемы MySQL, шаблонизатора/CSP, прав конфигурации, CSRF и rate limiter. Проверки читают схему без изменения данных. Не публиковать в сеть.

Для внешнего мониторинга используйте два эндпоинта: liveness-проба → `/api/health` (перезапуск процесса только если он реально мёртв), readiness-проба → `/api/health/ready` (отвод трафика при проблемах с БД). Рестарт MySQL теперь вызывает 503 на readiness без перезапуска контейнеров и без «шторма» переподключений.

## Архитектурные заметки и риски

- **БД желательна, но не блокирует запуск.** Страницы, меню и редиректы живут в MySQL; при недоступной базе приложение остаётся живым и честно отвечает 503 (readiness), а не падает с 500. Деградация сервиса вместо каскадной смерти.
- **DI без автоворки.** Все зависимости регистрируются явно в сервис-провайдерах — бойлерплейт осознанный, зато отсутствие магии и предсказуемый граф.
- **Единый источник правды статуса установки.** `InstalledGuard` (+ `EngineState`) используется и шлюзом, и мастером (`InstallService::isInstalled()` делегирует guard'у) — рассинхрон «мастер думает, что установлено, а шлюз — что нет» устранён.
- **Кэш guard'а статуса установки** инвалидируется при создании/удалении маркера; при ручных правках `storage/.installed` очистите `storage/cache/`.

## Рекомендуемые следующие шаги

1. ротация раскрытых секретов и (опционально) очистка истории Git;
2. PHPUnit-тесты на bootstrap, политику драйверов, CSRF и install-flow + CI;
3. вынос front controller в `public/`;
4. явный файл `LICENSE` (текущая формулировка «типовая MIT» требует подтверждения);
5. (зарезервировано) реализация SQLite как резервного драйвера — enum-слот и DSN-ветка сохранены, активация только через изменение политики `fromString()`.

## Используемые технологии

PHP 8.1+, Composer (только автозагрузка), PDO + MySQL/MariaDB, собственный шаблонизатор и MVC-подобная структура на сервис-провайдерах.

## Перенос на другой домен

Миграция движка описана в отдельном руководстве — **[readme-migrate.md](readme-migrate.md)**:

- **Сценарий A — чистая установка:** удаление маркеров (`storage/.installed`,
  `.install_key`, `.install_seed`), нового `config.env`, пустая MySQL, мастер установки;
- **Сценарий B — перенос с контентом:** дамп/импорт БД, замена домена в полях
  `pages` / `menu_items` / `redirects`, правка `APP_BASE_URL` и `FS_BASE_PATH`,
  очистка кэша шаблонов.

Три состояния движка (`installed` / `not_installed` / `db_unreachable`) и их поведение
при переезде — там же, в разделе 0.

## Лицензия
