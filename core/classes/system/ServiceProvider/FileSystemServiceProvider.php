<?php

declare(strict_types=1);

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