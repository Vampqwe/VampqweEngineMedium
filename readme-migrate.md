# VampqweEngineLight — перенос движка на другой домен

> Руководство по миграции. Два сценария: **A — чистая установка** (контент не нужен)
> и **B — перенос с сохранением контента** (дамп БД + замена домена).
> Рабочая среда боевого движка: PHP 8.1+, MySQL/MariaDB, Apache (`.htaccess`).

---

## 0. Как движок определяет своё состояние

Перед переезмом полезно понимать три состояния (`Core\System\InstalledGuard\EngineState`):

| Состояние | Признаки | Что делает движок |
|---|---|---|
| `Installed` | есть `storage/.installed` + в БД есть таблица `settings` | работает штатно |
| `NotInstalled` | нет метки `.installed` или нет схемы БД | отдаёт заглушку «Требуется установка», блокирует `/admin/*` кроме мастера |
| `DbUnreachable` | метка есть, но MySQL недоступна | отдаёт страницу «БД временно недоступна» (503), мастер НЕ предлагается |

Метка установки — файл `storage/.installed` (JSON с датой и версией). Он **не содержит**
домена, поэтому при переезде с сохранением контента его удалять не нужно.

---

## Сценарий A — Чистая установка на новом домене

Контент старого сайта не переносится. Полный сброс состояния движка.

### A.1. Что удалить в копии проекта на новом сервере

```bash
cd /path/to/project

# маркеры и ключи мастера установки
rm -f storage/.installed
rm -f storage/.install_key
rm -f storage/.install_seed

# конфигурация окружения (пересоздадим из example)
rm -f config.env

# кэш шаблонов и логи
rm -rf storage/cache/templates/*.php
rm -f logs/app.log
```

Удалять НЕЛЬЗЯ (без них движок не заработает):

```text
index.php                  # точка входа
core/                      # ядро (PSR-4 autoloader загружает всё отсюда)
views/                     # базовые представления (layout, errors)
assets/                    # статика
database/structure.sql     # схема для мастера установки
.htaccess                  # роутинг запросов на index.php
storage/.gitkeep           # структура каталогов хранилища
```

### A.2. Новый config.env

```bash
cp config.env.example config.env
```

Отредактируйте обязательные поля:

```ini
# --- Приложение ---
APP_ENV=production
APP_DEBUG=false
APP_BASE_URL=https://новый-домен.ru          # без завершающего слэша
FS_BASE_PATH=/var/www/новый-домен             # абсолютный путь к корню проекта на НОВОМ сервере

# --- База данных (MySQL — единственный активный драйвер) ---
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vamp_engine
DB_USERNAME=vamp_user
DB_PASSWORD=СильныйПарольНовогоСервера
DB_CHARSET=utf8mb4
DB_CONNECT_TIMEOUT_S=5
DB_CHECK_ON_BOOT=true

# --- Ключ мастера установки (одноразовый, для шага 1) ---
INSTALL_KEY=СгенерированныйКлюч64Hex

# --- Секция [hashes] ---
[hashes]
PASSWORD_HASH_1=$2y$12$...bcrypt-хеш-пароля-администратора...
```

Генерация ключа и bcrypt-хэша пароля администратора:

```bash
# INSTALL_KEY (64 hex-символа)
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"

# bcrypt-хеш пароля (cost 12, как требует мастер)
php -r "echo password_hash('ВашПарольАдмина', PASSWORD_BCRYPT, ['cost' => 12]), PHP_EOL;"
```

### A.3. Пустая база данных

```sql
CREATE DATABASE vamp_engine CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'vamp_user'@'localhost' IDENTIFIED BY 'СильныйПарольНовогоСервера';
GRANT ALL PRIVILEGES ON vamp_engine.* TO 'vamp_user'@'localhost';
FLUSH PRIVILEGES;
```

### A.4. Пройдите мастер установки

```text
https://новый-домен.ru/admin/install?key=<INSTALL_KEY>
```

5 шагов: проверка ключа → параметры БД (тест соединения) → учётная запись админа
→ структура БД → финализация. Мастер сам запишет `storage/.installed`, уберёт
`INSTALL_KEY` из обращения и вычистит sqlite-следы из `config.env`
(`enforceMysqlConfigPolicy()`).

### A.5. После установки

```bash
# убедитесь, что ключ больше не нужен — закомментируйте его в config.env
sed -i 's/^INSTALL_KEY=/#INSTALL_KEY=/' config.env

chmod 640 config.env storage/.installed
```

---

## Сценарий B — Перенос с сохранением контента

### B.1. Дамп базы со старого сервера

```bash
mysqldump -u OLD_USER -p --default-character-set=utf8mb4 \
  --single-transaction --routines --triggers OLD_DB_NAME > dump.sql
```

### B.2. Импорт на новом сервере

```bash
mysql -u NEW_USER -p NEW_DB_NAME < dump.sql
```

### B.3. Замена домена в контенте

Старый домен может «жить» в этих полях таблиц (схема `database/structure.sql`):

| Таблица | Колонки с URL |
|---|---|
| `pages` | `canonical`, `og_image`, `content`, `scripts` |
| `menu_items` | `url` |
| `redirects` | `source`, `destination` |

Замените домен (обязательно в транзакции, сначала SELECT для проверки):

```sql
START TRANSACTION;

-- проверьте, сколько строк задето, перед фиксацией:
SELECT COUNT(*) FROM pages WHERE canonical LIKE '%старый-домен.ru%';

UPDATE pages
   SET canonical    = REPLACE(canonical,    'старый-домен.ru', 'новый-домен.ru'),
       og_image     = REPLACE(og_image,     'старый-домен.ru', 'новый-домен.ru'),
       content      = REPLACE(content,      'старый-домен.ru', 'новый-домен.ru'),
       scripts      = REPLACE(scripts,      'старый-домен.ru', 'новый-домен.ru');

UPDATE menu_items
   SET url = REPLACE(url, 'старый-домен.ru', 'новый-домен.ru');

UPDATE redirects
   SET destination = REPLACE(destination, 'старый-домен.ru', 'новый-домен.ru');

-- колонка redirects.source по схеме хранится БЕЗ домена (путь вида /old-page) —
-- REPLACE с доменом её не затронет, менять не требуется.

COMMIT;
```

Если в контенте встречались абсолютные пути вида `http://старый-домен.ru/...`,
учтите протокол: делайте два REPLACE (`http://` и `https://`) или используйте
`//старый-домен.ru` как подстроку поиска.

### B.4. config.env на новом сервере

Файл **не шифруется на диске**: AES-шифрование (`InstallService::encryptDbConfig()`)
применяется только при передаче конфига БД между шагами мастера внутри сессии.
Поэтому `config.env` можно перенести как есть и поправить значения:

```ini
APP_BASE_URL=https://новый-домен.ru
FS_BASE_PATH=/var/www/новый-домен        # если путь на новом сервере другой
DB_HOST=127.0.0.1
DB_DATABASE=NEW_DB_NAME
DB_USERNAME=NEW_USER
DB_PASSWORD=NEW_PASSWORD
```

ВАЖНО: `storage/.installed`, `storage/.install_key`, `storage/.install_seed`
при сценарии B НЕ удаляем — движок остаётся установленным.

### B.5. Очистка кэша и логов

```bash
rm -rf storage/cache/templates/*.php
mv logs/app.log logs/app.log.migrated   # или truncate: : > logs/app.log
```

### B.6. Проверка после переезда

```bash
# liveness — процесс жив, БД не проверяется
curl -s https://новый-домен.ru/api/health
# ожидаем HTTP 200 и state=installed

# readiness — реальная доступность MySQL и схемы
curl -s -o /dev/null -w "%{http_code}\n" https://новый-домен.ru/api/health/ready
# ожидаем 200; 503 + database_unavailable / engine_not_installed — см. readme-migrate ниже

# страница должна открыться
curl -sI https://новый-домен.ru/ | head -1
```

---

## Типичные ошибки после переезда

| Симптом | Причина | Решение |
|---|---|---|
| Заглушка «Требуется установка» | удалён `storage/.installed` (сценарий B) или нет таблицы `settings` | восстановить метку из бэкапа либо пройти мастер заново |
| Страница «БД недоступна» (503) | неверные `DB_*` в config.env / MySQL не поднята | проверить креды, `DB_HOST`, доступность сервиса |
| Белый экран 500 | не обновлён `FS_BASE_PATH`, права на `storage/`, `logs/` | исправить путь, `chmod 775 storage logs` |
| Старые ссылки ведут на старый домен | не все REPLACE выполнены | пройтись по таблице URL-полей из B.3 |
| `/admin/install` отвечает 403 | установка уже завершена — это нормально | так задумано: мастер доступен только в состоянии not_installed |

---

## Краткий чек-лист

**Сценарий A (чисто):** удалить `.installed*`, `config.env`, кэш, лог → создать БД →
новый `config.env` → мастер `/admin/install?key=...` → закомментировать `INSTALL_KEY`.

**Сценарий B (с контентом):** дамп → импорт → REPLACE домена в `pages/menu_items/redirects` →
поправить `APP_BASE_URL`, `FS_BASE_PATH`, `DB_*` → очистить кэш шаблонов →
проверить `/api/health` и `/api/health/ready`.