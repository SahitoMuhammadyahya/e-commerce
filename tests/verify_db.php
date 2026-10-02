<?php
declare(strict_types=1);

spl_autoload_register(function ($class) {
    $prefix = 'EssenceStore\\';
    $baseDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, $len)) . '.php';
    if (file_exists($file)) require_once $file;
});

use EssenceStore\Config\Database;
use EssenceStore\Models\Category;
use EssenceStore\Models\Product;
use EssenceStore\Models\Sku;

Database::loadEnv();
echo "DB_CONNECTION: " . ($_ENV['DB_CONNECTION'] ?? 'not set') . PHP_EOL;

$pdo = Database::getConnection();
echo "Connected to: " . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . PHP_EOL;

$catCount = (int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
$prodCount = (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$skuCount = (int)$pdo->query('SELECT COUNT(*) FROM skus')->fetchColumn();
$varCount = (int)$pdo->query('SELECT COUNT(*) FROM variants')->fetchColumn();

echo "Categories: $catCount" . PHP_EOL;
echo "Products:   $prodCount" . PHP_EOL;
echo "Variants:   $varCount" . PHP_EOL;
echo "SKUs:       $skuCount" . PHP_EOL;

echo PHP_EOL . "--- Category Tree ---" . PHP_EOL;
$cat = new Category($pdo);
$tree = $cat->getTree();
function printTree($nodes, $indent = '') {
    foreach ($nodes as $n) {
        echo $indent . "[{$n['id']}] {$n['name']} ({$n['slug']})" . PHP_EOL;
        if (!empty($n['children'])) printTree($n['children'], $indent . '  ');
    }
}
printTree($tree);

echo PHP_EOL . "--- Products ---" . PHP_EOL;
$result = (new Product($pdo))->getAdminList(null, 20, 1);
foreach ($result['data'] as $p) {
    echo "[{$p['id']}] {$p['name']} | {$p['status']} | SKUs: {$p['sku_count']} | Stock: {$p['total_stock']}" . PHP_EOL;
}

echo PHP_EOL . "--- SKUs for Product 1 (Royal Oud Noir) ---" . PHP_EOL;
$skus = (new Sku($pdo))->findByProductId(1);
foreach ($skus as $s) {
    echo "[{$s['id']}] {$s['sku_code']} | Price: {$s['price']} | Stock: {$s['stock_quantity']} | Active: {$s['is_active']}" . PHP_EOL;
}
