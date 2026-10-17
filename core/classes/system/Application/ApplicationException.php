<?php

declare(strict_types=1);

namespace Core\System\Application;

use RuntimeException;
use Throwable;

/**
 * Исключение для операций приложения
 */
class ApplicationException extends RuntimeException
{
    public static function notInitialized(): self
    {
        return new self('Приложение не инициализировано. Вызовите Application::init()');
    }

    public static function alreadyInitialized(): self
    {
        return new self('Приложение уже инициализировано');
    }

    public static function basePathNotFound(string $path): self
    {
        return new self("Базовая директория не найдена: {$path}");
    }

    public static function serviceProviderFailed(
        string $provider,
        string $message,
        ?Throwable $previous = null
    ): self
    {
        return new self("Ошибка в провайдере {$provider}: {$message}", 0, $previous);
    }
}