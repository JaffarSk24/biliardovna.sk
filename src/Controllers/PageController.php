<?php

namespace App\Controllers;

class PageController extends Controller
{
    private function getEvents()
    {
        try {
            $pdo = \App\Database\Database::getInstance();

            $stmt = $pdo->prepare("
                SELECT title, slug, excerpt, image
                FROM articles 
                WHERE language = :language 
                AND (category = 'Turnaj' OR category = 'Tournament' OR category = 'Турнир' OR category = 'Турнір' OR category = 'Turnier')
                ORDER BY priority DESC, date DESC, created_at DESC
                LIMIT 10
            ");
            $stmt->execute(['language' => $this->language]);
            $articles = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $events = [];
            foreach ($articles as $article) {
                $events[] = [
                    'title' => $article['title'],
                    'short_description' => $article['excerpt'],
                    'image' => $article['image'],
                    'url' => '/' . $this->language . '/blog/' . $article['slug']
                ];
            }

            return $events;
        } catch (\Exception $e) {
            return [];
        }
    }

    private $metadata = [];

    private function getAlternates(string $pageKey, string $baseUri): array
    {
        $supported = ['sk', 'en', 'ru', 'uk', 'de'];

        $paths = [
            'home' => ['sk' => '/', 'base' => ''],
            'games' => ['sk' => '/biliard-a-hry', 'base' => '/games'],
            'pricing' => ['sk' => '/cennik', 'base' => '/pricing'],
            'deals' => ['sk' => '/akcie', 'base' => '/deals'],
            'blog' => ['sk' => '/blog', 'base' => '/blog'],
            'contact' => ['sk' => '/kontakt', 'base' => '/contact'],
            'privacy' => ['sk' => '/ochrana-osobnych-udajov', 'base' => '/privacy'],
            'terms' => ['sk' => '/vop', 'base' => '/terms'],
            'cookies' => ['sk' => '/ochrana-osobnych-udajov', 'base' => '/privacy'], // map to privacy
        ];

        if (!isset($paths[$pageKey])) {
            return [];
        }

        $map = $paths[$pageKey];
        $alternates = [];

        foreach ($supported as $lang) {
            if ($lang === 'sk') {
                $alternates[$lang] = $baseUri . $map['sk'];
            } else {
                // For other languages: /lang + base
                $suffix = $map['base'];
                $alternates[$lang] = $baseUri . '/' . $lang . $suffix;
            }
        }

        // Add x-default pointing to SK
        $alternates['x-default'] = $alternates['sk'];

        return $alternates;
    }

    public function __construct(string $language = 'sk')
    {
        parent::__construct($language);
        $this->metadata = require __DIR__ . '/../../config/metadata.php';
    }

    private function renderWithSeo(string $template, string $pageKey, array $data = [])
    {
        $meta = $this->metadata[$pageKey][$this->language] ?? $this->metadata[$pageKey]['sk'] ?? [];

        // Base canonical
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $domain = $_SERVER['HTTP_HOST'];
        $baseUri = $protocol . $domain;

        $currentUri = $_SERVER['REQUEST_URI'] ?? '/';
        $canonical = $baseUri . strtok($currentUri, '?');

        // Add lang param if not default (simple approach, or rely on clean URLs if router supports them)
        // If our router uses /en/page style, it's already in URI.
        // If we want detailed canonical logic, we might need a helper from Router.
        // For now, let's use the current clean URL as canonical.

        $data = array_merge($data, [
            'page_title' => $meta['title'] ?? '',
            'meta_description' => $meta['description'] ?? '',
            'meta_keywords' => $meta['keywords'] ?? '',
            'og_title' => $meta['og:title'] ?? ($meta['title'] ?? ''),
            'og_description' => $meta['og:description'] ?? ($meta['description'] ?? ''),
            'og_type' => $meta['og:type'] ?? 'website',
            'og_image' => $baseUri . ($meta['og:image'] ?? '/public/images/1920x1080_with_logo.webp'),
            'og_url' => $canonical,
            'canonical' => $canonical,
            'current_language' => $this->language,
            'alternates' => $this->getAlternates($pageKey, $baseUri)
        ]);

        $this->render($template, $data);
    }

    public function home()
    {
        $translations = $this->translationService->getUITranslations($this->language);

        // Load services from database or config
        $services = [
            [
                'id' => 1,
                'name' => $translations['service_piramida_name'] ?? 'Pyramída',
                'short_description' => $translations['service_piramida_short'] ?? 'Európska pyramída',
                'full_description' => $translations['service_piramida_full'] ?? '',
                'image' => '/public/images/piramide.webp',
                'price_morning' => '8',
                'price_afternoon' => '10',
                'price_evening' => '12',
                'price_holiday' => '15',
                'tables_count' => 4
            ],
            [
                'id' => 2,
                'name' => $translations['service_pool_name'] ?? 'Pool',
                'short_description' => $translations['service_pool_short'] ?? 'Americký pool',
                'full_description' => $translations['service_pool_full'] ?? '',
                'image' => '/public/images/pull.webp',
                'price_morning' => '6',
                'price_afternoon' => '8',
                'price_evening' => '10',
                'price_holiday' => '12',
                'tables_count' => 4
            ],
            [
                'id' => 3,
                'name' => $translations['service_darts_name'] ?? 'Šípky',
                'short_description' => $translations['service_darts_short'] ?? 'Elektronické šípky',
                'full_description' => $translations['service_darts_full'] ?? '',
                'image' => '/public/images/darts.webp',
                'price_morning' => '5',
                'price_afternoon' => '6',
                'price_evening' => '8',
                'price_holiday' => '10',
                'tables_count' => 6
            ],
            [
                'id' => 4,
                'name' => $translations['service_table-football_name'] ?? 'Stolný futbal',
                'short_description' => $translations['service_table-football_short'] ?? 'Stolný futbal',
                'full_description' => $translations['service_table-football_full'] ?? '',
                'image' => '/public/images/football.webp',
                'price_morning' => '5',
                'price_afternoon' => '6',
                'price_evening' => '8',
                'price_holiday' => '10',
                'tables_count' => 2
            ]
        ];

        $services[] = [
            'id' => 5,
            'name' => $translations['service_shuffleboard_name'] ?? 'Shuffleboard',
            'short_description' => $translations['service_shuffleboard_short'] ?? 'Shuffleboard',
            'full_description' => $translations['service_shuffleboard_full'] ?? '',
            'image' => '/public/images/shuffleboard.webp',
            'price_morning' => '5',
            'price_afternoon' => '6',
            'price_evening' => '8',
            'price_holiday' => '10',
            'tables_count' => 1
        ];

        $events = $this->getEvents();

        return $this->renderWithSeo('home.twig', 'home', [
            'services' => $services,
            'events' => $events,
            'current_page' => 'home'
        ]);
    }

    public function games()
    {
        return $this->renderWithSeo('games.twig', 'games', [
            'current_page' => 'games'
        ]);
    }

    public function pricing()
    {
        return $this->renderWithSeo('pricing.twig', 'pricing', [
            'current_page' => 'pricing',
            'pricing_tables' => $this->buildPricingTables(),
        ]);
    }

    /**
     * Price list per service built from the pricing table and opening hours.
     * Each day gets bands of consecutive open hours with the same price;
     * consecutive days with identical bands are merged (e.g. Monday - Thursday).
     * Holidays use Saturday prices, as the booking price calculation does.
     */
    private function buildPricingTables(): array
    {
        $db = \App\Database\Database::getInstance();
        $rows = $db->query(
            "SELECT service_id, day_of_week, HOUR(start_time) AS h, price_per_hour
             FROM pricing WHERE is_holiday_pricing = 0"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $prices = [];
        foreach ($rows as $r) {
            $prices[(int)$r['service_id']][(int)$r['day_of_week']][(int)$r['h']] = (float)$r['price_per_hour'];
        }

        $week = \App\Services\OpeningHours::week();
        $format = fn(float $p) => rtrim(rtrim(number_format($p, 2, '.', ''), '0'), '.');
        $time = fn(int $h) => sprintf('%02d:00', $h % 24);

        // bands of one day: consecutive hours with the same price
        $bandsFor = function (array $byHour, int $open, int $close) use ($format, $time): array {
            $bands = [];
            for ($h = $open; $h < $close; $h++) {
                if (!isset($byHour[$h])) {
                    continue;
                }
                $i = count($bands) - 1;
                if ($i >= 0 && $bands[$i]['price_value'] === $byHour[$h] && $bands[$i]['to_hour'] === $h) {
                    $bands[$i]['to_hour'] = $h + 1;
                    continue;
                }
                $bands[] = ['from_hour' => $h, 'to_hour' => $h + 1, 'price_value' => $byHour[$h]];
            }
            $first = $bands[0]['price_value'] ?? null;

            return array_map(fn(array $b) => [
                'from' => $time($b['from_hour']),
                'to' => $time($b['to_hour']),
                'price' => $format($b['price_value']),
                // evening tariff: dearer than the first band of the same day
                'prime' => $first !== null && $b['price_value'] > $first,
            ], $bands);
        };

        $tables = [];
        foreach ($prices as $serviceId => $byDay) {
            $groups = [];
            foreach ($week as $day => [$open, $close]) {
                $bands = $bandsFor($byDay[$day] ?? [], $open, $close);
                $i = count($groups) - 1;
                if ($i >= 0 && $groups[$i]['to'] === $day - 1 && $groups[$i]['bands'] === $bands) {
                    $groups[$i]['to'] = $day;
                    continue;
                }
                $groups[] = ['from' => $day, 'to' => $day, 'holiday' => false, 'bands' => $bands];
            }

            // holiday hours follow its weekday, so only the start of each band is shown
            [$satOpen, $satClose] = $week[6];
            $groups[] = [
                'holiday' => true,
                'bands' => $bandsFor($byDay[6] ?? [], $satOpen, $satClose),
            ];

            $tables[$serviceId] = $groups;
        }

        return $tables;
    }

    public function deals()
    {
        $db = \App\Database\Database::getInstance();
        $translations = $this->translationService->getUITranslations($this->language);

        $dealsController = new \DealsController($db, $translations, $this->language);
        $data = $dealsController->index();
        $data['current_page'] = 'deals';

        // Manually render with SEO since we are capturing data from another controller
        // Or refactor helper. For now let's use renderWithSeo
        return $this->renderWithSeo('deals.twig', 'deals', $data);
    }

    public function cafe()
    {
        return $this->renderWithSeo('cafe.twig', 'cafe', [
            'current_page' => 'cafe'
        ]);
    }

    public function contact()
    {
        return $this->renderWithSeo('contact.twig', 'contact', [
            'current_page' => 'contact'
        ]);
    }

    public function privacy()
    {
        return $this->renderWithSeo('privacy.twig', 'privacy', [
            'current_page' => 'privacy'
        ]);
    }

    public function terms()
    {
        return $this->renderWithSeo('terms.twig', 'terms', [
            'current_page' => 'terms'
        ]);
    }

    public function cookies()
    {
        // Re-using privacy template but scrolling to cookies section could be an option, 
        // or just render the same privacy page for now as cookies info is there.
        // Or better: pass a variable to highlight/scroll to cookies.
        return $this->renderWithSeo('privacy.twig', 'privacy', [
            'current_page' => 'cookies',
            'scroll_to' => 'cookies' // logic to be handled in js or manually
        ]);
    }

    public function blog()
    {
        $posts = [];
        $pagination = [
            'current_page' => 1,
            'total_pages' => 1
        ];

        return $this->render('blog.twig', [
            'posts' => $posts,
            'pagination' => $pagination,
            'current_page' => 'blog'
        ]);
    }

    public function post($slug)
    {
        $post = [
            'title' => 'Example Post',
            'content' => '<p>Content will be loaded from database</p>',
            'published_at' => new \DateTime(),
            'image' => null,
            'category' => null,
            'author' => null,
            'tags' => []
        ];

        return $this->render('post.twig', [
            'post' => $post,
            'prev_post' => null,
            'next_post' => null
        ]);
    }
}
