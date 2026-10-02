<?php

namespace EssenceStore\Models;

use EssenceStore\Config\Database;
use PDO;
use InvalidArgumentException;

class Sku
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT s.*, p.name AS product_name, p.slug AS product_slug, v.option_name, v.option_value
            FROM skus s
            JOIN products p ON s.product_id = p.id
            LEFT JOIN variants v ON s.variant_id = v.id
            WHERE s.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['id'] = (int)$row['id'];
        $row['product_id'] = (int)$row['product_id'];
        $row['variant_id'] = $row['variant_id'] !== null ? (int)$row['variant_id'] : null;
        $row['stock_quantity'] = (int)$row['stock_quantity'];
        $row['is_active'] = (int)$row['is_active'];
        $row['price'] = number_format((float)$row['price'], 2, '.', '');

        return $row;
    }

    public function findByCode(string $skuCode): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM skus WHERE sku_code = :sku_code LIMIT 1");
        $stmt->execute([':sku_code' => $skuCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['id'] = (int)$row['id'];
        $row['product_id'] = (int)$row['product_id'];
        $row['variant_id'] = $row['variant_id'] !== null ? (int)$row['variant_id'] : null;
        $row['stock_quantity'] = (int)$row['stock_quantity'];
        $row['is_active'] = (int)$row['is_active'];
        $row['price'] = number_format((float)$row['price'], 2, '.', '');

        return $row;
    }

    public function findByProductId(int $productId): array
    {
        $stmt = $this->db->prepare("
            SELECT s.*, v.option_name, v.option_value
            FROM skus s
            LEFT JOIN variants v ON s.variant_id = v.id
            WHERE s.product_id = :product_id
            ORDER BY s.id ASC
        ");
        $stmt->execute([':product_id' => $productId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['id'] = (int)$row['id'];
            $row['product_id'] = (int)$row['product_id'];
            $row['variant_id'] = $row['variant_id'] !== null ? (int)$row['variant_id'] : null;
            $row['stock_quantity'] = (int)$row['stock_quantity'];
            $row['is_active'] = (int)$row['is_active'];
            $row['price'] = number_format((float)$row['price'], 2, '.', '');
        }
        unset($row);

        return $rows;
    }

    /**
     * Create SKU with strict constraints.
     */
    public function create(array $data): array
    {
        $productId = isset($data['product_id']) ? (int)$data['product_id'] : 0;
        $variantId = isset($data['variant_id']) && $data['variant_id'] !== null && $data['variant_id'] !== ''
            ? (int)$data['variant_id']
            : null;
        $skuCode = trim($data['sku_code'] ?? '');
        $price = $data['price'] ?? null;
        $stockQuantity = $data['stock_quantity'] ?? 0;
        $isActive = isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1;

        if ($productId <= 0) {
            throw new InvalidArgumentException("Valid product_id is required.");
        }

        // Verify product exists
        $prodStmt = $this->db->prepare("SELECT id FROM products WHERE id = :id LIMIT 1");
        $prodStmt->execute([':id' => $productId]);
        if (!$prodStmt->fetch()) {
            throw new InvalidArgumentException("Product ID {$productId} does not exist.");
        }

        if ($skuCode === '') {
            throw new InvalidArgumentException("sku_code is required.");
        }

        // Duplicate SKU Code Check
        if ($this->findByCode($skuCode) !== null) {
            throw \EssenceStore\Exceptions\ConflictException::forSku($skuCode);
        }

        if ($price === null || !is_numeric($price)) {
            throw new InvalidArgumentException("Valid numeric price is required.");
        }

        $priceFloat = (float)$price;
        if ($priceFloat < 0) {
            throw new \EssenceStore\Exceptions\ValidationException("Price cannot be negative.");
        }

        if (!is_numeric($stockQuantity) || (int)$stockQuantity < 0) {
            throw new \EssenceStore\Exceptions\ValidationException("Stock quantity cannot be negative.");
        }
        $stockInt = (int)$stockQuantity;

        // If variant provided, verify it belongs to this product
        if ($variantId !== null) {
            $varStmt = $this->db->prepare("SELECT product_id FROM variants WHERE id = :id LIMIT 1");
            $varStmt->execute([':id' => $variantId]);
            $variant = $varStmt->fetch(PDO::FETCH_ASSOC);
            if (!$variant) {
                throw new InvalidArgumentException("Variant ID {$variantId} does not exist.");
            }
            if ((int)$variant['product_id'] !== $productId) {
                throw new InvalidArgumentException("Variant ID {$variantId} does not belong to Product ID {$productId}.");
            }
        }

        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $timeFunc = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        $stmt = $this->db->prepare("
            INSERT INTO skus (product_id, variant_id, sku_code, price, stock_quantity, is_active, created_at, updated_at)
            VALUES (:product_id, :variant_id, :sku_code, :price, :stock_quantity, :is_active, {$timeFunc}, {$timeFunc})
        ");

        $stmt->execute([
            ':product_id' => $productId,
            ':variant_id' => $variantId,
            ':sku_code' => $skuCode,
            ':price' => number_format($priceFloat, 2, '.', ''),
            ':stock_quantity' => $stockInt,
            ':is_active' => $isActive,
        ]);

        $id = (int)$this->db->lastInsertId();
        return $this->findById($id);
    }

    /**
     * Update price, stock, or active status.
     */
    public function update(int $id, array $data): array
    {
        $current = $this->findById($id);
        if (!$current) {
            throw new InvalidArgumentException("SKU ID {$id} not found.");
        }

        $priceFloat = (float)$current['price'];
        if (array_key_exists('price', $data)) {
            if (!is_numeric($data['price'])) {
                throw new InvalidArgumentException("Price must be a valid number.");
            }
            $p = (float)$data['price'];
            if ($p < 0) {
                throw new \EssenceStore\Exceptions\ValidationException("Price cannot be negative.");
            }
            $priceFloat = $p;
        }

        $stockInt = (int)$current['stock_quantity'];
        if (array_key_exists('stock_quantity', $data)) {
            if (!is_numeric($data['stock_quantity'])) {
                throw new InvalidArgumentException("Stock quantity must be an integer.");
            }
            $s = (int)$data['stock_quantity'];
            if ($s < 0) {
                throw new \EssenceStore\Exceptions\ValidationException("Stock quantity cannot be negative.");
            }
            $stockInt = $s;
        }

        $isActive = array_key_exists('is_active', $data)
            ? (int)(bool)$data['is_active']
            : (int)$current['is_active'];

        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $timeFunc = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        $stmt = $this->db->prepare("
            UPDATE skus
            SET price = :price,
                stock_quantity = :stock_quantity,
                is_active = :is_active,
                updated_at = {$timeFunc}
            WHERE id = :id
        ");

        $stmt->execute([
            ':price' => number_format($priceFloat, 2, '.', ''),
            ':stock_quantity' => $stockInt,
            ':is_active' => $isActive,
            ':id' => $id,
        ]);

        return $this->findById($id);
    }
}
