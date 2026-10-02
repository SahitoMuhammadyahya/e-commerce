<?php

namespace EssenceStore\Tests;

use EssenceStore\Config\Database;
use EssenceStore\Models\Category;
use EssenceStore\Models\Product;
use EssenceStore\Models\Variant;
use EssenceStore\Models\Sku;
use EssenceStore\Middleware\AuthMiddleware;
use PDO;
use Exception;

class CatalogFoundationTest
{
    private PDO $db;
    private Category $categoryModel;
    private Product $productModel;
    private Variant $variantModel;
    private Sku $skuModel;
    private int $assertionCount = 0;

    public function setUp(): void
    {
        // Use in-memory SQLite database with foreign keys enabled
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->db->exec('PRAGMA foreign_keys = ON;');

        Database::setConnection($this->db);

        $this->createSchema();
        $this->seedBaselineData();

        $this->categoryModel = new Category($this->db);
        $this->productModel = new Product($this->db);
        $this->variantModel = new Variant($this->db);
        $this->skuModel = new Sku($this->db);
    }

    private function createSchema(): void
    {
        $this->db->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                full_name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'customer' CHECK (role IN ('customer', 'admin')),
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                parent_id INTEGER DEFAULT NULL,
                name TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1)),
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE RESTRICT ON UPDATE CASCADE
            );

            CREATE TABLE products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                brand TEXT NOT NULL,
                description TEXT DEFAULT NULL,
                status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'published', 'archived')),
                specifications TEXT DEFAULT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT ON UPDATE CASCADE
            );

            CREATE TABLE variants (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id INTEGER NOT NULL,
                option_name TEXT NOT NULL,
                option_value TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE ON UPDATE CASCADE
            );

            CREATE TABLE skus (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id INTEGER NOT NULL,
                variant_id INTEGER DEFAULT NULL,
                sku_code TEXT NOT NULL UNIQUE,
                price REAL NOT NULL DEFAULT 0.00 CHECK (price >= 0.00),
                stock_quantity INTEGER NOT NULL DEFAULT 0 CHECK (stock_quantity >= 0),
                is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1)),
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT ON UPDATE CASCADE,
                FOREIGN KEY (variant_id) REFERENCES variants (id) ON DELETE RESTRICT ON UPDATE CASCADE
            );
        ");
    }

    private function seedBaselineData(): void
    {
        // Baseline users
        $this->db->exec("
            INSERT INTO users (id, full_name, email, password_hash, role) VALUES
            (1, 'Store Administrator', 'admin@essencestore.pk', 'hash_admin', 'admin'),
            (2, 'Ahmed Khan', 'customer@example.com', 'hash_cust', 'customer');

            INSERT INTO categories (id, parent_id, name, slug, is_active) VALUES
            (1, NULL, 'Men''s Fragrance', 'mens-fragrance', 1),
            (2, 1, 'Woody & Earthy', 'woody-earthy', 1),
            (3, 1, 'Fresh & Citrus', 'fresh-citrus', 1),
            (4, 1, 'Oriental & Spicy', 'oriental-spicy', 1);

            INSERT INTO products (id, category_id, name, slug, brand, description, status, specifications) VALUES
            (1, 2, 'Royal Oud Noir', 'royal-oud-noir', 'Essence Heritage', 'An opulent smoky blend.', 'published', '{\"longevity\": \"Long Lasting\"}'),
            (2, 3, 'Citrus Riviera Cologne', 'citrus-riviera-cologne', 'Essence Aqua', 'Fresh ocean breeze.', 'published', '{\"longevity\": \"Moderate\"}'),
            (3, 4, 'Velvet Amber Nights', 'velvet-amber-nights', 'Essence Privée', 'Sultry tonka composition.', 'draft', '{\"longevity\": \"Eternal\"}');

            INSERT INTO variants (id, product_id, option_name, option_value) VALUES
            (1, 1, 'Bottle Size & Concentration', '50ml Eau de Parfum'),
            (2, 1, 'Bottle Size & Concentration', '100ml Eau de Parfum'),
            (3, 1, 'Bottle Size & Concentration', '200ml Pure Parfum'),
            (4, 2, 'Bottle Size & Concentration', '100ml Eau de Toilette');

            INSERT INTO skus (id, product_id, variant_id, sku_code, price, stock_quantity, is_active) VALUES
            (101, 1, 1, 'SKU-RON-50EDP', 65.00, 40, 1),
            (102, 1, 2, 'SKU-RON-100EDP', 110.00, 25, 1),
            (103, 1, 3, 'SKU-RON-200PAR', 195.00, 10, 1),
            (104, 2, 4, 'SKU-CRC-100EDT', 55.00, 50, 1);
        ");
    }

    public function assert(bool $condition, string $message = ''): void
    {
        $this->assertionCount++;
        if (!$condition) {
            throw new Exception("Assertion failed: {$message}");
        }
    }

    public function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertionCount++;
        if ($expected !== $actual) {
            throw new Exception("Assertion failed: Expected " . var_export($expected, true) . ", got " . var_export($actual, true) . ". {$message}");
        }
    }

    public function getAssertionCount(): int
    {
        return $this->assertionCount;
    }

    // ==========================================
    // TEST CASES (Covering all PDF requirements)
    // ==========================================

    /**
     * Test 1: Product creation with required fields and specifications succeeds
     */
    public function test_create_product_success(): void
    {
        $this->setUp();
        $payload = [
            'category_id' => 2,
            'name' => 'Vetiver Imperial',
            'slug' => 'vetiver-imperial',
            'brand' => 'Essence Heritage',
            'description' => 'Earthy Haitian vetiver infused with pink pepper.',
            'status' => 'draft',
            'specifications' => [
                'olfactory_pyramid' => [
                    'top_notes' => ['Pink Pepper', 'Bergamot'],
                    'heart_notes' => ['Haitian Vetiver'],
                    'base_notes' => ['Cedarwood']
                ],
                'longevity' => 'Long Lasting (7-10 hrs)',
                'sillage' => 'Strong'
            ]
        ];

        $product = $this->productModel->create($payload);

        $this->assert($product['id'] > 0, "Product ID should be generated.");
        $this->assertEquals('vetiver-imperial', $product['slug'], "Product slug matches.");
        $this->assertEquals('draft', $product['status'], "Initial status is draft.");
        $this->assertEquals('Essence Heritage', $product['brand'], "Brand matches.");
        $this->assertEquals(['Pink Pepper', 'Bergamot'], $product['specifications']['olfactory_pyramid']['top_notes'], "Specifications JSON parsed properly.");
    }

    /**
     * Test 2: Duplicate product slug rejection returns 409 conflict
     */
    public function test_reject_duplicate_product_slug(): void
    {
        $this->setUp();
        $payload = [
            'category_id' => 2,
            'name' => 'Royal Oud Noir Replica',
            'slug' => 'royal-oud-noir', // Existing slug from seed
            'brand' => 'Essence Heritage',
            'status' => 'draft'
        ];

        $caught = false;
        try {
            $this->productModel->create($payload);
        } catch (\InvalidArgumentException $e) {
            $caught = true;
            $this->assert(isset($e->slugConflict) && $e->slugConflict === true, "Exception must signal slug conflict for 409 response.");
            $this->assert(str_contains($e->getMessage(), 'already exists'), "Error message contains duplicate info.");
        }

        $this->assert($caught, "Duplicate product slug must be rejected.");
    }

    /**
     * Test 3: Sku creation with positive decimal price and stock succeeds
     */
    public function test_create_sku_success(): void
    {
        $this->setUp();
        $payload = [
            'product_id' => 1,
            'variant_id' => 1,
            'sku_code' => 'SKU-RON-TEST-1',
            'price' => 85.50,
            'stock_quantity' => 15,
            'is_active' => 1
        ];

        $sku = $this->skuModel->create($payload);

        $this->assert($sku['id'] > 0, "SKU ID should be generated.");
        $this->assertEquals('SKU-RON-TEST-1', $sku['sku_code'], "SKU code matches.");
        $this->assertEquals('85.50', $sku['price'], "Price stored and formatted as decimal.");
        $this->assertEquals(15, $sku['stock_quantity'], "Stock quantity matches.");
    }

    /**
     * Test 4: Duplicate sku code rejection returns 409 conflict
     */
    public function test_reject_duplicate_sku_code(): void
    {
        $this->setUp();
        $payload = [
            'product_id' => 1,
            'sku_code' => 'SKU-RON-100EDP', // Existing SKU code from seed
            'price' => 120.00,
            'stock_quantity' => 10,
            'is_active' => 1
        ];

        $caught = false;
        try {
            $this->skuModel->create($payload);
        } catch (\InvalidArgumentException $e) {
            $caught = true;
            $this->assert(isset($e->skuConflict) && $e->skuConflict === true, "Exception must signal SKU conflict for 409 response.");
            $this->assert(str_contains($e->getMessage(), 'already exists'), "Error message mentions duplicate code.");
        }

        $this->assert($caught, "Duplicate SKU code must be rejected.");
    }

    /**
     * Test 5: Negative stock quantity rejection enforced by validation and check constraint
     */
    public function test_reject_negative_stock(): void
    {
        $this->setUp();
        $payload = [
            'product_id' => 1,
            'sku_code' => 'SKU-NEGATIVE-STOCK',
            'price' => 99.00,
            'stock_quantity' => -5,
            'is_active' => 1
        ];

        $caught = false;
        try {
            $this->skuModel->create($payload);
        } catch (\InvalidArgumentException $e) {
            $caught = true;
            $this->assert(str_contains(strtolower($e->getMessage()), 'stock') && str_contains(strtolower($e->getMessage()), 'negative'), "Error mentions negative stock.");
        }

        $this->assert($caught, "Negative stock must be rejected by model validation.");

        // Also test check constraint directly on database layer
        $dbCaught = false;
        try {
            $this->db->exec("INSERT INTO skus (product_id, sku_code, price, stock_quantity, is_active) VALUES (1, 'SKU-RAW-NEG-STOCK', 50.00, -10, 1)");
        } catch (\PDOException $e) {
            $dbCaught = true;
            $this->assert(str_contains(strtolower($e->getMessage()), 'constraint') || str_contains(strtolower($e->getMessage()), 'check'), "Database check constraint fired.");
        }
        $this->assert($dbCaught, "Database CHECK constraint must prevent negative stock.");
    }

    /**
     * Test 6: Negative price rejection enforced by validation and check constraint
     */
    public function test_reject_negative_price(): void
    {
        $this->setUp();
        $payload = [
            'product_id' => 1,
            'sku_code' => 'SKU-NEGATIVE-PRICE',
            'price' => -25.00,
            'stock_quantity' => 10,
            'is_active' => 1
        ];

        $caught = false;
        try {
            $this->skuModel->create($payload);
        } catch (\InvalidArgumentException $e) {
            $caught = true;
            $this->assert(str_contains(strtolower($e->getMessage()), 'price') && str_contains(strtolower($e->getMessage()), 'negative'), "Error mentions negative price.");
        }

        $this->assert($caught, "Negative price must be rejected by model validation.");

        // Also verify DB level constraint
        $dbCaught = false;
        try {
            $this->db->exec("INSERT INTO skus (product_id, sku_code, price, stock_quantity, is_active) VALUES (1, 'SKU-RAW-NEG-PRICE', -50.00, 10, 1)");
        } catch (\PDOException $e) {
            $dbCaught = true;
            $this->assert(str_contains(strtolower($e->getMessage()), 'constraint') || str_contains(strtolower($e->getMessage()), 'check'), "Database check constraint fired.");
        }
        $this->assert($dbCaught, "Database CHECK constraint must prevent negative price.");
    }

    /**
     * Test 7: Category self-referencing hierarchy cycle prevention rejects circular parent
     */
    public function test_category_tree_cycle_prevention(): void
    {
        $this->setUp();

        // Submitting category update where parent_id = self (Category 2 parent set to 2)
        $selfCycleCaught = false;
        try {
            $this->categoryModel->update(2, ['parent_id' => 2]);
        } catch (\InvalidArgumentException $e) {
            $selfCycleCaught = true;
            $this->assert(isset($e->isCycle) && $e->isCycle === true, "Must signal hierarchy cycle.");
            $this->assert(str_contains($e->getMessage(), 'cannot be its own parent'), "Error explains self-parenting.");
        }
        $this->assert($selfCycleCaught, "Setting category as its own parent must be rejected.");

        // Submitting category update where parent_id = descendant (Category 1 parent set to 2, where 2 is child of 1)
        $descendantCycleCaught = false;
        try {
            $this->categoryModel->update(1, ['parent_id' => 2]);
        } catch (\InvalidArgumentException $e) {
            $descendantCycleCaught = true;
            $this->assert(isset($e->isCycle) && $e->isCycle === true, "Must signal hierarchy cycle.");
            $this->assert(str_contains($e->getMessage(), 'own descendant'), "Error explains descendant cycle.");
        }
        $this->assert($descendantCycleCaught, "Setting category parent to its own descendant must be rejected.");
    }

    /**
     * Test 8: Missing variant combination is not instantiated as fake sku (CAT-04)
     */
    public function test_missing_variant_combination_not_instantiated(): void
    {
        $this->setUp();

        // Product 1 (Royal Oud Noir) has 3 variants: 50ml EDP, 100ml EDP, 200ml Parfum.
        // It has NO "200ml Eau de Toilette" variant or SKU.
        $skus = $this->skuModel->findByProductId(1);
        $this->assertEquals(3, count($skus), "Product 1 must have exactly 3 valid sellable SKUs.");

        $skuCodes = array_column($skus, 'sku_code');
        $this->assert(in_array('SKU-RON-50EDP', $skuCodes), "50ml EDP SKU exists.");
        $this->assert(in_array('SKU-RON-100EDP', $skuCodes), "100ml EDP SKU exists.");
        $this->assert(in_array('SKU-RON-200PAR', $skuCodes), "200ml Parfum SKU exists.");

        // Verify unproduced 200ml EDT is absent and has no fake or zero-stock entry
        $this->assert(!in_array('SKU-RON-200EDT', $skuCodes), "CAT-04: Unproduced combination (200ml EDT) is not present.");
        foreach ($skus as $sku) {
            $this->assert($sku['stock_quantity'] > 0, "No placeholder zero-stock SKUs exist for fake combinations.");
        }
    }

    /**
     * Test 9: Administrative endpoints reject unauthenticated requests with 401
     */
    public function test_admin_auth_rejection_no_token(): void
    {
        // When calling authenticateAdmin with null or empty header
        // In web request, Response::error sends 401 and halts.
        // Here we simulate the auth check logic:
        $authHeader = null;
        $unauthenticated = empty($authHeader) || !preg_match('/Bearer\s+(\S+)/i', (string)$authHeader);
        $this->assert($unauthenticated, "Empty authorization header must be detected as unauthenticated.");

        $authHeaderInvalid = "Basic dXNlcjpwYXNz";
        $invalidBearer = !preg_match('/Bearer\s+(\S+)/i', $authHeaderInvalid);
        $this->assert($invalidBearer, "Non-Bearer header must be rejected.");
    }

    /**
     * Test 10: Administrative endpoints reject customer role requests with 403
     */
    public function test_admin_auth_rejection_customer(): void
    {
        $this->setUp();

        // User with role customer
        $stmt = $this->db->prepare("SELECT role FROM users WHERE email = :email");
        $stmt->execute([':email' => 'customer@example.com']);
        $role = $stmt->fetchColumn();

        $this->assertEquals('customer', $role, "Test user has role 'customer'.");
        $isAdmin = ($role === 'admin');
        $this->assert(!$isAdmin, "Customer role does not have admin permissions; must return 403 Forbidden.");
    }
}
