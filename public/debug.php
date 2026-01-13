<?php
// public/debug.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';

// Mock Router to capture routes
class DebugRouter {
    public array $routes = [];
    public function get($path, $handler) { $this->routes[] = ['GET', $path]; }
    public function post($path, $handler) { $this->routes[] = ['POST', $path]; }
    public function getLanguage() { return 'sk'; }
    public function dispatch() {} 
    public function url($p) { return $p; }
    // Add other methods to prevent crash if routes.php calls them
    public function add($m, $p, $h) { $this->routes[] = [$m, $p]; }
}

$router = new DebugRouter();
$language = 'sk';

echo "<h1>Debug Info</h1>";
echo "<pre>";

// Load routes
if (file_exists(__DIR__ . '/../src/routes.php')) {
    echo "Loading ../src/routes.php ...\n";
    require_once __DIR__ . '/../src/routes.php';
    echo "Routes loaded successfully.\n\n";
    
    echo "<b>Registered Routes:</b>\n";
    foreach ($router->routes as $r) {
        $path = $r[1];
        // Highlight critical routes
        if (strpos($path, 'ochrana') !== false || strpos($path, 'vop') !== false) {
             echo "<span style='color:green; font-weight:bold'>FOUND: {$r[0]} $path</span>\n";
        } else {
             echo "{$r[0]} $path\n";
        }
    }
} else {
    echo "<span style='color:red'>ERROR: ../src/routes.php NOT FOUND</span>";
}

echo "\n<b>File Info:</b>\n";
echo "routes.php Size: " . filesize(__DIR__ . '/../src/routes.php') . " bytes\n";
echo "routes.php Modified: " . date("Y-m-d H:i:s", filemtime(__DIR__ . '/../src/routes.php')) . "\n";

echo "</pre>";
