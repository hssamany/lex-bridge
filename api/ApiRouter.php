<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Api;

use Exception;

final class ApiRouter
{
    private array $routes = [];
    
    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }
    
    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }
    
    public function handle(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        
        error_log(sprintf(
            '[Router] handle(): %s %s',
            $method ?? 'UNKNOWN',
            $_SERVER['REQUEST_URI'] ?? '/'
        ));

        // Try PATH_INFO first (from rewrite), then parse URI
        if (!empty($_SERVER['PATH_INFO'])) {
            $path = $_SERVER['PATH_INFO'];
        } else {
            $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
            $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
            $scriptDir = str_replace('\\', '/', dirname($scriptName));

            if ($scriptDir !== '/' && $scriptDir !== '\\' && $scriptDir !== '.' && $scriptDir !== '') {
                if (strncmp($uri, $scriptDir, strlen($scriptDir)) === 0) {
                    $uri = substr($uri, strlen($scriptDir)) ?: '/';
                }
            }

            if (strncmp($uri, '/index.php', 10) === 0) {
                $uri = substr($uri, 10) ?: '/';
            }

            if (strncmp($uri, '/api', 4) === 0) {
                $uri = substr($uri, 4) ?: '/';

                error_log(sprintf(
                '[Router] handle() Api req: %s %s',
                $method ?? 'UNKNOWN',
                $_SERVER['REQUEST_URI'] ?? '/'
                ));
            }

            $path = $uri;
        }
        
        // Ensure path starts with /
        if (empty($path) || $path[0] !== '/') {
            $path = '/' . $path;
        }
        
        // Remove trailing slashes
        $path = rtrim($path, '/');
        if (empty($path)) {
            $path = '/';
        }
        
        if (!isset($this->routes[$method][$path])) {
            $this->jsonResponse([
                'error' => 'Not found', 
                'path' => $path, 
                'method' => $method,
                'available_routes' => array_keys($this->routes[$method] ?? [])
            ], 404);
            return;
        }
        
        try {
            $result = call_user_func($this->routes[$method][$path]);
            $this->jsonResponse($result);
        } catch (Exception $e) {
            error_log('API Error: ' . $e->getMessage());
            $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    private function jsonResponse(array $data, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($data);
        exit;
    }
}
