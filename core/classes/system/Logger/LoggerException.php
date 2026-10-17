<?php

declare(strict_types=1);

namespace Core\System\Logger;

use RuntimeException;

/**
 * Исключение для операций логгера
 */
class LoggerException extends RuntimeException
{
    public static function invalidLevel(string $level): self
    {
        return new self("Неверный уровень логирования: {$level}");
    }

    public static function fileNotWritable(string $path): self
    {
        return new self("Файл логов недоступен для записи: {$path}");
    }

    public static function directoryCreationFailed(string $path): self
    {
        return new self("Не удалось создать директорию для логов: {$path}");
    }

    public static function writeFailed(string $message): self
    {
        return new self("Не удалось записать лог: {$message}");
    }
}