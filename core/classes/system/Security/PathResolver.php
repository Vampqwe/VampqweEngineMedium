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
     *     с движком / fallback от расположения файлов движка;
     *  4. legacy-подсказка (аргумент $legacyHint, устаревший FS_BASE_PATH):
     *     используется, только если корень ещё НЕ зафиксирован и значение
     *     указывает на существующий каталог storage/ внутри проекта
     *     (…/storage → родитель). Иначе — отбрасывается.
     *
     * @param string $configRoot Значение APP_ROOT_PATH из конфига ('' если нет).
     * @param string $legacyHint Значение устаревшего FS_BASE_PATH ('' если нет).
     */
    public static function normalizeRootPath(string $configRoot = '', string $legacyHint = ''): string
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
        //    затем физический корень движка). Legacy-подсказка его не перебивает.
        $detected = ProjectRoot::get();

        // 4. Last-resort: если ProjectRoot ещё ничего не нашёл (не зафиксирован
        //    и не определён), допускается вывод корня из старого …/storage-значения
        //    FS_BASE_PATH — для обратной совместимости с неисправленными config.env.
        $legacyHint = rtrim($legacyHint, '/\\');
        if ($legacyHint !== '' && !is_dir($detected)
            && is_dir($legacyHint) && strtolower(basename($legacyHint)) === 'storage') {
            return dirname($legacyHint);
        }

        return $detected;
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
