<?php

namespace EssenceStore\Exceptions;

use InvalidArgumentException;

class ConflictException extends InvalidArgumentException
{
    public bool $slugConflict = false;
    public bool $skuConflict = false;

    public static function forSlug(string $slug): self
    {
        $e = new self("A resource with slug '{$slug}' already exists.");
        $e->slugConflict = true;
        return $e;
    }

    public static function forSku(string $skuCode): self
    {
        $e = new self("An SKU with code '{$skuCode}' already exists.");
        $e->skuConflict = true;
        return $e;
    }
}
