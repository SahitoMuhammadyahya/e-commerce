<?php

namespace EssenceStore\Models;

use EssenceStore\Config\Database;
use PDO;
use InvalidArgumentException;

class Product
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            return null;
        }

        if (isset($product['specifications']) && is_string($product['specifications'])) {
            $product['specifications'] = json_decode($product['specifications'], true);
        }

        return $product;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM products WHERE slug = :slug LIMIT 1");
        $stmt->execute([':slug' => $slug]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            return null;
        }

        if (isset($product['specifications']) && is_string($product['specifications'])) {
            $product['specifications'] = json_decode($product['specifications'], true);
        }

        return $product;
    }

    /**
     * Retrieve administrative product list with category and inventory metrics.
     */
    public function getAdminList(?string $status = null, int $limit = 20, int $page = 1): array
    {
        $offset = ($page - 1) * $limit;
        $params = [];
        $whereSql = "";

        if ($status !== null && $status !== '' && $status !== 'all') {
            $whereSql = "WHERE p.status = :status";
            $params[':status'] = $status;
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM products p {$whereSql}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $sql = "
            SELECT 
                p.id,
                p.name,
                p.slug,
                p.brand,
                p.description,
                p.status,
                p.created_at,
                p.updated_at,
                c.id AS category_id,
                c.name AS category_name,
                c.slug AS category_slug,
                COUNT(s.id) AS sku_count,
                COALESCE(SUM(s.stock_quantity), 0) AS total_stock,
                MIN(s.price) AS min_price,
                MAX(s.price) AS max_price
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN skus s ON p.id = s.product_id AND s.is_active = 1
            {$whereSql}
            GROUP BY p.id, c.id
            ORDER BY p.id ASC
            LIMIT {$limit} OFFSET {$offset}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = [];
        foreach ($rows as $row) {
            $priceRange = null;
            if ($row['min_price'] !== null && $row['max_price'] !== null) {
                $priceRange = [
                    'min' => number_format((float)$row['min_price'], 2, '.', ''),
                    'max' => number_format((float)$row['max_price'], 2, '.', '')
                ];
            }

            $data[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'slug' => $row['slug'],
                'brand' => $row['brand'],
                'category' => [
                    'id' => (int)$row['category_id'],
                    'name' => $row['category_name'],
                    'slug' => $row['category_slug']
                ],
                'status' => $row['status'],
                'sku_count' => (int)$row['sku_count'],
                'total_stock' => (int)$row['total_stock'],
                'price_range' => $priceRange
            ];
        }

        return [
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $limit
            ],
            'data' => $data
        ];
    }

    /**
     * Create a draft product with specifications.
     */
    public function create(array $data): array
    {
        $name = trim($data['name'] ?? '');
        $brand = trim($data['brand'] ?? '');
        $categoryId = isset($data['category_id']) ? (int)$data['category_id'] : 0;
        $slug = trim($data['slug'] ?? '');
        $description = isset($data['description']) ? trim($data['description']) : null;
        $status = trim($data['status'] ?? 'draft');
        $specifications = $data['specifications'] ?? null;

        if ($name === '') {
            throw new InvalidArgumentException("Product name is required.");
        }
        if ($brand === '') {
            throw new InvalidArgumentException("Brand name is required.");
        }
        if ($categoryId <= 0) {
            throw new InvalidArgumentException("Valid category_id is required.");
        }

        // Validate category exists
        $catStmt = $this->db->prepare("SELECT id FROM categories WHERE id = :id LIMIT 1");
        $catStmt->execute([':id' => $categoryId]);
        if (!$catStmt->fetch()) {
            throw new InvalidArgumentException("Category ID {$categoryId} does not exist.");
        }

        if ($slug === '') {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        }

        // Slug uniqueness check
        if ($this->findBySlug($slug) !== null) {
            throw \EssenceStore\Exceptions\ConflictException::forSlug($slug);
        }

        // Validate specifications JSON
        $specJson = null;
        if ($specifications !== null) {
            if (is_array($specifications)) {
                $specJson = json_encode($specifications, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } elseif (is_string($specifications)) {
                $decoded = json_decode($specifications, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new InvalidArgumentException("Invalid JSON provided for specifications.");
                }
                $specJson = $specifications;
            }
        }

        $validStatuses = ['draft', 'published', 'archived'];
        if (!in_array($status, $validStatuses, true)) {
            throw new InvalidArgumentException("Invalid product status. Allowed: " . implode(', ', $validStatuses));
        }

        // Business Rule 1: A new product cannot be directly published without SKUs
        if ($status === 'published') {
            throw new \EssenceStore\Exceptions\PublicationException("Cannot publish product without at least one sellable SKU.");
        }

        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $timeFunc = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        $stmt = $this->db->prepare("
            INSERT INTO products (category_id, name, slug, brand, description, status, specifications, created_at, updated_at)
            VALUES (:category_id, :name, :slug, :brand, :description, :status, :specifications, {$timeFunc}, {$timeFunc})
        ");

        $stmt->execute([
            ':category_id' => $categoryId,
            ':name' => $name,
            ':slug' => $slug,
            ':brand' => $brand,
            ':description' => $description,
            ':status' => $status,
            ':specifications' => $specJson,
        ]);

        $id = (int)$this->db->lastInsertId();
        return $this->findById($id);
    }

    /**
     * Update product details or lifecycle status.
     */
    public function update(int $id, array $data): array
    {
        $current = $this->findById($id);
        if (!$current) {
            throw new InvalidArgumentException("Product ID {$id} not found.");
        }

        $name = isset($data['name']) ? trim($data['name']) : $current['name'];
        $brand = isset($data['brand']) ? trim($data['brand']) : $current['brand'];
        $slug = isset($data['slug']) ? trim($data['slug']) : $current['slug'];
        $description = array_key_exists('description', $data) ? $data['description'] : $current['description'];
        $status = isset($data['status']) ? trim($data['status']) : $current['status'];
        $categoryId = isset($data['category_id']) ? (int)$data['category_id'] : (int)$current['category_id'];

        // Slug collision check
        if ($slug !== $current['slug']) {
            $existing = $this->findBySlug($slug);
            if ($existing && (int)$existing['id'] !== $id) {
                throw \EssenceStore\Exceptions\ConflictException::forSlug($slug);
            }
        }

        // Category validation
        if ($categoryId !== (int)$current['category_id']) {
            $catStmt = $this->db->prepare("SELECT id FROM categories WHERE id = :id LIMIT 1");
            $catStmt->execute([':id' => $categoryId]);
            if (!$catStmt->fetch()) {
                throw new InvalidArgumentException("Category ID {$categoryId} does not exist.");
            }
        }

        // Business Rule 1: Cannot transition to published without active sellable SKUs
        if ($status === 'published' && $current['status'] !== 'published') {
            $skuCheck = $this->db->prepare("SELECT COUNT(*) FROM skus WHERE product_id = :product_id AND is_active = 1 AND price > 0");
            $skuCheck->execute([':product_id' => $id]);
            $sellableSkus = (int)$skuCheck->fetchColumn();

            if ($sellableSkus === 0) {
                throw new \EssenceStore\Exceptions\PublicationException("Cannot publish product without at least one active, priced SKU.");
            }
        }

        $specJson = isset($current['specifications']) ? json_encode($current['specifications']) : null;
        if (array_key_exists('specifications', $data)) {
            $specs = $data['specifications'];
            if (is_array($specs)) {
                $specJson = json_encode($specs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } elseif (is_string($specs)) {
                $decoded = json_decode($specs, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new InvalidArgumentException("Invalid JSON provided for specifications.");
                }
                $specJson = $specs;
            } elseif ($specs === null) {
                $specJson = null;
            }
        }

        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $timeFunc = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        $stmt = $this->db->prepare("
            UPDATE products
            SET category_id = :category_id,
                name = :name,
                slug = :slug,
                brand = :brand,
                description = :description,
                status = :status,
                specifications = :specifications,
                updated_at = {$timeFunc}
            WHERE id = :id
        ");

        $stmt->execute([
            ':category_id' => $categoryId,
            ':name' => $name,
            ':slug' => $slug,
            ':brand' => $brand,
            ':description' => $description,
            ':status' => $status,
            ':specifications' => $specJson,
            ':id' => $id,
        ]);

        return $this->findById($id);
    }
}
