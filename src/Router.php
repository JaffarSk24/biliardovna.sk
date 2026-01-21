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

        // 1. Check URL prefix
        $urlLang = (!empty($segments[0]) && in_array($segments[0], $supported) && $segments[0] !== $default) ? $segments[0] : null;

        if ($urlLang) {
            $this->language = $urlLang;
        } else {
            $this->language = $default;

            // 2. Auto-redirect ONLY on Root URL ('/')
            if ($uri === '/' || $uri === '') {
                $cookieLang = $_COOKIE['app_lang'] ?? null;
                $redirectLang = null;

                if ($cookieLang && in_array($cookieLang, $supported) && $cookieLang !== $default) {
                    $redirectLang = $cookieLang;
                } elseif (!$cookieLang) {
                    // No cookie -> Check Browser Header
                    $browserLang = $this->getBrowserLanguage($supported);
                    if ($browserLang && $browserLang !== $default) {
                        $redirectLang = $browserLang;
                    }
                }

                if ($redirectLang) {
                    header("HTTP/1.1 302 Found");
                    header("Location: /{$redirectLang}");
                    exit;
                }
            }
        }

        // 3. Update Cookie to match current language (so we remember for next time)
        if (!isset($_COOKIE['app_lang']) || $_COOKIE['app_lang'] !== $this->language) {
            setcookie('app_lang', $this->language, [
                'expires' => time() + 60 * 60 * 24 * 365,
                'path' => '/',
                'httponly' => false,
                'samesite' => 'Lax'
            ]);
        }
    }

    private function getBrowserLanguage(array $supported): ?string
    {
        if (empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            return null;
        }
        $langs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        foreach ($langs as $lang) {
            $code = substr($lang, 0, 2);
            if (in_array($code, $supported)) {
                return $code;
            }
        }
        return null;
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
        } else {
            // Logic fix: if URI explicitly has default language prefix (e.g. /sk/kaviaren), 
            // but language matches default, we still need to strip it to match the route definitions which are base-relative.
            $uri = preg_replace("#^/{$default}#", '', $uri);
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
