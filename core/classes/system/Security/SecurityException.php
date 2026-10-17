<?php

declare(strict_types=1);

namespace Core\System\Security;

use RuntimeException;

/**
 * Исключение для операций безопасности
 */
class SecurityException extends RuntimeException
{
    public static function pathTraversal(string $path): self
    {
        return new self("Обнаружена попытка Path Traversal: {$path}");
    }

    public static function pathOutsideBase(string $path, string $base): self
    {
        return new self("Путь '{$path}' выходит за пределы базовой директории '{$base}'");
    }

    public static function invalidCsrfToken(): self
    {
        return new self('Невалидный CSRF токен');
    }

    public static function rateLimitExceeded(int $limit, int $window): self
    {
        return new self("Превышен лимит запросов: {$limit} за {$window} секунд");
    }

    public static function configPermissionsInvalid(string $path, int $actual, int $expected): self
    {
        return new self(
            "Файл '{$path}' имеет небезопасные права " . decoct($actual) .
            " (ожидалось " . decoct($expected) . ")"
        );
    }

    public static function unsafeSqlQuery(string $reason): self
    {
        return new self("Небезопасный SQL-запрос: {$reason}");
    }

    public static function invalidMimeType(string $expected, string $actual): self
    {
        return new self("Недопустимый MIME-тип: ожидался '{$expected}', получен '{$actual}'");
    }

    public static function fileTooLarge(int $size, int $max): self
    {
        return new self("Файл слишком большой: {$size} байт (максимум: {$max} байт)");
    }

    public static function regexTimeout(string $pattern): self
    {
        return new self("Регулярное выражение '{$pattern}' выполняется слишком долго");
    }
}