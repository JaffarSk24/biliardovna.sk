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
            'og_image' => $baseUri . ($meta['og:image'] ?? '/public/images/og/default.jpg'),
            'og_url' => $canonical,
            'canonical' => $canonical,
            'current_language' => $this->language,
            'alternates' => [] // Could be calculated if we map pages to routes
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
                'tables_count' => 5
            ]
            /*[
                'id' => 4,
                'name' => $translations['service_table-football_name'] ?? 'Stolný futbal',
                'short_description' => $translations['service_table-football_short'] ?? 'Stolný futbal',
                'full_description' => $translations['service_table-football_full'] ?? '',
                'image' => '/public/images/football.webp',
                'price_morning' => '4',
                'price_afternoon' => '5',
                'price_evening' => '6',
                'price_holiday' => '8',
                'tables_count' => 2
            ]*/
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
            'current_page' => 'pricing'
        ]);
    }

    public function deals() {
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