<?php
declare(strict_types=1);

namespace App\Services;

use Medoo\Medoo;

class SeoService
{
    private const STOPWORDS = [
        '的', '是', '了', '在', '和', '与', '或', '及', '等', '为',
        '之', '于', '以', '而', '但', '若', '则', '因', '所', '从',
        '对', '把', '被', '让', '给', '向', '到', '过', '来', '去',
        '这', '那', '他', '她', '它', '们', '你', '我', '自己',
        '不', '有', '没', '无', '是', '否', '可', '能', '要', '会',
        '就', '也', '都', '而', '且', '如', '若', '因为', '所以',
        '虽然', '但是', '然而', '并且', '或者', '还是', '若是',
        '一个', '一些', '这个', '那个', '什么', '怎样', '如何',
        '可以', '应该', '需要', '必须', '可能', '应当', '值得'
    ];

    public function __construct(
        private readonly Medoo $db
    ) {
    }

    public function generateMetaTitle(string $title, ?string $siteName = null): string
    {
        $maxLength = 60;
        $separator = ' - ';
        
        if (empty($siteName)) {
            $siteName = $this->getSiteName();
        }
        
        $fullTitle = trim($title) . $separator . $siteName;
        
        if (mb_strlen($fullTitle, 'UTF-8') > $maxLength) {
            return mb_substr(trim($title), 0, $maxLength - mb_strlen($siteName) - strlen($separator), 'UTF-8') . $separator . $siteName;
        }
        
        return $fullTitle;
    }

    public function generateMetaDescription(?string $description, int $maxLength = 155): string
    {
        if (empty($description)) {
            return '';
        }
        
        $description = strip_tags($description);
        $description = preg_replace('/\s+/', ' ', $description);
        $description = trim($description);
        
        if (mb_strlen($description, 'UTF-8') > $maxLength) {
            return mb_substr($description, 0, $maxLength - 3, 'UTF-8') . '...';
        }
        
        return $description;
    }

    public function generateCanonicalUrl(string $path): string
    {
        $baseUrl = $this->getSiteUrl();
        $path = trim($path, '/');
        $path = strtolower($path);
        
        if (empty($path)) {
            return $baseUrl . '/';
        }
        
        return $baseUrl . '/' . $path;
    }

    public function generateOpenGraphTags(array $data): string
    {
        $tags = [];
        
        $tags[] = '<meta property="og:type" content="website" />';
        $tags[] = '<meta property="og:site_name" content="' . htmlspecialchars($this->getSiteName()) . '" />';
        
        if (isset($data['title'])) {
            $tags[] = '<meta property="og:title" content="' . htmlspecialchars($this->generateMetaTitle($data['title'])) . '" />';
        }
        
        if (isset($data['description'])) {
            $tags[] = '<meta property="og:description" content="' . htmlspecialchars($this->generateMetaDescription($data['description'])) . '" />';
        }
        
        if (isset($data['url'])) {
            $tags[] = '<meta property="og:url" content="' . htmlspecialchars($data['url']) . '" />';
        }
        
        if (isset($data['image'])) {
            $tags[] = '<meta property="og:image" content="' . htmlspecialchars($data['image']) . '" />';
        }
        
        return implode("\n    ", $tags);
    }

    public function generateArticleSchema(array $article): string
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $article['title'] ?? '',
            'description' => $this->generateMetaDescription($article['summary'] ?? ''),
            'datePublished' => $article['published_at'] ?? '',
            'dateModified' => $article['updated_at'] ?? '',
        ];
        
        if (!empty($article['cover_img'])) {
            $schema['image'] = $this->getSiteUrl() . $article['cover_img'];
        }
        
        return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE) . '</script>';
    }

    public function generateBreadcrumbSchema(array $breadcrumbs): string
    {
        $items = [];
        
        foreach ($breadcrumbs as $index => $crumb) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['title'] ?? '',
                'item' => $crumb['url'] ?? ''
            ];
        }
        
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items
        ];
        
        return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE) . '</script>';
    }

    public function generateOrganizationSchema(): string
    {
        $siteName = $this->getSiteName();
        $siteUrl = $this->getSiteUrl();
        
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $siteName,
            'url' => $siteUrl,
            'logo' => $siteUrl . '/logo.png'
        ];
        
        return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE) . '</script>';
    }

    public function extractKeywordsLocal(string $content, int $limit = 10): array
    {
        if (empty($content) || !is_string($content)) {
            return [];
        }
        
        $content = strip_tags($content);
        if (empty($content)) {
            return [];
        }
        
        $content = (string)preg_replace('/[^\x{4e00}-\x{9fa5}a-zA-Z0-9]/u', ' ', $content);
        $content = (string)preg_replace('/\s+/', ' ', $content);
        $content = strtolower(trim($content));
        
        if (empty($content)) {
            return [];
        }
        
        $words = explode(' ', $content);
        $wordFreq = [];
        
        foreach ($words as $word) {
            $word = trim($word);
            if (mb_strlen($word, 'UTF-8') < 2) {
                continue;
            }
            
            if (in_array($word, self::STOPWORDS, true)) {
                continue;
            }
            
            $wordFreq[$word] = ($wordFreq[$word] ?? 0) + 1;
        }
        
        // 如果没有有效词，直接返回空
        if (empty($wordFreq)) {
            return [];
        }

        // 计算 TF-IDF，若数据库访问失败则退化为纯频次排序
        $wordScores = [];
        try {
            $totalDocs = max($this->getTotalArticleCount(), 1);
            foreach ($wordFreq as $word => $freq) {
                $docCount = $this->safeGetWordDocumentCount($word);
                $idf = log($totalDocs / (1 + $docCount));
                $wordScores[$word] = $freq * $idf;
            }
        } catch (\Throwable $e) {
            error_log('关键词提取 IDF 计算失败: ' . $e->getMessage());
            // 回退为词频
            $wordScores = $wordFreq;
        }
        
        arsort($wordScores);
        
        return array_slice(array_keys($wordScores), 0, $limit);
    }

    public function generateAutoDescription(string $content, int $maxLength = 155): string
    {
        if (empty($content) || !is_string($content)) {
            return '';
        }
        
        $content = strip_tags($content);
        if (empty($content)) {
            return '';
        }
        
        $content = (string)preg_replace('/\s+/', ' ', trim($content));
        if (empty($content)) {
            return '';
        }
        
        $sentences = preg_split('/[。！？.!?]/', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
        $description = '';
        
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (mb_strlen($sentence, 'UTF-8') > 10) {
                $description = $sentence;
                break;
            }
        }
        
        if (empty($description) && !empty($sentences)) {
            $firstSentence = trim($sentences[0]);
            if (!empty($firstSentence)) {
                $description = $firstSentence;
            }
        }
        
        return $this->generateMetaDescription($description, $maxLength);
    }

    public function checkSeoIssues(array $article): array
    {
        $issues = [];
        
        $title = $article['title'] ?? '';
        $description = $article['summary'] ?? '';
        $keywords = $article['keywords'] ?? '';
        
        if (empty($title)) {
            $issues[] = ['type' => 'error', 'message' => '标题不能为空'];
        } elseif (mb_strlen($title, 'UTF-8') > 60) {
            $issues[] = ['type' => 'warning', 'message' => '标题过长，可能被搜索引擎截断'];
        } elseif (mb_strlen($title, 'UTF-8') < 10) {
            $issues[] = ['type' => 'info', 'message' => '标题过短，建议增加长度'];
        }
        
        if (empty($description)) {
            $issues[] = ['type' => 'warning', 'message' => '描述为空，建议添加页面描述'];
        } elseif (mb_strlen($description, 'UTF-8') > 155) {
            $issues[] = ['type' => 'info', 'message' => '描述过长，可能被截断'];
        }
        
        if (empty($keywords)) {
            $issues[] = ['type' => 'info', 'message' => '未设置关键词'];
        }
        
        return $issues;
    }

    private function getSiteName(): string
    {
        return $this->db->get('sys_setting', 'value', ['key' => 'site_title']) ?? 'YYCMS';
    }

    private function getSiteUrl(): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }

    private function getTotalArticleCount(): int
    {
        try {
            return (int)$this->db->count('sys_content', ['status' => 1]);
        } catch (\Throwable $e) {
            error_log('获取文章总数失败: ' . $e->getMessage());
            return 1;
        }
    }

    private function getWordDocumentCount(string $word): int
    {
        return (int)$this->db->count('sys_content', [
            'OR' => [
                'title[~]' => $word,
                'content[~]' => $word,
                'summary[~]' => $word
            ]
        ]);
    }

    /**
     * 安全获取词出现的文档数，异常时返回0
     */
    private function safeGetWordDocumentCount(string $word): int
    {
        try {
            return $this->getWordDocumentCount($word);
        } catch (\Throwable $e) {
            error_log('获取词文档数失败: ' . $e->getMessage());
            return 0;
        }
    }
}
