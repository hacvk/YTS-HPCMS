<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Content;
use App\Models\Tag;
use Medoo\Medoo;

class RecommendationService
{
    public function __construct(
        private readonly Medoo $db
    ) {
    }

    public function getRelatedArticles(int $contentId, int $limit = 5): array
    {
        $tagModel = new Tag($this->db);
        $tags = $tagModel->getByContentId($contentId);
        
        if (empty($tags)) {
            return $this->getFallbackRecommendations($contentId, $limit);
        }
        
        $tagIds = array_column($tags, 'id');
        
        $list = $this->db->select(
            Content::TABLE . '(c)',
            [
                '[>]' . Tag::CONTENT_TAGS_TABLE . '(ct)' => ['c.id' => 'content_id']
            ],
            [
                'c.id', 'c.category_id', 'c.title', 'c.cover_img', 'c.summary', 
                'c.status', 'c.views', 'c.published_at', 'c.updated_at',
                'ct.tag_id'
            ],
            [
                'ct.tag_id' => $tagIds,
                'c.id[!]' => $contentId,
                'c.status' => 1,
                'GROUP' => 'c.id',
                'ORDER' => ['c.published_at' => 'DESC'],
                'LIMIT' => $limit
            ]
        );
        
        if (empty($list)) {
            return $this->getFallbackRecommendations($contentId, $limit);
        }
        
        return $list;
    }

    public function getCategoryBasedRecommendations(int $contentId, int $categoryId, int $limit = 5): array
    {
        $contentModel = new Content($this->db);
        $list = $contentModel->getList(1, $limit + 1, [
            'cid' => $categoryId
        ]);
        
        $articles = array_filter($list['list'], function($article) use ($contentId) {
            return $article['id'] != $contentId;
        });
        
        return array_slice(array_values($articles), 0, $limit);
    }

    public function getPopularArticles(int $limit = 5): array
    {
        $contentModel = new Content($this->db);
        return $contentModel->getList(1, $limit, [])['list'];
    }

    public function getRecentArticles(int $limit = 5): array
    {
        $contentModel = new Content($this->db);
        return $contentModel->getList(1, $limit, [])['list'];
    }

    public function getTagBasedRecommendations(array $tagNames, int $excludeId, int $limit = 5): array
    {
        if (empty($tagNames)) {
            return [];
        }
        
        $list = $this->db->select(
            Content::TABLE . '(c)',
            [
                '[>]' . Tag::CONTENT_TAGS_TABLE . '(ct)' => ['c.id' => 'content_id'],
                '[>]' . Tag::TABLE . '(t)' => ['ct.tag_id' => 'id']
            ],
            [
                'c.id', 'c.category_id', 'c.title', 'c.cover_img', 'c.summary', 
                'c.status', 'c.views', 'c.published_at', 'c.updated_at'
            ],
            [
                't.name' => $tagNames,
                'c.id[!]' => $excludeId,
                'c.status' => 1,
                'ORDER' => ['c.published_at' => 'DESC'],
                'LIMIT' => $limit
            ]
        );
        
        return $list ?: [];
    }

    private function getFallbackRecommendations(int $contentId, int $limit): array
    {
        $contentModel = new Content($this->db);
        $list = $contentModel->getList(1, $limit + 1, []);
        
        $articles = array_filter($list['list'], function($article) use ($contentId) {
            return $article['id'] != $contentId;
        });
        
        return array_slice(array_values($articles), 0, $limit);
    }

    public function calculateSimilarityScore(int $contentId1, int $contentId2): float
    {
        $tagModel = new Tag($this->db);
        $tags1 = $tagModel->getByContentId($contentId1);
        $tags2 = $tagModel->getByContentId($contentId2);
        
        if (empty($tags1) || empty($tags2)) {
            return 0.0;
        }
        
        $tagIds1 = array_column($tags1, 'id');
        $tagIds2 = array_column($tags2, 'id');
        
        $commonTags = array_intersect($tagIds1, $tagIds2);
        $totalTags = count(array_unique(array_merge($tagIds1, $tagIds2)));
        
        if ($totalTags === 0) {
            return 0.0;
        }
        
        return count($commonTags) / $totalTags;
    }
}
