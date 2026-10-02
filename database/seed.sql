-- ========================================================
-- Essence Store: Men's Perfume E-Commerce Platform
-- Sprint 2 Seed Data Fixture (catalog_seed.sql)
-- ========================================================

USE `essence_store`;

-- Clear existing data in correct FK order
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE `order_items`;
TRUNCATE TABLE `orders`;
TRUNCATE TABLE `cart_items`;
TRUNCATE TABLE `carts`;
TRUNCATE TABLE `assets`;
TRUNCATE TABLE `skus`;
TRUNCATE TABLE `variants`;
TRUNCATE TABLE `products`;
TRUNCATE TABLE `categories`;
TRUNCATE TABLE `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. USERS (Admin and Customer for RBAC tests)
INSERT INTO `users` (`id`, `full_name`, `email`, `password_hash`, `role`) VALUES
(1, 'Store Administrator', 'admin@essencestore.pk', '$2y$10$abcdefghijklmnopqrstuvwxyzA1B2C3D4E5F6G7H8I9J0K', 'admin'),
(2, 'Ahmed Khan', 'customer@example.com', '$2y$10$abcdefghijklmnopqrstuvwxyzA1B2C3D4E5F6G7H8I9J0K', 'customer');

-- 2. CATEGORIES (2 Levels: Root + 3 Children)
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `is_active`) VALUES
(1, NULL, 'Men\'s Fragrance', 'mens-fragrance', 1),
(2, 1, 'Woody & Earthy', 'woody-earthy', 1),
(3, 1, 'Fresh & Citrus', 'fresh-citrus', 1),
(4, 1, 'Oriental & Spicy', 'oriental-spicy', 1);

-- 3. PRODUCTS (3 Representative Perfume Masters)
INSERT INTO `products` (`id`, `category_id`, `name`, `slug`, `brand`, `description`, `status`, `specifications`) VALUES
(1, 2, 'Royal Oud Noir', 'royal-oud-noir', 'Essence Heritage', 
 'An opulent, smoky blend of Cambodian agarwood, cardamom, and dark amber crafted for refined gentlemen.', 
 'published', 
 '{"olfactory_pyramid": {"top_notes": ["Cardamom", "Bergamot", "Pink Pepper"], "heart_notes": ["Cedarwood", "Patchouli"], "base_notes": ["Agarwood Oud", "Leather", "Amber"]}, "longevity": "Long Lasting (7-10 hrs)", "sillage": "Strong"}'),

(2, 3, 'Citrus Riviera Cologne', 'citrus-riviera-cologne', 'Essence Aqua', 
 'A refreshing, sun-drenched breeze of Calabrian lemon, bitter orange, and sea minerals.', 
 'published', 
 '{"olfactory_pyramid": {"top_notes": ["Calabrian Lemon", "Mandarin", "Bergamot"], "heart_notes": ["Rosemary", "Neroli", "Sea Salt"], "base_notes": ["Vetiver", "White Musk", "Cedar"]}, "longevity": "Moderate (4-6 hrs)", "sillage": "Moderate"}'),

(3, 4, 'Velvet Amber Nights', 'velvet-amber-nights', 'Essence Privée', 
 'A nocturnal, sultry composition of bourbon vanilla, roasted tonka bean, and warm frankincense.', 
 'draft', 
 '{"olfactory_pyramid": {"top_notes": ["Nutmeg", "Cinnamon"], "heart_notes": ["Bourbon Vanilla", "Tonka Bean"], "base_notes": ["Amber", "Frankincense", "Sandalwood"]}, "longevity": "Eternal (12+ hrs)", "sillage": "Enormous"}');

-- 4. VARIANTS (Attribute Option Definitions)
-- For Product 1 (Royal Oud Noir)
INSERT INTO `variants` (`id`, `product_id`, `option_name`, `option_value`) VALUES
(1, 1, 'Bottle Size & Concentration', '50ml Eau de Parfum'),
(2, 1, 'Bottle Size & Concentration', '100ml Eau de Parfum'),
(3, 1, 'Bottle Size & Concentration', '200ml Pure Parfum');

-- For Product 2 (Citrus Riviera Cologne)
INSERT INTO `variants` (`id`, `product_id`, `option_name`, `option_value`) VALUES
(4, 2, 'Bottle Size & Concentration', '100ml Eau de Toilette');

-- 5. SKUS (4 Valid Sellable SKUs)
-- Note: CAT-04 Compliance: The 200ml Eau de Toilette combination for Royal Oud Noir 
-- is INTENTIONALLY NOT CREATED (no fake or zero-stock SKU).
INSERT INTO `skus` (`id`, `product_id`, `variant_id`, `sku_code`, `price`, `stock_quantity`, `is_active`) VALUES
(101, 1, 1, 'SKU-RON-50EDP', 65.00, 40, 1),
(102, 1, 2, 'SKU-RON-100EDP', 110.00, 25, 1),
(103, 1, 3, 'SKU-RON-200PAR', 195.00, 10, 1),
(104, 2, 4, 'SKU-CRC-100EDT', 55.00, 50, 1);

-- 6. ASSETS (Images linked to products and SKUs)
INSERT INTO `assets` (`id`, `product_id`, `sku_id`, `storage_url`, `role`, `alt_text`, `sort_order`) VALUES
(1, 1, NULL, '/assets/perfumes/royal-oud-noir-hero.webp', 'hero', 'Royal Oud Noir Luxury Perfume Bottle', 1),
(2, 1, 102, '/assets/perfumes/royal-oud-noir-100ml.webp', 'gallery', 'Royal Oud Noir 100ml Eau de Parfum Packshot', 2),
(3, 2, 104, '/assets/perfumes/citrus-riviera-hero.webp', 'hero', 'Citrus Riviera Cologne Fresh Summer Spray', 1);
