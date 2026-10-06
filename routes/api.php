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
Route::post('/register/verify', [AuthController::class, 'verifyRegistration'])->middleware('throttle:10,1');
Route::post('/forgot-password', [\App\Http\Controllers\Api\SupportController::class, 'forgotPassword'])->middleware('throttle:5,1');
Route::post('/contact', [\App\Http\Controllers\Api\SupportController::class, 'contact'])->middleware('throttle:5,1');

// Self-hosted LiveKit webhooks — authenticated by the signed Authorization token, not a session.
Route::post('/webhooks/livekit', \App\Http\Controllers\Api\LiveKitWebhookController::class)
    ->middleware('throttle:600,1')
    ->name('api.webhooks.livekit');

// AzamPay / ClickPesa payment results — authenticated by the secret token in the URL.
Route::post('/payments/callback/{gateway}/{token}', \App\Http\Controllers\Api\PaymentCallbackController::class)
    ->middleware('throttle:120,1')
    ->name('api.payments.callback');

// Signed learning room materials for app clients (no browser session required).
Route::get('/signed/learning/rooms/{slug}/materials/{material}', [\App\Http\Controllers\Api\LearningController::class, 'signedLiveMaterial'])
    ->middleware(['signed', 'throttle:120,1'])
    ->whereNumber('material')
    ->name('api.signed.learning.rooms.materials');
Route::get('/signed/learning/rooms/{slug}/recordings/{recording}', [\App\Http\Controllers\Api\LearningController::class, 'signedRecording'])
    ->middleware(['signed', 'throttle:240,1'])
    ->whereNumber('recording')
    ->name('api.signed.learning.rooms.recordings');
Route::get('/signed/learning/rooms/{slug}/calendar.ics', [\App\Http\Controllers\Api\LearningController::class, 'signedIcs'])
    ->middleware(['signed', 'throttle:60,1'])
    ->name('api.signed.learning.rooms.ics');

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

    // Teaching Studio: live classes and lessons (instructors / learning staff).
    Route::controller(\App\Http\Controllers\Api\StudioController::class)->prefix('studio')->group(function () {
        Route::get('/', 'home');
        Route::post('/rooms', 'storeRoom');
        Route::get('/rooms/{room:id}', 'room');
        Route::put('/rooms/{room:id}', 'updateRoom');
        Route::delete('/rooms/{room:id}', 'destroyRoom');
        Route::post('/rooms/{room:id}/publish', 'publishRoom');
        Route::post('/rooms/{room:id}/cancel', 'cancelRoom');
        Route::post('/videos', 'storeVideo');
        Route::get('/videos/{video:id}', 'video');
        Route::post('/videos/{video:id}', 'updateVideo'); // POST: may carry a thumbnail file
        Route::delete('/videos/{video:id}', 'destroyVideo');
        Route::post('/videos/{video:id}/publish', 'publishVideo');
        Route::post('/videos/{video:id}/unpublish', 'unpublishVideo');
    });
    // Studio class extras: recordings, materials, invited members, attendance.
    Route::controller(\App\Http\Controllers\Api\StudioRoomExtrasController::class)->prefix('studio/rooms/{room:id}')->group(function () {
        Route::get('/extras', 'show');
        Route::get('/attendance', 'attendance');
        Route::post('/recordings', 'storeRecording');
        Route::post('/recordings/{recording}/share', 'shareRecording')->whereNumber('recording');
        Route::post('/recordings/{recording}/publish', 'publishRecording')->whereNumber('recording');
        Route::delete('/recordings/{recording}', 'destroyRecording')->whereNumber('recording');
        Route::post('/materials', 'storeMaterial');
        Route::delete('/materials/{material}', 'destroyMaterial')->whereNumber('material');
        Route::post('/members', 'storeMembers');
        Route::delete('/members/{member}', 'destroyMember')->whereNumber('member');
    });
    Route::get('/studio/users/search', [\App\Http\Controllers\Studio\UserSearchController::class, 'index'])
        ->middleware(['can:learning.studio', 'throttle:60,1']);

    // Chunked video upload (same endpoints the web Studio uses).
    Route::controller(\App\Http\Controllers\Studio\UploadController::class)->prefix('studio/uploads')->group(function () {
        Route::get('/config', 'config');
        Route::post('/', 'init')->middleware('throttle:30,1');
        Route::post('/{token}/chunk', 'chunk')->middleware('throttle:600,1');
        Route::post('/{token}/complete', 'complete');
        Route::delete('/{token}', 'abort');
    });

    // Writing research from the app (contributors).
    Route::controller(\App\Http\Controllers\Api\ResearchAuthorController::class)->prefix('my-research')->group(function () {
        Route::get('/categories', 'categories');
        Route::post('/', 'store');
        Route::get('/{research}', 'show')->whereNumber('research');
        Route::put('/{research}', 'update')->whereNumber('research');
        Route::delete('/{research}', 'destroy')->whereNumber('research');
        Route::post('/{research}/submit', 'submit')->whereNumber('research');
        Route::post('/{research}/import', 'import')->whereNumber('research');
        Route::post('/{research}/reorder', 'reorder')->whereNumber('research');
        Route::post('/{research}/chapters', 'storeChapter')->whereNumber('research');
        Route::put('/chapters/{chapter}', 'updateChapter');
        Route::delete('/chapters/{chapter}', 'destroyChapter');
        Route::post('/chapters/{chapter}/sections', 'storeSection');
        Route::get('/sections/{section}', 'showSection');
        Route::put('/sections/{section}', 'updateSection');
        Route::delete('/sections/{section}', 'destroySection');
    });

    // Learning (ROOM) — lessons, courses and live rooms; media streams via signed URLs
    Route::controller(\App\Http\Controllers\Api\LearningController::class)
        ->prefix('learning')
        ->name('api.learning.')
        ->group(function () {
            Route::get('/', 'home')->name('home');
            Route::get('/categories', 'categories')->name('categories');
            Route::get('/categories/{slug}', 'category')->name('categories.show');
            Route::get('/progress', 'progressDashboard')->name('progress');
            Route::get('/calendar', 'calendar')->name('calendar');
            Route::get('/instructors', 'instructors')->name('instructors');
            Route::get('/instructors/{instructor}', 'instructor')->whereNumber('instructor')->name('instructors.show');
            Route::get('/courses', 'courses')->name('courses');
            Route::get('/courses/{slug}', 'course')->name('courses.show');
            Route::post('/courses/{slug}/enroll', 'enroll')->name('courses.enroll');
            Route::get('/videos', 'videos')->name('videos');
            Route::get('/videos/{slug}', 'video')->name('videos.show');
            Route::post('/videos/{slug}/progress', 'progress')->middleware('throttle:120,1')->name('videos.progress');
            Route::post('/videos/{slug}/complete', 'complete')->name('videos.complete');
            Route::post('/videos/{slug}/comments', 'comment')->middleware('throttle:20,1')->name('videos.comments.store');
            Route::delete('/videos/{slug}/comments/{comment}', 'deleteComment')->whereNumber('comment')->name('videos.comments.destroy');
            Route::get('/rooms', 'rooms')->name('rooms');
            Route::get('/rooms/{slug}', 'room')->name('rooms.show');
            Route::post('/rooms/{slug}/join', 'join')->middleware('throttle:30,1')->name('rooms.join');
            // Fresh short-lived media token for a reconnect (same checks as join).
            Route::post('/rooms/{slug}/token', 'join')->middleware('throttle:30,1')->name('rooms.token');
            Route::post('/rooms/{slug}/leave', 'leave')->middleware('throttle:30,1')->name('rooms.leave');
            Route::get('/rooms/{slug}/feed', 'liveFeed')->middleware('throttle:120,1')->name('rooms.feed');
            Route::post('/rooms/{slug}/messages', 'liveMessage')->middleware('throttle:30,1')->name('rooms.messages');
            Route::post('/rooms/{slug}/hand', 'liveHand')->middleware('throttle:30,1')->name('rooms.hand');
            Route::post('/rooms/{slug}/polls/{poll}/vote', 'livePollVote')->whereNumber('poll')->middleware('throttle:30,1')->name('rooms.polls.vote');
            Route::post('/rooms/{slug}/polls', 'livePollCreate')->middleware('throttle:30,1')->name('rooms.polls.store');
            Route::post('/rooms/{slug}/polls/{poll}/close', 'livePollClose')->whereNumber('poll')->middleware('throttle:30,1')->name('rooms.polls.close');
            Route::get('/rooms/{slug}/materials/{material}', 'liveMaterial')->whereNumber('material')->name('rooms.materials');
            Route::get('/rooms/{slug}/board', 'liveBoard')->middleware('throttle:120,1')->name('rooms.board');
            Route::post('/rooms/{slug}/board/strokes', 'liveBoardStroke')->middleware('throttle:240,1')->name('rooms.board.strokes');
            Route::delete('/rooms/{slug}/board/strokes/{stroke}', 'liveBoardDelete')->whereNumber('stroke')->middleware('throttle:60,1')->name('rooms.board.strokes.destroy');
            Route::post('/rooms/{slug}/board', 'liveBoardUpdate')->middleware('throttle:60,1')->name('rooms.board.update');
            Route::post('/rooms/{slug}/moderate', 'liveModerate')->middleware('throttle:60,1')->name('rooms.moderate');
            Route::post('/rooms/{slug}/start', 'start')->middleware('throttle:10,1')->name('rooms.start');
            Route::post('/rooms/{slug}/end', 'end')->middleware('throttle:10,1')->name('rooms.end');
        });

    // Host controls in a live class: one participant, guests, breakout rooms
    Route::controller(\App\Http\Controllers\Api\LiveHostController::class)
        ->prefix('learning/rooms/{slug}')
        ->middleware('throttle:60,1')
        ->group(function () {
            Route::post('/participants/{user}/permissions', 'permissions')->whereNumber('user');
            Route::post('/participants/{user}/mute', 'mute')->whereNumber('user');
            Route::post('/participants/{user}/lower-hand', 'lowerHand')->whereNumber('user');
            Route::post('/participants/{user}/remove', 'remove')->whereNumber('user');
            Route::post('/guest-link', 'guestLink');
            Route::post('/guests/admit-all', 'admit');
            Route::post('/guests/{user}/admit', 'admit')->whereNumber('user');
            Route::post('/guests/{user}/deny', 'deny')->whereNumber('user');
            Route::post('/breakouts', 'breakouts');
            Route::post('/breakouts/open', 'openBreakouts');
            Route::post('/breakouts/close', 'closeBreakouts');
        });

    // Orders
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::get('/orders/{order}/receipt-url', [OrderController::class, 'receiptUrl']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);

    // Payments
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::get('/payment-methods', [PaymentController::class, 'methods']);
    Route::get('/payments/{payment}', [PaymentController::class, 'show']);
    Route::post('/orders/{order}/payments', [PaymentController::class, 'store']);

    // Automatic payments (mobile money push, card, PayPal) from the app.
    Route::get('/orders/{order}/payment-options', [\App\Http\Controllers\Api\GatewayPaymentController::class, 'options']);
    Route::middleware('throttle:6,1')->group(function () {
        Route::post('/orders/{order}/payments/mobile', [\App\Http\Controllers\Api\GatewayPaymentController::class, 'mobile']);
        Route::post('/orders/{order}/payments/card', [\App\Http\Controllers\Api\GatewayPaymentController::class, 'card']);
        Route::post('/orders/{order}/payments/paypal', [\App\Http\Controllers\Api\GatewayPaymentController::class, 'paypal']);
    });
    Route::get('/gateway-payments/{gatewayPayment}', [\App\Http\Controllers\Api\GatewayPaymentController::class, 'status'])
        ->middleware('throttle:60,1');

    // Subscriptions
    Route::get('/subscriptions', [SubscriptionController::class, 'index']);
    Route::get('/subscriptions/{subscription}', [SubscriptionController::class, 'show']);

    // Support chat
    Route::get('/chat', [ChatController::class, 'show']);
    Route::post('/chat', [ChatController::class, 'store']);
    Route::get('/chat/messages/{message}/file', [ChatController::class, 'attachment']);
    Route::post('/email/verification-notification', [\App\Http\Controllers\Api\SupportController::class, 'resendVerification'])->middleware('throttle:3,1');
    Route::post('/email/verify', [\App\Http\Controllers\Api\SupportController::class, 'verifyEmailCode'])->middleware('throttle:6,1');

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
        Route::put('/orders/{order}', [\App\Http\Controllers\Api\Admin\OrderController::class, 'update']);
        Route::post('/orders/{order}/reject', [\App\Http\Controllers\Api\Admin\OrderController::class, 'reject']);
        Route::delete('/orders/{order}', [\App\Http\Controllers\Api\Admin\OrderController::class, 'destroy']);
        Route::get('/orders/{order}/receipt-url', [\App\Http\Controllers\Api\Admin\OrderController::class, 'receiptUrl']);

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

        // Learning management (learning.view / learning.manage / learning.trash)
        Route::controller(\App\Http\Controllers\Api\Admin\LearningAdminController::class)->prefix('learning')->group(function () {
            Route::get('/', 'overview');
            Route::post('/guest-links', 'guestLinks');
            Route::get('/categories', 'categories');
            Route::post('/categories', 'storeCategory');
            Route::put('/categories/{category}', 'updateCategory')->whereNumber('category');
            Route::delete('/categories/{category}', 'destroyCategory')->whereNumber('category');
            Route::get('/courses', 'courses');
            Route::post('/courses', 'storeCourse');
            Route::get('/courses/{course}', 'course')->whereNumber('course');
            Route::post('/courses/{course}', 'updateCourse')->whereNumber('course'); // POST: may carry a thumbnail
            Route::delete('/courses/{course}', 'destroyCourse')->whereNumber('course');
            Route::post('/courses/{course}/enrolments', 'enrol')->whereNumber('course');
            Route::delete('/courses/{course}/enrolments/{enrollment}', 'unenrol')->whereNumber(['course', 'enrollment']);
            Route::get('/trash', 'trash');
            Route::post('/trash/{type}/{id}/restore', 'restore')->whereIn('type', ['category', 'course', 'video', 'room'])->whereNumber('id');
            Route::delete('/trash/{type}/{id}', 'purge')->whereIn('type', ['category', 'course', 'video', 'room'])->whereNumber('id');
        });

        // Research review (research.view / research.manage)
        Route::controller(\App\Http\Controllers\Api\Admin\ResearchReviewController::class)->group(function () {
            Route::get('/research', 'index');
            Route::get('/research-categories', 'categories');
            Route::post('/research-categories', 'storeCategory');
            Route::put('/research-categories/{category}', 'updateCategory');
            Route::delete('/research-categories/{category}', 'destroyCategory');
            Route::get('/research/{research}', 'show')->whereNumber('research');
            Route::post('/research/{research}/approve', 'approve')->whereNumber('research');
            Route::post('/research/{research}/request-changes', 'requestChanges')->whereNumber('research');
            Route::post('/research/{research}/reject', 'reject')->whereNumber('research');
            Route::post('/research/{research}/unpublish', 'unpublish')->whereNumber('research');
            Route::delete('/research/{research}', 'destroy')->whereNumber('research');
        });

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
            Route::get('/expenses/{expense}', 'expenseShow');
            Route::put('/expenses/{expense}', 'expenseUpdate');
            Route::delete('/expenses/{expense}', 'expenseDestroy');
            Route::get('/expenses/{expense}/receipt', 'receipt');
            Route::get('/capital', 'capital');
            Route::post('/capital', 'storeCapital');
            Route::get('/capital/{capitalEntry}', 'capitalShow');
            Route::put('/capital/{capitalEntry}', 'capitalUpdate');
            Route::delete('/capital/{capitalEntry}', 'capitalDestroy');
        });

        // Finance HR: staff, payroll (statutory), returns and loan repayments.
        Route::controller(\App\Http\Controllers\Api\Admin\FinanceHrController::class)->prefix('finance')->group(function () {
            Route::get('/staff', 'staff');
            Route::post('/staff', 'storeStaff');
            Route::post('/staff/settings', 'storeSetting');
            Route::get('/payroll', 'payroll');
            Route::post('/payroll', 'preparePayroll');
            Route::put('/payroll/rates', 'updateRates');
            Route::get('/returns', 'returns');
            Route::post('/returns/{statutoryReturn}/paid', 'markReturnPaid');
            Route::post('/returns/{statutoryReturn}/pending', 'markReturnPending');
            Route::get('/loans', 'loans');
            Route::get('/loans/{capitalEntry}', 'loan');
            Route::post('/loans/{capitalEntry}/repayments', 'repay');
            Route::delete('/loans/{capitalEntry}/repayments/{repayment}', 'destroyRepayment');
        });

        // AI assistant (each admin sees only their own conversations).
        Route::controller(\App\Http\Controllers\Api\Admin\AiAssistantController::class)->prefix('ai-assistant')->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::get('/{conversation}', 'show');
            Route::post('/{conversation}', 'send')->middleware('throttle:30,1');
            Route::delete('/{conversation}', 'destroy');
        });

        // Team chat between admins (private chats + groups).
        Route::controller(\App\Http\Controllers\Api\Admin\TeamChatController::class)->prefix('team-chat')->group(function () {
            Route::get('/', 'index');
            Route::post('/start/{admin}', 'start');
            Route::post('/groups', 'storeGroup');
            Route::put('/groups/{group}', 'updateGroup');
            Route::delete('/groups/{group}', 'destroyGroup');
            Route::get('/{group}/messages', 'messages')->whereNumber('group');
            Route::post('/{group}/messages', 'send')->whereNumber('group');
            Route::put('/{group}/messages/{message}', 'updateMessage')->whereNumber(['group', 'message']);
            Route::delete('/{group}/messages/{message}', 'destroyMessage')->whereNumber(['group', 'message']);
            Route::get('/{group}/messages/{message}/file', 'attachment')->whereNumber(['group', 'message']);
        });

        // Automatic payment gateways (AzamPay, ClickPesa, card, PayPal).
        Route::controller(\App\Http\Controllers\Api\Admin\PaymentGatewayController::class)->prefix('payment-gateways')->group(function () {
            Route::get('/', 'index');
            Route::put('/{gateway}', 'update');
            Route::post('/{gateway}/test', 'test');
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

            // Installers (exe, dmg, apk …) on the private disk
            $f = \App\Http\Controllers\Api\Admin\CatalogueFileController::class;
            Route::post('/products/{product}/software-file', [$f, 'storeProductFile']);
            Route::delete('/products/{product}/software-file', [$f, 'destroyProductFile']);
            Route::post('/tools/{tool}/file', [$f, 'storeToolFile']);
            Route::delete('/tools/{tool}/file', [$f, 'destroyToolFile']);
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
