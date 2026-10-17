<?php

declare(strict_types=1);

namespace Core\System\FileSystem;

/**
 * Низкоуровневый класс для проверки существования путей.
 * Используется в bootstrap-коде ДО инициализации Config.
 */
final class PathProbe
{
    public static function exists(string $path): bool
    {
        return file_exists($path);
    }

    public static function isFile(string $path): bool
    {
        return is_file($path);
    }

    public static function isDirectory(string $path): bool
    {
        return is_dir($path);
    }

    public static function isReadable(string $path): bool
    {
        return is_readable($path);
    }

    public static function isWritable(string $path): bool
    {
        return is_writable($path);
    }
}