<?php

declare(strict_types=1);

namespace Core\System\FileSystem;

use RuntimeException;

/**
 * Исключение для всех операций файловой системы
 */
class FileSystemException extends RuntimeException
{
    public static function notFound(string $path): self
    {
        return new self("Путь не найден: {$path}");
    }

    public static function permissionDenied(string $path): self
    {
        return new self("Нет прав доступа: {$path}");
    }

    public static function alreadyExists(string $path): self
    {
        return new self("Путь уже существует: {$path}");
    }

    public static function notWritable(string $path): self
    {
        return new self("Путь недоступен для записи: {$path}");
    }

    public static function maxSizeExceeded(int $size, int $max): self
    {
        return new self(
            "Превышен максимальный размер: {$size} байт (лимит: {$max} байт)"
        );
    }
}