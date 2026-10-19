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

/**
 * Единая точка нормализации корня проекта.
 *
 * Исторически логика дублировалась в трёх местах (SecurityManager,
 * AdminServiceProvider, InstallService) с расхождениями в деталях.
 * Теперь все потребители вызывают PathResolver::normalizeRootPath().
 */
final class PathResolver
{
    /**
     * Нормализация корня проекта из FS_BASE_PATH.
     *
     * Правила:
     *  - путь заканчивается на storage/ → поднимаемся на уровень вверх;
     *  - путь не существует или это не корень проекта (нет index.php)
     *    → fallback: ищем корень от расположения этого файла
     *      (core/classes/system/Security → 5 уровней вверх).
     */
    public static function normalizeRootPath(string $basePath): string
    {
        $basePath = rtrim($basePath, '/\\');

        if ($basePath !== '' && is_dir($basePath)
            && strtolower(basename($basePath)) === 'storage') {
            return dirname($basePath);
        }

        if ($basePath === '' || !is_dir($basePath) || !is_file($basePath . DIRECTORY_SEPARATOR . 'index.php')) {
            // Недостоверный путь — fallback на реальный корень проекта от __DIR__.
            return dirname(__DIR__, 4);
        }

        return $basePath;
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
