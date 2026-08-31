<?php

use App\Http\Controllers\Admin\AccountController as AdminAccountController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\ChatController as AdminChatController;
use App\Http\Controllers\Admin\ContactMessageController as AdminContactMessageController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DatabaseController;
use App\Http\Controllers\Admin\DeletedRecordController;
use App\Http\Controllers\Admin\ErrorLogController;
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

// Research library — public teaser view (reading requires sign-in)
Route::get('/research', [\App\Http\Controllers\Public\ResearchController::class, 'index'])->name('research.index');
Route::get('/research/c/{category:slug}', [\App\Http\Controllers\Public\ResearchController::class, 'category'])->name('research.category');
Route::get('/research/{research:slug}', [\App\Http\Controllers\Public\ResearchController::class, 'show'])->name('research.show');

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
    Route::post('/my-orders/{order}/cancel', [OrderController::class, 'cancel'])->name('user.orders.cancel');
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

    // --- Research library — the signed-in reading experience ---------
    Route::get('/library', [\App\Http\Controllers\Research\LibraryController::class, 'index'])->name('library.index');
    Route::get('/library/c/{category:slug}', [\App\Http\Controllers\Research\LibraryController::class, 'category'])->name('library.category');
    Route::get('/library/{research:slug}', [\App\Http\Controllers\Research\LibraryController::class, 'show'])->name('library.show');
    Route::get('/library/{research:slug}/read/{chapter}', [\App\Http\Controllers\Research\LibraryController::class, 'read'])->name('library.read');
    Route::post('/library/{research:slug}/progress', [\App\Http\Controllers\Research\LibraryController::class, 'markSection'])->name('library.progress');

    // --- Research & Consultancy — contributor portal -----------------
    Route::middleware('research.contribute')->group(function () {
        Route::get('/my-research', [\App\Http\Controllers\Research\ContributorController::class, 'index'])->name('research.contributor.index');
        Route::get('/my-research/create', [\App\Http\Controllers\Research\ContributorController::class, 'create'])->name('research.contributor.create');
        Route::post('/my-research', [\App\Http\Controllers\Research\ContributorController::class, 'store'])->name('research.contributor.store');
        Route::get('/my-research/{research}/edit', [\App\Http\Controllers\Research\ContributorController::class, 'edit'])->name('research.contributor.edit');
        Route::put('/my-research/{research}', [\App\Http\Controllers\Research\ContributorController::class, 'update'])->name('research.contributor.update');
        Route::delete('/my-research/{research}', [\App\Http\Controllers\Research\ContributorController::class, 'destroy'])->name('research.contributor.destroy');
        Route::post('/my-research/{research}/submit', [\App\Http\Controllers\Research\ContributorController::class, 'submit'])->name('research.contributor.submit');
        Route::post('/my-research/{research}/reorder', [\App\Http\Controllers\Research\ContributorController::class, 'reorder'])->name('research.contributor.reorder');

        Route::post('/my-research/{research}/chapters', [\App\Http\Controllers\Research\ContributorController::class, 'storeChapter'])->name('research.contributor.chapter.store');
        Route::put('/my-research/chapters/{chapter}', [\App\Http\Controllers\Research\ContributorController::class, 'updateChapter'])->name('research.contributor.chapter.update');
        Route::delete('/my-research/chapters/{chapter}', [\App\Http\Controllers\Research\ContributorController::class, 'destroyChapter'])->name('research.contributor.chapter.destroy');

        Route::post('/my-research/chapters/{chapter}/sections', [\App\Http\Controllers\Research\ContributorController::class, 'createSection'])->name('research.contributor.section.create');
        Route::get('/my-research/chapters/{chapter}/sections/{section}/edit', [\App\Http\Controllers\Research\ContributorController::class, 'editSection'])->name('research.contributor.section.edit');
        Route::put('/my-research/chapters/{chapter}/sections/{section}', [\App\Http\Controllers\Research\ContributorController::class, 'storeSection'])->name('research.contributor.section.update');
        Route::delete('/my-research/chapters/{chapter}/sections/{section}', [\App\Http\Controllers\Research\ContributorController::class, 'destroySection'])->name('research.contributor.section.destroy');
    });
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

        // --- Catalogue --------------------------------------------------
        // One resource registration per model (correct route ordering);
        // per-action permissions via middlewareFor. These controllers have
        // no show() action, so 'show' is excluded everywhere.
        Route::resource('products', AdminProductController::class)
            ->except(['show'])
            ->middlewareFor('index', 'can:products.view')
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'can:products.manage');
        Route::post('software-files', [AdminProductController::class, 'uploadSoftwareFile'])
            ->middleware('can:products.manage')->name('software-files.store');

        Route::resource('plans', AdminPlanController::class)
            ->except(['show'])
            ->middlewareFor('index', 'can:products.view')
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'can:products.manage');

        Route::resource('tools', AdminToolController::class)
            ->except(['show'])
            ->middlewareFor('index', 'can:tools.view')
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'can:tools.manage');
        Route::post('tool-files', [AdminToolController::class, 'uploadFile'])
            ->middleware('can:tools.manage')->name('tool-files.store');

        Route::resource('scholarships', AdminScholarshipController::class)
            ->except(['show'])
            ->middlewareFor('index', 'can:scholarships.view')
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'can:scholarships.manage');

        // --- Contact messages ------------------------------------------
        Route::middleware('can:contact_messages.view')->group(function () {
            Route::get('contact-messages', [AdminContactMessageController::class, 'index'])->name('contact-messages.index');
            Route::get('contact-messages/{contactMessage}', [AdminContactMessageController::class, 'show'])->name('contact-messages.show');
        });
        Route::middleware('can:contact_messages.manage')->group(function () {
            Route::delete('contact-messages/{contactMessage}', [AdminContactMessageController::class, 'destroy'])->name('contact-messages.destroy');
        });

        // --- Shared accounts -----------------------------------------
        Route::resource('accounts', AdminAccountController::class)
            ->middlewareFor(['index', 'show'], 'can:accounts.view')
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'can:accounts.manage');
        Route::post('accounts/{account}/decrypt', [AdminAccountController::class, 'decryptCredentials'])
            ->middleware('can:accounts.manage')->name('accounts.decrypt');

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

        Route::middleware('can:error_logs.view')->group(function () {
            Route::get('error-logs', [ErrorLogController::class, 'index'])->name('error-logs.index');
            Route::post('error-logs/clear-resolved', [ErrorLogController::class, 'clearResolved'])->name('error-logs.clear-resolved');
            Route::get('error-logs/{errorLog}', [ErrorLogController::class, 'show'])->name('error-logs.show');
            Route::post('error-logs/{errorLog}/resolve', [ErrorLogController::class, 'resolve'])->name('error-logs.resolve');
            Route::post('error-logs/{errorLog}/reopen', [ErrorLogController::class, 'reopen'])->name('error-logs.reopen');
            Route::delete('error-logs/{errorLog}', [ErrorLogController::class, 'destroy'])->name('error-logs.destroy');
        });

        Route::middleware('can:settings.manage')->group(function () {
            Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
            Route::post('settings', [SettingController::class, 'update'])->name('settings.update');
            Route::post('settings/branding', [SettingController::class, 'updateBranding'])->name('settings.branding.update');
            Route::post('settings/downloads', [SettingController::class, 'updateDownloads'])->name('settings.downloads.update');
            Route::post('settings/dev', [SettingController::class, 'updateDev'])->name('settings.dev.update');
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

        // --- Research library -----------------------------------------
        Route::middleware('can:research.view')->group(function () {
            Route::get('research', [\App\Http\Controllers\Admin\ResearchController::class, 'index'])->name('research.index');
            Route::get('research/categories', [\App\Http\Controllers\Admin\ResearchController::class, 'categories'])->name('research.categories');
            Route::get('research/{research}', [\App\Http\Controllers\Admin\ResearchController::class, 'show'])->name('research.show');
            Route::get('research/{research}/chapters/{chapter}', [\App\Http\Controllers\Admin\ResearchController::class, 'readChapter'])->name('research.read');
        });
        Route::middleware('can:research.manage')->group(function () {
            Route::post('research/{research}/approve', [\App\Http\Controllers\Admin\ResearchController::class, 'approve'])->name('research.approve');
            Route::post('research/{research}/request-changes', [\App\Http\Controllers\Admin\ResearchController::class, 'requestChanges'])->name('research.request-changes');
            Route::post('research/{research}/reject', [\App\Http\Controllers\Admin\ResearchController::class, 'reject'])->name('research.reject');
            Route::post('research/{research}/unpublish', [\App\Http\Controllers\Admin\ResearchController::class, 'unpublish'])->name('research.unpublish');
            Route::delete('research/{research}', [\App\Http\Controllers\Admin\ResearchController::class, 'destroy'])->name('research.destroy');
            Route::post('research-categories', [\App\Http\Controllers\Admin\ResearchController::class, 'storeCategory'])->name('research.categories.store');
            Route::put('research-categories/{category}', [\App\Http\Controllers\Admin\ResearchController::class, 'updateCategory'])->name('research.categories.update');
            Route::delete('research-categories/{category}', [\App\Http\Controllers\Admin\ResearchController::class, 'destroyCategory'])->name('research.categories.destroy');
        });
    });

require __DIR__.'/auth.php';

