<?php

use App\Http\Controllers\API\ActivityLogController;
use App\Http\Controllers\API\AttributeController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\AuthorizationController;
use App\Http\Controllers\API\BrandController;
use App\Http\Controllers\API\CatalogImportController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\GoogleAuthController;
use App\Http\Controllers\API\PermissionController;
use App\Http\Controllers\API\PosController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\PurchaseController;
use App\Http\Controllers\API\ReportsController;
use App\Http\Controllers\API\RoleController;
use App\Http\Controllers\API\RolePermissionController;
use App\Http\Controllers\API\SalesController;
use App\Http\Controllers\API\SupplierController;
use App\Http\Controllers\API\SystemSettingController;
use App\Http\Controllers\API\TransactionController;
use App\Http\Controllers\API\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::get('/test-permissions', function () {
        try {
            \File::put(storage_path('test.txt'), 'ok');

            return 'Storage writable!';
        } catch (\Exception $e) {
            return 'Error: '.$e->getMessage();
        }
    });

    // Public routes (Unauthenticated)
    Route::middleware('guest.api')->group(function () {
        // Auth
        Route::prefix('auth')->group(function () {
            Route::post('/login', [AuthController::class, 'login']);
            Route::post('/login/google', [GoogleAuthController::class, 'login']);
        });

        Route::get('test-activity-logs', [ActivityLogController::class, 'showLogs']);
    });

    // Private routes (Authenticated)
    Route::middleware('auth:api')->group(function () {
        // Auth
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/refresh', [AuthController::class, 'refresh']);
            Route::get('/authorizers', [AuthorizationController::class, 'authorizers']);
            Route::put('/authorization-pin', [AuthorizationController::class, 'setPin']);
        });

        Route::group([], function () {
            // activity-logs
            Route::prefix('activity-logs')->middleware('modules:Activity Logs')->group(function () {
                Route::get('/', [ActivityLogController::class, 'showLogs'])->middleware('permissions:View Own,View All');
                Route::get('/export', [ActivityLogController::class, 'export'])->middleware('permissions:Export');
            });
            // Users
            Route::prefix('users')->middleware('modules:Users')->group(function () {
                Route::get('/', [UserController::class, 'index'])->middleware('permissions:View');
                Route::get('/assignable-roles', [UserController::class, 'assignableRoles'])->middleware('permissions:View');
                Route::delete('/{id}/authorization-pin', [UserController::class, 'clearAuthorizationPin'])->middleware('permissions:Edit');
                // modules middleware of users
                Route::get('/{id}', [UserController::class, 'show'])->middleware('permissions:View');
                Route::post('/', [UserController::class, 'store'])->middleware('permissions:Create');
                Route::post('/{id}/reset-password', [UserController::class, 'resetPassword'])->middleware('permissions:Edit');
                Route::patch('/{id}', [UserController::class, 'update'])->middleware('permissions:Edit');
                Route::delete('/{id}', [UserController::class, 'destroy'])->middleware('permissions:Delete');
            });

            // Roles
            Route::prefix('roles')->middleware('modules:Roles')->group(function () {
                Route::get('/', [RoleController::class, 'index'])->middleware('permissions:View');

                // module middleware of roles
                Route::get('/{id}', [RoleController::class, 'show'])->middleware('permissions:View');
                Route::post('/', [RoleController::class, 'store'])->middleware('permissions:Create');
                Route::put('/{id}', [RoleController::class, 'update'])->middleware('permissions:Edit');
                Route::delete('/{id}', [RoleController::class, 'destroy'])->middleware('permissions:Delete');
                Route::post('/{id}/permissions', [RolePermissionController::class, 'assignPermissions'])->middleware('permissions:Edit');
            });

            // Permissions
            Route::prefix('permissions')->middleware('modules:Roles')->group(function () {
                Route::get('/', [PermissionController::class, 'index'])->middleware('permissions:View');
            });

            // Categories
            Route::prefix('categories')->group(function () {
                // categories module middleware
                Route::middleware('modules:Categories')->group(function () {
                    Route::get('/', [CategoryController::class, 'index'])->middleware('permissions:View');
                    Route::post('/', [CategoryController::class, 'store'])->middleware('permissions:Create');
                    Route::get('/{id}', [CategoryController::class, 'show'])->middleware('permissions:View');
                    Route::put('/{id}', [CategoryController::class, 'update'])->middleware('permissions:Edit');
                    Route::delete('/{id}', [CategoryController::class, 'destroy'])->middleware('permissions:Delete');
                });
            });

            // Brands
            Route::prefix('brands')->group(function () {
                // brands module middleware
                Route::middleware('modules:Brands')->group(function () {
                    Route::get('/', [BrandController::class, 'index'])->middleware('permissions:View');
                    Route::get('/{id}', [BrandController::class, 'show'])->middleware('permissions:View');
                    Route::post('/', [BrandController::class, 'store'])->middleware('permissions:Create');
                    Route::put('/{id}', [BrandController::class, 'update'])->middleware('permissions:Edit');
                    Route::delete('/{id}', [BrandController::class, 'destroy'])->middleware('permissions:Delete');
                });
            });

            // Attributes
            Route::prefix('attributes')->group(function () {
                // attribute module middleware
                Route::middleware('modules:Attributes')->group(function () {
                    Route::get('/', [AttributeController::class, 'index'])->middleware('permissions:View');
                    Route::get('/{id}', [AttributeController::class, 'show'])->middleware('permissions:View');
                    Route::post('/', [AttributeController::class, 'store'])->middleware('permissions:Create');
                    Route::put('/{id}', [AttributeController::class, 'update'])->middleware('permissions:Edit');
                    Route::delete('/{id}', [AttributeController::class, 'destroy'])->middleware('permissions:Delete');
                });

                Route::post('/{id}/values', [AttributeController::class, 'storeAttributesValue'])->middleware(['modules:Attributes', 'permissions:Create']);
                Route::patch('/values/{valueId}', [AttributeController::class, 'updateAttributesValue'])->middleware(['modules:Attributes', 'permissions:Edit']);
                Route::delete('/values/{valueId}', [AttributeController::class, 'destroyAttributesValue'])->middleware(['modules:Attributes', 'permissions:Delete']);
            });

            // Products
            Route::middleware('modules:Products')->prefix('products')->group(function () {
                Route::get('/', [ProductController::class, 'index'])->middleware('permissions:Products.View');
                Route::get('/export', [ProductController::class, 'export'])->middleware('permissions:Products.Export');
                Route::post('/sku/generate', [ProductController::class, 'generateSku'])->middleware('permissions:Products.Create');
                Route::get('/{id}/stock-movements', [ProductController::class, 'movements'])->middleware('permissions:Products.View');
                Route::post('/{id}/stock-adjustments', [ProductController::class, 'adjustStock'])->middleware('permissions:Products.Adjust Stock');
                Route::get('/{id}', [ProductController::class, 'show'])->middleware('permissions:Products.View');
                Route::post('/', [ProductController::class, 'store'])->middleware('permissions:Products.Create');
                Route::put('/{id}', [ProductController::class, 'update'])->middleware('permissions:Products.Edit');
                Route::delete('/{id}', [ProductController::class, 'destroy'])->middleware('permissions:Products.Delete');

                Route::post('/{id}/attributes/{attributeId}', [ProductController::class, 'storeAttribute'])->middleware('permissions:Products.Edit');
                Route::delete('/{id}/attributeValueId/{attributeProductId}', [ProductController::class, 'destroyAttributeProduct'])->middleware('permissions:Products.Edit');
            });

            // Catalog imports
            Route::prefix('imports')->middleware('modules:Products,Categories,Brands,Attributes,Suppliers')->group(function () {
                Route::get('/catalog-template', [CatalogImportController::class, 'template'])->middleware('permissions:View,Create,Import');
                Route::post('/catalog', [CatalogImportController::class, 'import'])->middleware('permissions:Create,Import');
            });

            // suppliers
            Route::prefix('suppliers')->group(function () {
                // suppliers module middleware
                Route::middleware('modules:Suppliers')->group(function () {
                    Route::get('/', [SupplierController::class, 'index'])->middleware('permissions:View');
                    Route::post('/', [SupplierController::class, 'store'])->middleware('permissions:Create');
                    Route::get('/{id}', [SupplierController::class, 'show'])->middleware('permissions:View');
                    Route::patch('/{id}', [SupplierController::class, 'update'])->middleware('permissions:Edit');
                    Route::delete('/{id}', [SupplierController::class, 'destroy'])->middleware('permissions:Delete');
                });
            });

            // POS
            Route::prefix('pos')->middleware('modules:POS')->group(function () {
                Route::get('/products', [PosController::class, 'products'])->middleware('permissions:POS.Access');
                // Cart
                Route::prefix('cart')->group(function () {
                    // pos module middleware
                    Route::get('/', [PosController::class, 'show'])->middleware('permissions:Access');
                    Route::post('/add-item', [PosController::class, 'store'])->middleware('permissions:Access');
                    Route::patch('/update-item/{id}', [PosController::class, 'update'])->middleware('permissions:Access');
                    Route::delete('/remove-item/{id}', [PosController::class, 'delete'])->middleware('permissions:Access');
                    Route::post('/clear', [PosController::class, 'clearCart'])->middleware('permissions:Access');
                    Route::post('/apply-discount', [PosController::class, 'applyDiscount'])->middleware('permissions:POS.Request Discount,POS.Authorize Discount');
                });

                Route::post('/checkout', [PosController::class, 'checkoutCart'])->middleware('permissions:Create Transaction');
            });
            // purchase
            Route::prefix('purchases')->middleware('modules:Purchases')->group(function () {
                Route::get('/', [PurchaseController::class, 'index'])->middleware('permissions:View');
                Route::get('/{id}', [PurchaseController::class, 'show'])->middleware('permissions:View');
                Route::post('/', [PurchaseController::class, 'store'])->middleware('permissions:Create');
                Route::patch('/{id}', [PurchaseController::class, 'update'])->middleware('permissions:Edit');
                Route::delete('/{id}', [PurchaseController::class, 'destroy'])->middleware('permissions:Delete');
                Route::post('/{id}/receive', [PurchaseController::class, 'receive'])->middleware('permissions:Edit');
            });

            // Sales
            Route::prefix('sales')->middleware('role:superadmin,admin')->group(function () {
                Route::get('/', [SalesController::class, 'index']);
                Route::get('/{id}', [SalesController::class, 'show']);
                Route::post('/{id}/void', [SalesController::class, 'void']);
                Route::post('/{id}/refund', [SalesController::class, 'refund']);
                Route::get('/{id}/receipt', [SalesController::class, 'receipt']);
            });

            Route::prefix('transactions')->middleware('modules:Transactions')->group(function () {
                Route::get('/', [TransactionController::class, 'index'])->middleware('permissions:View,View Own,View All');
                Route::get('/daily-report', [TransactionController::class, 'dailyReport'])->middleware('permissions:View,View Own,View All');
                Route::get('/export', [TransactionController::class, 'export'])->middleware('permissions:Export');
                Route::get('/{id}', [TransactionController::class, 'show'])->middleware('permissions:View,View Own,View All');
                Route::get('/{id}/receipt', [TransactionController::class, 'receipt'])->middleware('permissions:View,View Own,View All');
                Route::post('/{id}/refund', [TransactionController::class, 'refund'])->middleware('permissions:Request Refund,Refund');
                Route::post('/{id}/void', [TransactionController::class, 'void'])->middleware('permissions:Request Void,Void');
            });

            // Reports
            Route::prefix('reports')->middleware('modules:Reports')->group(function () {
                Route::get('/sales', [ReportsController::class, 'showSalesReport'])->middleware('permissions:View');
                Route::get('/purchases', [ReportsController::class, 'showPurchases'])->middleware('permissions:View');
                Route::get('/inventory', [ReportsController::class, 'showInventory'])->middleware('permissions:View');
                Route::get('/product-performance', [ReportsController::class, 'showPerformance'])->middleware('permissions:View');
                Route::get('/stock-adjustments', [ReportsController::class, 'showStockAdjustments'])->middleware('permissions:View');
                Route::get('/profit-loss', [ReportsController::class, 'showProfitLossReport'])->middleware('permissions:View');
                Route::get('/{type}/export', [ReportsController::class, 'export'])->middleware('permissions:View');
            });

            // Settings
            Route::prefix('settings')->group(function () {
                // System Settings
                Route::prefix('system')->group(function () {
                    Route::middleware('modules:Settings')->group(function () {
                        Route::get('/', [SystemSettingController::class, 'index'])->middleware('permissions:View');
                        Route::patch('/', [SystemSettingController::class, 'update'])->middleware('permissions:Edit');

                        // Backup & Restore (Superadmin only)
                        Route::middleware('permissions:Settings.Manage Database')->group(function () {
                            Route::get('/backup', [SystemSettingController::class, 'backup']);
                            Route::post('/restore', [SystemSettingController::class, 'restore']);
                        });
                    });
                });
            });
        });

        // Dashboard
        Route::prefix('dashboard')->middleware('permissions:Dashboard.View')->group(function () {
            Route::get('/stats', [DashboardController::class, 'showStats']);
            Route::get('/charts/top-products', [DashboardController::class, 'showTopProducts']);
            Route::get('/recent-activities', [DashboardController::class, 'showRecentActivities']);

            Route::middleware('permissions:Dashboard.View Financial Data')->group(function () {
                Route::get('/charts/sales-trend', [DashboardController::class, 'showSalesTrend']);
                Route::get('/charts/inventory-overview', [DashboardController::class, 'showInventoryOverview']);
                Route::get('/charts/revenue-by-category', [DashboardController::class, 'showRevenueByCategory']);
                Route::get('/charts/revenue-by-brand', [DashboardController::class, 'showRevenueByBrand']);
            });
        });

        // Settings
        Route::prefix('settings')->group(function () {
            Route::get('/profile', [ProfileController::class, 'showProfile']);
            Route::patch('/profile', [ProfileController::class, 'updateProfile']);
            Route::patch('/password', [ProfileController::class, 'updatePassword']);
            Route::patch('/theme', [ProfileController::class, 'updateTheme']);
        });
    });
});
