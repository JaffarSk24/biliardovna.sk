<?php

class DealsController {
    private $db;
    private $lang;
    private $currentLang;

    public function __construct($db, $lang, $currentLang) {
        $this->db = $db;
        $this->lang = $lang;
        $this->currentLang = $currentLang;
    }

    public function index() {
        $deals = $this->getActiveDeals();
        $translations = $this->lang;

        // Load services (matching PageController logic)
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
        
        return [
            'deals' => $deals,
            'lang' => $this->lang,
            'services' => $services
        ];
    }

    private function getActiveDeals() {
        $stmt = $this->db->prepare("
            SELECT * FROM deals 
            WHERE is_active = 1 
            AND (end_date IS NULL OR end_date >= CURDATE())
            ORDER BY sort_order ASC, created_at DESC
        ");
        $stmt->execute();
        $dealsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $deals = [];
        foreach ($dealsRaw as $deal) {
            $deals[] = [
                'id' => $deal['id'],
                'title' => $deal['title_' . $this->currentLang] ?: $deal['title_sk'],
                'description' => $deal['description_' . $this->currentLang] ?: $deal['description_sk'],
                'benefit' => $deal['benefit_' . $this->currentLang] ?: $deal['benefit_sk'],
                'image' => $deal['image'],
                'trigger' => $deal['trigger_data']
            ];
        }
        
        return $deals;
    }
}