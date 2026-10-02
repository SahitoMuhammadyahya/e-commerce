<?php

namespace EssenceStore\Controllers;

use EssenceStore\Models\Category;
use EssenceStore\Middleware\AuthMiddleware;
use EssenceStore\Response;
use InvalidArgumentException;

class CategoryController
{
    private Category $categoryModel;

    public function __construct(?Category $categoryModel = null)
    {
        $this->categoryModel = $categoryModel ?? new Category();
    }

    /**
     * GET /api/v1/admin/categories
     * Return hierarchical category tree.
     */
    public function index(): void
    {
        AuthMiddleware::authenticateAdmin();
        $tree = $this->categoryModel->getTree();
        Response::success($tree, 200);
    }

    /**
     * POST /api/v1/admin/categories
     * Create a category.
     */
    public function create(): void
    {
        AuthMiddleware::authenticateAdmin();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        try {
            $cat = $this->categoryModel->create($body);
            Response::success($cat, 201);
        } catch (InvalidArgumentException $e) {
            if (isset($e->slugConflict)) {
                Response::error(
                    $e->getMessage(),
                    409,
                    'RESOURCE_CONFLICT',
                    ['slug' => ['The slug has already been taken.']]
                );
            }
            Response::error($e->getMessage(), 422, 'VALIDATION_ERROR');
        } catch (\Throwable $e) {
            Response::error($e->getMessage(), 500, 'SERVER_ERROR');
        }
    }

    /**
     * PATCH /api/v1/admin/categories/:id
     * Update category and prevent hierarchy cycles.
     */
    public function update(int $id): void
    {
        AuthMiddleware::authenticateAdmin();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        try {
            $cat = $this->categoryModel->update($id, $body);
            Response::success($cat, 200);
        } catch (InvalidArgumentException $e) {
            if (isset($e->slugConflict)) {
                Response::error(
                    $e->getMessage(),
                    409,
                    'RESOURCE_CONFLICT',
                    ['slug' => ['The slug has already been taken.']]
                );
            }
            if (isset($e->isCycle)) {
                Response::error(
                    $e->getMessage(),
                    422,
                    'HIERARCHY_CYCLE_ERROR',
                    ['parent_id' => [$e->getMessage()]]
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
}
