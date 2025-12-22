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
        
        $events = $this->getEvents();
        
        return $this->render('home.twig', [
            'services' => $services,
            'events' => $events,
            'current_page' => 'home'
        ]);
    }

    public function games()
    {
        return $this->render('games.twig', [
            'current_page' => 'games'
        ]);
    }

    public function pricing()
    {
        return $this->render('pricing.twig', [
            'current_page' => 'pricing'
        ]);
    }

    public function deals() {
        $db = \App\Database\Database::getInstance();
        $translations = $this->translationService->getUITranslations($this->language);
        
        $dealsController = new \DealsController($db, $translations, $this->language);
        $data = $dealsController->index();
        $data['current_page'] = 'deals';
        
        echo $this->twig->render('deals.twig', $data);
    }

    public function cafe()
    {
        return $this->render('cafe.twig', [
            'current_page' => 'cafe'
        ]);
    }

    public function contact()
    {
        return $this->render('contact.twig', [
            'current_page' => 'contact'
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