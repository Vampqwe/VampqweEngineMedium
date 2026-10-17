<?php

declare(strict_types=1);

namespace Core\System\Http;

/**
 * Интерфейс middleware
 */
interface Middleware
{
    /**
     * Обработать запрос
     *
     * @param Request $request
     * @param callable(Request): Response $next
     * @return Response
     */
    public function handle(Request $request, callable $next): Response;
}