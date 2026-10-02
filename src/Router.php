<?php

namespace EssenceStore;

class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function patch(string $path, callable|array $handler): void
    {
        $this->addRoute('PATCH', $path, $handler);
    }

    public function delete(string $path, callable|array $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    private function addRoute(string $method, string $path, callable|array $handler): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
        ];
    }

    public function dispatch(string $requestMethod, string $requestUri): void
    {
        $parsedUri = parse_url($requestUri, PHP_URL_PATH);
        $parsedUri = rtrim($parsedUri, '/') ?: '/';

        $matchedPath = false;

        foreach ($this->routes as $route) {
            $pattern = preg_replace('#:([a-zA-Z0-9_]+)#', '(?P<$1>[^/]+)', $route['path']);
            $pattern = '#^' . rtrim($pattern, '/') . '$#';

            if (preg_match($pattern, $parsedUri, $matches)) {
                $matchedPath = true;

                if ($route['method'] === $requestMethod) {
                    $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                    if (is_array($route['handler'])) {
                        [$class, $method] = $route['handler'];
                        $controller = is_string($class) ? new $class() : $class;
                        call_user_func_array([$controller, $method], array_values($params));
                        return;
                    }

                    if (is_callable($route['handler'])) {
                        call_user_func_array($route['handler'], array_values($params));
                        return;
                    }
                }
            }
        }

        if ($matchedPath) {
            Response::error("Method {$requestMethod} not allowed for {$parsedUri}.", 405, 'METHOD_NOT_ALLOWED');
        }

        Response::error("Route {$requestMethod} {$parsedUri} not found.", 404, 'NOT_FOUND');
    }
}
