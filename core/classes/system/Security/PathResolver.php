<?php

declare(strict_types=1);

/**
 * VampqweEngineMedium — проприетарный PHP-движок
 *
 * @copyright  Copyright (c) 2024-2026. Все права защищены.
 * @author     [Дедюля Александр Иванович / VampqweEngine]
 * @license    Proprietary
 * @link       https://github.com/Vampqwe/VampqweEngine.git
 *
 * Данный код является интеллектуальной собственностью автора.
 * Любое копирование, распространение, модификация или использование
 * без письменного разрешения правообладателя строго запрещено.
 *
 * This software is proprietary and confidential.
 * Unauthorized copying, distribution, or use is strictly prohibited.
 */

namespace Core\System\Security;

use Core\System\Application\ProjectRoot;

/**
 * Единая точка нормализации корня проекта.
 *
 * Каноническая настройка — APP_ROOT_PATH (абсолютный путь к корню
 * проекта). FS_BASE_PATH поддерживается только как устаревший
 * historical-fallback (значение могло указывать на storage/).
 * Теперь все потребители вызывают PathResolver::normalizeRootPath().
 */
final class PathResolver
{
    /**
     * Нормализация корня проекта из конфига.
     *
     * Источники по приоритету:
     *  1. зафиксированный ProjectRoot (Application::init / VCORE_ROOT);
     *  2. APP_ROOT_PATH из config.env (аргумент $configRoot);
     *  3. устаревший FS_BASE_PATH (аргумент $legacyRoot): …/storage →
     *     родитель, недостоверный путь → отбрасывается;
     *  4. fallback — реальный корень проекта от расположения файлов движка.
     *
     * @param string $configRoot Значение APP_ROOT_PATH из конфига ('' если нет).
     * @param string $legacyRoot Значение устаревшего FS_BASE_PATH ('' если нет).
     */
    public static function normalizeRootPath(string $basePath = '', string $configRoot = ''): string
    {
        // 1. Явно зафиксированный корень приложения имеет высший приоритет.
        $fixed = ProjectRoot::current();
        if ($fixed !== null && $fixed !== '') {
            return $fixed;
        }

        // 2. Каноническая настройка APP_ROOT_PATH.
        $configRoot = rtrim($configRoot, '/\\');
        if ($configRoot !== '' && is_dir($configRoot)) {
            return $configRoot;
        }

        $basePath = rtrim($basePath, '/\\');

        // 3. Устаревший FS_BASE_PATH: допускался в формате пути к storage/.
        if ($basePath !== '' && is_dir($basePath)
            && strtolower(basename($basePath)) === 'storage') {
            return dirname($basePath);
        }

        if ($basePath !== '' && is_dir($basePath) && is_file($basePath . DIRECTORY_SEPARATOR . 'index.php')) {
            return $basePath;
        }

        // 4. Недостоверный путь — fallback на реальный корень проекта.
        return ProjectRoot::fallbackRoot();
    }

    /**
     * Каталог storage/ внутри корня проекта. Если корень сам указывает
     * на storage-каталог (маловероятный, но исторический вариант) —
     * возвращаем его как есть, без вложенного storage/storage.
     */
    public static function resolveStorageDir(string $rootPath): string
    {
        $rootPath = rtrim($rootPath, '/\\');

        if ($rootPath !== '' && strtolower(basename($rootPath)) === 'storage'
            && is_dir($rootPath)) {
            return $rootPath;
        }

        return $rootPath . DIRECTORY_SEPARATOR . 'storage';
    }
}
