<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

// Composer autoloader
require_once BASE_PATH . '/vendor/autoload.php';

use NEvents\Core\Application;
use NEvents\Core\ErrorHandler;
use NEvents\Core\Request;
use NEvents\Core\Router;
use NEvents\Middleware\SecurityHeadersMiddleware;

// Catch anything uncaught — including failures during bootstrap itself —
// with a safe (non-verbose) default. Upgraded to the real APP_DEBUG value
// once config is loaded below.
ErrorHandler::register(BASE_PATH, false);

// Bootstrap application
$app = Application::getInstance();
$app->bootstrap(BASE_PATH);
ErrorHandler::setDebug((bool) $app->config('app.debug', false));

// Build router
$router = new Router();
require BASE_PATH . '/app/routes.php';

// Build request and dispatch
$request  = new Request();
$response = $router->dispatch($request);

// Apply global security headers
$secMiddleware = new SecurityHeadersMiddleware();
$finalResponse = $secMiddleware->handle($request, fn() => $response);

// Handle 404 with our custom error page
if ($finalResponse->getStatus() === 404) {
    $view = new \NEvents\Core\View();
    try {
        $body = $view->render('errors.404', [
            'title'     => '404 — Page Not Found',
            'app_name'  => $app->config('app.name'),
        ]);
        $finalResponse->setBody($body);
        $finalResponse->setHeader('Content-Type', 'text/html; charset=UTF-8');
    } catch (\Throwable) {
        $finalResponse->setBody('<h1>404 — Not Found</h1>');
    }
}

$finalResponse->send();
