# Essence Store - Men's Perfume E-Commerce Platform

Essence Store is a specialized e-commerce platform dedicated to men's perfumes, built with PHP 8.2+, MySQL 8.0 / SQLite, and HTML5/CSS3 for the E-Commerce course at the Department of Computer Science / Artificial Intelligence, University of Sindh, Jamshoro.

---

## Project Sprints

* [Sprint 1: System Architecture & Scope Definition](docs/SPRINT_1.md)
* [Sprint 2: Catalog Data Foundation Manual & Implementation Document](docs/SPRINT_2.md)

---

## Local Development Setup

### 1. Requirements
* PHP 8.2 or higher (installed automatically via `winget install PHP.PHP.8.2`)
* MySQL 8.0 **or** SQLite (SQLite works out of the box — no server needed)
* Apache or PHP built-in CLI server

### 2. Environment Configuration

The project ships with `.env` pre-configured for **SQLite** (works out of the box). Copy the example if you need to customise:

```bash
copy .env.example .env
```

**SQLite (default — no setup needed):**
```ini
DB_CONNECTION=sqlite
DB_DATABASE=database/essence_store.sqlite
APP_ENV=local
APP_SECRET=your_secret_here
ADMIN_API_TOKEN=ADMIN_MOCK_TOKEN
```

**MySQL / XAMPP:**
```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=essence_store
DB_USERNAME=root
DB_PASSWORD=
APP_ENV=local
APP_SECRET=your_secret_here
ADMIN_API_TOKEN=ADMIN_MOCK_TOKEN
```

> **Security Notice:** Never commit `.env` or production credentials to the repository.

---

### 3. Database Migration and Seeding

**Option A — Universal PHP script (SQLite or MySQL):**
```bash
php database/init_db.php
```

**Option B — MySQL CLI (requires MySQL/XAMPP running):**
```bash
# 1. Run migrations / create tables
mysql -u root -p < database/schema.sql

# 2. Populate deterministic seed fixtures
mysql -u root -p < database/seed.sql
```

The seed data creates:
- **4 categories** in a 2-level hierarchy (Men's Fragrance → Woody & Earthy, Fresh & Citrus, Oriental & Spicy)
- **3 products** (Royal Oud Noir, Citrus Riviera Cologne, Velvet Amber Nights [draft])
- **4 variants + 4 SKUs** (1 combination intentionally absent per CAT-04)
- **3 asset records** with storage URLs

---

### 4. Running the Local Server

```bash
php -S localhost:8000 -t public
```

Then test the API at `http://localhost:8000/api/v1/health`

---

### 5. Running Automated Tests

```bash
php tests/run_tests.php
```

Expected output:
```
Catalog Foundation Test Suite
 [x] Product creation with required fields and specifications succeeds
 [x] Duplicate product slug rejection returns 409 conflict
 [x] Sku creation with positive decimal price and stock succeeds
 [x] Duplicate sku code rejection returns 409 conflict
 [x] Negative stock quantity rejection enforced by validation and check constraint
 [x] Negative price rejection enforced by validation and check constraint
 [x] Category self-referencing hierarchy cycle prevention rejects circular parent
 [x] Missing variant combination is not instantiated as fake sku
 [x] Administrative endpoints reject unauthenticated requests with 401
 [x] Administrative endpoints reject customer role requests with 403

OK (10 tests, 41 assertions)
```

---

## API Endpoints (Sprint 2 — Admin Catalog API)

All endpoints require: `Authorization: Bearer ADMIN_MOCK_TOKEN`

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/v1/health` | Health check |
| `GET` | `/api/v1/admin/categories` | Category hierarchy tree |
| `POST` | `/api/v1/admin/categories` | Create a category |
| `PATCH` | `/api/v1/admin/categories/:id` | Update a category |
| `GET` | `/api/v1/admin/products` | Admin product list |
| `POST` | `/api/v1/admin/products` | Create a draft product |
| `PATCH` | `/api/v1/admin/products/:id` | Update product / change status |
| `POST` | `/api/v1/admin/products/:id/skus` | Add SKU to product |
| `PATCH` | `/api/v1/admin/skus/:id` | Update SKU price/stock/status |

---

## Project Structure

```
ecommerce/
├── .env                        # Local environment config (SQLite by default)
├── .env.example                # Environment template
├── composer.json               # Project metadata
├── phpunit.xml                 # PHPUnit configuration
├── public/
│   ├── index.php               # Front controller / router
│   └── .htaccess               # Apache mod_rewrite
├── src/
│   ├── Config/
│   │   └── Database.php        # PDO connection manager
│   ├── Controllers/
│   │   ├── CategoryController.php
│   │   ├── ProductController.php
│   │   └── SkuController.php
│   ├── Exceptions/
│   │   ├── ConflictException.php
│   │   ├── HierarchyCycleException.php
│   │   ├── PublicationException.php
│   │   └── ValidationException.php
│   ├── Middleware/
│   │   └── AuthMiddleware.php  # Admin RBAC (401/403)
│   ├── Models/
│   │   ├── Category.php        # Hierarchy tree + cycle prevention
│   │   ├── Product.php         # Product master + business rules
│   │   ├── Sku.php             # Sellable SKU with constraints
│   │   └── Variant.php         # Variant option axes
│   ├── Response.php            # JSON response helpers
│   └── Router.php              # URL router
├── database/
│   ├── schema.sql              # MySQL schema (10 tables)
│   ├── seed.sql                # MySQL seed data
│   ├── init_db.php             # Universal PHP migration+seed script
│   └── essence_store.sqlite    # SQLite database file
├── tests/
│   ├── CatalogFoundationTest.php  # 10 tests, 41 assertions
│   ├── run_tests.php              # Zero-dependency test runner
│   └── verify_db.php              # DB content verification script
└── docs/
    ├── SPRINT_1.md
    └── SPRINT_2.md
```
