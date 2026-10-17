<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Core\System\Application\Application;
use Core\System\Http\Router;

Application::init(__DIR__)->run(function (Application $app) {
    /** @var Router $router */
    $router = $app->make(Router::class);
    
    $response = $router->dispatch();
    $response->send();
});