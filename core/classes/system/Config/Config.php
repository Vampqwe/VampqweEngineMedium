<?php

declare(strict_types=1);

/**
 * VampqweEngineMedium — проприетарный PHP-движок
 *
 * @copyright  Copyright (c) 2024-2026. Все права защищены.
 * @author     [Дедюля Александр Иванович / VampqweEngine]
 * @license    Proprietary
 * @link       https://github.com/Vampqwe/VampqweEngine.git
 * @email	   doktor_try@mail.ru
 *
 * Данный код является интеллектуальной собственностью автора.
 * Любое копирование, распространение, модификация или использование
 * без письменного разрешения правообладателя строго запрещено.
 *
 * This software is proprietary and confidential.
 * Unauthorized copying, distribution, or use is strictly prohibited.
 */


namespace Core\System\Config;

use Core\System\FileSystem\FileSystemException;
use Core\System\FileSystem\PathProbe;
use Core\System\Security\SecurityException;

/**
 * Читает и хранит значения из config.env с проверкой безопасности
 */
final class Config
{
    /** @var array<string, string> */
    private array $values = [];

    private static ?self $instance = null;

    private function __construct(string $envPath)
    {
        if (!PathProbe::exists($envPath)) {
            throw new FileSystemException("Конфигурационный файл не найден: {$envPath}");
        }

        if (!PathProbe::isReadable($envPath)) {
            throw FileSystemException::permissionDenied($envPath);
        }

        // Проверка прав доступа (только для Unix-систем).
        // Единая политика: максимум 0640 (владелец rw, группа r), управляется
        // флагом SECURITY_ENFORCE_CONFIG_PERMS. Флаг читается прямым
        // препросмотром файла — значения ещё не распарсены.
        // SecurityManager::checkConfigPermissions() применяет то же правило
        // повторно после загрузки конфига.
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            throw new FileSystemException("Не удалось прочитать файл: {$envPath}");
        }

        if (DIRECTORY_SEPARATOR !== '\\') {
            $perms = fileperms($envPath) & 0777;

            $enforcePerms = true;

            foreach ($lines as $line) {
                if (preg_match('/^\s*SECURITY_ENFORCE_CONFIG_PERMS\s*=\s*(.+)$/', trim($line), $m)) {
                    $enforcePerms = !in_array(strtolower(trim($m[1], " \t\"'")), ['false', '0', 'no', 'off'], true);
                    break;
                }
            }

            // World-access запрещён безусловно, даже при выключенном флаге.
            if (($perms & 0007) !== 0 || ($enforcePerms && (($perms & ~0640) !== 0))) {
                throw SecurityException::configPermissionsInvalid($envPath, $perms, 0640);
            }
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if (str_starts_with($line, '#') || $line === '') {
                continue;
            }

            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);

                if ($key === '') {
                    continue;
                }

                $this->values[$key] = self::parseValue(trim($value));
            }
        }
    }

    /**
     * Нормализует значение: снимает парные кавычки и неэкранированные хвостовые комментарии.
     *
     * Поддерживаются форматы (совместимость со старыми config.env без кавычек сохранена):
     *   KEY=value            -> value
     *   KEY="value"          -> value
     *   KEY='va\'lue'        -> va'lue   (одинарные кавычки — «сырое» значение, внутренний ' экранируется \)
     *   KEY="value # note"   -> value # note (комментарий внутри кавычек игнорируется)
     *   KEY=value # note     -> value
     *   KEY="say \"hi\""     -> say "hi" (распаковка \\ \" \n \r \t только для двойных кавычек)
     */
    private static function parseValue(string $raw): string
    {
        if ($raw === '') {
            return '';
        }

        $quote = $raw[0];

        if (($quote === '"' || $quote === "'") && strlen($raw) >= 2) {
            // Найти закрывающую кавычку того же типа, не экранированную \\ и
            // (для одинарных) не удвоенную ''. Раньше сравнивался только
            // последний символ строки — значение `"a b" # note` парсилось как
            // `a b"` с хвостовой кавычкой.
            $closePos = self::findClosingQuote($raw, $quote);

            if ($closePos !== null) {
                $inner = substr($raw, 1, $closePos - 1);

                if ($quote === "'") {
                    // Одинарные кавычки: «сырое» значение; '' -> ' (SQL-удвоение), \' -> '
                    $inner = str_replace(["''", "\\'"], "'", $inner);
                    return self::stripTrailingComment(substr($raw, $closePos + 1), $inner);
                }

                // Однопроходная распаковка: \\ \" \n \r \t (порядок важен — preg обрабатывает слева направо)
                $inner = preg_replace_callback(
                    '/\\\\(["nrt\\\\])/',
                    static fn(array $m): string => match ($m[1]) {
                        'n' => "\n",
                        'r' => "\r",
                        't' => "\t",
                        default => $m[1], // " и \
                    },
                    $inner
                );
                return self::stripTrailingComment(substr($raw, $closePos + 1), $inner);
            }

            // Незакрывающаяся кавычка — трактуем как есть, только срезаем хвостовой комментарий
            $raw = substr($raw, 1);
        }

        // Неэкранированный хвостовой комментарий: пробел + # ...
        $cut = preg_match('/(?<!\\\\)\s+#.*$/', $raw, $m, PREG_OFFSET_CAPTURE);

        if ($cut === 1) {
            $raw = rtrim(substr($raw, 0, $m[0][1]));
        }

        return $raw;
    }

    /**
     * Позиция закрывающей кавычки $quote (строка начинается с открывающей)
     * или null, если пара отсутствует.
     */
    private static function findClosingQuote(string $s, string $quote): ?int
    {
        $len = strlen($s);
        for ($i = 1; $i < $len; $i++) {
            $ch = $s[$i];

            if ($ch === '\\') {
                // backslash-escape: следующий символ часть литерала
                $i++;
                continue;
            }

            if ($ch === $quote) {
                if ($quote === "'" && $i + 1 < $len && $s[$i + 1] === "'") {
                    // SQL-удвоение '' внутри '...' — остаёмся в строке
                    $i++;
                    continue;
                }
                return $i;
            }
        }

        return null;
    }

    /**
     * Обработать «хвост» после закрывающей кавычки. Хвостовой комментарий
     * (`KEY="v" # note`) отбрасывается; прочий мусор после кавычки игнорируется,
     * значение возвращается как есть.
     */
    private static function stripTrailingComment(string $tail, string $value): string
    {
        return $value;
    }

    public static function init(string $envPath): self
    {
        if (self::$instance === null) {
            self::$instance = new self($envPath);
        }

        return self::$instance;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            // Ленивая самоинициализация: позволяет системным классам
            // (SessionManager, CsrfToken и др.) обращаться к конфигу без
            // жёсткой зависимости от порядка вызовов в Application.
            // Путь по умолчанию — config.env рядом с точкой входа.
            $fallback = getcwd() . DIRECTORY_SEPARATOR . 'config.env';

            if (PathProbe::exists($fallback)) {
                return self::init($fallback);
            }

            throw new FileSystemException('Config не инициализирован. Вызовите Config::init()');
        }

        return self::$instance;
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->values[$key] ?? $default;
    }

    /**
     * Целочисленное значение конфига.
     *
     * Пустое значение или мусор (не парсящийся в число) возвращает $default —
     * раньше (int)"abc" молча давало 0, игнорируя семантику «default при отсутствии».
     */
    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key);

        if ($value === null || $value === '') {
            return $default;
        }

        // Строгое распознавание: опциональный знак + цифры (без хвостового мусора)
        if (!preg_match('/^[+-]?\d+$/', trim($value))) {
            return $default;
        }

        return (int) trim($value);
    }

    /**
     * Булево значение конфига.
     *
     * Распознаются true/1/yes/on и false/0/no/off (регистронезависимо).
     * Пустое значение или мусор возвращают $default вместо тихого false.
     */
    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        if ($value === null || $value === '') {
            return $default;
        }

        return match (strtolower(trim($value))) {
            'true', '1', 'yes', 'on'  => true,
            'false', '0', 'no', 'off' => false,
            default                   => $default,
        };
    }

    /**
     * Восьмеричное значение (права доступа), например "0755" или "755".
     * Некорректный ввод возвращает $default.
     */
    public function getOctal(string $key, int $default = 0644): int
    {
        $value = $this->get($key);

        if ($value === null || $value === '') {
            return $default;
        }

        $value = trim($value);

        if (!preg_match('/^0?[0-7]{3,4}$/', $value)) {
            return $default;
        }

        return (int) octdec($value);
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->values;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public static function reset(): void
    {
        self::$instance = null;
    }
}