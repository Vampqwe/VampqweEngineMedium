<?php
declare(strict_types=1);

namespace Core\Module\Admin\Services;

use Core\System\Application\Application;
use Core\System\Application\EngineState;
use Core\System\Application\InstalledGuard;
use Core\System\Config\Config;
use PDO;
use PDOException;
use Throwable;

class InstallService
{
    /** Ключ для шифрования временного конфига БД в сессии мастера установки. */
    private const SESSION_ENC_KEY = 'vampqwe.install.dbconfig.v1';

    /** ID сессии, для которого выдан текущий CSRF-токен (привязка токена к сессии). */
    private ?string $csrfBoundSessionId = null;
    private string $csrfSeed;

    /** Файл с долгоживущим seed'ом для производного ключа шифрования конфига БД в сессии. */
    private const SEED_FILE = 'storage/.install_seed';

    private Config $config;
    private string $basePath;

    /**
     * @param string|null $basePath Корень приложения. По умолчанию вычисляется
     *        от расположения класса (как раньше). Инжектируется сервис-провайдером
     *        из конфига FS_BASE_PATH — иначе мастер читал бы storage/.installed,
     *        .install_key и .install_seed не из того корня (расхождение путей
     *        ломало guard «уже установлено» и шифрование сессии).
     */
    public function __construct(Config $config, ?string $basePath = null)
    {
        $this->config = $config;
        // Нормализация корня приложения: в config.env FS_BASE_PATH может быть
        // указан как путь к storage-каталогу (исторический формат OSPanel) —
        // приводим его к корню проекта, иначе database/, config.env и т.д.
        // искались бы внутри storage/.
        $base = rtrim($basePath ?? dirname(__DIR__, 5), '/\\');
        if (is_dir($base) && strtolower(basename($base)) === 'storage') {
            // Указан storage-каталог (исторический формат FS_BASE_PATH) — поднимаемся к корню.
            $base = dirname($base);
        } elseif (!is_dir($base) || !is_file($base . '/index.php')) {
            // Путь из конфига недостоверен (например, Windows-путь на Linux-хосте) —
            // fallback: корень проекта, вычисленный от расположения этого класса.
            $base = dirname(__DIR__, 5);
        }
        $this->basePath = $base;
        // Долгоживущий случайный seed: привязывает производный ключ
        // шифрования к данному экземпляру приложения. Seed сохраняется
        // в storage/.install_seed, чтобы оставаться стабильным между
        // запросами инициализированного PHP-FPM/Apache (иначе расшифровать
        // конфиг на шаге 2+ было бы невозможно).
        $this->csrfSeed = $this->loadOrCreateSeed();
    }

    /**
     * Загрузить (или создать при первом запуске) persistентный seed
     * для deriveSessionKey(). В fallback используется процессный random seed.
     */
    private function loadOrCreateSeed(): string
    {
        $path = $this->basePath . '/' . self::SEED_FILE;

        if (is_readable($path)) {
            $stored = trim((string) @file_get_contents($path));
            if (strlen($stored) >= 64) {
                return substr($stored, 0, 128);
            }
        }

        $seed = bin2hex(random_bytes(32));

        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        // Если записать не удалось — seed живёт только в этом процессе;
        // мастер установки всё равно работает при sticky-сессиях/одном воркере.
        @file_put_contents($path, $seed, LOCK_EX);
        @chmod($path, 0600);

        return $seed;
    }

    // ─── Ключ активации мастера установки ───

    /**
     * Возвращает действующий ключ активации мастера установки.
     *
     * Приоритет: INSTALL_KEY из config.env (для первоначальной «чистой»
     * установки), затем сохранённый хэш ключа storage/.install_key
     * (позволяет сменить ключ после установки, не ломая доступ).
     */
    public function getActivationKey(): ?string
    {
        $configured = trim((string) $this->config->get('INSTALL_KEY', ''));
        if ($configured !== '') {
            return $configured;
        }

        return null;
    }

    /**
     * Сохранить хэш нового ключа активации в storage/.install_key.
     * Используется CLI-утилитой bin/install-key.php (доверенный контекст).
     */
    public function storeActivationKeyHash(string $key): bool
    {
        $hash = hash_hmac('sha256', $key, self::SESSION_ENC_KEY);
        $path = $this->basePath . '/storage/.install_key';

        return file_put_contents($path, $hash, LOCK_EX) !== false
            && @chmod($path, 0600);
    }

    /**
     * Проверить предоставленный ключ активации по сохранённому хэшу.
     */
    public function verifyStoredActivationKey(string $providedKey): bool
    {
        $path = $this->basePath . '/storage/.install_key';
        if ($providedKey === '' || !is_readable($path)) {
            return false;
        }

        $stored = trim((string) @file_get_contents($path));
        if ($stored === '') {
            return false;
        }

        return hash_equals($stored, hash_hmac('sha256', $providedKey, self::SESSION_ENC_KEY));
    }

    // ─── Шифрованное хранение конфига БД между шагами мастера ───

    /**
     * Зашифровать конфиг БД перед помещением в сессию.
     * Пароль больше не хранится в $_SESSION в открытом виде.
     *
     * @param array<string, string> $dbConfig
     */
    public function encryptDbConfig(array $dbConfig): string
    {
        $key = $this->deriveSessionKey();
        $iv = random_bytes(16);
        $plaintext = json_encode($dbConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-ctr', $key, OPENSSL_RAW_DATA, $iv);

        if ($ciphertext === false) {
            throw new \RuntimeException('Не удалось зашифровать конфиг установки: ' . openssl_error_string());
        }

        // raw output (32 байта) — должен совпадать по формату с decryptDbConfig(),
        // где MAC извлекается как substr($decoded, 0, 32).
        $mac = hash_hmac('sha256', $iv . $ciphertext, $key, true);

        return base64_encode($mac . $iv . $ciphertext);
    }

    /**
     * Расшифровать конфиг БД из сессии (с проверкой целостности HMAC).
     *
     * @return array<string, string>|null
     */
    public function decryptDbConfig(?string $payload): ?array
    {
        if ($payload === null || $payload === '') {
            return null;
        }

        $decoded = base64_decode($payload, true);
        if ($decoded === false || strlen($decoded) < 48) {
            return null;
        }

        $key = $this->deriveSessionKey();
        $mac = substr($decoded, 0, 32);
        $rest = substr($decoded, 32);

        if (!hash_equals(hash_hmac('sha256', $rest, $key, true), $mac)) {
            return null;
        }

        $plaintext = openssl_decrypt(
            substr($rest, 16),
            'aes-256-ctr',
            $key,
            OPENSSL_RAW_DATA,
            substr($rest, 0, 16)
        );

        if ($plaintext === false) {
            return null;
        }

        $data = json_decode($plaintext, true);

        return is_array($data) ? $data : null;
    }

    /**
     * Ключ шифрования, привязанный к ID сессии: расшифровать payload
     * может только тот же браузер в той же сессии.
     */
    private function deriveSessionKey(): string
    {
        $sessionId = session_id() !== '' ? session_id() : 'no-session';

        return hash_pbkdf2(
            'sha256',
            $sessionId . '|' . self::SESSION_ENC_KEY,
            $this->csrfSeed,
            1000,
            32,
            true
        );
    }

    public function testConnection(array $config): array
    {
        // Таймаут соединения: недоступный хост должен отвечать «ошибка»,
        // а не вешать worker на системный TCP-timeout.
        $timeout = max(1, $this->config->getInt('DB_CONNECT_TIMEOUT_S', 5));

        try {
            $pdo = new PDO(
                sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4;connect_timeout=%d',
                    $config['host'] ?? '127.0.0.1',
                    $config['port'] ?? '3306',
                    $config['name'] ?? '',
                    $timeout
                ),
                $config['user'] ?? '',
                $config['pass'] ?? '',
                array_filter([
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    // Константа есть не во всех сборках pdo_mysql — таймаут
                    // гарантирован DSN-параметром connect_timeout выше.
                    (defined('PDO::MYSQL_ATTR_CONNECT_TIMEOUT') ? PDO::MYSQL_ATTR_CONNECT_TIMEOUT : 0) => $timeout,
                ], static fn($k, $v) => $k !== 0 && $v !== null, ARRAY_FILTER_USE_BOTH),
            );
            $pdo->query("SELECT 1");
            return ['success' => true, 'message' => 'Подключение успешно', 'config' => $config];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Ошибка подключения: ' . $e->getMessage()];
        }
    }

    public function createTables(array $dbConfig): array
    {
        try {
            $pdo = $this->getPdo($dbConfig);
            $sqlFile = $this->basePath . '/database/structure.sql';

            if (!file_exists($sqlFile)) {
                return ['success' => false, 'message' => 'Файл database/structure.sql не найден'
                    . ' (искали: ' . $sqlFile . '; корень приложения: ' . $this->basePath
                    . '; проверьте FS_BASE_PATH в config.env)'];
            }

            $sql = file_get_contents($sqlFile);
            $sql = preg_replace('/--.*$/m', '', $sql);
            $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

            $statements = [];
            $current = '';
            $inString = false;
            $stringChar = '';

            for ($i = 0; $i < strlen($sql); $i++) {
                $char = $sql[$i];
                $nextChar = $i + 1 < strlen($sql) ? $sql[$i + 1] : '';
                if (!$inString && ($char === '"' || $char === "'")) {
                    $inString = true;
                    $stringChar = $char;
                } elseif ($inString && $char === $stringChar && $nextChar !== '\\') {
                    $inString = false;
                }
                if ($char === ';' && !$inString) {
                    $stmt = trim($current);
                    if (!empty($stmt)) $statements[] = $stmt;
                    $current = '';
                } else {
                    $current .= $char;
                }
            }
            $stmt = trim($current);
            if (!empty($stmt)) $statements[] = $stmt;

            foreach ($statements as $statement) {
                if (empty($statement) || str_starts_with($statement, 'SET ') || str_starts_with($statement, '/*!') || str_starts_with($statement, 'START TRANSACTION') || str_starts_with($statement, 'COMMIT')) {
                    continue;
                }
                try {
                    $pdo->exec($statement);
                } catch (PDOException $e) {
                    $errorMsg = $e->getMessage();
                    if (strpos($errorMsg, 'already exists') === false && strpos($errorMsg, 'Duplicate') === false) {
                        throw $e;
                    }
                }
            }

            return ['success' => true, 'message' => 'Таблицы успешно созданы'];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Ошибка создания таблиц: ' . $e->getMessage()];
        }
    }

    public function seedInitialData(array $dbConfig): array
    {
        $pdo = null;
        try {
            $pdo = $this->getPdo($dbConfig);
            $baseUrl = rtrim($dbConfig['base_url'] ?? 'https://localhost', '/');
            $pdo->beginTransaction();

            $pdo->prepare("INSERT IGNORE INTO authors (name, slug, bio, is_active) VALUES (?, ?, ?, 1)")->execute([
                'Команда ветеринарных врачей 103vet', 'komanda-103vet',
                'Специализация: паллиативная помощь и оценка качества жизни домашних животных',
            ]);

            $pdo->prepare("INSERT IGNORE INTO pages (title, description, keywords, canonical, og_title, og_description, meta_robots, og_image, reading_time, views_count, author_id, slug, content, scripts, page_type, parent_id, status, sort_order, is_in_menu, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute([
                'Ветеринарная клиника в Минске - Достойный уход', 'Круглосуточная ветеринарная помощь в Минске.', null, $baseUrl . '/',
                'Достойный уход — ветеринарная клиника в Минске', 'Профессиональная ветеринарная помощь 24/7', 'index, follow', null, null, 0, 1, '',
                '<h1>Главная</h1><p>Добро пожаловать на сайт ветеринарной клиники "Достойный уход".</p>', null, 'home', null, 'published', 0, 1, date('Y-m-d H:i:s'),
            ]);

            $pdo->prepare("INSERT IGNORE INTO pages (title, description, keywords, canonical, og_title, og_description, meta_robots, og_image, reading_time, views_count, author_id, slug, content, scripts, page_type, parent_id, status, sort_order, is_in_menu, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute([
                '404 - Страница не найдена', 'Запрашиваемая страница не существует.', null, $baseUrl . '/404',
                '404 - Страница не найдена', 'Запрашиваемая страница не существует.', 'noindex, nofollow', null, null, 0, 1, '404',
                '<h1>Страница не найдена</h1><p>К сожалению, запрашиваемая страница не существует или была перемещена.</p>', null, '404', null, 'published', 0, 0, date('Y-m-d H:i:s'),
            ]);

            $pdo->prepare("INSERT IGNORE INTO pages (title, description, keywords, canonical, og_title, og_description, meta_robots, og_image, reading_time, views_count, author_id, slug, content, scripts, page_type, parent_id, status, sort_order, is_in_menu, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute([
                '500 - Ошибка сервера', 'Внутренняя ошибка сервера.', null, $baseUrl . '/500',
                '500 - Ошибка сервера', 'Внутренняя ошибка сервера.', 'noindex, nofollow', null, null, 0, 1, '500',
                '<h1>Ошибка сервера</h1><p>Произошла внутренняя ошибка сервера. Пожалуйста, попробуйте позже.</p>', null, '500', null, 'published', 0, 0, date('Y-m-d H:i:s'),
            ]);

            $pdo->prepare("INSERT IGNORE INTO schema_org (page_id, route, schema_type, data, priority, is_active) VALUES (?, ?, ?, ?, ?, 1)")->execute([
                null, '*', 'VeterinaryCare', json_encode(['name' => 'Достойный уход', 'description' => 'Ветеринарная клиника в Минске', 'url' => $baseUrl], JSON_UNESCAPED_UNICODE), 100,
            ]);

            $pdo->prepare("INSERT IGNORE INTO menu_items (parent_id, title, url, page_id, sort_order, is_visible, is_external, css_class, icon, note) VALUES (?, ?, ?, ?, ?, 1, 0, ?, ?, ?)")->execute([null, 'Главная', '/', 1, 10, null, null, null]);
            $pdo->prepare("INSERT IGNORE INTO menu_items (parent_id, title, url, page_id, sort_order, is_visible, is_external, css_class, icon, note) VALUES (?, ?, ?, ?, ?, 1, 0, ?, ?, ?)")->execute([null, 'Поддержка собак', '/sobaki/', null, 20, null, null, null]);
            $pdo->prepare("INSERT IGNORE INTO menu_items (parent_id, title, url, page_id, sort_order, is_visible, is_external, css_class, icon, note) VALUES (?, ?, ?, ?, ?, 1, 0, ?, ?, ?)")->execute([null, 'Поддержка кошек', '/koshki/', null, 30, null, null, null]);

            $pdo->commit();
            return ['success' => true, 'message' => 'Начальные данные успешно добавлены'];
        } catch (Throwable $e) {
            if ($pdo !== null && $pdo->inTransaction()) $pdo->rollBack();
            return ['success' => false, 'message' => 'Ошибка заполнения данных: ' . $e->getMessage()];
        }
    }

    public function updateConfig(string $key, string $value): array
    {
        try {
            $configFile = $this->basePath . '/config.env';
            $content = file_get_contents($configFile);
            $pattern = '/^' . preg_quote($key, '/') . '=.*/m';
            $replacement = $key . '=' . $value;
            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $replacement, $content);
            } else {
                $content .= "\n" . $replacement;
            }
            file_put_contents($configFile, $content);
            return ['success' => true, 'message' => 'Конфиг обновлён'];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Ошибка обновления конфига: ' . $e->getMessage()];
        }
    }

    public function markAsInstalled(array $dbConfig): void
    {
        $content = "# Файл создан автоматически при установке\n";
        $content .= "installed_at=" . date('Y-m-d H:i:s') . "\n";
        $content .= "version=1.0.0\n";
        $content .= "db_name={$dbConfig['name']}\n";
        $content .= "db_host={$dbConfig['host']}\n";

        $storageDir = $this->basePath . '/storage';
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }
        file_put_contents($storageDir . '/.installed', $content);
    }

    /**
     * Записать финальные значения в config.env (шаг 4 мастера).
     *
     * Пароли (SUPER_ADMIN_PASSWORD) пишутся ТОЛЬКО как bcrypt-хэш —
     * открытый пароль нигде не сохраняется. Пустые значения пропускаются,
     * чтобы не затирать уже существующие строки конфига.
     *
     * @param array<string, string> $values ключ => значение
     */
    public function writeConfigValues(array $values): array
    {
        try {
            $configFile = $this->basePath . '/config.env';
            if (!is_file($configFile)) {
                return ['success' => false, 'message' => 'Файл config.env не найден по пути: ' . $configFile];
            }
            if (!is_writable($configFile)) {
                return ['success' => false, 'message' => 'Нет прав на запись в config.env (' . $configFile . ')'];
            }

            $content = file_get_contents($configFile);
            if ($content === false) {
                return ['success' => false, 'message' => 'Не удалось прочитать config.env'];
            }

            foreach ($values as $key => $value) {
                $value = trim((string)$value);
                if ($value === '') {
                    continue; // пустые поля не перезаписывают конфиг
                }
                $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';
                // $replacement — callable: в bcrypt-хэшах присутствуют $-последовательности,
                // которые preg_replace интерпретировал бы как backreference и портил значение.
                $replacement = fn() => $key . '=' . $value;
                if (preg_match($pattern, $content)) {
                    $content = preg_replace_callback($pattern, $replacement, $content);
                } else {
                    $content .= "\n" . $key . '=' . $value;
                }
            }

            if (file_put_contents($configFile, $content) === false) {
                return ['success' => false, 'message' => 'Не удалось записать config.env'];
            }
            return ['success' => true, 'message' => 'Конфигурация обновлена'];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Ошибка обновления конфига: ' . $e->getMessage()];
        }
    }

    /**
     * Создать/обновить учётную запись супер-администратора (шаг 4 мастера).
     *
     * Идемпотентно: повторный запуск с тем же email обновляет хэш пароля
     * и имя, не создавая дубликат. Пароль в БД хранится только как bcrypt.
     *
     * @return array{success: bool, message: string}
     */
    public function createAdminUser(PDO $pdo, string $email, string $password, string $name): array
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Некорректный email администратора'];
        }
        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Пароль администратора должен быть не короче 8 символов'];
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        if ($hash === null || $hash === false) {
            return ['success' => false, 'message' => 'Не удалось вычислить хэш пароля'];
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users (email, password_hash, name, role, is_active)
                 VALUES (?, ?, ?, \'admin\', 1)
                 ON DUPLICATE KEY UPDATE
                     password_hash = VALUES(password_hash),
                     name = VALUES(name),
                     role = \'admin\',
                     is_active = 1'
            );
            $stmt->execute([$email, $hash, trim($name) !== '' ? trim($name) : 'Администратор']);
            return ['success' => true, 'message' => 'Учётная запись администратора готова'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Ошибка создания администратора: ' . $e->getMessage()];
        }
    }

    public function isInstalled(): bool
    {
        // Метка установки — быстрый, ДЕТЕРМИНИРОВАННЫЙ признак завершённой
        // установки. Раньше здесь делегировалось в InstalledGuard::isInstalled(),
        // который при недоступной БД возвращал true (EngineState::DbUnreachable
        // считается «установлен») — и мастер установки отвечал 403 «Установка
        // уже выполнена» даже на пустом сервере без .installed: путь к
        // первичной установке блокировался из-за временного сбоя сети/БД.
        return file_exists($this->basePath . '/storage/.installed');
    }

    /**
     * Полный признак установленности для админ-роутеров: метка + реальная
     * схема БД (делегация в единый системный InstalledGuard). Используется
     * там, где нужно отличить «установлен по-настоящему» от «метка есть,
     * но базы нет». При недоступном guard — консервативный fallback по метке.
     */
    public function isFullyInstalled(): bool
    {
        try {
            return Application::getInstance()->make(InstalledGuard::class)->isInstalled();
        } catch (Throwable) {
            return $this->isInstalled();
        }
    }

    /**
     * Текущее состояние движка для мастера установки и мониторинга.
     */
    public function engineState(): EngineState
    {
        try {
            return Application::getInstance()->make(InstalledGuard::class)->state();
        } catch (Throwable) {
            return file_exists($this->basePath . '/storage/.installed')
                ? EngineState::DbUnreachable
                : EngineState::NotInstalled;
        }
    }

    /**
     * Публичный доступ к PDO для шага 4 мастера (создание администратора).
     */
    public function getPdoForInstall(array $dbConfig): PDO
    {
        return $this->getPdo($dbConfig);
    }

    private function getPdo(array $config): PDO
    {
        // Таймаут соединения — как в testConnection(): недоступный хост
        // не должен вешать worker мастера установки.
        $timeout = max(1, $this->config->getInt('DB_CONNECT_TIMEOUT_S', 5));

        return new PDO(
            sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4;connect_timeout=%d',
                $config['host'] ?? '127.0.0.1',
                $config['port'] ?? '3306',
                $config['name'] ?? '',
                $timeout
            ),
            $config['user'] ?? '',
            $config['pass'] ?? '',
            array_filter([
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                // Константа есть не во всех сборках pdo_mysql — таймаут
                // гарантирован DSN-параметром connect_timeout выше.
                (defined('PDO::MYSQL_ATTR_CONNECT_TIMEOUT') ? PDO::MYSQL_ATTR_CONNECT_TIMEOUT : 0) => $timeout,
            ], static fn($k, $v) => $k !== 0 && $v !== null, ARRAY_FILTER_USE_BOTH),
        );
    }

    /**
     * Привести config.env к политике движка: боевая БД — только MySQL.
     *
     * Вызывается на финальном шаге установки (step5). Если в конфиге
     * остались унаследованные sqlite-параметры (DB_DRIVER=sqlite,
     * DB_PATH=storage/app.sqlite из старых шаблонов), они нейтрализуются:
     * DB_DRIVER принудительно mysql, DB_PATH удаляется. SQLite остаётся
     * лишь зарезервированным enum-драйвером для будущей реализации,
     * в рабочем контуре он быть не должен.
     */
    public function enforceMysqlConfigPolicy(): array
    {
        $configFile = $this->basePath . '/config.env';

        if (!is_file($configFile)) {
            return ['changed' => false, 'message' => 'config.env не найден — политика не применена'];
        }

        if (!is_writable($configFile)) {
            return ['changed' => false, 'message' => 'Нет прав на запись config.env — проверьте политику MySQL вручную'];
        }

        $content   = (string) file_get_contents($configFile);
        $original  = $content;
        $changed   = false;

        // 1. DB_DRIVER: любое значение, кроме mysql/mariadb → mysql.
        if (preg_match('/^DB_DRIVER=(.*)$/m', $content, $m)) {
            $driver = strtolower(trim($m[1]));
            if ($driver !== 'mysql' && $driver !== 'mariadb') {
                $content = preg_replace('/^DB_DRIVER=.*$/m', 'DB_DRIVER=mysql', $content);
                $changed = true;
            }
        } else {
            $content = "DB_DRIVER=mysql\n" . $content;
            $changed = true;
        }

        // 2. Убираем sqlite-следы из рабочего конфига.
        if (preg_match('/^DB_PATH=.*$/m', $content)) {
            $pathValue = '';
            if (preg_match('/^DB_PATH=(.*)$/m', $content, $m2)) {
                $pathValue = strtolower(trim($m2[1]));
            }
            if ($pathValue !== '' || str_contains($content, 'sqlite')) {
                $content = preg_replace('/^DB_PATH=.*\n?/m', '', $content);
                $changed = true;
            }
        }

        if (!$changed) {
            return ['changed' => false, 'message' => 'Политика MySQL уже соблюдена'];
        }

        if (file_put_contents($configFile, $content) === false) {
            return ['changed' => false, 'message' => 'Не удалось записать config.env'];
        }

        unset($original);
        return ['changed' => true, 'message' => 'config.env приведён к политике MySQL (SQLite-параметры удалены)'];
    }
}