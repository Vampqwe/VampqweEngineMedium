<?php

declare(strict_types=1);

namespace Core\System\TimeDate;

/**
 * Enum стандартных форматов даты и времени
 */
enum DateFormat: string
{
    case ISO8601   = 'Y-m-d\TH:i:sP';
    case Date      = 'Y-m-d';
    case Time      = 'H:i:s';
    case DateTime  = 'Y-m-d H:i:s';
    case Timestamp = 'U';
    case RFC2822   = 'r';
    case Cookie    = 'l, d-M-Y H:i:s T';
}