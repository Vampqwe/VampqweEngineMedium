<?php

declare(strict_types=1);

namespace Core\System\Http;

/**
 * Enum стандартных HTTP-заголовков
 */
enum Header: string
{
    case ACCEPT                  = 'Accept';
    case ACCEPT_CHARSET          = 'Accept-Charset';
    case ACCEPT_ENCODING         = 'Accept-Encoding';
    case ACCEPT_LANGUAGE         = 'Accept-Language';
    case AUTHORIZATION           = 'Authorization';
    case CACHE_CONTROL           = 'Cache-Control';
    case CONNECTION              = 'Connection';
    case CONTENT_DISPOSITION     = 'Content-Disposition';
    case CONTENT_ENCODING        = 'Content-Encoding';
    case CONTENT_LENGTH          = 'Content-Length';
    case CONTENT_TYPE            = 'Content-Type';
    case COOKIE                  = 'Cookie';
    case ETAG                    = 'ETag';
    case EXPIRES                 = 'Expires';
    case HOST                    = 'Host';
    case IF_MODIFIED_SINCE       = 'If-Modified-Since';
    case IF_NONE_MATCH           = 'If-None-Match';
    case LAST_MODIFIED           = 'Last-Modified';
    case LOCATION                = 'Location';
    case ORIGIN                  = 'Origin';
    case PRAGMA                  = 'Pragma';
    case REFERER                 = 'Referer';
    case SET_COOKIE              = 'Set-Cookie';
    case TRANSFER_ENCODING       = 'Transfer-Encoding';
    case USER_AGENT              = 'User-Agent';
    case X_CSRF_TOKEN            = 'X-CSRF-Token';
    case X_FORWARDED_FOR         = 'X-Forwarded-For';
    case X_FORWARDED_HOST        = 'X-Forwarded-Host';
    case X_FORWARDED_PROTO       = 'X-Forwarded-Proto';
    case X_REQUESTED_WITH        = 'X-Requested-With';

    /**
     * Получить значение из $_SERVER
     */
    public function fromServer(): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $this->value));

        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }

        // Специальные заголовки
        return match ($this) {
            self::CONTENT_TYPE   => $_SERVER['CONTENT_TYPE'] ?? null,
            self::CONTENT_LENGTH => $_SERVER['CONTENT_LENGTH'] ?? null,
            self::HOST           => $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? null,
            default              => null,
        };
    }
}