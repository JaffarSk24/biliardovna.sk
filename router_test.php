<?php
// Mock server env
$_SERVER['REQUEST_URI'] = '/en/privacy';
$_SERVER['REQUEST_METHOD'] = 'GET';

// Load config mocked
$config = [
    'languages' => [
        'supported' => ['sk', 'en', 'ru', 'uk', 'de'],
        'default' => 'sk'
    ]
];

// Replicate Router logic
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($uri, '/'));

$supported = $config['languages']['supported'];
$default = $config['languages']['default'];
$language = 'default';

if (!empty($segments[0]) && in_array($segments[0], $supported) && $segments[0] !== $default) {
    $language = $segments[0];
} else {
    $language = $default;
}

echo "Detected Language: " . $language . "\n";

// Dispatch Logic
if ($language !== $default) {
    $uri = preg_replace("#^/{$language}#", '', $uri);
}
echo "Modified URI: " . $uri . "\n";

$routes = [
    '/privacy', '/terms'
];

foreach ($routes as $route) {
    $pattern = preg_replace('#\{([a-z_]+)\}#', '([^/]+)', $route);
    $pattern = "#^{$pattern}$#";
    if (preg_match($pattern, $uri)) {
        echo "MATCHED Route: " . $route . "\n";
    }
}
