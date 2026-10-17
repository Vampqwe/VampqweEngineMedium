<?php

declare(strict_types=1);

namespace Core\System\Http;

/**
 * Стек middleware — вызывает их по очереди
 */
class MiddlewareStack
{
    /** @var Middleware[] */
    private array $middlewares = [];

    /**
     * Добавить middleware
     */
    public function push(Middleware $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    /**
     * Добавить несколько middleware
     *
     * @param Middleware[] $middlewares
     */
    public function merge(array $middlewares): self
    {
        foreach ($middlewares as $middleware) {
            $this->push($middleware);
        }
        return $this;
    }

    /**
     * Выполнить запрос через весь стек
     */
    public function process(Request $request, callable $finalHandler): Response
    {
        // Создаем цепочку: каждый middleware вызывает следующий
        $handler = $finalHandler;

        // Идем с конца, чтобы первый middleware был внешним
        for ($i = count($this->middlewares) - 1; $i >= 0; $i--) {
            $middleware = $this->middlewares[$i];
            $next = $handler;
            $handler = function (Request $req) use ($middleware, $next): Response {
                return $middleware->handle($req, $next);
            };
        }

        return $handler($request);
    }

    /**
     * Очистить стек
     */
    public function clear(): self
    {
        $this->middlewares = [];
        return $this;
    }

    /**
     * Получить количество middleware
     */
    public function count(): int
    {
        return count($this->middlewares);
    }

    /**
     * Получить все middleware как массив
     *
     * @return Middleware[]
     */
    public function all(): array
    {
        return $this->middlewares;
    }
}