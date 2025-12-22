<?php

namespace App\Controllers;

use App\Router;
use Twig\Environment;

class BlogController
{
    private Environment $twig;
    private Router $router;
    private string $language;

    public function __construct(Environment $twig, Router $router)
    {
        $this->twig = $twig;
        $this->router = $router;
        $this->language = $router->getLanguage();
    }

    public function index(): void
    {
        // Get articles from database
        try {
            $pdo = \App\Database\Database::getInstance();
            
            $stmt = $pdo->prepare("
            SELECT id, title, slug, excerpt, image, category, article_group_id,
            COALESCE(DATE_FORMAT(date, '%d.%m.%Y'), DATE_FORMAT(created_at, '%d.%m.%Y')) as date
            FROM articles 
            WHERE language = :language 
            ORDER BY priority DESC, created_at DESC
            ");
            $stmt->execute(['language' => $this->language]);
            $articles = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            $articles = [];
        }
        
        // Load translations from file
        $translationsFile = __DIR__ . '/../../translations/' . $this->language . '.php';
        $translations = file_exists($translationsFile) ? require $translationsFile : [];
        
        echo $this->twig->render('blog.twig', [
            'language' => $this->language,
            'translations' => $translations,
            'articles' => $articles,
            'current_page' => 'blog'
        ]);
    }

    public function show(string $slug): void
    {
        // Get article from database
        try {
        $pdo = \App\Database\Database::getInstance();
        
        $stmt = $pdo->prepare("
        SELECT id, title, slug, excerpt, content, image, category, article_group_id,
        COALESCE(DATE_FORMAT(date, '%d.%m.%Y'), DATE_FORMAT(created_at, '%d.%m.%Y')) as date
        FROM articles 
        WHERE slug = :slug
        ");
        $stmt->execute(['slug' => $slug]);
        $article = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$article) {
            http_response_code(404);
            echo "404 - Article Not Found";
            return;
        }
        
        // Get all language versions of this article
        $stmtLangs = $pdo->prepare("
        SELECT language, slug 
        FROM articles 
        WHERE article_group_id = :group_id
        ");
        $stmtLangs->execute(['group_id' => $article['article_group_id']]);
        $languageVersions = $stmtLangs->fetchAll(\PDO::FETCH_KEY_PAIR);
        
        // If current language version exists and differs from current slug, redirect
        if (isset($languageVersions[$this->language]) && $languageVersions[$this->language] !== $slug) {
            header('Location: ' . $this->router->url('/blog/' . $languageVersions[$this->language]));
            exit;
        }
        
        } catch (\Exception $e) {
        http_response_code(404);
        echo "404 - Article Not Found";
        return;
        }
        
        // Load translations from file
        $translationsFile = __DIR__ . '/../../translations/' . $this->language . '.php';
        $translations = file_exists($translationsFile) ? require $translationsFile : [];
        
        echo $this->twig->render('article.twig', [
            'language' => $this->language,
            'translations' => $translations,
            'article' => $article,
            'current_page' => 'blog'
        ]);
    }
}