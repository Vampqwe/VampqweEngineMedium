<?php

declare(strict_types=1);

/**
 * VampqweEngineMedium — проприетарный PHP-движок
 *
 * @copyright  Copyright (c) 2024-2026. Все права защищены.
 * @author     [Дедюля Александр Иванович / VampqweEngine]
 * @license    Proprietary
 * @link       https://github.com/Vampqwe/VampqweEngine.git
 * @email	   doktor_try@mail.ru
 *
 * Данный код является интеллектуальной собственностью автора.
 * Любое копирование, распространение, модификация или использование
 * без письменного разрешения правообладателя строго запрещено.
 *
 * This software is proprietary and confidential.
 * Unauthorized copying, distribution, or use is strictly prohibited.
 */


namespace Core\System\ServiceProvider;

class FileSystemServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // File и Directory создаются по требованию через new
    }

    public function boot(): void
    {
        // Нормализуем корень (…/storage → корень проекта), иначе при
        // историческом значении FS_BASE_PATH=.../storage провайдер
        // создавал «проект рядом с проектом» — артефактный каталог.
        $basePath = \Core\System\Security\SecurityManager::normalizeRootPath(
            (string) ($this->config->get('FS_BASE_PATH') ?: getcwd())
        );

        $storage = $basePath . DIRECTORY_SEPARATOR . 'storage';
        if (!is_dir($storage)) {
            $perm = $this->config->getOctal('FS_DEFAULT_DIR_PERMISSION', 0755);
            @mkdir($storage, $perm, true);
        }

        $logs = $basePath . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($logs)) {
            $perm = $this->config->getOctal('FS_DEFAULT_DIR_PERMISSION', 0755);
            @mkdir($logs, $perm, true);
        }
    }
}