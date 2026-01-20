<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Exception\DatabaseOperationException;
use Medoo\Medoo;

class Tag
{
    public const CONTENT_TAGS_TABLE = 'content_tags';
    public const TABLE = 'sys_tags';

    public function __construct(
        private readonly Medoo $db
    ) {
    }

    public function getAll(): array
    {
        return $this->db->select(self::TABLE, '*', [
            'ORDER' => ['usage_count' => 'DESC', 'name' => 'ASC']
        ]);
    }

    public function getById(int $id): ?array
    {
        $tag = $this->db->get(self::TABLE, '*', ['id' => $id]);
        return $tag ?: null;
    }

    public function getByName(string $name): ?array
    {
        $tag = $this->db->get(self::TABLE, '*', ['name' => $name]);
        return $tag ?: null;
    }

    public function getByContentId(int $contentId): array
    {
        $result = $this->db->select(
            self::TABLE . '(t)',
            [
                '[>]' . self::CONTENT_TAGS_TABLE . '(ct)' => ['id' => 'tag_id']
            ],
            [
                't.id', 't.name', 't.slug', 't.usage_count', 't.created_at', 't.updated_at'
            ],
            [
                'ct.content_id' => $contentId,
                'ORDER' => ['t.name' => 'ASC']
            ]
        );
        
        return $result ?: [];
    }

    public function create(array $data): int
    {
        $slug = $this->generateSlug($data['name']);
        
        $tagData = [
            'name' => trim($data['name']),
            'slug' => $slug,
            'usage_count' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->insert(self::TABLE, $tagData);
        
        if ($this->db->error) {
            throw new DatabaseOperationException('创建标签失败: ' . $this->db->error);
        }
        
        return (int)$this->db->id();
    }

    public function update(int $id, array $data): void
    {
        $updateData = [];
        
        if (isset($data['name'])) {
            $updateData['name'] = trim($data['name']);
            $updateData['slug'] = $this->generateSlug($updateData['name']);
        }
        
        if (!empty($updateData)) {
            $this->db->update(self::TABLE, $updateData, ['id' => $id]);
            
            if ($this->db->error) {
                throw new DatabaseOperationException('更新标签失败: ' . $this->db->error);
            }
        }
    }

    public function delete(int $id): void
    {
        $this->db->delete(self::TABLE, ['id' => $id]);
        
        if ($this->db->error) {
            throw new DatabaseOperationException('删除标签失败: ' . $this->db->error);
        }
    }

    public function findOrCreate(string $name): int
    {
        $existing = $this->getByName($name);
        
        if ($existing) {
            return (int)$existing['id'];
        }
        
        return $this->create(['name' => $name]);
    }

    public function attachToContent(int $contentId, array $tagIds): void
    {
        foreach ($tagIds as $tagId) {
            $existing = $this->db->get(self::CONTENT_TAGS_TABLE, '*', [
                'content_id' => $contentId,
                'tag_id' => $tagId
            ]);
            
            if (!$existing) {
                $this->db->insert(self::CONTENT_TAGS_TABLE, [
                    'content_id' => $contentId,
                    'tag_id' => $tagId
                ]);
                
                $this->db->update(self::TABLE, [
                    'usage_count[+]' => 1
                ], ['id' => $tagId]);
            }
        }
    }

    public function detachFromContent(int $contentId, ?int $tagId = null): void
    {
        $where = ['content_id' => $contentId];
        
        if ($tagId !== null) {
            $where['tag_id'] = $tagId;
            
            $this->db->update(self::TABLE, [
                'usage_count[-]' => 1
            ], ['id' => $tagId]);
        } else {
            $tagIds = $this->db->select(self::CONTENT_TAGS_TABLE, 'tag_id', ['content_id' => $contentId]);
            if (!empty($tagIds)) {
                foreach ($tagIds as $tid) {
                    $this->db->update(self::TABLE, [
                        'usage_count[-]' => 1
                    ], ['id' => $tid]);
                }
            }
        }
        
        $this->db->delete(self::CONTENT_TAGS_TABLE, $where);
    }

    public function syncToContent(int $contentId, array $tagNames): void
    {
        $currentTags = $this->getByContentId($contentId);
        $currentTagIds = array_column($currentTags, 'id');
        
        $newTagIds = [];
        foreach ($tagNames as $name) {
            $name = trim($name);
            if (!empty($name)) {
                $newTagIds[] = $this->findOrCreate($name);
            }
        }
        
        $toRemove = array_diff($currentTagIds, $newTagIds);
        $toAdd = array_diff($newTagIds, $currentTagIds);
        
        foreach ($toRemove as $tagId) {
            $this->detachFromContent($contentId, $tagId);
        }
        
        $this->attachToContent($contentId, $toAdd);
    }

    public function search(string $keyword, int $limit = 20): array
    {
        return $this->db->select(self::TABLE, '*', [
            'name[~]' => $keyword,
            'ORDER' => ['usage_count' => 'DESC'],
            'LIMIT' => $limit
        ]);
    }

    public function getPopular(int $limit = 10): array
    {
        return $this->db->select(self::TABLE, '*', [
            'ORDER' => ['usage_count' => 'DESC'],
            'LIMIT' => $limit
        ]);
    }

    private function generateSlug(string $name): string
    {
        $slug = preg_replace('/[^\x{4e00}-\x{9fa5}a-zA-Z0-9]/u', '-', $name);
        $slug = strtolower($slug);
        $slug = trim($slug, '-');
        $slug = preg_replace('/-+/', '-', $slug);
        
        return $slug ?: 'tag-' . time();
    }
}
