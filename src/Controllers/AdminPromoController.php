<?php

namespace App\Controllers;

use App\Repositories\ContentRepository;
use App\Database\Database;

class AdminPromoController extends Controller
{
    public function __construct(string $language = 'sk')
    {
        parent::__construct($language);
        $this->checkAuth();
    }

    private function checkAuth(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['admin_logged_in'])) {
            header('Location: /admin/login');
            exit;
        }
    }

    public function index(): void
    {
        $pdo = Database::getInstance();
        $items = ContentRepository::getAllPromos($pdo);
        $this->render('admin/promo_list.twig', ['items' => $items]);
    }

    public function create(): void
    {
        $this->form();
    }

    public function edit(): void
    {
        $this->form();
    }

    private function form(): void
    {
        $pdo = Database::getInstance();
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        $data = ContentRepository::getPromoForEdit($pdo, $id);
        
        $this->render('admin/promo_form.twig', [
            'item' => $data['item'],
            'promo' => $data['promo'],
            'errors' => [],
            'languages' => ['sk','ru','uk'],
            'statuses' => ['draft','published','archived'],
            'services' => [
                ['id' => 1, 'name' => 'Pyramída'],
                ['id' => 2, 'name' => 'Pool'],
                ['id' => 3, 'name' => 'Šípky'],
                ['id' => 4, 'name' => 'Stolný futbal'],
            ],
        ]);
    }

    public function save(): void
    {
        $pdo = Database::getInstance();
        
        $item = [
            'id' => isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null,
            'slug' => trim((string)($_POST['slug'] ?? '')),
            'title' => trim((string)($_POST['title'] ?? '')),
            'subtitle' => trim((string)($_POST['subtitle'] ?? '')),
            'excerpt' => trim((string)($_POST['excerpt'] ?? '')),
            'hero_image' => trim((string)($_POST['hero_image'] ?? '')),
            'language' => (string)($_POST['language'] ?? 'sk'),
            'body_html' => (string)($_POST['body_html'] ?? ''),
            'status' => (string)($_POST['status'] ?? 'draft'),
            'featured' => isset($_POST['featured']) ? 1 : 0,
            'publish_at' => (string)($_POST['publish_at'] ?? null),
        ];
        $promo = [
            'coupon_code' => trim((string)($_POST['coupon_code'] ?? '')),
            'service_id' => isset($_POST['service_id']) && $_POST['service_id'] !== '' ? (int)$_POST['service_id'] : null,
            'min_slots' => (int)($_POST['min_slots'] ?? 1),
            'allowed_weekdays' => is_array($_POST['weekdays'] ?? null) ? implode(',', array_map('intval', $_POST['weekdays'])) : (string)($_POST['allowed_weekdays'] ?? ''),
            'time_start' => (string)($_POST['time_start'] ?? null),
            'time_end' => (string)($_POST['time_end'] ?? null),
            'date_start' => (string)($_POST['date_start'] ?? null),
            'date_end' => (string)($_POST['date_end'] ?? null),
            'auto_apply' => isset($_POST['auto_apply']) ? 1 : 0,
        ];

        $errors = [];
        if ($item['slug'] === '') $errors['slug'] = 'Slug is required';
        if ($item['title'] === '') $errors['title'] = 'Title is required';
        if ($promo['coupon_code'] === '') $errors['coupon_code'] = 'Coupon code is required';
        if (!in_array($item['language'], ['sk','ru','uk'], true)) $errors['language'] = 'Invalid language';
        if (!in_array($item['status'], ['draft','published','archived'], true)) $item['status'] = 'draft';

        if ($errors) {
            $this->render('admin/promo_form.twig', [
                'item' => $item,
                'promo' => $promo,
                'errors' => $errors,
                'languages' => ['sk','ru','uk'],
                'statuses' => ['draft','published','archived'],
                'services' => [
                    ['id' => 1, 'name' => 'Pyramída'],
                    ['id' => 2, 'name' => 'Pool'],
                    ['id' => 3, 'name' => 'Šípky'],
                    ['id' => 4, 'name' => 'Stolný futbal'],
                ],
            ]);
            return;
        }

        try {
            $id = ContentRepository::savePromo($pdo, $item, $promo);
            $this->redirect('/admin/promo/edit?id=' . $id);
        } catch (\Exception $e) {
            // In a real app we'd pass the error to the view
            // For now just error out or show form with error
            $errors['global'] = $e->getMessage();
             $this->render('admin/promo_form.twig', [
                'item' => $item,
                'promo' => $promo,
                'errors' => $errors,
                'languages' => ['sk','ru','uk'],
                'statuses' => ['draft','published','archived'],
                'services' => [
                    ['id' => 1, 'name' => 'Pyramída'],
                    ['id' => 2, 'name' => 'Pool'],
                    ['id' => 3, 'name' => 'Šípky'],
                    ['id' => 4, 'name' => 'Stolný futbal'],
                ],
            ]);
        }
    }
}