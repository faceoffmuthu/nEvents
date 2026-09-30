<?php

declare(strict_types=1);

namespace NEvents\Core;

class Router
{
    private array $routes = [];
    private array $middleware = [];
    private string $prefix = '';
    private array $groupMiddleware = [];

    public function get(string $uri, array|callable $handler): void
    {
        $this->addRoute('GET', $uri, $handler);
    }

    public function post(string $uri, array|callable $handler): void
    {
        $this->addRoute('POST', $uri, $handler);
    }

    public function put(string $uri, array|callable $handler): void
    {
        $this->addRoute('PUT', $uri, $handler);
    }

    public function delete(string $uri, array|callable $handler): void
    {
        $this->addRoute('DELETE', $uri, $handler);
    }

    public function group(array $options, callable $callback): void
    {
        $previousPrefix = $this->prefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->prefix = $previousPrefix . ($options['prefix'] ?? '');
        $this->groupMiddleware = array_merge($previousMiddleware, $options['middleware'] ?? []);

        $callback($this);

        $this->prefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    private function addRoute(string $method, string $uri, array|callable $handler): void
    {
        $uri = $this->prefix . $uri;
        $this->routes[] = [
            'method'     => $method,
            'uri'        => $uri,
            'pattern'    => $this->buildPattern($uri),
            'handler'    => $handler,
            'middleware' => $this->groupMiddleware,
        ];
    }

    private function buildPattern(string $uri): string
    {
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $uri);
        return '#^' . $pattern . '$#';
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->getMethod();
        $path   = $request->getPath();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            $request->setRouteParams($params);

            return $this->runMiddlewareChain($route['middleware'], $route['handler'], $request);
        }

        return $this->notFound();
    }

    private function runMiddlewareChain(array $middlewareList, array|callable $handler, Request $request): Response
    {
        $app = Application::getInstance();

        $pipeline = function (Request $req) use ($handler, $app): Response {
            if (is_callable($handler)) {
                return $handler($req);
            }
            [$controllerClass, $method] = $handler;
            $controller = $app->get($controllerClass);
            return $controller->$method($req);
        };

        foreach (array_reverse($middlewareList) as $middlewareClass) {
            $m = $app->get($middlewareClass);
            $next = $pipeline;
            $pipeline = function (Request $req) use ($m, $next): Response {
                return $m->handle($req, $next);
            };
        }

        return $pipeline($request);
    }

    private function notFound(): Response
    {
        $response = new Response();
        $response->setStatus(404);
        return $response;
    }
}
