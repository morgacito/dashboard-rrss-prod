<?php
declare(strict_types=1);

namespace App;

class Router
{
    private array $routes = [];
    private float $lastResponseTime = 0;

    public function get(string $route, callable $callback): void
    {
        $this->routes['GET'][$route] = $callback;
    }

    public function post(string $route, callable $callback): void
    {
        $this->routes['POST'][$route] = $callback;
    }

    public function dispatch(string $uri, string $method): void
    {
        $start = microtime(true);
        ob_start();
        
        $parsedUrl = parse_url($uri);
        $path = $parsedUrl['path'] ?? '/';

        // Si la peticion viene bajo el subdirectorio /backend (tipico de despliegue en produccion)
        // removemos el prefijo "/backend" para que coincida con las rutas estandar (/api/...)
        if (str_starts_with($path, '/backend/')) {
            $path = substr($path, 8); // conserva el slash inicial, ej: /backend/api -> /api
        } elseif ($path === '/backend') {
            $path = '/';
        }

        $this->handleCors();

        if ($method === 'OPTIONS') {
            http_response_code(204);
            ob_end_flush();
            return;
        }

        if (isset($this->routes[$method][$path])) {
            http_response_code(200);
            $callback = $this->routes[$method][$path];
            $callback();
        } else {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Not Found']);
        }

        ob_end_flush();

        $this->lastResponseTime = (microtime(true) - $start) * 1000;
        header("X-Response-Time: " . number_format($this->lastResponseTime, 2) . "ms");
    }

    public function getLastResponseTime(): float
    {
        return $this->lastResponseTime;
    }

    private function handleCors(): void
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-File-Name, X-Month, X-Year, X-Upload-Password, x-upload-password");
    }
}
