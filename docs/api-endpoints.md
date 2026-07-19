## API ENDPOINT STRUCTURE

**Annotations:**

-   `(AP | AP only)` - Restricted to admin/superadmin roles
-   `(WP | with permission)` - Can access with module permission
-   `(requires auth)` - Needs authentication token
-   `(paginated)` - Supports page & limit parameters
-   `(filterable)` - Supports filtering via query params
-   `(searchable)` - Supports search/q parameter
-   `(file upload)` - Accepts multipart/form-data
-   `(returns file)` - Returns downloadable file

**Standard Query Parameters (for paginated endpoints):**

-   `page` (default: 1)
-   `limit` (default: 20)
-   `sort` (default varies per endpoint)
-   `order` (asc|desc, default: desc)

**Standard Response Format:**

```json
{
  "success": true,
  "data": [...],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 100,
    "total_pages": 5
  }
}
```

---

### Authentication

```
GET     /api/v1/auth/me (requires auth)
POST    /api/v1/auth/login
POST    /api/v1/auth/login/google
POST    /api/v1/auth/logout
POST    /api/v1/auth/refresh
```

### Users

```
GET     /api/v1/users (paginated, searchable, filterable, AP only)
GET     /api/v1/users/:id (AP and WP only)
PUT     /api/v1/users/:id (AP and WP only)
POST    /api/v1/users (AP an WP only)
POST    /api/v1/users/:id/activate (AP and WP only)
POST    /api/v1/users/:id/deactivate (AP and WP only)
POST    /api/v1/users/:id/reset-password (AP and WP only)
DELETE  /api/v1/users/:id (soft delete, AP only)
```

### Roles & Permissions

```
GET     /api/v1/roles (AP only)
POST    /api/v1/roles (AP only)
GET     /api/v1/roles/:id (AP only)
PUT     /api/v1/roles/:id (AP only)
DELETE  /api/v1/roles/:id (soft delete, AP only)
GET     /api/v1/permissions (AP only)
POST    /api/v1/roles/:id/permissions (AP only)
```

### Products

```
GET     /api/v1/products (paginated, searchable, filterable, AP only)
GET     /api/v1/products/:id (AP only)
GET     /api/v1/products/export (file, AP only)
POST    /api/v1/products (AP only)
POST    /api/v1/products/:id/attributes/:attributeId (AP only)
PUT     /api/v1/products/:id (AP only)
DELETE  /api/v1/products/:id (soft delete, AP only)
DELETE  /api/v1/products/:id/attributes/:attributeId (soft delete, AP only)
```

### Categories

```
GET     /api/v1/categories (paginated,searchable, AP only)
POST    /api/v1/categories (AP only)
GET     /api/v1/categories/:id (AP only)
PUT     /api/v1/categories/:id (AP only)
DELETE  /api/v1/categories/:id (soft delete, AP only)
```

### Brands

```
GET     /api/v1/brands (paginated, searchable, AP only)
POST    /api/v1/brands (AP only)
GET     /api/v1/brands/:id (AP only)
PUT     /api/v1/brands/:id (AP only)
DELETE  /api/v1/brands/:id (soft delete, AP only)
```

### Attributes

```
GET     /api/v1/attributes (paginated, searchable, AP only)
GET     /api/v1/attributes/:id (AP only)
POST    /api/v1/attributes (AP only)
POST    /api/v1/attributes/:id/values (AP only)
PUT     /api/v1/attributes/:id (AP only)
DELETE  /api/v1/attributes/:id (soft delete, AP only)
```

### Suppliers

```
GET     /api/v1/suppliers (paginated, searchable, AP only)
GET     /api/v1/suppliers/:id (AP only)
POST    /api/v1/suppliers (AP only)
PUT     /api/v1/suppliers/:id (AP only)
DELETE  /api/v1/suppliers/:id (soft delete, AP only)
```

### Inventory

```
GET     /api/v1/inventory (paginated, searchable, filterable, requires auth)
GET     /api/v1/inventory/:productId (requires auth)
GET     /api/v1/inventory/low-stock (requires auth)
GET     /api/v1/inventory/out-of-stock (requires auth)
GET     /api/v1/inventory/:productId/movements (filterable, AP and WP only)
POST    /api/v1/inventory/:productId/adjust (requires auth)
```

### Stock Adjustments

```
GET     /api/v1/stock-adjustments (paginated, filterable, AP and WP only)
GET     /api/v1/stock-adjustments/:id (returns file, AP and WP only)
```

### Stock Movement History (Unified View)

```
GET     /api/v1/stock-movements (paginated, filterable, AP and WP only)
GET     /api/v1/stock-movements/:id (AP and WP only)
GET     /api/v1/stock-movements/export (returns file, AP and WP only)
GET     /api/v1/inventory/:productId/movements (specific product history, AP and WP only)
```
Note: Stock movements include sales, purchases, and adjustments in one unified view.

### POS - Hold Cart Transaction (Drafts)

```
GET     /api/v1/pos/held-transactions (draft muna)
POST    /api/v1/pos/hold-transaction (draft muna)
POST    /api/v1/pos/resume-transaction/:id (draft muna)
DELETE  /api/v1/pos/held-transactions/:id (draft muna)
```

### POS - Cart Management

```
GET     /api/v1/pos/cart
POST    /api/v1/pos/cart/add-item
POST    /api/v1/pos/cart/clear
POST    /api/v1/pos/cart/apply-discount
PUT     /api/v1/pos/cart/update-item/:id
DELETE  /api/v1/pos/cart/remove-item/:id
```

### POS - Checkout & Payment (Create sale)

```
POST    /api/v1/pos/checkout (requires auth)
```

### Sales - Transaction History (After checkout) //currently, walang ui view sa figma nito.

```
GET     /api/v1/sales (paginated, searchable, filterable, AP and WP only)
GET     /api/v1/sales/:id/receipt (AP and WP only)
GET     /api/v1/sales/:id (AP and WP only)
POST    /api/v1/sales/:id/void (AP and WP only)
POST    /api/v1/sales/:id/refund (AP and WP only)
```

### Purchases

```
GET     /api/v1/purchases (paginated, searchable, filterable, AP and WP only)
GET     /api/v1/purchases/:id (AP and WP only)
POST    /api/v1/purchases (AP and WP only)
POST    /api/v1/purchases/:id/receive (AP and WP only)
PUT     /api/v1/purchases/:id (AP and WP only)
DELETE  /api/v1/purchases/:id (soft delete, AP and WP only)

```

### Dashboard

```
GET /api/v1/dashboard/stats (requires auth, response payload varies on user role)
GET /api/v1/dashboard/charts/sales-trend (requires auth, response payload varies on user role)
GET /api/v1/dashboard/charts/top-products (requires auth, response payload varies on user role)
GET /api/v1/dashboard/charts/revenue-by-category (AP only, response payload varies on user role)
GET /api/v1/dashboard/charts/inventory-overview (AP only, response payload varies on user role)
GET /api/v1/dashboard/recent-activities (requires auth, response payload varies on user role)

```

### Reports

```
GET /api/v1/reports/sales (filterable, AP and WP only)
GET /api/v1/reports/purchases (filterable, AP and WP only)
GET /api/v1/reports/inventory (filterable, AP and WP only)
GET /api/v1/reports/product-performance (filterable, AP and WP only)
GET /api/v1/reports/stock-adjustments (filterable, AP and WP only)
GET /api/v1/reports/profit-loss (filterable, AP and WP only)
GET /api/v1/reports/:type/export (filterable, AP and WP only)

```

### Activity Logs

```

GET /api/v1/activity-logs (paginated, searchable, filterable, AP and WP only, response payload varies on user role, )
GET /api/v1/activity-logs/export (WP only)

```

### Settings

```
GET /api/v1/settings/profile (requires auth)
PUT /api/v1/settings/profile (requires auth)
PUT /api/v1/settings/password (requires auth)
PUT /api/v1/settings/theme (requires auth)
GET /api/v1/settings/system/database (Settings.Manage Database)
POST /api/v1/settings/system/backups (Settings.Manage Database; queues manual backup)
GET /api/v1/settings/system/backups/:filename (Settings.Manage Database; returns temporary download URL)
DELETE /api/v1/settings/system/backups/:filename (Settings.Manage Database)
GET /api/v1/settings/system/operations/:id (Settings.Manage Database)
POST /api/v1/settings/system/restore (Settings.Manage Database; queues history-only restore)

```
