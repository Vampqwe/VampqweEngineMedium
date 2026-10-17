<?php

declare(strict_types=1);

namespace Core\System\Map;

/**
 * Enum порядка сортировки
 */
enum SortOrder: string
{
    case Asc  = 'asc';
    case Desc = 'desc';

    public function multiplier(): int
    {
        return match ($this) {
            self::Asc  => 1,
            self::Desc => -1,
        };
    }
}