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

        // Проверка прав доступа (только для Unix-систем)
        if (DIRECTORY_SEPARATOR !== '\\') {
            $perms = fileperms($envPath) & 0777;

            // Права не должны позволять чтение/запись всем
            if (($perms & 0007) !== 0) {
                throw SecurityException::configPermissionsInvalid($envPath, $perms, 0640);
            }
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            throw new FileSystemException("Не удалось прочитать файл: {$envPath}");
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
     *   KEY='va'lue'         -> va'lue   (одинарные кавычки — «сырое» значение)
     *   KEY="value # note"   -> value # note (комментарий внутри кавычек игнорируется)
     *   KEY=value # note     -> value
     *   KEY="say \"hi\""     -> say "hi" (только для двойных кавычек)
     */
    private static function parseValue(string $raw): string
    {
        if ($raw === '') {
            return '';
        }

        $quote = $raw[0];

        if (($quote === '"' || $quote === "'") && strlen($raw) >= 2) {
            $last = strlen($raw) - 1;

            // Парная закрывающая кавычка в конце строки
            if ($raw[$last] === $quote) {
                $inner = substr($raw, 1, -1);

                if ($quote === '"') {
                    // Однопроходная распаковка: \\ \" \n \r \t (порядок важен — preg обрабатывает слева направо)
                    return preg_replace_callback(
                        '/\\\\(["nrt\\\\])/',
                        static fn(array $m): string => match ($m[1]) {
                            'n' => "\n",
                            'r' => "\r",
                            't' => "\t",
                            default => $m[1], // " и \
                        },
                        $inner
                    );
                }

                return $inner; // одинарные кавычки: экранирование не применяется
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
            throw new FileSystemException('Config не инициализирован. Вызовите Config::init()');
        }

        return self::$instance;
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->values[$key] ?? $default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key);

        return $value !== null ? (int) $value : $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
    }

    public function getOctal(string $key, int $default = 0644): int
    {
        $value = $this->get($key);

        return $value !== null ? octdec($value) : $default;
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