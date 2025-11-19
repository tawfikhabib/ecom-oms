# E-Commerce Order Management System

A production-ready REST API backend for an e-commerce order management system built with Laravel 11, featuring JWT authentication, inventory tracking, order workflows, and comprehensive role-based access control.

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Tech Stack](#tech-stack)
- [Installation](#installation)
- [Configuration](#configuration)
- [Database Setup](#database-setup)
- [Authentication](#authentication)
- [API Endpoints](#api-endpoints)
- [Architecture](#architecture)
- [Testing](#testing)
- [Performance & Scalability](#performance--scalability)
- [Database Sharding Strategy](#database-sharding-strategy)
- [Troubleshooting](#troubleshooting)

## Overview

This system is designed to handle complex e-commerce order management with real-time inventory tracking, multiple user roles (Admin, Vendor, Customer), and comprehensive order lifecycle management (Pending → Processing → Shipped → Delivered → Cancelled).

## Features

### 1. Product & Inventory Management
- **Product CRUD** with variants support
- **Real-time inventory tracking** with automatic stock deduction on order confirmation
- **Low stock alerts** (queued jobs for notifications)
- **Bulk CSV import** for products
- **Full-text search** (MySQL-based, Elasticsearch optional)
- **Inventory movement history** tracking

### 2. Order Processing
- **Multi-item orders** with flexible pricing
- **Complete order workflow**: Pending → Processing → Shipped → Delivered → Cancelled
- **Automatic inventory management**: deduct on confirmation, restore on cancellation
- **Order rollback** with inventory restoration
- **PDF invoice generation** using DomPDF
- **Email notifications** on order status changes (queued)

### 3. Authentication & Authorization
- **JWT authentication** with access and refresh tokens
- **Role-based access control** (RBAC):
  - **Admin**: Full system access
  - **Vendor**: Manage own products and orders
  - **Customer**: Place orders and view history
- **Permission-based granular controls**
- **Secure token refresh** mechanism

### 4. API Design
- **RESTful API** with versioning (`/api/v1`)
- **Pagination** on all list endpoints (default 15 per page)
- **Comprehensive error handling** and validation
- **Standardized JSON responses**
- **Rate limiting** (configurable)

## Tech Stack

- **Framework**: Laravel 11 (PHP 8.2+)
- **Database**: MySQL 8.0+ (or PostgreSQL)
- **Authentication**: JWT (tymon/jwt-auth)
- **Authorization**: Spatie Permissions
- **Queue Driver**: Database (configurable to Redis, SQS)
- **Cache**: File-based (configurable to Redis)
- **PDF Generation**: DomPDF
- **CSV Import**: Maatwebsite Excel
- **Search**: MySQL Full-Text Search (Elasticsearch optional via Scout)
- **Testing**: PHPUnit, Pest

## Installation

### Prerequisites

- PHP 8.2+ with required extensions
- MySQL 8.0+ or PostgreSQL
- Composer
- Git

### Steps

```bash
# 1. Clone the repository
git clone <repository-url>
cd ecom_oms

# 2. Install dependencies
composer install

# 3. Copy environment file
cp .env.example .env

# 4. Generate application key
php artisan key:generate

# 5. Generate JWT secret
php artisan jwt:secret

# 6. Create database
mysql -u root -p
CREATE DATABASE ecom_oms;
exit;

# 7. Run migrations
php artisan migrate

# 8. Seed the database with roles and test users
php artisan db:seed

# 9. Create the public storage symlink
php artisan storage:link

# 10. (Optional) Generate test data
php artisan tinker
# In tinker:
# App\Models\Product::factory(20)->create()
# App\Models\Order::factory(10)->create()
```

## Configuration

### Environment Variables

Key variables in `.env`:

```dotenv
APP_NAME="E-Commerce Order Management System"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ecom_oms
DB_USERNAME=root
DB_PASSWORD=

# JWT
JWT_ALGORITHM=HS256
JWT_SECRET=<generated-by-jwt:secret>

# Queue & Cache
QUEUE_CONNECTION=database
CACHE_DRIVER=file

# Filesystem
FILESYSTEM_DISK=private

# Email (for notifications)
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_FROM_ADDRESS="orders@example.com"
```

### Queue Configuration

#### Using Database Driver (Default)
```php
// config/queue.php
'default' => env('QUEUE_CONNECTION', 'database'),
```

#### Switch to Redis
```bash
composer require predis/predis
```

```dotenv
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### Cache Configuration

#### File-based (Default)
```dotenv
CACHE_DRIVER=file
```

#### Switch to Redis
```dotenv
CACHE_DRIVER=redis
```

### Rate Limiting

Configure in `app/Http/Middleware/TrustProxies.php` and routes:

```php
Route::middleware('throttle:60,1')->group(function () {
    // 60 requests per minute
});
```

## Database Setup

### Running Migrations

```bash
# Fresh migration (wipes all data)
php artisan migrate:fresh --seed

# Migrate only
php artisan migrate

# Rollback
php artisan migrate:rollback

# Check status
php artisan migrate:status
```

### Seeding

```bash
# Run all seeders
php artisan db:seed

# Run specific seeder
php artisan db:seed --class=DatabaseSeeder

# Seed with fresh migrations
php artisan migrate:fresh --seed
```

#### Default Test Users (After Seeding)

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@example.com | password |
| Vendor | vendor@example.com | password |
| Customer | customer@example.com | password |

## Authentication

### Register User

```http
POST /api/v1/auth/register
Content-Type: application/json

{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password",
    "password_confirmation": "password",
    "role": "customer"
}
```

**Response (201):**
```json
{
    "message": "User registered successfully",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com"
    }
}
```

### Login

```http
POST /api/v1/auth/login
Content-Type: application/json

{
    "email": "john@example.com",
    "password": "password"
}
```

**Response (200):**
```json
{
    "message": "Login successful",
    "data": {
        "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
        "token_type": "Bearer",
        "expires_in": 3600
    }
}
```

### Get Authenticated User

```http
GET /api/v1/auth/me
Authorization: Bearer {access_token}
```

### Refresh Token

```http
POST /api/v1/auth/refresh
Authorization: Bearer {access_token}
```

### Logout

```http
POST /api/v1/auth/logout
Authorization: Bearer {access_token}
```

## API Endpoints

### Products

#### List Products
```http
GET /api/v1/products?per_page=15&page=1
Authorization: Bearer {access_token}
```

#### Create Product
```http
POST /api/v1/products
Authorization: Bearer {access_token}
Content-Type: application/json

{
    "name": "Product Name",
    "sku": "SKU-001",
    "description": "Product description",
    "price": 99.99,
    "cost": 50.00,
    "quantity": 100,
    "low_stock_threshold": 10
}
```

#### Get Product
```http
GET /api/v1/products/{id}
Authorization: Bearer {access_token}
```

#### Update Product
```http
PUT /api/v1/products/{id}
Authorization: Bearer {access_token}
Content-Type: application/json

{
    "name": "Updated Name",
    "price": 149.99
}
```

#### Delete Product
```http
DELETE /api/v1/products/{id}
Authorization: Bearer {access_token}
```

#### Search Products
```http
GET /api/v1/products/search?q=laptop
Authorization: Bearer {access_token}
```

#### Get Low Stock Products
```http
GET /api/v1/products/low-stock
Authorization: Bearer {access_token}
```

### Orders

#### Create Order
```http
POST /api/v1/orders
Authorization: Bearer {access_token}
Content-Type: application/json

{
    "items": [
        {
            "product_id": 1,
            "product_variant_id": null,
            "quantity": 2,
            "price": 99.99
        }
    ],
    "tax_amount": 20.00,
    "shipping_amount": 10.00,
    "notes": "Please hurry"
}
```

#### List Orders
```http
GET /api/v1/orders?per_page=15
Authorization: Bearer {access_token}
```

#### Get Order
```http
GET /api/v1/orders/{id}
Authorization: Bearer {access_token}
```

#### Confirm Order (Deduct Inventory)
```http
POST /api/v1/orders/{id}/confirm
Authorization: Bearer {access_token}
```

#### Ship Order
```http
POST /api/v1/orders/{id}/ship
Authorization: Bearer {access_token}
```

#### Deliver Order
```http
POST /api/v1/orders/{id}/deliver
Authorization: Bearer {access_token}
```

#### Cancel Order (Restore Inventory)
```http
POST /api/v1/orders/{id}/cancel
Authorization: Bearer {access_token}
Content-Type: application/json

{
    "reason": "Customer requested cancellation"
}
```

#### Generate Invoice
```http
POST /api/v1/orders/{id}/invoice
Authorization: Bearer {access_token}
```

### Import Products (CSV)

Bulk product import is available via `POST /api/v1/products/import` (implemented next). Upload a CSV as form-data under the `file` field and the import will be processed asynchronously.

Recommended CSV columns (suggested):
- `sku` (required): unique product SKU to match existing products
- `name` (required): product name
- `description` (optional)
- `price` (required)
- `cost` (optional)
- `quantity` (optional)
- `low_stock_threshold` (optional)
- `vendor_email` or `vendor_id` (optional)
- `variants` (optional, JSON string)

Example CSV row:

sku,name,description,price,cost,quantity,low_stock_threshold,vendor_email,variants
"TSHIRT-001","T-Shirt","100% cotton",19.99,8.5,100,10,"vendor@example.com","[{\"size\":\"M\",\"color\":\"blue\"}]"

Upload example (curl):
```bash
curl -X POST http://127.0.0.1:8000/api/v1/products/import \\
    -H "Authorization: Bearer {access_token}" \\
    -F "file=@/path/to/product_import.csv"
```

Processing notes:
- Imports are processed by `ImportProductsJob` (queued). Ensure `php artisan queue:work` is running to process imports.
- Results will be written to a `-results.json` file next to the uploaded CSV (under `storage/app/private/imports/products` by default).
- For very large imports, chunking and per-row dispatch will be used (or recommended) to scale.

CSV Specification & Example
--------------------------
- Required columns: `sku`, `name`, `price`.
- Optional columns: `description`, `cost`, `quantity`, `low_stock_threshold`, `vendor_email`, `vendor_id`, `variants`.
- `variants` should be a JSON string representing an array of variant objects. Variant object fields: `sku` (optional), `name`, `attributes` (object), `price` (optional), `quantity` (optional).

Example CSV header and row:

```csv
sku,name,description,price,cost,quantity,low_stock_threshold,vendor_email,variants
TSHIRT-001,T-Shirt,100% cotton,19.99,8.5,100,10,vendor@example.com,"[{\"sku\":\"TSHIRT-001-RED\",\"name\":\"Red\",\"attributes\":{\"color\":\"red\"},\"price\":19.99,\"quantity\":40}]"
```

Import Behavior & Limitations
-----------------------------
- Idempotency: Products are upserted by `sku` (the job uses an idempotent `updateOrCreate`), so re-processing the same CSV will not create duplicate products.
- Transactions: Each CSV row is processed inside a DB transaction to avoid partially-applied rows. Failures on a row will be recorded in the results JSON and the job will continue processing remaining rows.
- Variant deduplication: Variants are matched by `sku` when provided; if no `sku` is supplied the job attempts to match by `attributes` JSON. If no match is found a new variant is created.
- Errors & results: Per-row errors are captured in the `-results.json` file. Inspect that file to see row-level failures and messages.
- Scale: For very large files (>10k rows) consider splitting or implementing chunked processing to avoid long-running jobs and memory pressure.

Re-running / Admin operations
----------------------------
- Re-dispatch a specific import by id (synchronous for debugging):

```bash
php artisan tinker
>>> \App\Jobs\ImportProductsJob::dispatchSync(<import_id>);
```

- Requeue pending/failed imports (queued worker):

```bash
php artisan imports:requeue           # requeue pending and failed (default)
php artisan imports:requeue 123       # requeue import id 123
php artisan imports:requeue --status=failed
```

Migration Note
--------------
The `product_imports` table is created by the migration `database/migrations/2025_11_18_000001_create_product_imports_table.php`. Run migrations to ensure the table exists:

```bash
php artisan migrate
```

Missing / Notable API endpoints
--------------------------------
- The project includes order lifecycle endpoints for shipping, delivery, and invoicing. Tests and example requests for these endpoints are not fully covered in the current test suite and may be added as needed:
    - `POST /api/v1/orders/{id}/ship`
    - `POST /api/v1/orders/{id}/deliver`
    - `POST /api/v1/orders/{id}/invoice`
- The token refresh endpoint `POST /api/v1/auth/refresh` is implemented but currently has no focused test in `tests/`.

Where to find import files & logs
--------------------------------
- Uploaded CSVs and results are stored under the configured filesystem disk (default recommended `private`): `storage/app/private/imports/products`.
- Import job logs and general application logs are at `storage/logs/laravel.log`.



## Architecture

### Folder Structure

```
app/
├── Actions/                  # Complex operations
├── Services/                 # Business logic
├── Repositories/             # Data access layer
├── Events/                   # Event classes
├── Listeners/                # Event listeners
├── Jobs/                     # Queued jobs
├── Http/
│   ├── Controllers/Api/V1/   # API controllers
│   ├── Requests/             # Form requests
│   └── Resources/            # API resources
└── Models/                   # Eloquent models

database/
├── migrations/               # Database migrations
└── seeders/                  # Database seeders

tests/
├── Feature/                  # Feature tests
└── Unit/                     # Unit tests
```

### Design Patterns

1. **Service Layer**: Business logic separated from controllers
2. **Repository Pattern**: Data access abstraction
3. **Actions**: Encapsulates complex operations with transactions
4. **Events & Listeners**: Decoupled event-driven architecture
5. **Queue Jobs**: Async processing for heavy tasks
6. **Request Classes**: Centralized validation
7. **Resources**: Standardized API responses

## Testing

### Run All Tests

```bash
php artisan test
php artisan test --coverage
```

### Run Specific Test Suite

```bash
php artisan test tests/Feature
php artisan test tests/Unit
php artisan test tests/Feature/Auth/AuthenticationTest
```

### Test Database

Tests use SQLite in-memory database for speed (configured in `phpunit.xml`).

### Example Test Coverage

- **Auth**: Register, Login, Logout, Token Refresh
- **Products**: CRUD, Search, Low Stock
- **Orders**: Create, Confirm, Cancel, Status Workflow
- **Permissions**: Role-based access
- **Services**: InventoryService, ProductService, OrderService

## Performance & Scalability

### Query Optimization

All routes implement eager loading to prevent N+1 queries:

```php
Product::with(['vendor', 'variants'])->paginate();
```

### Database Indexing

- Products: `vendor_id`, `sku`, `(name, description)` FULLTEXT
- Orders: `customer_id`, `status`, `created_at`
- OrderItems: `order_id`, `product_id`
- InventoryMovements: `product_id`, `type`, `created_at`

### Caching

Configure caching on frequently accessed data:

```php
Cache::remember("product:{$id}", 3600, fn() => Product::find($id));
```

### Queue Jobs

- `SendOrderNotificationJob`: Async email notifications
- `LowStockAlertJob`: Background alerts
- `SendInvoiceJob`: Async invoice delivery

### Response Pagination

All list endpoints support pagination (default 15 per page):

```json
{
    "data": [...],
    "pagination": {
        "total": 500,
        "per_page": 15,
        "current_page": 1,
        "last_page": 34
    }
}
```

## Database Sharding Strategy

For production with millions of orders, implement **range-based sharding on customer_id**:

```
Shard 1: customer_id 1-1,000,000 → Database: ecom_oms_shard_1
Shard 2: customer_id 1,000,001-2,000,000 → Database: ecom_oms_shard_2
Shard 3: customer_id 2,000,001-3,000,000 → Database: ecom_oms_shard_3
```

### Implementation

1. Create shard configuration in `config/sharding.php`
2. Create `ShardResolver` service to determine shard for customer
3. Override `getConnection()` in Order and OrderItem models
4. Update database configuration with shard hosts

### Benefits

- Horizontal scaling as customer base grows
- Reduced query load per database
- Parallel processing across shards
- Disaster recovery isolation

### Trade-offs

- Global lookups require scanning all shards
- Cross-shard transactions need application-level coordination
- Rebalancing required for even distribution

## Troubleshooting

### Common Issues

**JWT Token Expired**: Refresh token using `/api/v1/auth/refresh`

**Insufficient Inventory**: Check product quantity, restock if needed

**Permission Denied**: Ensure user has proper role assigned

**Database Connection Error**: Verify `.env` database credentials

### Debugging

```bash
# Enable query logging
php artisan tinker
DB::enableQueryLog();
// ... your code ...
dd(DB::getQueryLog());

# Check queue
php artisan queue:work

# View logs
tail -f storage/logs/laravel.log
```

## Support & Contributions

For issues and feature requests, please open a GitHub issue.

---

## Author

**Name**: Tawfik Habib  
**Email**: twfkhabib@gmail.com  
**GitHub**: https://github.com/tawfikhabib(https://github.com/tawfikhabib)

---

**Version**: 1.0.0 | **Status**: Production Ready | **Last Updated**: 2024-11-19
