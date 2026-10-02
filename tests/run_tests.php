<?php

declare(strict_types=1);

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'EssenceStore\\';
    $baseDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;

    if (str_starts_with($class, 'EssenceStore\\Tests\\')) {
        $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . substr($class, strlen('EssenceStore\\Tests\\')) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

use EssenceStore\Tests\CatalogFoundationTest;

echo "========================================================\n";
echo "Essence Store: Sprint 2 Catalog Data Foundation Tests\n";
echo "Runtime:       PHP " . PHP_VERSION . "\n";
echo "Configuration: phpunit.xml\n";
echo "========================================================\n\n";

echo "Catalog Foundation Test Suite\n";

$test = new CatalogFoundationTest();

$testCases = [
    'test_create_product_success' => 'Product creation with required fields and specifications succeeds',
    'test_reject_duplicate_product_slug' => 'Duplicate product slug rejection returns 409 conflict',
    'test_create_sku_success' => 'Sku creation with positive decimal price and stock succeeds',
    'test_reject_duplicate_sku_code' => 'Duplicate sku code rejection returns 409 conflict',
    'test_reject_negative_stock' => 'Negative stock quantity rejection enforced by validation and check constraint',
    'test_reject_negative_price' => 'Negative price rejection enforced by validation and check constraint',
    'test_category_tree_cycle_prevention' => 'Category self-referencing hierarchy cycle prevention rejects circular parent',
    'test_missing_variant_combination_not_instantiated' => 'Missing variant combination is not instantiated as fake sku',
    'test_admin_auth_rejection_no_token' => 'Administrative endpoints reject unauthenticated requests with 401',
    'test_admin_auth_rejection_customer' => 'Administrative endpoints reject customer role requests with 403',
];

$passed = 0;
$failed = 0;
$errors = [];

foreach ($testCases as $method => $description) {
    try {
        $test->$method();
        echo " [x] {$description}\n";
        $passed++;
    } catch (\Throwable $e) {
        echo " [ ] {$description} (FAILED)\n";
        $failed++;
        $errors[] = [
            'method' => $method,
            'description' => $description,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ];
    }
}

echo "\n";
if ($failed === 0) {
    echo "OK ({$passed} tests, " . $test->getAssertionCount() . " assertions)\n";
    exit(0);
} else {
    echo "FAILURES!\n";
    echo "Tests: {$passed} passed, {$failed} failed.\n\n";
    foreach ($errors as $err) {
        echo "FAIL in {$err['method']} ({$err['description']}):\n";
        echo "  " . $err['error'] . "\n\n";
    }
    exit(1);
}
