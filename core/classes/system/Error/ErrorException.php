<?php

declare(strict_types=1);

namespace Core\System\Error;

use ErrorException as BaseErrorException;

/**
 * Обёртка для стандартных ошибок PHP
 */
class ErrorException extends BaseErrorException
{
    /**
     * Получить иконку для уровня ошибки
     */
    public function getIcon(): string
    {
        return match ($this->getSeverity()) {
            E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE => '💥',
            E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING => '⚠️',
            E_NOTICE, E_USER_NOTICE, E_STRICT => 'ℹ️',
            E_DEPRECATED, E_USER_DEPRECATED => '📦',
            default => '❓',
        };
    }

    /**
     * Получить название уровня ошибки
     */
	public function getLevelName(): string
	{
		$levels = [
			E_ERROR             => 'E_ERROR',
			E_WARNING           => 'E_WARNING',
			E_PARSE             => 'E_PARSE',
			E_NOTICE            => 'E_NOTICE',
			E_CORE_ERROR        => 'E_CORE_ERROR',
			E_CORE_WARNING      => 'E_CORE_WARNING',
			E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
			E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
			E_USER_ERROR        => 'E_USER_ERROR',
			E_USER_WARNING      => 'E_USER_WARNING',
			E_USER_NOTICE       => 'E_USER_NOTICE',
			E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
		];

		return $levels[$this->getSeverity()] ?? 'UNKNOWN';
	}

    /**
     * Является ли ошибка фатальной
     */
    public function isFatal(): bool
    {
        return in_array($this->getSeverity(), [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true);
    }
}