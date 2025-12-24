<?php

/**
 * Biliardovna.sk Booking System
 * Public Entry point
 */

use App\Router;
use Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();



// Start session
$config = require __DIR__ . '/../config/app.php';
session_start([
    'cookie_lifetime' => $config['session']['lifetime'] ?? 7200,
    'cookie_secure' => $config['session']['secure'] ?? false,
    'cookie_httponly' => $config['session']['httponly'] ?? true,
]);

// Allow webhook access without session check
if ($_SERVER['REQUEST_URI'] && strpos($_SERVER['REQUEST_URI'], '/webhook/telegram') !== false) {
    $_SESSION['access_granted'] = true;
}

// Under Construction Logic
if (!isset($_SESSION['access_granted'])) {
    if (isset($_POST['access'])) {
        $_SESSION['access_granted'] = true;
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    
    // Check if we should skip this (e.g. if APP_ENV is local)
    // But preserving original behavior which enforced this.
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Site under construction</title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                display: flex; 
                justify-content: center; 
                align-items: center; 
                height: 100vh; 
                margin: 0;
                background: #f5f5f5;
            }
            .access-form {
                background: white;
                padding: 40px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                text-align: center;
            }
            h2, p {
                color: #333;
            }
            button {
                padding: 12px 30px;
                font-size: 16px;
                background: transparent;
                color: transparent;
                border: 1px solid transparent;
                border-radius: 4px;
                cursor: pointer;
            }
        </style>
    </head>
    <body>
        <form method="POST" class="access-form">
            <h2>Site under construction</h2>
            <p>See you later 😉✌️</p>
            <button type="submit" name="access">OK</button>
        </form>
    </body>
    </html>';
    exit;
}

// Error handling
if ($_ENV['APP_DEBUG'] ?? false) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Initialize router
$router = new Router();
$language = $router->getLanguage();

// Load routes
require_once __DIR__ . '/../src/routes.php';

// Dispatch
$router->dispatch();
