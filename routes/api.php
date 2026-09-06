<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ScholarshipController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\ToolController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes for the MHub cross-platform client (Sanctum token auth)
|--------------------------------------------------------------------------
*/

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/dashboard', [DashboardController::class, 'user']);
    Route::get('/currency-rates', [\App\Http\Controllers\Api\CurrencyController::class, 'rates']);

    // Catalogue
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{slug}', [ProductController::class, 'show']);
    Route::get('/tools', [ToolController::class, 'index']);
    Route::get('/tools/{slug}', [ToolController::class, 'show']);
    Route::get('/scholarships', [ScholarshipController::class, 'index']);
    Route::get('/scholarships/{slug}', [ScholarshipController::class, 'show']);

    // Research & Consultancy library (reader)
    Route::get('/research', [\App\Http\Controllers\Api\ResearchController::class, 'index']);
    Route::get('/research/mine', [\App\Http\Controllers\Api\ResearchController::class, 'mine']);
    Route::get('/research/category/{slug}', [\App\Http\Controllers\Api\ResearchController::class, 'category']);
    Route::get('/research/{slug}', [\App\Http\Controllers\Api\ResearchController::class, 'show']);
    Route::get('/research/{slug}/chapters/{chapter}', [\App\Http\Controllers\Api\ResearchController::class, 'chapter']);
    Route::post('/research/{slug}/progress', [\App\Http\Controllers\Api\ResearchController::class, 'markSection']);

    // Orders
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);

    // Payments
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::get('/payment-methods', [PaymentController::class, 'methods']);
    Route::get('/payments/{payment}', [PaymentController::class, 'show']);
    Route::post('/orders/{order}/payments', [PaymentController::class, 'store']);

    // Subscriptions
    Route::get('/subscriptions', [SubscriptionController::class, 'index']);
    Route::get('/subscriptions/{subscription}', [SubscriptionController::class, 'show']);

    // Support chat
    Route::get('/chat', [ChatController::class, 'show']);
    Route::post('/chat', [ChatController::class, 'store']);

    // Profile
    Route::match(['put', 'patch'], '/profile', [ProfileController::class, 'update']);
    Route::match(['put', 'patch'], '/profile/password', [ProfileController::class, 'updatePassword']);

    // Push notification device registration
    Route::post('/device-tokens', [DeviceTokenController::class, 'store']);
    Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy']);

    // In-app notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read']);

    // Admin area
    Route::middleware('admin')->prefix('admin')->name('api.admin.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'admin']);

        Route::get('/orders', [\App\Http\Controllers\Api\Admin\OrderController::class, 'index']);
        Route::get('/orders/{order}', [\App\Http\Controllers\Api\Admin\OrderController::class, 'show']);
        Route::post('/orders/{order}/reject', [\App\Http\Controllers\Api\Admin\OrderController::class, 'reject']);

        Route::get('/payments', [\App\Http\Controllers\Api\Admin\PaymentController::class, 'index']);
        Route::get('/payments/{payment}', [\App\Http\Controllers\Api\Admin\PaymentController::class, 'show']);
        Route::post('/payments/{payment}/approve', [\App\Http\Controllers\Api\Admin\PaymentController::class, 'approve']);
        Route::post('/payments/{payment}/reject', [\App\Http\Controllers\Api\Admin\PaymentController::class, 'reject']);
        Route::get('/payments/{payment}/proofs/{proof}', [\App\Http\Controllers\Api\Admin\PaymentController::class, 'proof'])
            ->name('payments.proof');

        Route::get('/users', [\App\Http\Controllers\Api\Admin\UserController::class, 'index']);
        Route::get('/users/{user}', [\App\Http\Controllers\Api\Admin\UserController::class, 'show']);
        Route::post('/users/{user}/status', [\App\Http\Controllers\Api\Admin\UserController::class, 'setStatus']);

        Route::get('/chat', [\App\Http\Controllers\Api\Admin\ChatController::class, 'index']);
        Route::get('/chat/{conversation}', [\App\Http\Controllers\Api\Admin\ChatController::class, 'show']);
        Route::post('/chat/{conversation}', [\App\Http\Controllers\Api\Admin\ChatController::class, 'store']);

        Route::get('/reports', [\App\Http\Controllers\Api\Admin\ReportController::class, 'index']);

        // Team / RBAC (super admin only)
        Route::controller(\App\Http\Controllers\Api\Admin\TeamController::class)->group(function () {
            Route::get('/team', 'index');
            Route::post('/team', 'store');
            Route::get('/team/{user}', 'show');
            Route::put('/team/{user}', 'update');
            Route::delete('/team/{user}', 'destroy');
        });

        // System: error logs, deleted records, activity, settings, subscriptions, payment methods
        Route::controller(\App\Http\Controllers\Api\Admin\SystemController::class)->group(function () {
            Route::get('/error-logs', 'errorLogs');
            Route::get('/error-logs/{errorLog}', 'errorLog');
            Route::post('/error-logs/{errorLog}/resolve', 'resolveError');
            Route::post('/error-logs/{errorLog}/reopen', 'reopenError');
            Route::delete('/error-logs/{errorLog}', 'deleteError');

            Route::get('/deleted-records', 'deletedRecords');
            Route::get('/deleted-records/{deletedRecord}', 'deletedRecord');

            Route::get('/activity', 'activity');

            Route::get('/settings', 'settings');
            Route::post('/settings/dev', 'updateDev');

            Route::get('/subscriptions', 'subscriptions');
            Route::get('/subscriptions/{subscription}', 'subscription');
            Route::post('/subscriptions/{subscription}/extend', 'extendSubscription');
            Route::post('/subscriptions/{subscription}/state', 'setSubscriptionState');

            Route::get('/payment-methods', 'paymentMethods');
            Route::post('/payment-methods', 'storePaymentMethod');
            Route::put('/payment-methods/{paymentMethod}', 'updatePaymentMethod');
            Route::post('/payment-methods/{paymentMethod}/qr', 'uploadPaymentMethodQr');

            Route::get('/contact-messages', 'contactMessages');
            Route::get('/contact-messages/{contactMessage}', 'contactMessage');
            Route::delete('/contact-messages/{contactMessage}', 'deleteContactMessage');
        });

        // Database (super admin only)
        Route::controller(\App\Http\Controllers\Api\Admin\DatabaseController::class)->prefix('database')->group(function () {
            Route::get('/', 'index');
            Route::post('/apply', 'apply');
            Route::post('/backup', 'backup');
            Route::get('/backups/{name}', 'downloadBackup');
        });

        // Shared account vault
        Route::controller(\App\Http\Controllers\Api\Admin\AccountController::class)->prefix('accounts')->group(function () {
            Route::get('/', 'index');
            Route::get('/targets', 'targets');
            Route::post('/', 'store');
            Route::get('/{account}', 'show');
            Route::put('/{account}', 'update');
            Route::post('/{account}/archive', 'archive');
            Route::post('/{account}/reveal', 'reveal');
        });

        // Finance (permission + per-session PIN unlock)
        Route::controller(\App\Http\Controllers\Api\Admin\FinanceController::class)->prefix('finance')->group(function () {
            Route::get('/status', 'status');
            Route::post('/unlock', 'unlock');
            Route::get('/targets', 'targets');
            Route::get('/overview', 'overview');
            Route::get('/expenses', 'expenses');
            Route::post('/expenses', 'storeExpense');
            Route::get('/expenses/{expense}/receipt', 'receipt');
            Route::get('/capital', 'capital');
            Route::post('/capital', 'storeCapital');
        });

        // Catalogue management
        Route::prefix('catalogue')->group(function () {
            $c = \App\Http\Controllers\Api\Admin\CatalogueController::class;

            Route::get('/products', [$c, 'products']);
            Route::post('/products', [$c, 'productStore']);
            Route::get('/products/{product}', [$c, 'productShow']);
            Route::put('/products/{product}', [$c, 'productUpdate']);
            Route::post('/products/{product}/image', [$c, 'productImage']);
            Route::delete('/products/{product}', [$c, 'productDestroy']);

            Route::post('/products/{product}/plans', [$c, 'planStore']);
            Route::put('/plans/{plan}', [$c, 'planUpdate']);
            Route::delete('/plans/{plan}', [$c, 'planDestroy']);

            Route::get('/tools', [$c, 'tools']);
            Route::post('/tools', [$c, 'toolStore']);
            Route::get('/tools/{tool}', [$c, 'toolShow']);
            Route::put('/tools/{tool}', [$c, 'toolUpdate']);
            Route::post('/tools/{tool}/image', [$c, 'toolImage']);
            Route::delete('/tools/{tool}', [$c, 'toolDestroy']);

            Route::get('/scholarships', [$c, 'scholarships']);
            Route::post('/scholarships', [$c, 'scholarshipStore']);
            Route::get('/scholarships/{scholarship}', [$c, 'scholarshipShow']);
            Route::put('/scholarships/{scholarship}', [$c, 'scholarshipUpdate']);
            Route::post('/scholarships/{scholarship}/image', [$c, 'scholarshipImage']);
            Route::delete('/scholarships/{scholarship}', [$c, 'scholarshipDestroy']);
        });
    });
});
