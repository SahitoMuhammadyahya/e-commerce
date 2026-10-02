<?php

declare(strict_types=1);

// Autoload EssenceStore classes
spl_autoload_register(function ($class) {
    $prefix = 'EssenceStore\\';
    $baseDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;

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

use EssenceStore\Router;
use EssenceStore\Response;
use EssenceStore\Controllers\CategoryController;
use EssenceStore\Controllers\ProductController;
use EssenceStore\Controllers\SkuController;

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $router = new Router();

    // Health check / API root
    $router->get('/api/v1/health', function () {
        Response::success(['service' => 'Essence Store Catalog API', 'sprint' => 2, 'status' => 'healthy']);
    });

    // ==========================================
    // Sprint 2: Minimum Administration Contract
    // ==========================================

    // Categories
    $router->get('/api/v1/admin/categories', [CategoryController::class, 'index']);
    $router->post('/api/v1/admin/categories', [CategoryController::class, 'create']);
    $router->patch('/api/v1/admin/categories/:id', [CategoryController::class, 'update']);

    // Products
    $router->get('/api/v1/admin/products', [ProductController::class, 'index']);
    $router->post('/api/v1/admin/products', [ProductController::class, 'create']);
    $router->patch('/api/v1/admin/products/:id', [ProductController::class, 'update']);
    $router->post('/api/v1/admin/products/:id/skus', [ProductController::class, 'addSku']);

    // SKUs
    $router->patch('/api/v1/admin/skus/:id', [SkuController::class, 'update']);

    // Dispatch request
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';

    $router->dispatch($method, $uri);

} catch (\Throwable $e) {
    Response::error($e->getMessage(), 500, 'INTERNAL_SERVER_ERROR');
}
