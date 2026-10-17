<?php

declare(strict_types=1);

namespace Core\System\Http;

use Closure;

/**
 * Группа маршрутов с общим префиксом и middleware
 */
class RouteGroup
{
    private string $prefix;
    private MiddlewareStack $middlewareStack;
    private Router $router;

    public function __construct(Router $router, string $prefix = '', Middleware ...$middlewares)
    {
        $this->router = $router;
        $this->prefix = $prefix;
        $this->middlewareStack = new MiddlewareStack();
        $this->middlewareStack->merge($middlewares);
    }

    /**
     * Добавить маршрут в группу
     */
	public function addRoute(string $path, Method $method, Closure|string|array $handler): Route
	{
		$fullPath = $this->prefix . $path;
		$route = $this->router->addRoute($fullPath, $method, $handler);
		
		// Добавляем middleware группы к маршруту
		$middlewares = $this->middlewareStack->all();
		if (!empty($middlewares)) {
			$route->middleware(...$middlewares);
		}
		
		return $route;
	}
    public function get(string $path, Closure|string|array $handler): Route
    {
        return $this->addRoute($path, Method::GET, $handler);
    }

    public function post(string $path, Closure|string|array $handler): Route
    {
        return $this->addRoute($path, Method::POST, $handler);
    }

    public function put(string $path, Closure|string|array $handler): Route
    {
        return $this->addRoute($path, Method::PUT, $handler);
    }

    public function patch(string $path, Closure|string|array $handler): Route
    {
        return $this->addRoute($path, Method::PATCH, $handler);
    }

    public function delete(string $path, Closure|string|array $handler): Route
    {
        return $this->addRoute($path, Method::DELETE, $handler);
    }
}