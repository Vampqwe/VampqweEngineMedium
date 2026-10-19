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
 * проекта). Все потребители базового пути вызывают этот резолвер
 * (напрямую или через Config::getRootPath()/Config::getStoragePath()).
 *
 * FS_BASE_PATH упразднён: исторически он задавал путь к storage/, а не
 * к корню проекта, и являлся источником расхождения путей. Если ключ
 * всё ещё остался в старом config.env, он читается ТОЛЬКО как
 * last-resort-подсказка (см. п. 4) и подлежит удалению из конфига.
 */
final class PathResolver
{
    /**
     * Нормализация корня проекта из конфига.
     *
     * Источники по приоритету:
     *  1. зафиксированный ProjectRoot (Application::init / VCORE_ROOT);
     *  2. APP_ROOT_PATH из config.env (аргумент $configRoot) — канонический;
     *  3. ProjectRoot::get() — детекция APP_ROOT_PATH из config.env рядом
     *     с движком / fallback от расположения файлов движка.
     *
     * @param string $configRoot Значение APP_ROOT_PATH из конфига ('' если нет).
     */
    public static function normalizeRootPath(string $configRoot = ''): string
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

        // 3. Резолвер единой точки определения (config.env рядом с движком,
        //    затем физический корень движка).
        return ProjectRoot::get();
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

    /**
     * Резолв файлового пути из config.env ОТНОСИТЕЛЬНО корня проекта
     * (единая точка входа — APP_ROOT_PATH).
     *
     * Правила:
     *  - пустое $configuredPath → разрешается $default (тоже от корня);
     *  - «виртуально абсолютный» путь (/log/app.log, \storage\x, C:/dir,
     *    UNC \\server\share) трактуется относительно корня: ведущие
     *    разделители (и диск/UNC-префикс) снимаются — абсолютных путей в
     *    конфиге быть не должно;
     *  - обычный относительный путь (logs/app.log) присоединяется к корню;
     *  - результат нормализуется («.», «..») и не может выйти за пределы
     *    корня проекта (защита от path traversal в конфиге).
     *
     * Примеры (корень h:/Server/OSPanel/home/VampqweEngineRef):
     *   LOG_FILE=/log/app.log      → {корень}/log/app.log
     *   LOG_FILE=logs/app.log      → {корень}/logs/app.log
     *   SECURITY_AUDIT_FILE=/log/audit.log → {корень}/log/audit.log
     */
    public static function resolveFromRoot(string $rootPath, string $configuredPath, string $default = ''): string
    {
        $root = self::normalizeSeparators(rtrim($rootPath, '/\\'));

        $path = trim($configuredPath);
        if ($path === '') {
            $path = trim($default);
        }

        // Снимаем «абсолютность»: ведущие /, \, диск C:/ или C:\, UNC-префикс.
        $relative = preg_replace(
            '#^(?:\\\\\\\\[^\\\\]+\\\\[^\\\\]+|[A-Za-z]:)?[/\\\\]+#',
            '',
            $path
        ) ?? '';

        // Обычный относительный путь тоже режем на сегменты и пересобираем.
        $segments = $relative === ''
            ? []
            : array_filter(explode('/', str_replace('\\', '/', $relative)), static fn($s) => $s !== '' && $s !== '.');

        $parts = $root === '' ? [] : array_values(array_filter(explode('/', $root), static fn($s) => $s !== ''));
        // Первый сегмент Windows-корня (h:) сохраняем как есть.

        foreach ($segments as $segment) {
            if ($segment === '..') {
                // Не выходим за пределы корня проекта.
                if (count($parts) > (self::isWindowsRootPrefix($parts[0] ?? '') ? 1 : 0)) {
                    array_pop($parts);
                }
                continue;
            }

            if (str_contains($segment, '/') || str_contains($segment, '\\')) {
                continue; // защита: осколки после разделения не ожидаются
            }

            $parts[] = $segment;
        }

        if ($parts === []) {
            return $root !== '' ? $root : '.';
        }

        $joined = implode('/', $parts);

        // Windows-диск: h:/path → h:\path (сохраняем исходный стиль корня).
        if (self::isWindowsRootPrefix($parts[0])) {
            $joined = $parts[0] . substr($joined, strlen($parts[0]));
        }

        // Если исходный корень был с обратными слешами — вернуть в том же стиле.
        if (str_contains($rootPath, '\\')) {
            $joined = str_replace('/', '\\', $joined);
        }

        return $joined;
    }

    private static function isWindowsRootPrefix(string $firstSegment): bool
    {
        return (bool) preg_match('/^[A-Za-z]:$/', $firstSegment)
            || str_starts_with($firstSegment, '\\\\');
    }

    private static function normalizeSeparators(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
