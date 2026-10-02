<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Config/Database.php';

use EssenceStore\Config\Database;

Database::loadEnv();

$connectionType = $_ENV['DB_CONNECTION'] ?? 'mysql';

echo "Initializing Essence Store Database ({$connectionType})...\n";

$pdo = Database::getConnection();
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

if ($driver === 'sqlite') {
    $pdo->exec('PRAGMA foreign_keys = OFF;');
    $tables = ['order_items', 'orders', 'cart_items', 'carts', 'assets', 'skus', 'variants', 'products', 'categories', 'users'];
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS {$table};");
    }
    $pdo->exec('PRAGMA foreign_keys = ON;');

    $pdo->exec("
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

        CREATE TABLE assets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            sku_id INTEGER DEFAULT NULL,
            storage_url TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'gallery' CHECK (role IN ('hero', 'gallery', 'thumbnail')),
            alt_text TEXT DEFAULT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE ON UPDATE CASCADE,
            FOREIGN KEY (sku_id) REFERENCES skus (id) ON DELETE SET NULL ON UPDATE CASCADE
        );

        CREATE TABLE carts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        );

        CREATE TABLE cart_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            cart_id INTEGER NOT NULL,
            sku_id INTEGER NOT NULL,
            quantity INTEGER NOT NULL DEFAULT 1 CHECK (quantity > 0),
            added_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (cart_id) REFERENCES carts (id) ON DELETE CASCADE,
            FOREIGN KEY (sku_id) REFERENCES skus (id) ON DELETE RESTRICT
        );

        CREATE TABLE orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            total_amount REAL NOT NULL DEFAULT 0.00,
            status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'processing', 'shipped', 'delivered', 'cancelled')),
            shipping_address TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT
        );

        CREATE TABLE order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL,
            sku_id INTEGER NOT NULL,
            quantity INTEGER NOT NULL DEFAULT 1 CHECK (quantity > 0),
            unit_price REAL NOT NULL CHECK (unit_price >= 0.00),
            FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
            FOREIGN KEY (sku_id) REFERENCES skus (id) ON DELETE RESTRICT
        );
    ");

} else {
    // MySQL
    $schemaFile = __DIR__ . '/schema.sql';
    if (file_exists($schemaFile)) {
        $sql = file_get_contents($schemaFile);
        $pdo->exec($sql);
    }
}

// Populate deterministic seed data
$seedUsers = [
    [1, 'Store Administrator', 'admin@essencestore.pk', '$2y$10$abcdefghijklmnopqrstuvwxyzA1B2C3D4E5F6G7H8I9J0K', 'admin'],
    [2, 'Ahmed Khan', 'customer@example.com', '$2y$10$abcdefghijklmnopqrstuvwxyzA1B2C3D4E5F6G7H8I9J0K', 'customer']
];

$userStmt = $pdo->prepare("INSERT INTO users (id, full_name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)");
foreach ($seedUsers as $u) {
    $userStmt->execute($u);
}

// 2 Levels of Categories
$seedCategories = [
    [1, null, "Men's Fragrance", 'mens-fragrance', 1],
    [2, 1, 'Woody & Earthy', 'woody-earthy', 1],
    [3, 1, 'Fresh & Citrus', 'fresh-citrus', 1],
    [4, 1, 'Oriental & Spicy', 'oriental-spicy', 1]
];

$catStmt = $pdo->prepare("INSERT INTO categories (id, parent_id, name, slug, is_active) VALUES (?, ?, ?, ?, ?)");
foreach ($seedCategories as $c) {
    $catStmt->execute($c);
}

// 3 Products (including multi-variant)
$seedProducts = [
    [1, 2, 'Royal Oud Noir', 'royal-oud-noir', 'Essence Heritage', 'An opulent, smoky blend of Cambodian agarwood, cardamom, and dark amber crafted for refined gentlemen.', 'published', '{"olfactory_pyramid": {"top_notes": ["Cardamom", "Bergamot", "Pink Pepper"], "heart_notes": ["Cedarwood", "Patchouli"], "base_notes": ["Agarwood Oud", "Leather", "Amber"]}, "longevity": "Long Lasting (7-10 hrs)", "sillage": "Strong"}'],
    [2, 3, 'Citrus Riviera Cologne', 'citrus-riviera-cologne', 'Essence Aqua', 'A refreshing, sun-drenched breeze of Calabrian lemon, bitter orange, and sea minerals.', 'published', '{"olfactory_pyramid": {"top_notes": ["Calabrian Lemon", "Mandarin", "Bergamot"], "heart_notes": ["Rosemary", "Neroli", "Sea Salt"], "base_notes": ["Vetiver", "White Musk", "Cedar"]}, "longevity": "Moderate (4-6 hrs)", "sillage": "Moderate"}'],
    [3, 4, 'Velvet Amber Nights', 'velvet-amber-nights', 'Essence Privée', 'A nocturnal, sultry composition of bourbon vanilla, roasted tonka bean, and warm frankincense.', 'draft', '{"olfactory_pyramid": {"top_notes": ["Nutmeg", "Cinnamon"], "heart_notes": ["Bourbon Vanilla", "Tonka Bean"], "base_notes": ["Amber", "Frankincense", "Sandalwood"]}, "longevity": "Eternal (12+ hrs)", "sillage": "Enormous"}']
];

$prodStmt = $pdo->prepare("INSERT INTO products (id, category_id, name, slug, brand, description, status, specifications) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
foreach ($seedProducts as $p) {
    $prodStmt->execute($p);
}

// Variants
$seedVariants = [
    [1, 1, 'Bottle Size & Concentration', '50ml Eau de Parfum'],
    [2, 1, 'Bottle Size & Concentration', '100ml Eau de Parfum'],
    [3, 1, 'Bottle Size & Concentration', '200ml Pure Parfum'],
    [4, 2, 'Bottle Size & Concentration', '100ml Eau de Toilette']
];

$varStmt = $pdo->prepare("INSERT INTO variants (id, product_id, option_name, option_value) VALUES (?, ?, ?, ?)");
foreach ($seedVariants as $v) {
    $varStmt->execute($v);
}

// 4 Valid SKUs (and 1 intentionally uncreated combination)
$seedSkus = [
    [101, 1, 1, 'SKU-RON-50EDP', 65.00, 40, 1],
    [102, 1, 2, 'SKU-RON-100EDP', 110.00, 25, 1],
    [103, 1, 3, 'SKU-RON-200PAR', 195.00, 10, 1],
    [104, 2, 4, 'SKU-CRC-100EDT', 55.00, 50, 1]
];

$skuStmt = $pdo->prepare("INSERT INTO skus (id, product_id, variant_id, sku_code, price, stock_quantity, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
foreach ($seedSkus as $s) {
    $skuStmt->execute($s);
}

// Assets
$seedAssets = [
    [1, 1, null, '/assets/perfumes/royal-oud-noir-hero.webp', 'hero', 'Royal Oud Noir Luxury Perfume Bottle', 1],
    [2, 1, 102, '/assets/perfumes/royal-oud-noir-100ml.webp', 'gallery', 'Royal Oud Noir 100ml Eau de Parfum Packshot', 2],
    [3, 2, 104, '/assets/perfumes/citrus-riviera-hero.webp', 'hero', 'Citrus Riviera Cologne Fresh Summer Spray', 1]
];

$assetStmt = $pdo->prepare("INSERT INTO assets (id, product_id, sku_id, storage_url, role, alt_text, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
foreach ($seedAssets as $a) {
    $assetStmt->execute($a);
}

echo "Database initialized and seeded successfully!\n";
echo "Categories: 4 (2 levels)\n";
echo "Products:   3 (Royal Oud Noir [multi-variant], Citrus Riviera Cologne, Velvet Amber Nights [draft])\n";
echo "Variants:   4\n";
echo "SKUs:       4 valid SKUs (200ml EDT intentionally unavailable)\n";
echo "Assets:     3\n";
