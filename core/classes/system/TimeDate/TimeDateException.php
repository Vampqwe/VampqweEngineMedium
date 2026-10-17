<?php

declare(strict_types=1);

namespace Core\System\TimeDate;

use RuntimeException;

/**
 * Исключение для операций с датой и временем
 */
class TimeDateException extends RuntimeException
{
    public static function invalidFormat(string $date, string $format): self
    {
        return new self("Неверный формат даты '{$date}' для формата '{$format}'");
    }

    public static function invalidTimezone(string $timezone): self
    {
        return new self("Неверный часовой пояс: {$timezone}");
    }

    public static function invalidDate(string $date): self
    {
        return new self("Некорректная дата: {$date}");
    }
}