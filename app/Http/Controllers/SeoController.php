<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SeoService;
use Medoo\Medoo;
use Symfony\Component\HttpFoundation\Response;

class SeoController extends BaseController
{
    private const SITEMAP_MAX_URLS = 50000;
    private const SITEMAP_CHUNK_SIZE = 1000;

    public function __construct(
        private readonly SeoService $seoService,
        private readonly Medoo $db
    ) {
        parent::__construct();
    }

    public function sitemap(): void
    {
        $type = $this->get('type', 'index');
        $page = (int)$this->get('page', 1);

        switch ($type) {
            case 'index':
                $xml = $this->generateSitemapIndex();
                break;
            case 'articles':
                $xml = $this->generateArticleSitemap($page);
                break;
            case 'categories':
                $xml = $this->generateCategorySitemap();
                break;
            case 'pages':
                $xml = $this->generatePageSitemap();
                break;
            default:
                $xml = $this->generateSitemapIndex();
        }

        $response = new Response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
        \App\Http\ResponseContext::set($response);
    }

    private function generateSitemapIndex(): string
    {
        $baseUrl = $this->getBaseUrl();
        $totalArticles = $this->db->count('sys_content', ['status' => 1]);
        $totalPages = ceil($totalArticles / self::SITEMAP_CHUNK_SIZE);
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        $xml .= '  <sitemap>' . "\n";
        $xml .= '    <loc>' . $baseUrl . '/seo/sitemap?type=categories</loc>' . "\n";
        $xml .= '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
        $xml .= '  </sitemap>' . "\n";
        
        $xml .= '  <sitemap>' . "\n";
        $xml .= '    <loc>' . $baseUrl . '/seo/sitemap?type=pages</loc>' . "\n";
        $xml .= '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
        $xml .= '  </sitemap>' . "\n";
        
        if ($totalArticles > 0) {
            $xml .= '  <sitemap>' . "\n";
            $xml .= '    <loc>' . $baseUrl . '/seo/sitemap?type=articles&amp;page=1</loc>' . "\n";
            $xml .= '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
            $xml .= '  </sitemap>' . "\n";
        }
        
        $xml .= '</sitemapindex>';
        
        return $xml;
    }

    private function generateArticleSitemap(int $page): string
    {
        $baseUrl = $this->getBaseUrl();
        $offset = ($page - 1) * self::SITEMAP_CHUNK_SIZE;
        
        $list = $this->db->select('sys_content', [
            'id', 'updated_at', 'published_at'
        ], [
            'status' => 1,
            'ORDER' => ['id' => 'DESC'],
            'LIMIT' => [$offset, self::SITEMAP_CHUNK_SIZE]
        ]);
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        foreach ($list as $article) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . $baseUrl . '/article/' . $article['id'] . '.html</loc>' . "\n";
            $xml .= '    <lastmod>' . date('Y-m-d', strtotime($article['updated_at'] ?? $article['published_at'])) . '</lastmod>' . "\n";
            $xml .= '    <changefreq>weekly</changefreq>' . "\n";
            $xml .= '    <priority>0.8</priority>' . "\n";
            $xml .= '  </url>' . "\n";
        }
        
        $xml .= '</urlset>';
        
        return $xml;
    }

    private function generateCategorySitemap(): string
    {
        $baseUrl = $this->getBaseUrl();
        
        $categories = $this->db->select('sys_category', [
            'id', 'slug'
        ], [
            'slug[!=]' => ''
        ]);
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        foreach ($categories as $category) {
            if ($category['slug']) {
                $xml .= '  <url>' . "\n";
                $xml .= '    <loc>' . $baseUrl . '/c/' . $category['slug'] . '</loc>' . "\n";
                $xml .= '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
                $xml .= '    <changefreq>daily</changefreq>' . "\n";
                $xml .= '    <priority>0.9</priority>' . "\n";
                $xml .= '  </url>' . "\n";
            }
        }
        
        $xml .= '</urlset>';
        
        return $xml;
    }

    private function generatePageSitemap(): string
    {
        $baseUrl = $this->getBaseUrl();
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        $xml .= '  <url>' . "\n";
        $xml .= '    <loc>' . $baseUrl . '/</loc>' . "\n";
        $xml .= '    <changefreq>daily</changefreq>' . "\n";
        $xml .= '    <priority>1.0</priority>' . "\n";
        $xml .= '  </url>' . "\n";
        
        $xml .= '  <url>' . "\n";
        $xml .= '    <loc>' . $baseUrl . '/search</loc>' . "\n";
        $xml .= '    <changefreq>weekly</changefreq>' . "\n";
        $xml .= '    <priority>0.5</priority>' . "\n";
        $xml .= '  </url>' . "\n";
        
        $xml .= '</urlset>';
        
        return $xml;
    }

    public function robots(): void
    {
        $baseUrl = $this->getBaseUrl();
        
        $robots = "User-agent: *\n";
        $robots .= "Allow: /\n";
        $robots .= "Disallow: /admin/\n";
        $robots .= "Disallow: /api/\n";
        $robots .= "Disallow: /?*\n";
        $robots .= "\n";
        $robots .= "Sitemap: " . $baseUrl . "/seo/sitemap\n";
        
        $response = new Response($robots, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
        \App\Http\ResponseContext::set($response);
    }

    public function extractKeywords(): void
    {
        try {
            $content = $this->post('content', '');

            if (empty($content)) {
                $this->json(['success' => false, 'message' => '内容不能为空']);
                return;
            }

            $keywords = $this->seoService->extractKeywordsLocal($content);
            $description = $this->seoService->generateAutoDescription($content);

            $this->json([
                'success' => true,
                'keywords' => $keywords,
                'description' => $description
            ]);
        } catch (\Throwable $e) {
            if ($e instanceof \App\Http\ResponseHaltException) {
                throw $e;
            }
            error_log('关键词提取失败: ' . $e->getMessage());
            $this->json([
                'success' => false,
                'message' => '关键词提取失败，请稍后重试'
            ]);
        }
    }

    private function getBaseUrl(): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }
}
