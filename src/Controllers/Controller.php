<?php

namespace App\Controllers;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use App\Services\TranslationService;

abstract class Controller
{
    protected Environment $twig;
    protected TranslationService $translationService;
    protected string $language;
    
    public function __construct(string $language = 'sk')
    {
        $this->language = $language;
        $this->translationService = new TranslationService();
        $this->initializeTwig();
    }

    protected function trans(string $key): string
    {
        $translations = $this->translationService->getUITranslations($this->language);
        return $translations[$key] ?? $key;
    }
    
    private function initializeTwig(): void
    {
        $loader = new FilesystemLoader(realpath(__DIR__ . '/../../templates'));
        $config = require __DIR__ . '/../../config/app.php';
        
        $this->twig = new Environment($loader, [
            'cache' => $config['debug'] ? false : __DIR__ . '/../../storage/cache',
            'debug' => $config['debug'],
        ]);
        
        // Add global variables
        $this->twig->addGlobal('language', $this->language);
        $this->twig->addGlobal('current_lang', $this->language);
        $this->twig->addGlobal('translations', $this->translationService->getUITranslations($this->language));
        $this->twig->addGlobal('t', $this->translationService->getUITranslations($this->language));
        
        // Add trans filter
        $translationService = $this->translationService;
        $language = $this->language;
        $this->twig->addFilter(new TwigFilter('trans', function ($key) use ($translationService, $language) {
            $translations = $translationService->getUITranslations($language);
            return $translations[$key] ?? $key;
        }));
    }
    
    protected function render(string $template, array $data = []): void
    {
        echo $this->twig->render($template, $data);
    }
    
    protected function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
    
    protected function redirect(string $url): void
    {
        header("Location: {$url}");
        exit;
    }
}