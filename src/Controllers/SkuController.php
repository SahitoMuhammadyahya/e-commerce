<?php

namespace EssenceStore\Controllers;

use EssenceStore\Models\Sku;
use EssenceStore\Middleware\AuthMiddleware;
use EssenceStore\Response;
use InvalidArgumentException;

class SkuController
{
    private Sku $skuModel;

    public function __construct(?Sku $skuModel = null)
    {
        $this->skuModel = $skuModel ?? new Sku();
    }

    /**
     * PATCH /api/v1/admin/skus/:id
     * Update price, stock, or active status.
     */
    public function update(int $id): void
    {
        AuthMiddleware::authenticateAdmin();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        try {
            $sku = $this->skuModel->update($id, $body);
            Response::success($sku, 200);
        } catch (InvalidArgumentException $e) {
            if (isset($e->isValidation)) {
                Response::error($e->getMessage(), 400, 'BAD_REQUEST');
            }
            if (str_contains($e->getMessage(), 'not found')) {
                Response::error($e->getMessage(), 404, 'NOT_FOUND');
            }
            Response::error($e->getMessage(), 400, 'BAD_REQUEST');
        } catch (\Throwable $e) {
            Response::error($e->getMessage(), 500, 'SERVER_ERROR');
        }
    }
}
