<?php

namespace Database\Seeders;

use App\Models\LearningCategory;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\LearningRoom;
use App\Models\LearningRoomAttendance;
use App\Models\LearningRoomMember;
use App\Models\LearningRoomMessage;
use App\Models\LearningRoomRecording;
use App\Models\LearningRoomSession;
use App\Models\LearningTopic;
use App\Models\LearningVideo;
use App\Models\LearningVideoComment;
use App\Models\LearningVideoProgress;
use App\Models\LearningVideoResource;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Demo content for every part of the learning feature (ROOM). Not called
 * from DatabaseSeeder — run it on purpose:
 *
 *   php artisan db:seed --class=LearningDemoSeeder
 *
 * What it fills: categories, courses, topics, lessons (with resources,
 * comments, views and learner progress), enrolments, instructors, live rooms
 * in every status (private members, chat / Q&A / announcements, attendance,
 * a shared recording) and a few items in the trash.
 *
 * Sample videos: drop .mp4 / .webm / .m4v files into storage/app/learning-demo
 * (or config('learning.demo_video_dir')). Each lesson gets its own copy of one
 * of them and is published; without samples lessons stay drafts, so nothing
 * unplayable is shown to learners.
 *
 * Demo accounts (password "password") are created only in the local and
 * testing environments: instructor@, mentor@, learner@, amina@ and
 * baraka@example.test. Elsewhere the first instructor or super admin hosts the
 * demo content and no learner activity is invented.
 *
 * Idempotent: records are looked up by slug / email / natural key first, so a
 * second run adds nothing.
 */
class LearningDemoSeeder extends Seeder
{
    private const CATEGORIES = [
        'Programming' => ['icon' => 'code', 'description' => 'Programming fundamentals, languages and problem solving.'],
        'Web Development' => ['icon' => 'globe', 'description' => 'Building websites and web applications, front to back.'],
        'Mobile Development' => ['icon' => 'phone', 'description' => 'Android, iOS and cross-platform apps.'],
        'Database' => ['icon' => 'database', 'description' => 'Designing, querying and tuning databases.'],
        'Networking' => ['icon' => 'wifi', 'description' => 'How computers talk to each other.'],
        'Business' => ['icon' => 'briefcase', 'description' => 'Running and growing a business.'],
        'Design' => ['icon' => 'swatch', 'description' => 'Visual, UI and UX design.'],
    ];

    /** [category, course title, level, access, instructor key, [topic => [lesson titles]]] */
    private const COURSES = [
        ['Programming', 'Python for Beginners', 'beginner', 'open', 'instructor', [
            'Getting started' => ['Installing Python', 'Your first program'],
            'Core ideas' => ['Variables and types', 'Loops and conditions', 'Functions'],
        ]],
        ['Web Development', 'Laravel from Zero', 'beginner', 'open', 'instructor', [
            'Foundations' => ['What is Laravel?', 'Routing and controllers'],
            'Data' => ['Migrations and models', 'Eloquent relationships'],
        ]],
        ['Web Development', 'Modern CSS with Tailwind', 'intermediate', 'open', 'mentor', [
            'Utility-first basics' => ['Why utility classes?', 'Responsive layouts'],
        ]],
        ['Mobile Development', 'Flutter App Essentials', 'intermediate', 'enrolled', 'mentor', [
            'Widgets' => ['Stateless and stateful widgets', 'Layouts with Row and Column'],
            'State' => ['setState and beyond'],
        ]],
        ['Database', 'SQL Queries that Scale', 'advanced', 'open', 'instructor', [
            'Querying' => ['SELECT in depth', 'JOINs explained'],
            'Performance' => ['Indexes', 'Reading EXPLAIN'],
        ]],
        ['Business', 'Starting a Small Business', 'beginner', 'open', 'mentor', [
            'Planning' => ['Finding your customer', 'Pricing your product'],
        ]],
    ];

    /** Lessons left as drafts on purpose (shows the Studio "Drafts" tab). */
    private const DRAFT_LESSONS = ['Pricing your product'];

    /** Standalone lessons (no course) — "category" visibility. */
    private const STANDALONE = [
        ['Networking', 'How the internet works in 10 minutes'],
        ['Design', 'Colour basics for beginners'],
    ];

    /** @var list<string> */
    private array $samples = [];

    private int $sampleIndex = 0;

    public function run(): void
    {
        $people = $this->people();
        $instructor = $people['instructor'];

        if (! $instructor) {
            $this->command?->warn('No instructor or super admin found to own the demo content — nothing seeded.');

            return;
        }

        $this->samples = $this->sampleVideos();
        if ($this->samples === []) {
            $this->command?->warn('No sample videos in '.$this->sampleDir().' — lessons are created as drafts without a file.');
        }

        $categories = $this->categories($instructor);
        [$courses, $lessons] = $this->courses($categories, $people);
        $this->standaloneLessons($categories, $instructor, $lessons);

        $this->lessonExtras($lessons, $people);
        $this->learnerActivity($courses, $lessons, $people);
        $this->rooms($categories, $courses, $people);
        $this->trash($categories, $instructor);

        $this->command?->info('Learning demo ready: '.count($courses).' courses, '.count($lessons).' lessons, '
            .LearningRoom::query()->where('slug', 'like', 'demo-%')->count().' rooms.');
    }

    // --- People ------------------------------------------------------------
    /** @return array{instructor:?User,mentor:?User,learners:list<User>} */
    private function people(): array
    {
        if (app()->environment('local', 'testing')) {
            $instructor = $this->demoUser('instructor@example.test', 'Demo Instructor', true);
            $mentor = $this->demoUser('mentor@example.test', 'Neema Mentor', true);

            return [
                'instructor' => $instructor,
                'mentor' => $mentor,
                'learners' => [
                    $this->demoUser('learner@example.test', 'Demo Learner'),
                    $this->demoUser('amina@example.test', 'Amina Juma'),
                    $this->demoUser('baraka@example.test', 'Baraka Mushi'),
                ],
            ];
        }

        $host = User::query()->where('can_teach', true)->where('status', 'active')->orderBy('id')->first()
            ?? User::query()->where('role', 'super_admin')->orderBy('id')->first();

        return ['instructor' => $host, 'mentor' => $host, 'learners' => []];
    }

    private function demoUser(string $email, string $name, bool $teacher = false): User
    {
        $user = User::query()->firstOrCreate(['email' => $email], [
            'name' => $name,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        if ($teacher && ! $user->can_teach) {
            $user->forceFill(['can_teach' => true])->save();
        }

        return $user;
    }

    // --- Catalogue ---------------------------------------------------------------
    /** @return array<string,LearningCategory> */
    private function categories(User $owner): array
    {
        $categories = [];
        $position = 0;
        foreach (self::CATEGORIES as $name => $meta) {
            $categories[$name] = $this->firstBySlug(LearningCategory::class, $name, [
                'name' => $name,
                'description' => $meta['description'],
                'icon' => $meta['icon'],
                'position' => ++$position,
                'created_by' => $owner->id,
            ]);
        }

        return $categories;
    }

    /** @return array{0:array<string,LearningCourse>,1:array<string,LearningVideo>} */
    private function courses(array $categories, array $people): array
    {
        $courses = [];
        $lessons = [];
        $views = [180, 145, 120, 96, 80, 64, 52, 40, 33, 27, 21, 16, 12, 9, 7, 5];

        foreach (self::COURSES as $i => [$categoryName, $title, $level, $access, $teacherKey, $topics]) {
            $category = $categories[$categoryName];
            $teacher = $people[$teacherKey] ?? $people['instructor'];

            $course = $this->firstBySlug(LearningCourse::class, $title, [
                'learning_category_id' => $category->id,
                'title' => $title,
                'summary' => 'A short, practical course: '.Str::lower($title).'.',
                'description' => "## What you will learn\n\nStep-by-step lessons on **{$title}**, with live sessions to ask questions.\n\n"
                    ."- Short videos you can rewatch\n- Resources to download\n- A live class to ask the instructor",
                'level' => $level,
                'access' => $access,
                'status' => 'published',
                'published_at' => now()->subDays(20 - $i),
                'instructor_id' => $teacher->id,
                'position' => $i + 1,
                'created_by' => $teacher->id,
            ]);
            $courses[$title] = $course;

            $topicPosition = 0;
            $lessonPosition = 0;
            foreach ($topics as $topicTitle => $lessonTitles) {
                $topic = LearningTopic::query()->firstOrCreate(
                    ['learning_course_id' => $course->id, 'title' => $topicTitle],
                    ['position' => ++$topicPosition],
                );

                foreach ($lessonTitles as $lessonTitle) {
                    $video = $this->firstBySlug(LearningVideo::class, $title.' '.$lessonTitle, [
                        'learning_category_id' => $category->id,
                        'learning_course_id' => $course->id,
                        'learning_topic_id' => $topic->id,
                        'instructor_id' => $teacher->id,
                        'created_by' => $teacher->id,
                        'title' => $lessonTitle,
                        'description' => "In this lesson: **{$lessonTitle}**.\n\nWatch the video, then try the exercise in the resources below.",
                        'tags' => [Str::slug($categoryName), 'demo'],
                        'visibility' => 'course',
                        'status' => 'draft',
                        'position' => ++$lessonPosition,
                        'views' => $views[count($lessons) % count($views)],
                    ]);

                    if (! in_array($lessonTitle, self::DRAFT_LESSONS, true)) {
                        $this->attachSample($video, now()->subDays(18 - min(17, count($lessons))));
                    }
                    $lessons[$title.' / '.$lessonTitle] = $video->refresh();
                }
            }
        }

        return [$courses, $lessons];
    }

    private function standaloneLessons(array $categories, User $instructor, array &$lessons): void
    {
        foreach (self::STANDALONE as [$categoryName, $title]) {
            $video = $this->firstBySlug(LearningVideo::class, $title, [
                'learning_category_id' => $categories[$categoryName]->id,
                'instructor_id' => $instructor->id,
                'created_by' => $instructor->id,
                'title' => $title,
                'description' => 'A standalone lesson — it belongs to a category, not to a course.',
                'tags' => [Str::slug($categoryName), 'demo'],
                'visibility' => 'category',
                'status' => 'draft',
                'views' => 11,
            ]);
            $this->attachSample($video, now()->subDays(2));
            $lessons[$title] = $video->refresh();
        }
    }

    /** Resources and a comment thread on the first lessons. */
    private function lessonExtras(array $lessons, array $people): void
    {
        $first = $lessons['Python for Beginners / Installing Python'] ?? null;
        $laravel = $lessons['Laravel from Zero / What is Laravel?'] ?? null;

        if ($first) {
            LearningVideoResource::query()->firstOrCreate(
                ['learning_video_id' => $first->id, 'title' => 'Official Python downloads'],
                ['type' => 'link', 'url' => 'https://www.python.org/downloads/', 'position' => 1],
            );

            if (! $first->resources()->where('type', 'file')->exists()) {
                $disk = (string) config('learning.disk', 'private');
                $path = 'learning/resources/'.Str::random(40).'.txt';
                $body = "PYTHON CHEAT SHEET (demo)\n\nprint('Hello')      # output\nname = input()      # input\n"
                    ."for i in range(3):  # loop\n    print(i)\n";
                Storage::disk($disk)->put($path, $body);

                LearningVideoResource::create([
                    'learning_video_id' => $first->id,
                    'title' => 'Python cheat sheet',
                    'type' => 'file',
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => 'python-cheat-sheet.txt',
                    'mime' => 'text/plain',
                    'size_bytes' => strlen($body),
                    'position' => 2,
                ]);
            }
        }

        if ($laravel) {
            LearningVideoResource::query()->firstOrCreate(
                ['learning_video_id' => $laravel->id, 'title' => 'Laravel documentation'],
                ['type' => 'link', 'url' => 'https://laravel.com/docs', 'position' => 1],
            );
        }

        [$learner, $amina] = [$people['learners'][0] ?? null, $people['learners'][1] ?? null];
        if (! $first || ! $learner || $first->comments()->exists()) {
            return;
        }

        $question = LearningVideoComment::create([
            'learning_video_id' => $first->id,
            'user_id' => $learner->id,
            'body' => 'Should I install Python 3.12 or the newest version?',
        ]);
        LearningVideoComment::create([
            'learning_video_id' => $first->id,
            'user_id' => $people['instructor']->id,
            'parent_id' => $question->id,
            'body' => 'The newest 3.x is fine — everything in this course works on 3.10 and later.',
        ]);
        if ($amina) {
            LearningVideoComment::create([
                'learning_video_id' => $first->id,
                'user_id' => $amina->id,
                'body' => 'Clear explanation, thank you! The cheat sheet helps a lot.',
            ]);
        }
    }

    /** Enrolments and watch progress so dashboards and analytics have numbers. */
    private function learnerActivity(array $courses, array $lessons, array $people): void
    {
        [$learner, $amina, $baraka] = array_pad($people['learners'], 3, null);
        if (! $learner) {
            return;
        }

        $enrol = function (User $user, string $course, string $source = 'self') use ($courses, $people) {
            LearningEnrollment::query()->firstOrCreate(
                ['user_id' => $user->id, 'learning_course_id' => $courses[$course]->id],
                ['source' => $source, 'enrolled_by' => $source === 'admin' ? $people['instructor']->id : null, 'enrolled_at' => now()->subDays(10)],
            );
        };

        $enrol($learner, 'Python for Beginners');
        $enrol($learner, 'Flutter App Essentials', 'admin');
        $enrol($amina, 'Laravel from Zero');
        $enrol($amina, 'Python for Beginners');
        $enrol($baraka, 'SQL Queries that Scale');

        // [user, lesson key, percent watched, days ago]
        $watching = [
            [$learner, 'Python for Beginners / Installing Python', 100, 6],
            [$learner, 'Python for Beginners / Your first program', 100, 5],
            [$learner, 'Python for Beginners / Variables and types', 45, 1],
            [$learner, 'Flutter App Essentials / Stateless and stateful widgets', 20, 2],
            [$amina, 'Laravel from Zero / What is Laravel?', 100, 4],
            [$amina, 'Laravel from Zero / Routing and controllers', 100, 3],
            [$amina, 'Laravel from Zero / Migrations and models', 60, 0],
            [$amina, 'Python for Beginners / Installing Python', 100, 7],
            [$baraka, 'SQL Queries that Scale / SELECT in depth', 100, 2],
            [$baraka, 'SQL Queries that Scale / JOINs explained', 30, 1],
        ];

        foreach ($watching as [$user, $key, $percent, $daysAgo]) {
            $video = $lessons[$key] ?? null;
            if (! $user || ! $video || ! $video->isPublished()) {
                continue;
            }

            $duration = max(1, (int) $video->duration_seconds);
            $position = $percent >= 100 ? 0 : (int) floor($duration * $percent / 100);
            $when = now()->subDays($daysAgo)->subMinutes(30);

            LearningVideoProgress::query()->firstOrCreate(
                ['user_id' => $user->id, 'learning_video_id' => $video->id],
                [
                    'position_seconds' => $position,
                    'max_position_seconds' => $percent >= 100 ? $duration : $position,
                    'watched_seconds' => (int) floor($duration * min(100, $percent) / 100),
                    'duration_seconds' => $duration,
                    'percent' => $percent,
                    'play_count' => $percent >= 100 ? 2 : 1,
                    'completed_at' => $percent >= 90 ? $when : null,
                    'last_watched_at' => $when,
                ],
            );
        }
    }

    // --- Live rooms --------------------------------------------------------------------
    /** One room in each status, plus a private one, with sessions and activity. */
    private function rooms(array $categories, array $courses, array $people): void
    {
        $host = $people['instructor'];
        [$learner, $amina, $baraka] = array_pad($people['learners'], 3, null);
        $base = [
            'host_id' => $host->id,
            'created_by' => $host->id,
            'duration_minutes' => 60,
            'chat_enabled' => true,
            'questions_enabled' => true,
        ];

        $this->firstBySlug(LearningRoom::class, 'Demo Draft Workshop', $base + [
            'title' => 'Demo Draft Workshop',
            'description' => 'Still being planned — only the host and room managers see drafts.',
            'status' => 'draft',
            'access' => 'public',
            'learning_category_id' => $categories['Design']->id,
        ]);

        $this->firstBySlug(LearningRoom::class, 'Demo Laravel Q&A', $base + [
            'title' => 'Demo Laravel Q&A',
            'description' => 'Bring your questions about routing and Eloquent.',
            'status' => 'scheduled',
            'access' => 'course',
            'learning_category_id' => $categories['Web Development']->id,
            'learning_course_id' => $courses['Laravel from Zero']->id,
            'scheduled_at' => now()->addDays(2)->setTime(18, 0),
        ]);

        $this->firstBySlug(LearningRoom::class, 'Demo Networking Basics', $base + [
            'title' => 'Demo Networking Basics',
            'description' => 'IP addresses, routers and DNS in one hour.',
            'status' => 'scheduled',
            'access' => 'category',
            'learning_category_id' => $categories['Networking']->id,
            'scheduled_at' => now()->addDays(5)->setTime(10, 0),
            'allow_participant_media' => true,
        ]);

        $private = $this->firstBySlug(LearningRoom::class, 'Demo Mentorship Circle', $base + [
            'host_id' => $people['mentor']->id,
            'created_by' => $people['mentor']->id,
            'title' => 'Demo Mentorship Circle',
            'description' => 'A small private group — only invited members can see and join it.',
            'status' => 'scheduled',
            'access' => 'private',
            'learning_category_id' => $categories['Business']->id,
            'scheduled_at' => now()->addDays(3)->setTime(19, 30),
            'duration_minutes' => 45,
            'allow_participant_media' => true,
        ]);
        foreach (array_filter([$learner, $amina]) as $member) {
            LearningRoomMember::query()->firstOrCreate(
                ['learning_room_id' => $private->id, 'user_id' => $member->id],
                ['added_by' => $people['mentor']->id],
            );
        }

        $this->firstBySlug(LearningRoom::class, 'Demo Business Clinic', $base + [
            'title' => 'Demo Business Clinic',
            'status' => 'cancelled',
            'access' => 'public',
            'learning_category_id' => $categories['Business']->id,
            'scheduled_at' => now()->addDay()->setTime(15, 0),
            'cancel_reason' => 'The host is travelling — a new date will follow.',
        ]);

        // Completed: a finished session with attendance, chat, Q&A and a shared recording.
        $start = now()->subDays(3)->setTime(17, 0);
        $completed = $this->firstBySlug(LearningRoom::class, 'Demo Python Kick-off', $base + [
            'title' => 'Demo Python Kick-off',
            'description' => 'The first live class of the Python course. The recording is shared below.',
            'status' => 'completed',
            'access' => 'public',
            'learning_category_id' => $categories['Programming']->id,
            'learning_course_id' => $courses['Python for Beginners']->id,
            'scheduled_at' => $start,
            'started_at' => $start,
            'ended_at' => $start->copy()->addHour(),
        ]);
        $session = $completed->sessions()->first() ?? LearningRoomSession::create([
            'learning_room_id' => $completed->id,
            'started_by' => $host->id,
            'ended_by' => $host->id,
            'started_at' => $completed->started_at,
            'ended_at' => $completed->ended_at,
            'peak_participants' => 1 + count(array_filter([$learner, $amina, $baraka])),
        ]);

        $this->attend($session, $host, 'host', 0, 3600);
        $this->attend($session, $learner, 'participant', 2, 3300);
        $this->attend($session, $amina, 'participant', 5, 2700);
        $this->attend($session, $baraka, 'participant', 20, 1500);

        $this->conversation($completed, $session, $host, [
            [$host, 'announcement', 'Welcome! The slides are in the first lesson’s resources.'],
            [$learner, 'chat', 'Good evening everyone 👋'],
            [$amina, 'question', 'Do we need an IDE, or is a text editor enough?', true],
            [$baraka, 'chat', 'The audio is clear on my side.'],
            [$learner, 'question', 'Will this session be recorded?', true],
        ]);

        if (! $completed->recordings()->exists() && ($sample = $this->nextSample())) {
            $disk = (string) config('learning.disk', 'private');
            $path = 'learning/recordings/'.Str::random(40).'.'.strtolower(pathinfo($sample, PATHINFO_EXTENSION));
            $this->copyInto($disk, $path, $sample);

            LearningRoomRecording::create([
                'learning_room_id' => $completed->id,
                'learning_room_session_id' => $session->id,
                'source' => 'upload',
                'status' => 'ready',
                'disk' => $disk,
                'path' => $path,
                'original_name' => 'python-kick-off.'.pathinfo($sample, PATHINFO_EXTENSION),
                'mime' => $this->mime($sample),
                'size_bytes' => (int) filesize($sample),
                'duration_seconds' => $this->probe($sample)['duration'] ?: null,
                'is_shared' => true,
                'uploaded_by' => $host->id,
            ]);
        }

        // Live now: an open session the host can join (or end) from the classroom.
        $live = $this->firstBySlug(LearningRoom::class, 'Demo Live Study Hall', $base + [
            'title' => 'Demo Live Study Hall',
            'description' => 'Open study hall — drop in with questions.',
            'status' => 'live',
            'access' => 'public',
            'scheduled_at' => now()->subMinutes(10),
            'started_at' => now()->subMinutes(10),
        ]);
        if ($live->isLive()) {
            $open = $live->sessions()->whereNull('ended_at')->first() ?? LearningRoomSession::create([
                'learning_room_id' => $live->id,
                'started_by' => $host->id,
                'started_at' => $live->started_at ?? now(),
                'peak_participants' => 2,
            ]);

            $this->conversation($live, $open, $host, [
                [$host, 'announcement', 'Study hall is open — post your questions in the Q&A tab.'],
                [$amina, 'chat', 'Hi! Working on the Laravel migrations lesson.'],
                [$baraka, 'question', 'What is the difference between an index and a primary key?', false],
            ]);
        }
    }

    private function attend(LearningRoomSession $session, ?User $user, string $role, int $joinedAfterMinutes, int $seconds): void
    {
        if (! $user) {
            return;
        }

        $joined = $session->started_at->copy()->addMinutes($joinedAfterMinutes);

        LearningRoomAttendance::query()->firstOrCreate(
            ['learning_room_session_id' => $session->id, 'user_id' => $user->id],
            [
                'learning_room_id' => $session->learning_room_id,
                'role' => $role,
                'first_joined_at' => $joined,
                'last_seen_at' => $joined->copy()->addSeconds($seconds),
                'left_at' => $joined->copy()->addSeconds($seconds),
                'total_seconds' => $seconds,
                'join_count' => 1,
            ],
        );
    }

    /** @param list<array{0:?User,1:string,2:string,3?:bool}> $lines [user, type, body, answered?] */
    private function conversation(LearningRoom $room, LearningRoomSession $session, User $host, array $lines): void
    {
        if ($room->messages()->exists()) {
            return;
        }

        $at = $session->started_at->copy();
        foreach ($lines as $line) {
            [$user, $type, $body] = $line;
            if (! $user) {
                continue;
            }

            $at = $at->copy()->addMinutes(3);
            $answered = $type === 'question' && ($line[3] ?? false);

            $message = LearningRoomMessage::create([
                'learning_room_id' => $room->id,
                'learning_room_session_id' => $session->id,
                'user_id' => $user->id,
                'type' => $type,
                'body' => $body,
                'is_answered' => $answered,
                'answered_by' => $answered ? $host->id : null,
                'answered_at' => $answered ? $at->copy()->addMinutes(2) : null,
            ]);
            $message->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();
        }
    }

    // --- Trash -----------------------------------------------------------------------------
    private function trash(array $categories, User $owner): void
    {
        $old = $this->firstBySlug(LearningCourse::class, 'Demo Retired Course', [
            'learning_category_id' => $categories['Programming']->id,
            'title' => 'Demo Retired Course',
            'summary' => 'Replaced by “Python for Beginners”.',
            'status' => 'draft',
            'access' => 'open',
            'instructor_id' => $owner->id,
            'created_by' => $owner->id,
        ]);
        if (! $old->trashed()) {
            $old->delete();
        }

        $room = $this->firstBySlug(LearningRoom::class, 'Demo Old Webinar', [
            'title' => 'Demo Old Webinar',
            'host_id' => $owner->id,
            'created_by' => $owner->id,
            'status' => 'cancelled',
            'access' => 'public',
            'scheduled_at' => now()->subDays(12),
            'duration_minutes' => 30,
            'cancel_reason' => 'Merged into the Python course.',
        ]);
        if (! $room->trashed()) {
            $room->delete();
        }

        $category = $this->firstBySlug(LearningCategory::class, 'Demo Archive', [
            'name' => 'Demo Archive',
            'description' => 'An empty category that was deleted.',
            'position' => 99,
            'created_by' => $owner->id,
        ]);
        if (! $category->trashed()) {
            $category->delete();
        }
    }

    // --- Sample video files ------------------------------------------------------------------
    private function sampleDir(): string
    {
        return (string) config('learning.demo_video_dir', storage_path('app/learning-demo'));
    }

    /** @return list<string> */
    private function sampleVideos(): array
    {
        $dir = $this->sampleDir();
        if (! is_dir($dir)) {
            return [];
        }

        $files = array_filter(
            glob(rtrim($dir, '/\\').DIRECTORY_SEPARATOR.'*') ?: [],
            fn (string $f) => is_file($f) && in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), ['mp4', 'm4v', 'webm'], true),
        );
        sort($files);

        return array_values($files);
    }

    private function nextSample(): ?string
    {
        if ($this->samples === []) {
            return null;
        }

        return $this->samples[$this->sampleIndex++ % count($this->samples)];
    }

    /** Give a lesson its own copy of a sample video and publish it (once). */
    private function attachSample(LearningVideo $video, \DateTimeInterface $publishedAt): void
    {
        if ($video->path || ! ($sample = $this->nextSample())) {
            return;
        }

        $disk = (string) config('learning.disk', 'private');
        $path = 'learning/videos/'.Str::random(40).'.'.strtolower(pathinfo($sample, PATHINFO_EXTENSION));
        $this->copyInto($disk, $path, $sample);
        $meta = $this->probe($sample);

        $video->forceFill([
            'disk' => $disk,
            'path' => $path,
            'original_name' => basename($sample),
            'mime' => $this->mime($sample),
            'size_bytes' => (int) filesize($sample),
            'duration_seconds' => $meta['duration'] ?: 60,
            'width' => $meta['width'],
            'height' => $meta['height'],
            'status' => 'published',
            'published_at' => $publishedAt,
        ])->save();
    }

    private function copyInto(string $disk, string $path, string $source): void
    {
        $stream = fopen($source, 'rb');
        try {
            Storage::disk($disk)->put($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function mime(string $file): string
    {
        return match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
            'webm' => 'video/webm',
            'm4v' => 'video/x-m4v',
            default => 'video/mp4',
        };
    }

    /**
     * Duration and frame size read straight from the MP4 boxes (mvhd / tkhd),
     * so no ffprobe is needed. Anything unreadable comes back as 0 / null.
     *
     * @return array{duration:int,width:?int,height:?int}
     */
    private function probe(string $file): array
    {
        $result = ['duration' => 0, 'width' => null, 'height' => null];
        $data = @file_get_contents($file);
        if ($data === false || $data === '') {
            return $result;
        }

        $u32 = fn (int $at) => $at + 4 <= strlen($data) ? unpack('N', substr($data, $at, 4))[1] : 0;

        if (($p = strpos($data, 'mvhd')) !== false) {
            $v1 = ord($data[$p + 4] ?? "\0") === 1;
            $timescale = $u32($p + ($v1 ? 24 : 16));
            $duration = $v1
                ? ($u32($p + 28) * 4294967296 + $u32($p + 32))
                : $u32($p + 20);
            if ($timescale > 0) {
                $result['duration'] = (int) round($duration / $timescale);
            }
        }

        $offset = 0;
        while (($p = strpos($data, 'tkhd', $offset)) !== false) {
            $v1 = ord($data[$p + 4] ?? "\0") === 1;
            $at = $p + 4 + ($v1 ? 36 : 24) + 16 + 36;
            $width = $u32($at) >> 16;
            $height = $u32($at + 4) >> 16;
            if ($width > 0 && $height > 0 && $width <= 8192 && $height <= 8192) {
                $result['width'] = $width;
                $result['height'] = $height;
                break;
            }
            $offset = $p + 4;
        }

        return $result;
    }

    /**
     * Find a record by the slug its name/title would get (trashed ones too,
     * so a deleted demo record is not recreated), else create it.
     *
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<T>  $model
     * @return T
     */
    private function firstBySlug(string $model, string $source, array $attributes)
    {
        $slug = Str::slug($source);

        return $model::withTrashed()->where('slug', $slug)->first()
            ?? $model::create(['slug' => $slug] + $attributes);
    }
}
