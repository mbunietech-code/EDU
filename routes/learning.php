<?php

use App\Http\Controllers\Admin\Learning as AdminLearning;
use App\Http\Controllers\Learn;
use App\Http\Controllers\Studio;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ROOM — Live classroom & video learning
|--------------------------------------------------------------------------
|
| learn.*   the learner experience (every signed-in member; content binds by slug)
| studio.*  the Teaching Studio for instructors and room/lesson staff (binds by id)
| admin.learning.*  catalogue, instructors, progress, attendance and trash
|
| Nested children use scopeBindings() wherever the parent has a matching
| relation, so /videos/a/resources/{id} can never resolve another video's file.
|
*/

// --- Learner ----------------------------------------------------------
Route::middleware(['auth', 'verified'])
    ->prefix('learn')
    ->name('learn.')
    ->group(function () {
        Route::get('/', [Learn\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/progress', [Learn\DashboardController::class, 'progress'])->name('progress');
        Route::get('/calendar', [Learn\CalendarController::class, 'index'])->name('calendar');
        Route::get('/search', [Learn\SearchController::class, 'index'])->name('search');

        Route::get('/categories', [Learn\CategoryController::class, 'index'])->name('categories.index');
        Route::get('/categories/{category:slug}', [Learn\CategoryController::class, 'show'])->name('categories.show');

        Route::get('/courses', [Learn\CourseController::class, 'index'])->name('courses.index');
        Route::get('/courses/{course:slug}', [Learn\CourseController::class, 'show'])->name('courses.show');
        Route::post('/courses/{course:slug}/enroll', [Learn\CourseController::class, 'enroll'])->name('courses.enroll');
        Route::delete('/courses/{course:slug}/enroll', [Learn\CourseController::class, 'unenroll'])->name('courses.unenroll');

        Route::get('/videos', [Learn\VideoController::class, 'index'])->name('videos.index');
        Route::get('/videos/{video:slug}', [Learn\VideoController::class, 'show'])->name('videos.show');
        Route::get('/videos/{video:slug}/stream/{rendition?}', [Learn\MediaController::class, 'stream'])
            ->scopeBindings()->name('videos.stream');
        Route::post('/videos/{video:slug}/progress', [Learn\VideoProgressController::class, 'store'])
            ->middleware('throttle:120,1')->name('videos.progress');
        Route::post('/videos/{video:slug}/complete', [Learn\VideoProgressController::class, 'complete'])->name('videos.complete');
        Route::get('/videos/{video:slug}/resources/{resource}', [Learn\MediaController::class, 'resource'])
            ->scopeBindings()->name('videos.resource');
        Route::post('/videos/{video:slug}/comments', [Learn\VideoCommentController::class, 'store'])
            ->middleware('throttle:20,1')->name('videos.comments.store');
        Route::delete('/videos/{video:slug}/comments/{comment}', [Learn\VideoCommentController::class, 'destroy'])
            ->scopeBindings()->name('videos.comments.destroy');

        Route::get('/instructors/{instructor}', [Learn\InstructorController::class, 'show'])->name('instructors.show');

        Route::get('/rooms', [Learn\RoomController::class, 'index'])->name('rooms.index');
        Route::get('/rooms/{room:slug}', [Learn\RoomController::class, 'show'])->name('rooms.show');
        Route::get('/rooms/{room:slug}/calendar.ics', [Learn\RoomController::class, 'ics'])->name('rooms.ics');
        Route::get('/rooms/{room:slug}/live', [Learn\LiveRoomController::class, 'show'])->name('rooms.live');
        Route::post('/rooms/{room:slug}/join', [Learn\LiveRoomController::class, 'join'])
            ->middleware('throttle:30,1')->name('rooms.join');
        // Fresh short-lived media token after a network drop (same checks as join).
        Route::post('/rooms/{room:slug}/token', [Learn\LiveRoomController::class, 'join'])
            ->middleware('throttle:30,1')->name('rooms.token');
        Route::get('/rooms/{room:slug}/materials/{material}', Learn\RoomMaterialController::class)
            ->scopeBindings()->name('rooms.materials.download');
        Route::post('/rooms/{room:slug}/presence', [Learn\LiveRoomController::class, 'presence'])
            ->middleware('throttle:60,1')->name('rooms.presence');
        Route::post('/rooms/{room:slug}/leave', [Learn\LiveRoomController::class, 'leave'])->name('rooms.leave');
        Route::get('/rooms/{room:slug}/feed', [Learn\LiveRoomController::class, 'feed'])
            ->middleware('throttle:120,1')->name('rooms.feed');
        Route::post('/rooms/{room:slug}/messages', [Learn\RoomMessageController::class, 'store'])
            ->middleware('throttle:30,1')->name('rooms.messages.store');
        Route::post('/rooms/{room:slug}/messages/{message}/answer', [Learn\RoomMessageController::class, 'answer'])
            ->scopeBindings()->name('rooms.messages.answer');
        Route::delete('/rooms/{room:slug}/messages/{message}', [Learn\RoomMessageController::class, 'destroy'])
            ->scopeBindings()->name('rooms.messages.destroy');
        Route::get('/rooms/{room:slug}/recordings/{recording}/stream', [Learn\MediaController::class, 'recording'])
            ->scopeBindings()->name('rooms.recordings.stream');
    });

// Signed media for API / mobile clients (no session): ?u=<user id> + signature.
Route::get('/signed/learning/videos/{video}/stream/{rendition?}', [Learn\MediaController::class, 'signedStream'])
    ->middleware(['signed', 'throttle:240,1'])
    ->whereNumber('video')
    ->scopeBindings()
    ->name('signed.learning.videos.stream');

// --- Teaching Studio ----------------------------------------------------
Route::middleware(['auth', 'verified', 'can:learning.studio'])
    ->prefix('studio')
    ->name('studio.')
    ->whereNumber(['room', 'video'])
    ->group(function () {
        Route::redirect('/', '/studio/rooms')->name('home');

        Route::get('/uploads/config', [Studio\UploadController::class, 'config'])->name('uploads.config');
        Route::post('/uploads', [Studio\UploadController::class, 'init'])
            ->middleware('throttle:30,1')->name('uploads.init');
        Route::post('/uploads/{token}/chunk', [Studio\UploadController::class, 'chunk'])
            ->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:1200,1')->name('uploads.chunk');
        Route::post('/uploads/{token}/complete', [Studio\UploadController::class, 'complete'])
            ->where('token', '[A-Za-z0-9]{40}')->name('uploads.complete');
        Route::delete('/uploads/{token}', [Studio\UploadController::class, 'abort'])
            ->where('token', '[A-Za-z0-9]{40}')->name('uploads.abort');

        Route::get('/users/search', [Studio\UserSearchController::class, 'index'])
            ->middleware('throttle:60,1')->name('users.search');

        // Rooms
        Route::get('/rooms', [Studio\RoomController::class, 'index'])->name('rooms.index');
        Route::get('/rooms/create', [Studio\RoomController::class, 'create'])->name('rooms.create');
        Route::post('/rooms', [Studio\RoomController::class, 'store'])->name('rooms.store');
        Route::get('/rooms/{room}', [Studio\RoomController::class, 'show'])->name('rooms.show');
        Route::get('/rooms/{room}/edit', [Studio\RoomController::class, 'edit'])->name('rooms.edit');
        Route::put('/rooms/{room}', [Studio\RoomController::class, 'update'])->name('rooms.update');
        Route::delete('/rooms/{room}', [Studio\RoomController::class, 'destroy'])->name('rooms.destroy');
        Route::post('/rooms/{room}/publish', [Studio\RoomController::class, 'publish'])->name('rooms.publish');

        Route::post('/rooms/{room}/start', [Studio\RoomSessionController::class, 'start'])->name('rooms.start');
        Route::post('/rooms/{room}/end', [Studio\RoomSessionController::class, 'end'])->name('rooms.end');
        Route::post('/rooms/{room}/cancel', [Studio\RoomSessionController::class, 'cancel'])->name('rooms.cancel');
        Route::post('/rooms/{room}/announce', [Studio\RoomSessionController::class, 'announce'])->name('rooms.announce');
        Route::delete('/rooms/{room}/sessions/{session}', [Studio\RoomSessionController::class, 'destroy'])
            ->scopeBindings()->name('rooms.sessions.destroy');

        Route::get('/rooms/{room}/participants', [Studio\RoomParticipantController::class, 'index'])->name('rooms.participants');
        Route::post('/rooms/{room}/members', [Studio\RoomParticipantController::class, 'storeMembers'])->name('rooms.members.store');
        Route::delete('/rooms/{room}/members/{member}', [Studio\RoomParticipantController::class, 'destroyMember'])
            ->scopeBindings()->name('rooms.members.destroy');
        Route::post('/rooms/{room}/participants/{user}/remove', [Studio\RoomParticipantController::class, 'remove'])
            ->whereNumber('user')->name('rooms.participants.remove');

        // Live moderation (self-hosted SFU): lock, publish rights, mute, recording, materials.
        Route::middleware('throttle:60,1')->group(function () {
            Route::post('/rooms/{room}/lock', [Studio\RoomModerationController::class, 'lock'])->name('rooms.lock');
            Route::post('/rooms/{room}/media', [Studio\RoomModerationController::class, 'media'])->name('rooms.media');
            Route::post('/rooms/{room}/participants/{user}/permissions', [Studio\RoomModerationController::class, 'permissions'])
                ->whereNumber('user')->name('rooms.participants.permissions');
            Route::post('/rooms/{room}/participants/{user}/mute', [Studio\RoomModerationController::class, 'mute'])
                ->whereNumber('user')->name('rooms.participants.mute');
            Route::post('/rooms/{room}/mute-all', [Studio\RoomModerationController::class, 'muteAll'])->name('rooms.mute-all');
            Route::post('/rooms/{room}/recording/start', [Studio\RoomModerationController::class, 'startRecording'])->name('rooms.recording.start');
            Route::post('/rooms/{room}/recording/stop', [Studio\RoomModerationController::class, 'stopRecording'])->name('rooms.recording.stop');
            Route::post('/rooms/{room}/recording/browser', [Studio\RoomModerationController::class, 'browserRecording'])->name('rooms.recording.browser');
            Route::post('/rooms/{room}/extend', [Studio\RoomModerationController::class, 'extend'])->name('rooms.extend');
            Route::post('/rooms/{room}/materials', [Studio\RoomMaterialController::class, 'store'])->name('rooms.materials.store');
            Route::delete('/rooms/{room}/materials/{material}', [Studio\RoomMaterialController::class, 'destroy'])
                ->scopeBindings()->name('rooms.materials.destroy');
        });

        Route::get('/rooms/{room}/attendance', [Studio\RoomAttendanceController::class, 'index'])->name('rooms.attendance');
        Route::get('/rooms/{room}/attendance/export', [Studio\RoomAttendanceController::class, 'export'])->name('rooms.attendance.export');

        Route::post('/rooms/{room}/recordings', [Studio\RoomRecordingController::class, 'store'])->name('rooms.recordings.store');
        Route::put('/rooms/{room}/recordings/{recording}', [Studio\RoomRecordingController::class, 'update'])
            ->scopeBindings()->name('rooms.recordings.update');
        Route::post('/rooms/{room}/recordings/{recording}/publish', [Studio\RoomRecordingController::class, 'publish'])
            ->scopeBindings()->name('rooms.recordings.publish');
        Route::delete('/rooms/{room}/recordings/{recording}', [Studio\RoomRecordingController::class, 'destroy'])
            ->scopeBindings()->name('rooms.recordings.destroy');

        // Video lessons
        Route::get('/videos', [Studio\VideoController::class, 'index'])->name('videos.index');
        Route::get('/videos/create', [Studio\VideoController::class, 'create'])->name('videos.create');
        Route::post('/videos', [Studio\VideoController::class, 'store'])->name('videos.store');
        Route::get('/videos/{video}/edit', [Studio\VideoController::class, 'edit'])->name('videos.edit');
        Route::put('/videos/{video}', [Studio\VideoController::class, 'update'])->name('videos.update');
        Route::delete('/videos/{video}', [Studio\VideoController::class, 'destroy'])->name('videos.destroy');
        Route::post('/videos/{video}/publish', [Studio\VideoController::class, 'publish'])->name('videos.publish');
        Route::post('/videos/{video}/unpublish', [Studio\VideoController::class, 'unpublish'])->name('videos.unpublish');
        Route::post('/videos/{video}/replace', [Studio\VideoController::class, 'replace'])->name('videos.replace');
        Route::delete('/videos/{video}/thumbnail', [Studio\VideoController::class, 'destroyThumbnail'])->name('videos.thumbnail.destroy');

        Route::post('/videos/{video}/renditions', [Studio\VideoAssetController::class, 'storeRendition'])->name('videos.renditions.store');
        Route::delete('/videos/{video}/renditions/{rendition}', [Studio\VideoAssetController::class, 'destroyRendition'])
            ->scopeBindings()->name('videos.renditions.destroy');
        Route::post('/videos/{video}/resources', [Studio\VideoAssetController::class, 'storeResource'])->name('videos.resources.store');
        Route::delete('/videos/{video}/resources/{resource}', [Studio\VideoAssetController::class, 'destroyResource'])
            ->scopeBindings()->name('videos.resources.destroy');
    });

// --- Admin ---------------------------------------------------------------
Route::middleware(['auth', 'verified', 'admin'])
    ->prefix('admin/learning')
    ->name('admin.learning.')
    ->whereNumber(['category', 'course', 'topic', 'enrollment', 'user', 'id'])
    ->group(function () {
        Route::middleware('can:learning.view')->group(function () {
            Route::get('/', [AdminLearning\DashboardController::class, 'index'])->name('dashboard');
            Route::get('/categories', [AdminLearning\CategoryController::class, 'index'])->name('categories.index');
            Route::get('/courses', [AdminLearning\CourseController::class, 'index'])->name('courses.index');
            Route::get('/topics', [AdminLearning\TopicController::class, 'index'])->name('topics.index');
            Route::get('/instructors', [AdminLearning\InstructorController::class, 'index'])->name('instructors.index');
            Route::get('/progress', [AdminLearning\ProgressController::class, 'index'])->name('progress.index');
            Route::get('/progress/{user}', [AdminLearning\ProgressController::class, 'show'])->name('progress.show');
        });

        Route::middleware('can:learning.manage')->group(function () {
            Route::post('/categories', [AdminLearning\CategoryController::class, 'store'])->name('categories.store');
            Route::put('/categories/{category}', [AdminLearning\CategoryController::class, 'update'])->name('categories.update');
            Route::delete('/categories/{category}', [AdminLearning\CategoryController::class, 'destroy'])->name('categories.destroy');

            // Before /courses/{course} so "create" is never read as an id.
            Route::get('/courses/create', [AdminLearning\CourseController::class, 'create'])->name('courses.create');
            Route::post('/courses', [AdminLearning\CourseController::class, 'store'])->name('courses.store');
            Route::get('/courses/{course}/edit', [AdminLearning\CourseController::class, 'edit'])->name('courses.edit');
            Route::put('/courses/{course}', [AdminLearning\CourseController::class, 'update'])->name('courses.update');
            Route::delete('/courses/{course}', [AdminLearning\CourseController::class, 'destroy'])->name('courses.destroy');

            Route::post('/courses/{course}/enrollments', [AdminLearning\EnrollmentController::class, 'store'])->name('enrollments.store');
            Route::delete('/courses/{course}/enrollments/{enrollment}', [AdminLearning\EnrollmentController::class, 'destroy'])
                ->scopeBindings()->name('enrollments.destroy');

            Route::post('/topics', [AdminLearning\TopicController::class, 'store'])->name('topics.store');
            Route::put('/topics/{topic}', [AdminLearning\TopicController::class, 'update'])->name('topics.update');
            Route::delete('/topics/{topic}', [AdminLearning\TopicController::class, 'destroy'])->name('topics.destroy');

            Route::post('/instructors', [AdminLearning\InstructorController::class, 'store'])->name('instructors.store');
            Route::delete('/instructors/{user}', [AdminLearning\InstructorController::class, 'destroy'])->name('instructors.destroy');
        });

        Route::get('/courses/{course}', [AdminLearning\CourseController::class, 'show'])
            ->middleware('can:learning.view')->name('courses.show');

        Route::get('/attendance', [AdminLearning\AttendanceController::class, 'index'])
            ->middleware('can:rooms.view')->name('attendance.index');

        // Live sessions on our self-hosted video server (actions reuse the studio endpoints).
        Route::middleware('can:rooms.view')->group(function () {
            Route::get('/live', [AdminLearning\LiveSessionController::class, 'index'])->name('live.index');
            Route::get('/live/{room}', [AdminLearning\LiveSessionController::class, 'show'])->whereNumber('room')->name('live.show');
            Route::delete('/live/{room}/messages/{message}', [AdminLearning\LiveSessionController::class, 'destroyMessage'])
                ->whereNumber(['room', 'message'])->name('live.messages.destroy');
        });

        // Per-type permission (learning.manage vs rooms.manage) is checked in the controller.
        Route::middleware('can:learning.trash')->group(function () {
            Route::get('/trash', [AdminLearning\TrashController::class, 'index'])->name('trash.index');
            Route::post('/trash/{type}/{id}/restore', [AdminLearning\TrashController::class, 'restore'])
                ->whereIn('type', ['category', 'course', 'video', 'room'])->name('trash.restore');
            Route::delete('/trash/{type}/{id}', [AdminLearning\TrashController::class, 'destroy'])
                ->whereIn('type', ['category', 'course', 'video', 'room'])->name('trash.destroy');
        });
    });
