<?php

namespace EssenceStore\Models;

use EssenceStore\Config\Database;
use PDO;
use InvalidArgumentException;

class Variant
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM variants WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByProduct(int $productId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM variants WHERE product_id = :product_id ORDER BY id ASC");
        $stmt->execute([':product_id' => $productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): array
    {
        $productId = isset($data['product_id']) ? (int)$data['product_id'] : 0;
        $optionName = trim($data['option_name'] ?? '');
        $optionValue = trim($data['option_value'] ?? '');

        if ($productId <= 0) {
            throw new InvalidArgumentException("Valid product_id is required.");
        }
        if ($optionName === '') {
            throw new InvalidArgumentException("option_name is required (e.g., 'Bottle Size & Concentration').");
        }
        if ($optionValue === '') {
            throw new InvalidArgumentException("option_value is required (e.g., '100ml Eau de Parfum').");
        }

        // Verify product exists
        $prodStmt = $this->db->prepare("SELECT id FROM products WHERE id = :id LIMIT 1");
        $prodStmt->execute([':id' => $productId]);
        if (!$prodStmt->fetch()) {
            throw new InvalidArgumentException("Product ID {$productId} does not exist.");
        }

        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $timeFunc = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        $stmt = $this->db->prepare("
            INSERT INTO variants (product_id, option_name, option_value, created_at)
            VALUES (:product_id, :option_name, :option_value, {$timeFunc})
        ");

        $stmt->execute([
            ':product_id' => $productId,
            ':option_name' => $optionName,
            ':option_value' => $optionValue,
        ]);

        $id = (int)$this->db->lastInsertId();
        return $this->findById($id);
    }
}
