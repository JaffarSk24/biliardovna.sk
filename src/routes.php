<?php

use App\Controllers\PageController;
use App\Controllers\BookingController;
use App\Controllers\BlogController;
use App\Controllers\ReviewController;
use App\Controllers\AuthController;
use App\Controllers\AdminController;
use App\Controllers\AdminPromoController;
use App\Controllers\TelegramController;

/** @var \App\Router $router */
/** @var string $language */

// Public routes - Homepage
$router->get('/', function() use ($language) {
    (new PageController($language))->home();
});

// Booking page
$router->get('/rezervacia', function() use ($language) {
    (new BookingController($language))->index();
});

$router->get('/booking', function() use ($language) {
    (new BookingController($language))->index();
});

// Public routes - Static pages
$router->get('/biliard-a-hry', function() use ($language) {
    (new PageController($language))->games();
});

$router->get('/cennik', function() use ($language) {
    (new PageController($language))->pricing();
});

$router->get('/akcie', function() use ($language) {
    (new PageController($language))->deals();
});

$router->get('/kaviaren', function() use ($language) {
    (new PageController($language))->cafe();
});

$router->get('/kontakt', function() use ($language) {
    (new PageController($language))->contact();
});

// Blog routes
$router->get('/blog', function() use ($router, $language) {
    // Note: BlogController seems to handle its own Twig setup in the original code. 
    // Ideally this should be refactored to use the base Controller's twig, but preserving behavior for now.
    // However, looking at the code, it needs a specific Twig setup.
    // Let's assume we can use the standard one or instantiate it as it was.
    // But BlogController constructor in 'src/Controllers/BlogController.php' might be different.
    // Checking file usage: it was instantiated with ($twig, $router).
    // Use the legacy way for now if strict refactoring is not done on BlogController.
    // But wait, PageController uses base Controller. BlogController seemed custom.
    // Let's use a closure wrapper to keep logic if needed, or instantiate properly.
    
    // Legacy instantiation logic from index.php:
    $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../templates');
    $twig = new \Twig\Environment($loader, [
        'cache' => false,
        'debug' => (bool)($_ENV['APP_DEBUG'] ?? false),
    ]);
    // ... globals ...
    // This logic is duplicate. 
    // We should probably rely on BlogController refactoring or just copy the logic here.
    // For now, let's instantiate it as it expects, but we need to verify BlogController signature.
    // Assuming we keep the manual setup for now as per "don't break things".
    
    $translationsFile = __DIR__ . '/../translations/' . $language . '.php';
    $translations = file_exists($translationsFile) ? require $translationsFile : [];
    
    $twig->addGlobal('router', $router);
    $twig->addGlobal('language', $language);
    $twig->addGlobal('translations', $translations);
    
    $twig->addFunction(new \Twig\TwigFunction('url', function (string $path, ?string $lang = null) use ($router) {
        return $router->url($path, $lang);
    }));
    
    $twig->addFilter(new \Twig\TwigFilter('trans', function ($key) use ($translations) {
        return $translations[$key] ?? $key;
    }));
    
    (new BlogController($twig, $router))->index();
});

$router->get('/blog/{slug}', function($slug) use ($router, $language) {
    // Same duplicate logic for show method
    $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../templates');
    $twig = new \Twig\Environment($loader, [
        'cache' => false,
        'debug' => (bool)($_ENV['APP_DEBUG'] ?? false),
    ]);
    
    $translationsFile = __DIR__ . '/../translations/' . $language . '.php';
    $translations = file_exists($translationsFile) ? require $translationsFile : [];
    
    $twig->addGlobal('router', $router);
    $twig->addGlobal('language', $language);
    $twig->addGlobal('translations', $translations);
    
    $twig->addFunction(new \Twig\TwigFunction('url', function (string $path, ?string $lang = null) use ($router) {
        return $router->url($path, $lang);
    }));
    
    $twig->addFilter(new \Twig\TwigFilter('trans', function ($key) use ($translations) {
        return $translations[$key] ?? $key;
    }));
    
    (new BlogController($twig, $router))->show($slug);
});


// EN/International variants
$router->get('/games', function() use ($language) { (new PageController($language))->games(); });
$router->get('/pricing', function() use ($language) { (new PageController($language))->pricing(); });
$router->get('/deals', function() use ($language) { (new PageController($language))->deals(); });
$router->get('/cafe', function() use ($language) { (new PageController($language))->cafe(); });
$router->get('/contact', function() use ($language) { (new PageController($language))->contact(); });

// API
$bookingController = new BookingController($language);
$router->get('/api/slots', fn() => $bookingController->getAvailableSlots());
$router->get('/api/price', fn() => $bookingController->calculatePrice());
$router->get('/api/resources-availability', fn() => $bookingController->getResourcesAvailability());
$router->get('/api/coupon/validate', fn() => $bookingController->validateCoupon());
$router->post('/api/booking/create', fn() => $bookingController->create());

$router->post('/booking/submit', fn() => $bookingController->submit());
$router->get('/booking/success', fn() => $bookingController->success());
$router->get('/booking/cancel', fn() => $bookingController->cancel());

// Reviews
$router->get('/review', function() use ($language) {
    (new ReviewController($language))->show();
});

// Admin Auth
$router->get('/admin/login', function() use ($language) { (new AuthController($language))->showLogin(); });
$router->post('/admin/login', function() use ($language) { (new AuthController($language))->login(); });
$router->get('/admin/logout', function() use ($language) { (new AuthController($language))->logout(); });

// Admin Dashboard & Bookings
$router->get('/admin', function() use ($language) { (new AdminController($language))->dashboard(); });
$router->get('/admin/bookings', function() use ($language) { (new AdminController($language))->listBookings(); });
$router->post('/admin/bookings/update', function() use ($language) { (new AdminController($language))->updateBookingStatus(); });
$router->get('/admin/holidays', function() use ($language) { (new AdminController($language))->manageHolidays(); });

// Admin Promo
$router->get('/admin/promo', function() use ($language) { (new AdminPromoController($language))->index(); });
$router->get('/admin/promo/new', function() use ($language) { (new AdminPromoController($language))->create(); });
$router->get('/admin/promo/edit', function() use ($language) { (new AdminPromoController($language))->edit(); });
$router->post('/admin/promo/save', function() use ($language) { (new AdminPromoController($language))->save(); });

// Webhooks
$router->post('/webhook/telegram', function() {
    (new TelegramController())->handleWebhook();
});
