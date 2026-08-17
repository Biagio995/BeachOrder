<?php

use App\Http\Controllers\Api\Admin\SubscriptionController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Api\PrivacyController;
use App\Http\Controllers\Api\Admin\PrintingSettingsController;
use App\Http\Controllers\Api\Admin\TenantSettingsController;
use App\Http\Controllers\Webhook\NexiWebhookController;
use App\Http\Controllers\Webhook\PosWebhookController;
use App\Http\Controllers\Webhook\StripeWebhookController;
use App\Http\Controllers\Api\Admin\PosIntegrationController;
use App\Http\Controllers\Api\Admin\PosMappingController;
use App\Http\Controllers\Api\Admin\PosOrderSyncController;
use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\LocationController as AdminLocationController;
use App\Http\Controllers\Api\Admin\PlatformOverviewController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\TagController as AdminTagController;
use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\Admin\TenantController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LocationAccessController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\OrderPrintController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderPaymentController;
use App\Http\Controllers\Api\WaiterCallController;
use App\Support\RolePermissions;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Stripe webhooks (no auth — verified via signature)
|--------------------------------------------------------------------------
*/

Route::post('/webhooks/stripe', StripeWebhookController::class);
Route::post('/webhooks/nexi/{tenant}', [NexiWebhookController::class, 'notify']);
Route::post('/webhooks/pos/{tenant}', PosWebhookController::class);

/*
|--------------------------------------------------------------------------
| Monitoring (US-09) — public health probe for uptime checks
|--------------------------------------------------------------------------
*/
Route::get('/health', HealthController::class)->middleware('throttle:120,1');

/*
|--------------------------------------------------------------------------
| Public tenant-scoped customer endpoints
|--------------------------------------------------------------------------
*/

Route::get('t/{tenant}/status', [MenuController::class, 'tenantStatus']);

Route::prefix('t/{tenant}')->middleware(['tenant.route', 'throttle:60,1'])->group(function () {
    Route::get('/menu', [MenuController::class, 'menu']);
    Route::get('/categories', [MenuController::class, 'categories']);
    Route::get('/locations/code/{code}', [MenuController::class, 'locationByCode']);
    Route::post('/locations/code/{code}/claim', [LocationAccessController::class, 'claim'])
        ->middleware('throttle:30,1');
    Route::get('/tenant', [MenuController::class, 'tenantInfo']);

    Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:20,1');
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::get('/orders/{order}/payment', [OrderPaymentController::class, 'show']);
    Route::post('/orders/{order}/payment/nexi/return', [NexiWebhookController::class, 'customerReturn']);
    Route::get('/orders/{order}/payment/receipt', [OrderPaymentController::class, 'receipt']);

    Route::post('/waiter-call', [WaiterCallController::class, 'store'])->middleware('throttle:10,1');

    Route::get('/privacy/info', [PrivacyController::class, 'info']);
    Route::post('/privacy/erase-session', [PrivacyController::class, 'eraseCustomerSession'])
        ->middleware('throttle:5,1');
});

/*
|--------------------------------------------------------------------------
| Staff auth
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware('signed')
    ->name('verification.verify');

Route::get('/privacy/info', [PrivacyController::class, 'info']);
Route::get('/legal/{document}', [PrivacyController::class, 'legalDocument'])
    ->where('document', 'privacy|terms|cookies|dpa|data-processing-roles');

// Session endpoints: auth only — must not depend on X-Tenant (landing/bootstrap).
Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/me/export', [PrivacyController::class, 'exportMe']);
    Route::delete('/me/account', [PrivacyController::class, 'deleteMe']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::post('/email/verification-notification', [AuthController::class, 'sendVerificationNotification'])
        ->middleware('throttle:3,1');
    Route::post('/email/verify-otp', [AuthController::class, 'verifyEmailWithOtp'])
        ->middleware('throttle:10,1');
});

Route::middleware(['auth:sanctum', 'tenant.user', 'throttle:120,1'])->group(function () {
    Route::middleware('permission:' . RolePermissions::SETTINGS_MANAGE)->prefix('admin')->group(function () {
        Route::post('subscription/checkout', [SubscriptionController::class, 'checkout']);
    });
});

Route::middleware(['auth:sanctum', 'tenant.user', 'verified', 'throttle:120,1'])->group(function () {
    Route::middleware('permission:' . RolePermissions::SETTINGS_MANAGE)->prefix('admin')->group(function () {
        Route::get('subscription', [SubscriptionController::class, 'show']);
        Route::post('subscription/portal', [SubscriptionController::class, 'portal']);
    });
    Route::middleware(['subscription.active', 'permission:' . RolePermissions::ORDERS_VIEW])->group(function () {
        Route::get('/orders', [OrderController::class, 'index']);
    });

    Route::middleware(['subscription.active', 'permission:' . RolePermissions::ORDERS_UPDATE_STATUS])->group(function () {
        Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
        Route::post('/orders/{order}/print', [OrderPrintController::class, 'store']);
        Route::get('/orders/{order}/print-logs', [OrderPrintController::class, 'logs']);
    });

    Route::middleware(['subscription.active', 'permission:' . RolePermissions::ORDERS_UPDATE_PAYMENT])->group(function () {
        Route::patch('/orders/{order}/payment', [OrderController::class, 'updatePayment']);
    });

    Route::middleware(['subscription.active', 'permission:' . RolePermissions::WAITER_CALLS_MANAGE])->group(function () {
        Route::get('/waiter-calls', [WaiterCallController::class, 'index']);
        Route::patch('/waiter-calls/{waiterCall}/status', [WaiterCallController::class, 'updateStatus']);
    });

    Route::prefix('admin')->middleware('subscription.active')->group(function () {
        Route::middleware('permission:' . RolePermissions::MENU_MANAGE)->group(function () {
            Route::apiResource('categories', AdminCategoryController::class);
            Route::apiResource('tags', AdminTagController::class);
            Route::apiResource('products', AdminProductController::class);
            Route::post('products/{product}/image', [AdminProductController::class, 'uploadImage']);
            Route::delete('products/{product}/image', [AdminProductController::class, 'deleteImage']);
        });

        Route::middleware('permission:' . RolePermissions::QR_MANAGE)->group(function () {
            Route::apiResource('locations', AdminLocationController::class);
            Route::post('locations/{location}/regenerate-qr', [AdminLocationController::class, 'regenerateQr']);
        });

        Route::middleware('permission:' . RolePermissions::USERS_MANAGE)->group(function () {
            Route::apiResource('users', AdminUserController::class)->except(['show']);
        });

        Route::middleware('permission:' . RolePermissions::ORDERS_MANAGE)->group(function () {
            Route::get('reports/sales', [ReportController::class, 'sales']);
            Route::get('reports/products', [ReportController::class, 'products']);
        });

        Route::middleware('permission:' . RolePermissions::SETTINGS_MANAGE)->group(function () {
            Route::get('reports/summary', [ReportController::class, 'summary']);
            Route::get('settings', [TenantSettingsController::class, 'show']);
            Route::put('settings', [TenantSettingsController::class, 'update']);
            Route::post('settings/printing/test', [PrintingSettingsController::class, 'test']);
            Route::get('settings/printing/preview', [PrintingSettingsController::class, 'preview']);
            Route::get('privacy/inventory', [PrivacyController::class, 'inventory']);
            Route::get('branding', [TenantSettingsController::class, 'show']);
            Route::put('branding', [TenantSettingsController::class, 'update']);
            Route::post('branding/logo', [TenantSettingsController::class, 'uploadLogo']);
            Route::post('branding/favicon', [TenantSettingsController::class, 'uploadFavicon']);
            Route::post('branding/menu-header', [TenantSettingsController::class, 'uploadMenuHeader']);

            Route::get('pos-integration', [PosIntegrationController::class, 'show']);
            Route::put('pos-integration', [PosIntegrationController::class, 'update']);
            Route::post('pos-integration/test', [PosIntegrationController::class, 'testConnection']);
            Route::get('pos-integration/providers', [PosIntegrationController::class, 'providers']);

            Route::get('pos-mappings', [PosMappingController::class, 'index']);
            Route::get('pos-mappings/entities', [PosMappingController::class, 'entities']);
            Route::post('pos-mappings', [PosMappingController::class, 'store']);
            Route::post('pos-mappings/bulk', [PosMappingController::class, 'bulkStore']);
            Route::delete('pos-mappings/{posMapping}', [PosMappingController::class, 'destroy']);

            Route::get('pos-syncs', [PosOrderSyncController::class, 'index']);
            Route::get('pos-syncs/{posOrderSync}', [PosOrderSyncController::class, 'show']);
            Route::post('pos-syncs/{posOrderSync}/retry', [PosOrderSyncController::class, 'retry']);
        });
    });

    Route::prefix('platform')->middleware('permission:' . RolePermissions::TENANTS_MANAGE)->group(function () {
        Route::get('overview', PlatformOverviewController::class);
        Route::apiResource('tenants', TenantController::class);
    });
});
