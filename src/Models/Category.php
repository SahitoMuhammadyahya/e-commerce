<?php

namespace EssenceStore\Models;

use EssenceStore\Config\Database;
use PDO;
use InvalidArgumentException;

class Category
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM categories WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM categories WHERE slug = :slug LIMIT 1");
        $stmt->execute([':slug' => $slug]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("SELECT * FROM categories ORDER BY id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Builds hierarchical category tree.
     */
    public function getTree(): array
    {
        $all = $this->getAll();
        $categoriesById = [];
        foreach ($all as $cat) {
            $cat['id'] = (int)$cat['id'];
            $cat['parent_id'] = $cat['parent_id'] !== null ? (int)$cat['parent_id'] : null;
            $cat['is_active'] = (int)$cat['is_active'];
            $cat['children'] = [];
            $categoriesById[$cat['id']] = $cat;
        }

        $tree = [];
        foreach ($categoriesById as $id => &$cat) {
            if ($cat['parent_id'] !== null && isset($categoriesById[$cat['parent_id']])) {
                $categoriesById[$cat['parent_id']]['children'][] = &$cat;
            } else {
                $tree[] = &$cat;
            }
        }
        unset($cat);

        return $tree;
    }

    /**
     * Create category. Enforces unique slug and parent existence.
     */
    public function create(array $data): array
    {
        $name = trim($data['name'] ?? '');
        $slug = trim($data['slug'] ?? '');
        $parentId = isset($data['parent_id']) && $data['parent_id'] !== null && $data['parent_id'] !== ''
            ? (int)$data['parent_id']
            : null;
        $isActive = isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1;

        if ($name === '') {
            throw new InvalidArgumentException("Category name is required.");
        }
        if ($slug === '') {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        }

        // Check unique slug
        if ($this->findBySlug($slug) !== null) {
            throw \EssenceStore\Exceptions\ConflictException::forSlug($slug);
        }

        // Check parent exists if given
        if ($parentId !== null) {
            $parent = $this->findById($parentId);
            if (!$parent) {
                throw new InvalidArgumentException("Parent category ID {$parentId} does not exist.");
            }
        }

        $stmt = $this->db->prepare("
            INSERT INTO categories (parent_id, name, slug, is_active, created_at, updated_at)
            VALUES (:parent_id, :name, :slug, :is_active, datetime('now'), datetime('now'))
        ");

        // Use standard SQL or check driver
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $stmt = $this->db->prepare("
                INSERT INTO categories (parent_id, name, slug, is_active, created_at, updated_at)
                VALUES (:parent_id, :name, :slug, :is_active, NOW(), NOW())
            ");
        }

        $stmt->execute([
            ':parent_id' => $parentId,
            ':name' => $name,
            ':slug' => $slug,
            ':is_active' => $isActive,
        ]);

        $id = (int)$this->db->lastInsertId();
        return $this->findById($id);
    }

    /**
     * Update category with cycle prevention.
     */
    public function update(int $id, array $data): array
    {
        $current = $this->findById($id);
        if (!$current) {
            throw new InvalidArgumentException("Category ID {$id} not found.");
        }

        $name = isset($data['name']) ? trim($data['name']) : $current['name'];
        $slug = isset($data['slug']) ? trim($data['slug']) : $current['slug'];
        $isActive = isset($data['is_active']) ? (int)(bool)$data['is_active'] : (int)$current['is_active'];

        $hasParentId = array_key_exists('parent_id', $data);
        $parentId = $hasParentId 
            ? ($data['parent_id'] !== null && $data['parent_id'] !== '' ? (int)$data['parent_id'] : null)
            : ($current['parent_id'] !== null ? (int)$current['parent_id'] : null);

        // Check slug conflict if changed
        if ($slug !== $current['slug']) {
            $existing = $this->findBySlug($slug);
            if ($existing && (int)$existing['id'] !== $id) {
                throw \EssenceStore\Exceptions\ConflictException::forSlug($slug);
            }
        }

        // CAT-01 Cycle Prevention
        if ($parentId !== null) {
            // Rule 1: Cannot become its own immediate parent
            if ($parentId === $id) {
                throw new \EssenceStore\Exceptions\HierarchyCycleException("Category hierarchy cycle detected: A category cannot be its own parent.");
            }

            // Rule 2: Cannot become a child of any of its own descendants
            if ($this->isDescendantOf($parentId, $id)) {
                throw new \EssenceStore\Exceptions\HierarchyCycleException("Category hierarchy cycle detected: A category cannot become a child of its own descendant.");
            }

            // Check parent exists
            $parent = $this->findById($parentId);
            if (!$parent) {
                throw new InvalidArgumentException("Parent category ID {$parentId} does not exist.");
            }
        }

        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $timeFunc = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        $stmt = $this->db->prepare("
            UPDATE categories 
            SET parent_id = :parent_id, name = :name, slug = :slug, is_active = :is_active, updated_at = {$timeFunc}
            WHERE id = :id
        ");

        $stmt->execute([
            ':parent_id' => $parentId,
            ':name' => $name,
            ':slug' => $slug,
            ':is_active' => $isActive,
            ':id' => $id,
        ]);

        return $this->findById($id);
    }

    /**
     * Checks if $possibleDescendantId is a descendant of $ancestorId.
     * Walks up the parent chain from $possibleDescendantId.
     */
    public function isDescendantOf(int $candidateId, int $ancestorId): bool
    {
        $visited = [];
        $currentParentId = $candidateId;

        while ($currentParentId !== null) {
            if ($currentParentId === $ancestorId) {
                return true;
            }

            if (isset($visited[$currentParentId])) {
                // Circular reference already in DB
                break;
            }
            $visited[$currentParentId] = true;

            $cat = $this->findById($currentParentId);
            if (!$cat || $cat['parent_id'] === null) {
                break;
            }

            $currentParentId = (int)$cat['parent_id'];
        }

        return false;
    }
}
