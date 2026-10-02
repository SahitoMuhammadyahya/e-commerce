<?php

namespace EssenceStore\Controllers;

use EssenceStore\Models\Product;
use EssenceStore\Models\Sku;
use EssenceStore\Middleware\AuthMiddleware;
use EssenceStore\Response;
use InvalidArgumentException;

class ProductController
{
    private Product $productModel;
    private Sku $skuModel;

    public function __construct(?Product $productModel = null, ?Sku $skuModel = null)
    {
        $this->productModel = $productModel ?? new Product();
        $this->skuModel = $skuModel ?? new Sku();
    }

    /**
     * GET /api/v1/admin/products
     * Returns administrative product records.
     */
    public function index(): void
    {
        AuthMiddleware::authenticateAdmin();

        $status = $_GET['status'] ?? null;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

        $result = $this->productModel->getAdminList($status, $limit, $page);
        Response::json([
            'status' => 'success',
            'meta' => $result['meta'],
            'data' => $result['data']
        ], 200);
    }

    /**
     * POST /api/v1/admin/products
     * Create a draft product.
     */
    public function create(): void
    {
        AuthMiddleware::authenticateAdmin();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        try {
            $product = $this->productModel->create($body);
            Response::success($product, 201);
        } catch (InvalidArgumentException $e) {
            if (isset($e->slugConflict)) {
                Response::error(
                    $e->getMessage(),
                    409,
                    'RESOURCE_CONFLICT',
                    ['slug' => ['The slug has already been taken.']]
                );
            }
            if (isset($e->publicationError)) {
                Response::error(
                    $e->getMessage(),
                    422,
                    'UNPROCESSABLE_ENTITY',
                    ['status' => [$e->getMessage()]]
                );
            }
            Response::error($e->getMessage(), 422, 'VALIDATION_ERROR');
        } catch (\Throwable $e) {
            Response::error($e->getMessage(), 500, 'SERVER_ERROR');
        }
    }

    /**
     * PATCH /api/v1/admin/products/:id
     * Update product content or lifecycle status.
     */
    public function update(int $id): void
    {
        AuthMiddleware::authenticateAdmin();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        try {
            $product = $this->productModel->update($id, $body);
            Response::success($product, 200);
        } catch (InvalidArgumentException $e) {
            if (isset($e->slugConflict)) {
                Response::error(
                    $e->getMessage(),
                    409,
                    'RESOURCE_CONFLICT',
                    ['slug' => ['The slug has already been taken.']]
                );
            }
            if (isset($e->publicationError)) {
                Response::error(
                    $e->getMessage(),
                    422,
                    'UNPROCESSABLE_ENTITY',
                    ['status' => [$e->getMessage()]]
                );
            }
            if (str_contains($e->getMessage(), 'not found')) {
                Response::error($e->getMessage(), 404, 'NOT_FOUND');
            }
            Response::error($e->getMessage(), 422, 'VALIDATION_ERROR');
        } catch (\Throwable $e) {
            Response::error($e->getMessage(), 500, 'SERVER_ERROR');
        }
    }

    /**
     * POST /api/v1/admin/products/:id/skus
     * Add a validated SKU to product.
     */
    public function addSku(int $id): void
    {
        AuthMiddleware::authenticateAdmin();
        $product = $this->productModel->findById($id);
        if (!$product) {
            Response::error("Product ID {$id} not found.", 404, 'NOT_FOUND');
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $body['product_id'] = $id;

        try {
            $sku = $this->skuModel->create($body);
            Response::success($sku, 201);
        } catch (InvalidArgumentException $e) {
            if (isset($e->skuConflict)) {
                Response::error(
                    $e->getMessage(),
                    409,
                    'RESOURCE_CONFLICT',
                    ['sku_code' => ['The SKU code has already been taken.']]
                );
            }
            if (isset($e->isValidation)) {
                Response::error($e->getMessage(), 422, 'VALIDATION_ERROR');
            }
            Response::error($e->getMessage(), 422, 'VALIDATION_ERROR');
        } catch (\Throwable $e) {
            Response::error($e->getMessage(), 500, 'SERVER_ERROR');
        }
    }
}
