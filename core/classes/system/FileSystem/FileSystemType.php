<?php

declare(strict_types=1);

namespace Core\System\FileSystem;

/**
 * Enum типа файловой сущности
 */
enum FileSystemType: string
{
    case File      = 'file';
    case Directory = 'directory';
    case Link      = 'link';
    case Unknown   = 'unknown';

    /**
     * Определить тип по реальному пути
     */
    public static function detect(string $path): self
    {
        if (!file_exists($path)) {
            return self::Unknown;
        }

        if (is_link($path)) {
            return self::Link;
        }

        if (is_dir($path)) {
            return self::Directory;
        }

        if (is_file($path)) {
            return self::File;
        }

        return self::Unknown;
    }
}