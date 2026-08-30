<?php

use App\Http\Controllers\Admin\AccountController as AdminAccountController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\ChatController as AdminChatController;
use App\Http\Controllers\Admin\ContactMessageController as AdminContactMessageController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DatabaseController;
use App\Http\Controllers\Admin\DeletedRecordController;
use App\Http\Controllers\Admin\TeamController as AdminTeamController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\PaymentMethodController as AdminPaymentMethodController;
use App\Http\Controllers\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ScholarshipController as AdminScholarshipController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SubscriptionController as AdminSubscriptionController;
use App\Http\Controllers\Admin\ToolController as AdminToolController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Public\AboutController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\FaqController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\ProductController as PublicProductController;
use App\Http\Controllers\Public\ScholarshipController as PublicScholarshipController;
use App\Http\Controllers\User\DashboardController;
use App\Http\Controllers\User\ChatController as UserChatController;
use App\Http\Controllers\User\NotificationController as UserNotificationController;
use App\Http\Controllers\User\OrderController;
use App\Http\Controllers\User\PaymentController as UserPaymentController;
use App\Http\Controllers\User\ProfileController;
use App\Http\Controllers\User\SubscriptionController;
use App\Http\Controllers\User\ToolController as UserToolController;
use App\Http\Controllers\User\ToolOrderController;

use Illuminate\Support\Facades\Route;

Route::get('/up', function () {
    return response('ok');
});

Route::get('/', [HomeController::class, 'index'])->name('public.home');
Route::get('/ticker/feed', [\App\Http\Controllers\Public\TickerController::class, 'feed'])->name('public.ticker.feed');
Route::get('/about', [AboutController::class, 'index'])->name('public.about');
Route::get('/faq', [FaqController::class, 'index'])->name('public.faq');
Route::get('/contact', [ContactController::class, 'index'])->name('public.contact');
Route::post('/contact', [ContactController::class, 'store'])->name('public.contact.store');
Route::view('/terms', 'public.legal.terms')->name('public.terms');
Route::view('/privacy', 'public.legal.privacy')->name('public.privacy');

Route::get('/scholarships', [PublicScholarshipController::class, 'index'])->name('public.scholarships.index');
Route::get('/scholarships/{scholarship:slug}', [PublicScholarshipController::class, 'show'])->name('public.scholarships.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/ai-tools', [PublicProductController::class, 'index'])->name('public.products.index');
    Route::get('/ai-tools/{product:slug}', [PublicProductController::class, 'show'])->name('public.products.show');

    Route::get('/research-tools', [UserToolController::class, 'index'])->name('user.tools.index');
    Route::get('/research-tools/{tool:slug}', [UserToolController::class, 'show'])->name('user.tools.show');
    Route::get('/research-tools/{tool:slug}/order', [ToolOrderController::class, 'create'])->name('user.tool-orders.create');
    Route::post('/tool-orders', [ToolOrderController::class, 'store'])->name('user.tool-orders.store');

    Route::get('/products/{product:slug}/order', [OrderController::class, 'create'])->name('user.orders.create');
    Route::get('/products/{product:slug}/order/{plan}', [OrderController::class, 'create'])->name('user.orders.create.plan');
    Route::post('/orders', [OrderController::class, 'store'])->name('user.orders.store');

    Route::get('/my-orders', [OrderController::class, 'index'])->name('user.orders.index');
    Route::get('/my-orders/{order}', [OrderController::class, 'show'])->name('user.orders.show');
    Route::get('/my-orders/{order}/download-software', [OrderController::class, 'downloadSoftware'])->name('user.orders.download-software');
    Route::get('/my-orders/{order}/download-tool', [ToolOrderController::class, 'download'])->name('user.orders.download-tool');
    Route::get('/my-orders/{order}/receipt', [ReceiptController::class, 'userShow'])->name('user.orders.receipt');

    Route::get('/payments', [UserPaymentController::class, 'index'])->name('user.payments.index');
    Route::get('/payments/{payment}', [UserPaymentController::class, 'show'])->name('user.payments.show');
    Route::get('/payments/{payment}/proof/{proof}', [UserPaymentController::class, 'showProof'])->name('user.payments.proof');
    Route::get('/orders/{order}/pay', [UserPaymentController::class, 'create'])->name('user.payments.create');
    Route::post('/orders/{order}/pay', [UserPaymentController::class, 'store'])->name('user.payments.store');

    Route::get('/messages', [UserChatController::class, 'index'])->name('user.chat.index');
    Route::get('/messages/{conversation}', [UserChatController::class, 'show'])->name('user.chat.show');
    Route::get('/messages/{conversation}/fetch', [UserChatController::class, 'fetch'])->name('user.chat.fetch');
    Route::post('/messages/{conversation}', [UserChatController::class, 'store'])->name('user.chat.store');
    Route::get('/messages/{conversation}/file/{message}', [UserChatController::class, 'attachment'])->name('user.chat.attachment');

    Route::get('/my-subscriptions', [SubscriptionController::class, 'index'])->name('user.subscriptions.index');
    Route::get('/my-subscriptions/{subscription}', [SubscriptionController::class, 'show'])->name('user.subscriptions.show');

    Route::get('/notifications', [UserNotificationController::class, 'index'])->name('user.notifications.index');
    Route::post('/notifications/mark-all-read', [UserNotificationController::class, 'markAllAsRead'])->name('user.notifications.mark-all-read');
    Route::post('/notifications/{notification}/read', [UserNotificationController::class, 'markAsRead'])->name('user.notifications.mark-read');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('user.profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('user.profile.update');
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->name('user.profile.password');
});

Route::prefix('admin')
    ->middleware(['auth', 'verified', 'admin'])
    ->name('admin.')
    ->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        // --- Users --------------------------------------------------------
        Route::middleware('can:users.view')->group(function () {
            Route::resource('users', AdminUserController::class)->only(['index', 'show']);
        });
        Route::middleware('can:users.manage')->group(function () {
            Route::post('users/{user}/suspend', [AdminUserController::class, 'suspend'])->name('users.suspend');
            Route::post('users/{user}/activate', [AdminUserController::class, 'activate'])->name('users.activate');
            Route::delete('users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
        });

        // --- Team (admins & permissions) — super admin only ---------------
        Route::middleware('can:team.manage')->group(function () {
            Route::get('team', [AdminTeamController::class, 'index'])->name('team.index');
            Route::get('team/create', [AdminTeamController::class, 'create'])->name('team.create');
            Route::post('team', [AdminTeamController::class, 'store'])->name('team.store');
            Route::get('team/{user}/edit', [AdminTeamController::class, 'edit'])->name('team.edit');
            Route::put('team/{user}', [AdminTeamController::class, 'update'])->name('team.update');
            Route::delete('team/{user}', [AdminTeamController::class, 'destroy'])->name('team.destroy');
        });

        // --- Database (schema changes & backup) — super admin only -------
        Route::middleware('can:database.access')->group(function () {
            Route::get('database', [DatabaseController::class, 'index'])->name('database.index');
            Route::post('database/apply', [DatabaseController::class, 'apply'])->name('database.apply');
            Route::get('database/backup', [DatabaseController::class, 'backup'])->name('database.backup');
        });

        // --- Catalogue ---------------------------------------------------
        Route::middleware('can:products.view')->group(function () {
            Route::resource('products', AdminProductController::class)->only(['index', 'show']);
            Route::resource('plans', AdminPlanController::class)->only(['index', 'show']);
        });
        Route::middleware('can:products.manage')->group(function () {
            Route::resource('products', AdminProductController::class)->except(['index', 'show']);
            Route::post('software-files', [AdminProductController::class, 'uploadSoftwareFile'])->name('software-files.store');
            Route::resource('plans', AdminPlanController::class)->except(['index', 'show']);
        });

        Route::middleware('can:tools.view')->group(function () {
            Route::resource('tools', AdminToolController::class)->only(['index']);
        });
        Route::middleware('can:tools.manage')->group(function () {
            Route::resource('tools', AdminToolController::class)->except(['show', 'index']);
            Route::post('tool-files', [AdminToolController::class, 'uploadFile'])->name('tool-files.store');
        });

        Route::middleware('can:scholarships.view')->group(function () {
            Route::resource('scholarships', AdminScholarshipController::class)->only(['index']);
        });
        Route::middleware('can:scholarships.manage')->group(function () {
            Route::resource('scholarships', AdminScholarshipController::class)->except(['show', 'index']);
        });

        // --- Contact messages ------------------------------------------
        Route::middleware('can:contact_messages.view')->group(function () {
            Route::get('contact-messages', [AdminContactMessageController::class, 'index'])->name('contact-messages.index');
            Route::get('contact-messages/{contactMessage}', [AdminContactMessageController::class, 'show'])->name('contact-messages.show');
        });
        Route::middleware('can:contact_messages.manage')->group(function () {
            Route::delete('contact-messages/{contactMessage}', [AdminContactMessageController::class, 'destroy'])->name('contact-messages.destroy');
        });

        // --- Shared accounts ------------------------------------------
        Route::middleware('can:accounts.view')->group(function () {
            Route::get('accounts', [AdminAccountController::class, 'index'])->name('accounts.index');
            Route::get('accounts/{account}', [AdminAccountController::class, 'show'])->name('accounts.show');
        });
        Route::middleware('can:accounts.manage')->group(function () {
            Route::resource('accounts', AdminAccountController::class)->only(['create', 'store', 'edit', 'update', 'destroy']);
            Route::post('accounts/{account}/decrypt', [AdminAccountController::class, 'decryptCredentials'])->name('accounts.decrypt');
        });

        // --- Orders ---------------------------------------------------
        Route::middleware('can:orders.view')->group(function () {
            Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
            Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
            Route::get('orders/{order}/receipt', [ReceiptController::class, 'adminShow'])->name('orders.receipt');
        });
        Route::middleware('can:orders.manage')->group(function () {
            Route::get('orders/{order}/edit', [AdminOrderController::class, 'edit'])->name('orders.edit');
            Route::put('orders/{order}', [AdminOrderController::class, 'update'])->name('orders.update');
            Route::post('orders/{order}/reject', [AdminOrderController::class, 'reject'])->name('orders.reject');
            Route::delete('orders/{order}', [AdminOrderController::class, 'destroy'])->name('orders.destroy');
            Route::post('orders/{order}/reopen-access', [AdminOrderController::class, 'reopenAccess'])->name('orders.reopen-access');
        });

        // --- Payments -----------------------------------------------
        Route::middleware('can:payments.view')->group(function () {
            Route::get('payments', [AdminPaymentController::class, 'index'])->name('payments.index');
            Route::get('payments/{payment}', [AdminPaymentController::class, 'show'])->name('payments.show');
            Route::get('payments/{payment}/proof/{proof}', [AdminPaymentController::class, 'showProof'])->name('payments.proof');
        });
        Route::middleware('can:payments.manage')->group(function () {
            Route::post('payments/{payment}/approve', [AdminPaymentController::class, 'approve'])->name('payments.approve');
            Route::post('payments/{payment}/reject', [AdminPaymentController::class, 'reject'])->name('payments.reject');
        });

        Route::middleware('can:payment_methods.manage')->group(function () {
            Route::get('payment-methods', [AdminPaymentMethodController::class, 'index'])->name('payment-methods.index');
            Route::post('payment-methods', [AdminPaymentMethodController::class, 'store'])->name('payment-methods.store');
            Route::put('payment-methods/{paymentMethod}', [AdminPaymentMethodController::class, 'update'])->name('payment-methods.update');
            Route::post('payment-methods/{paymentMethod}/qr', [AdminPaymentMethodController::class, 'uploadQr'])->name('payment-methods.qr');
            Route::delete('payment-methods/{paymentMethod}/qr', [AdminPaymentMethodController::class, 'removeQr'])->name('payment-methods.qr.remove');
        });

        // --- Support chat ------------------------------------------
        Route::middleware('can:chat.view')->group(function () {
            Route::get('messages', [AdminChatController::class, 'index'])->name('chat.index');
            Route::get('messages/create', [AdminChatController::class, 'create'])->name('chat.create');
            Route::get('messages/{conversation}', [AdminChatController::class, 'show'])->name('chat.show');
            Route::get('messages/{conversation}/fetch', [AdminChatController::class, 'fetch'])->name('chat.fetch');
            Route::get('messages/{conversation}/file/{message}', [AdminChatController::class, 'attachment'])->name('chat.attachment');
        });
        Route::middleware('can:chat.manage')->group(function () {
            Route::post('messages/create', [AdminChatController::class, 'start'])->name('chat.start');
            Route::post('messages/{conversation}', [AdminChatController::class, 'store'])->name('chat.store');
        });

        // --- Subscriptions ----------------------------------------
        Route::middleware('can:subscriptions.view')->group(function () {
            Route::get('subscriptions', [AdminSubscriptionController::class, 'index'])->name('subscriptions.index');
            Route::get('subscriptions/{subscription}', [AdminSubscriptionController::class, 'show'])->name('subscriptions.show');
        });
        Route::middleware('can:subscriptions.manage')->group(function () {
            Route::post('subscriptions/{subscription}/extend', [AdminSubscriptionController::class, 'extend'])->name('subscriptions.extend');
            Route::post('subscriptions/{subscription}/suspend', [AdminSubscriptionController::class, 'suspend'])->name('subscriptions.suspend');
            Route::post('subscriptions/{subscription}/revoke', [AdminSubscriptionController::class, 'revoke'])->name('subscriptions.revoke');
            Route::post('subscriptions/{subscription}/expire', [AdminSubscriptionController::class, 'expire'])->name('subscriptions.expire');
        });

        // --- Reports ---------------------------------------------
        Route::middleware('can:reports.view')->group(function () {
            Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('reports/revenue', [ReportController::class, 'revenue'])->name('reports.revenue');
            Route::get('reports/orders', [ReportController::class, 'orders'])->name('reports.orders');
            Route::get('reports/products', [ReportController::class, 'products'])->name('reports.products');
            Route::get('reports/accounts', [ReportController::class, 'accounts'])->name('reports.accounts');
            Route::get('reports/subscriptions', [ReportController::class, 'subscriptions'])->name('reports.subscriptions');
        });

        // --- Notifications (personal — every admin) --------------
        Route::get('notifications', [AdminNotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/mark-all-read', [AdminNotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
        Route::post('notifications/{notification}/read', [AdminNotificationController::class, 'markAsRead'])->name('notifications.mark-read');

        Route::middleware('can:activity_logs.view')->group(function () {
            Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        });

        Route::middleware('can:deleted_records.view')->group(function () {
            Route::get('deleted-records', [DeletedRecordController::class, 'index'])->name('deleted-records.index');
            Route::get('deleted-records/{deletedRecord}', [DeletedRecordController::class, 'show'])->name('deleted-records.show');
        });

        Route::middleware('can:settings.manage')->group(function () {
            Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
            Route::post('settings', [SettingController::class, 'update'])->name('settings.update');
            Route::post('settings/branding', [SettingController::class, 'updateBranding'])->name('settings.branding.update');
            Route::post('settings/mail', [SettingController::class, 'updateMail'])->name('settings.mail.update');
            Route::post('settings/mail/test', [SettingController::class, 'testMail'])->name('settings.mail.test');
        });

        Route::middleware('can:finance.access')->group(function () {
        Route::get('finance/pin', [FinanceController::class, 'pin'])->name('finance.pin');
        Route::post('finance/pin', [FinanceController::class, 'verify'])->name('finance.pin.verify');
        Route::post('finance/lock', [FinanceController::class, 'lock'])->name('finance.lock');
        Route::middleware('finance.unlocked')->group(function () {
            Route::get('finance', [FinanceController::class, 'dashboard'])->name('finance.dashboard');
            Route::get('finance/capital', [FinanceController::class, 'capitalIndex'])->name('finance.capital.index');
            Route::post('finance/capital', [FinanceController::class, 'capitalStore'])->name('finance.capital.store');
            Route::get('finance/capital/{capitalEntry}/edit', [FinanceController::class, 'capitalEdit'])->name('finance.capital.edit');
            Route::put('finance/capital/{capitalEntry}', [FinanceController::class, 'capitalUpdate'])->name('finance.capital.update');
            Route::delete('finance/capital/{capitalEntry}', [FinanceController::class, 'capitalDestroy'])->name('finance.capital.destroy');
            Route::get('finance/expenses', [FinanceController::class, 'expenseIndex'])->name('finance.expenses.index');
            Route::post('finance/expenses', [FinanceController::class, 'expenseStore'])->name('finance.expenses.store');
            Route::get('finance/expenses/{expense}/receipt', [FinanceController::class, 'expenseReceipt'])->name('finance.expenses.receipt');
            Route::get('finance/expenses/{expense}/edit', [FinanceController::class, 'expenseEdit'])->name('finance.expenses.edit');
            Route::put('finance/expenses/{expense}', [FinanceController::class, 'expenseUpdate'])->name('finance.expenses.update');
            Route::delete('finance/expenses/{expense}', [FinanceController::class, 'expenseDestroy'])->name('finance.expenses.destroy');
        });
        });
    });

require __DIR__.'/auth.php';