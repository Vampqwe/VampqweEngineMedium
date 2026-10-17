<?php

declare(strict_types=1);

namespace Core\System\Security;

/**
 * Ограничение частоты запросов (Rate Limiting)
 * Использует файловое хранилище
 */
class RateLimiter
{
    private const CLEANUP_FREQUENCY = 100;

    private string $storagePath;
    private int $maxAttempts;
    private int $windowSeconds;
    private bool $enabled;
    private int $attemptsSinceCleanup = 0;

    public function __construct(
        string $storagePath,
        int $maxAttempts = 60,
        int $windowSeconds = 60,
        bool $enabled = true
    ) {
        $this->storagePath = rtrim($storagePath, DIRECTORY_SEPARATOR);
        $this->maxAttempts = $maxAttempts;
        $this->windowSeconds = $windowSeconds;
        $this->enabled = $enabled;
    }

    /**
     * Попытаться выполнить действие
     *
     * @return array{allowed: bool, remaining: int, retry_after: int}
     */
/**
 * Попытаться выполнить действие
 *
 * @return array{allowed: bool, remaining: int, retry_after: int}
 */
public function attempt(string $key): array
{
    if (!$this->enabled) {
        return ['allowed' => true, 'remaining' => PHP_INT_MAX, 'retry_after' => 0];
    }

    $this->ensureStorageDirectory();
    $this->attemptsSinceCleanup++;
    if ($this->attemptsSinceCleanup >= self::CLEANUP_FREQUENCY) {
        $this->attemptsSinceCleanup = 0;
        $this->cleanupExpired();
    }

    $storageLock = $this->acquireStorageLock(LOCK_SH);
    try {
        $file = $this->getFilePath($key);
        $handle = fopen($file, 'c+');
        if ($handle === false) {
            throw new \RuntimeException("Не удалось открыть файл rate limit: {$file}");
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            throw new \RuntimeException("Не удалось заблокировать файл rate limit: {$file}");
        }

        try {
            $now = time();
            $attempts = $this->readAttemptsFromHandle($handle);
            $attempts = array_values(array_filter(
                $attempts,
                fn(int $timestamp) => ($now - $timestamp) < $this->windowSeconds
            ));

            if (count($attempts) >= $this->maxAttempts) {
                $oldest = min($attempts);
                $retryAfter = $this->windowSeconds - ($now - $oldest);
                $this->writeAttemptsToHandle($handle, $attempts);

                return [
                    'allowed'     => false,
                    'remaining'   => 0,
                    'retry_after' => max(1, $retryAfter),
                ];
            }

            $attempts[] = $now;
            $this->writeAttemptsToHandle($handle, $attempts);
            return [
                'allowed'     => true,
                'remaining'   => max(0, $this->maxAttempts - count($attempts)),
                'retry_after' => 0,
            ];
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    } finally {
        $this->releaseStorageLock($storageLock);
    }
}

    /**
     * Проверить, превышен ли лимит
     */
    public function tooManyAttempts(string $key): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $file = $this->getFilePath($key);
        $attempts = $this->getAttempts($file);

        $now = time();
        $recent = array_filter(
            $attempts,
            fn(int $timestamp) => ($now - $timestamp) < $this->windowSeconds
        );

        return count($recent) >= $this->maxAttempts;
    }

    /**
     * Получить количество оставшихся попыток
     */
    public function remainingAttempts(string $key): int
    {
        if (!$this->enabled) {
            return PHP_INT_MAX;
        }

        $file = $this->getFilePath($key);
        $attempts = $this->getAttempts($file);

        $now = time();
        $recent = array_filter(
            $attempts,
            fn(int $timestamp) => ($now - $timestamp) < $this->windowSeconds
        );

        return max(0, $this->maxAttempts - count($recent));
    }

    /**
     * Сбросить лимит для ключа
     */
    public function clear(string $key): void
    {
        $file = $this->getFilePath($key);

        if (file_exists($file)) {
            @unlink($file);
        }
    }

    /**
     * Сбросить все лимиты
     */
    public function clearAll(): void
    {
        $files = glob($this->storagePath . DIRECTORY_SEPARATOR . '*.rl');

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            @unlink($file);
        }
    }

    /**
     * Получить ключ для IP-адреса
     */
    public static function keyForIp(?string $ip = null): string
    {
        $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        return 'ip:' . hash('sha256', $ip);
    }

    /**
     * Получить ключ для пользователя
     */
    public static function keyForUser(string|int $userId): string
    {
        return 'user:' . $userId;
    }

    /**
     * Получить ключ для действия
     */
    public static function keyForAction(string $action, ?string $identifier = null): string
    {
        $key = 'action:' . $action;

        if ($identifier !== null) {
            $key .= ':' . $identifier;
        }

        return $key;
    }

    /**
     * Установить лимиты
     */
    public function setLimits(int $maxAttempts, int $windowSeconds): self
    {
        $this->maxAttempts = $maxAttempts;
        $this->windowSeconds = $windowSeconds;
        return $this;
    }

    /**
     * Включить/выключить
     */
    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    // ─── Приватные методы ───

    /**
     * Получить путь к файлу хранилища
     */
    private function getFilePath(string $key): string
    {
        return $this->storagePath . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.rl';
    }

    /**
     * Получить список попыток из файла
     *
     * @return int[]
     */
    private function getAttempts(string $file): array
    {
        if (!file_exists($file)) {
            return [];
        }

        $handle = fopen($file, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("Не удалось прочитать файл rate limit: {$file}");
        }

        if (!flock($handle, LOCK_SH)) {
            fclose($handle);
            throw new \RuntimeException("Не удалось заблокировать файл rate limit: {$file}");
        }

        try {
            return $this->readAttemptsFromHandle($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * Прочитать сериализованные попытки при уже взятой блокировке файла.
     *
     * @param resource $handle
     * @return int[]
     */
    private function readAttemptsFromHandle($handle): array
    {
        rewind($handle);
        $content = stream_get_contents($handle);
        if ($content === false) {
            throw new \RuntimeException('Не удалось прочитать данные rate limit');
        }

        if ($content === '') {
            return [];
        }

        try {
            $attempts = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('Повреждены данные rate limit', 0, $e);
        }

        if (!is_array($attempts)) {
            throw new \RuntimeException('Некорректный формат данных rate limit');
        }

        foreach ($attempts as $timestamp) {
            if (!is_int($timestamp) && !(is_string($timestamp) && ctype_digit($timestamp))) {
                throw new \RuntimeException('Некорректная отметка времени rate limit');
            }
        }

        return array_map('intval', array_values($attempts));
    }

    /**
     * Перезаписать счётчик при уже взятой эксклюзивной блокировке.
     *
     * @param resource $handle
     * @param int[] $attempts
     */
    private function writeAttemptsToHandle($handle, array $attempts): void
    {
        $content = json_encode(array_values($attempts), JSON_THROW_ON_ERROR);
        rewind($handle);
        if (!ftruncate($handle, 0) || fwrite($handle, $content) !== strlen($content) || !fflush($handle)) {
            throw new \RuntimeException('Не удалось записать данные rate limit');
        }
    }

    private function ensureStorageDirectory(): void
    {
        if (is_dir($this->storagePath)) {
            return;
        }

        if (!mkdir($this->storagePath, 0755, true) && !is_dir($this->storagePath)) {
            throw new \RuntimeException("Не удалось создать каталог rate limit: {$this->storagePath}");
        }
    }

    /**
     * Очистить устаревшие файлы
     */
    private function cleanupExpired(): void
    {
        $storageLock = $this->acquireStorageLock(LOCK_EX);
        try {
            $files = glob($this->storagePath . DIRECTORY_SEPARATOR . '*.rl');
            if ($files === false) {
                return;
            }

            $now = time();
            foreach ($files as $file) {
                $modifiedAt = filemtime($file);
                if ($modifiedAt !== false && $now - $modifiedAt > $this->windowSeconds * 2) {
                    @unlink($file);
                }
            }
        } finally {
            $this->releaseStorageLock($storageLock);
        }
    }

    /**
     * Блокировка каталога не даёт уборщику удалить файл, открытый активной попыткой.
     *
     * @return resource
     */
    private function acquireStorageLock(int $operation)
    {
        $lockFile = $this->storagePath . DIRECTORY_SEPARATOR . '.rate-limiter.lock';
        $handle = fopen($lockFile, 'c');
        if ($handle === false) {
            throw new \RuntimeException("Не удалось открыть блокировку rate limit: {$lockFile}");
        }

        if (!flock($handle, $operation)) {
            fclose($handle);
            throw new \RuntimeException("Не удалось захватить блокировку rate limit: {$lockFile}");
        }

        return $handle;
    }

    /** @param resource $handle */
    private function releaseStorageLock($handle): void
    {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}