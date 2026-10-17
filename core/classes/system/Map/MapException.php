<?php

declare(strict_types=1);

namespace Core\System\Map;

use RuntimeException;

/**
 * Исключение для операций с Map
 */
class MapException extends RuntimeException
{
    public static function keyNotFound(string|int $key): self
    {
        return new self("Ключ не найден: {$key}");
    }

    public static function emptyCollection(string $operation): self
    {
        return new self("Нельзя выполнить операцию '{$operation}' на пустой коллекции");
    }

    public static function invalidCallback(string $message): self
    {
        return new self("Неверный callback: {$message}");
    }
}