<?php
// public/server_check.php
header('Content-Type: text/html');
echo "<h1>Deployment Check</h1>";
echo "<b>Current Script Path:</b> " . __FILE__ . "<br>";
echo "<b>Current Directory:</b> " . __DIR__ . "<br>";
echo "<hr>";
echo "<b>Checking critical files:</b><br>";

$files = [
    'index.php',
    '../src/routes.php',
    '../src/Router.php'
];

foreach ($files as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "✅ Found: $file (Modified: " . date("Y-m-d H:i:s", filemtime(__DIR__ . '/' . $file)) . ")<br>";
    } else {
        echo "❌ MISSING: $file<br>";
    }
}
echo "<hr>";
echo "<b>Directory Listing:</b><pre>";
print_r(scandir(__DIR__));
echo "</pre>";
