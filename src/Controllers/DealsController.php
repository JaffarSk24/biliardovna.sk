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
        
        return [
            'deals' => $deals,
            'lang' => $this->lang
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