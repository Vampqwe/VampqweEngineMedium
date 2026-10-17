<?php

declare(strict_types=1);

namespace Core\System\DataBase;

use RuntimeException;

/**
 * Исключение для операций с базой данных
 */
class DataBaseException extends RuntimeException
{
    public static function connectionFailed(string $message): self
    {
        return new self("Не удалось подключиться к БД: {$message}");
    }

    public static function unsupportedDriver(string $driver): self
    {
        return new self("Неподдерживаемый драйвер БД: {$driver}");
    }

    /**
     * Драйвер существует в enum, но отключён политикой движка
     * (рабочее приложение работает только с MySQL).
     */
    public static function inactiveDriver(string $driver): self
    {
        return new self(
            "Драйвер БД «{$driver}» неактивен: движок работает только с MySQL/MariaDB "
            . "(укажите DB_DRIVER=mysql в config.env)"
        );
    }

    public static function queryFailed(string $sql, string $message): self
    {
        return new self("Ошибка выполнения запроса [{$sql}]: {$message}");
    }

    public static function transactionFailed(string $message): self
    {
        return new self("Ошибка транзакции: {$message}");
    }

    public static function notConnected(): self
    {
        return new self('Нет активного подключения к БД');
    }
}