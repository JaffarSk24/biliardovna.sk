<?php

namespace App;

class Router
{
    private array $routes = [];
    private string $language = 'sk';
    
    public function __construct()
    {
        $this->detectLanguage();
    }
    
    private function detectLanguage(): void
    {
        $config = require __DIR__ . '/../config/app.php';
        $supported = $config['languages']['supported'];
        $default = $config['languages']['default'];
        
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $segments = explode('/', trim($uri, '/'));
        
        // Check if first segment is a language code
        if (!empty($segments[0]) && in_array($segments[0], $supported) && $segments[0] !== $default) {
            $this->language = $segments[0];
        } else {
            $this->language = $default;
        }
    }
    
    public function getLanguage(): string
    {
        return $this->language;
    }
    
    public function get(string $path, callable $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }
    
    public function post(string $path, callable $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }
    
    private function addRoute(string $method, string $path, callable $handler): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler
        ];
    }
    
    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Remove language prefix from URI
        $config = require __DIR__ . '/../config/app.php';
        $default = $config['languages']['default'];
        
        if ($this->language !== $default) {
            $uri = preg_replace("#^/{$this->language}#", '', $uri);
        }
        
        // Normalize URI
        $uri = '/' . trim($uri, '/');
        if ($uri !== '/') {
            $uri = rtrim($uri, '/');
        }
        
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method && !($method === 'HEAD' && $route['method'] === 'GET')) {
                continue;
            }
            
            // Convert route pattern to regex
            $pattern = preg_replace('#\{([a-z_]+)\}#', '([^/]+)', $route['path']);
            $pattern = "#^{$pattern}$#";
            
            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches); // Remove full match
                call_user_func_array($route['handler'], $matches);
                return;
            }
        }
        
        // 404 Not Found
        http_response_code(404);
        echo "404 - Page Not Found";
    }
    
    public function url(string $path, ?string $language = null): string
    {
        $config = require __DIR__ . '/../config/app.php';
        $default = $config['languages']['default'];
        $lang = $language ?? $this->language;
        
        if ($lang !== $default) {
            return "/{$lang}{$path}";
        }
        
        return $path;
    }
    
    // Add route with explicit method parameter
    public function add(string $method, string $path, $handler): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler
        ];
    }
}