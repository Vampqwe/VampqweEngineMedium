<?php
declare(strict_types=1);

/**
 * CLI-утилита: сгенерировать и сохранить хэш ключа активации мастера установки.
 *
 * Использование:
 *   php bin/install-key.php generate   — создать новый случайный ключ,
 *                                        вывести его ОДИН раз и сохранить хэш
 *                                        в storage/.install_key
 *   php bin/install-key.php revoke     — отозвать сохранённый ключ (удалить файл)
 *
 * Доверенный контекст: запуск только из CLI (локально/по SSH).
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Только CLI-режим.\n");
    exit(1);
}

$basePath = dirname(__DIR__);
$keyFile  = $basePath . '/storage/.install_key';
$action   = $argv[1] ?? '';

switch ($action) {
    case 'generate':
        $key = bin2hex(random_bytes(16));
        if (!is_dir(dirname($keyFile))) {
            mkdir(dirname($keyFile), 0755, true);
        }
        $hash = hash_hmac('sha256', $key, 'vampqwe.install.dbconfig.v1');
        file_put_contents($keyFile, $hash, LOCK_EX);
        chmod($keyFile, 0600);

        fwrite(STDOUT, "Новый ключ активации (покажите один раз, сохранён только хэш):\n  {$key}\n");
        fwrite(STDOUT, "Файл: {$keyFile}\n");
        break;

    case 'revoke':
        if (is_file($keyFile)) {
            unlink($keyFile);
            fwrite(STDOUT, "Ключ активации отозван.\n");
        } else {
            fwrite(STDOUT, "Файл ключа не найден — отзыв не требуется.\n");
        }
        break;

    default:
        fwrite(STDERR, "Использование: php bin/install-key.php [generate|revoke]\n");
        exit(1);
}
