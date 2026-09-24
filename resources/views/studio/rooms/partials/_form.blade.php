{{--
    Room form body. Must sit inside <form x-data="learnRoomForm(@js($formConfig))">.
    Expects: $room, $categories, $courses, $canPickHost, $hosts, $anyCourse, $locked,
             $memberPicker, $defaultDate, $defaultTime, $timezone.
    Live rooms ($locked): only title, description and the toggles are editable; the rest is
    disabled (so it is not posted) and the server ignores it anyway.
--}}
@php
    $toggle = fn (string $name) => (bool) (session()->hasOldInput() ? old($name) : $room->{$name});
    $lockedCls = 'disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500';
@endphp

@if ($locked)
    <x-mbui.alert type="warning" class="mb-6">
        <p class="font-medium">This room is live right now.</p>
        <p class="mt-0.5">You can change the title, description and the chat / questions / media switches. Schedule, place and audience are locked until the session ends.</p>
    </x-mbui.alert>
@endif

<div class="grid gap-6 lg:grid-cols-3">
    <div class="min-w-0 space-y-6 lg:col-span-2">
        {{-- Details --}}
        <x-mbui.card>
            <h2 class="mbui-section-label">Details</h2>
            <div class="mt-3 space-y-5">
                <div>
                    <label for="room-title" class="mbui-label">Title <span class="text-red-600">*</span></label>
                    <input id="room-title" name="title" type="text" required maxlength="255" autocomplete="off"
                        value="{{ old('title', $room->title) }}" class="mbui-input mt-1 w-full"
                        placeholder="e.g. Weekly Q&A — Cell biology">
                    @error('title')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="room-description" class="mbui-label">Description</label>
                    <textarea id="room-description" name="description" rows="5" maxlength="20000"
                        class="mbui-input mt-1 w-full" placeholder="What will you cover? What should learners prepare?">{{ old('description', $room->description) }}</textarea>
                    <p class="mt-1 text-xs text-gray-500">Markdown is supported (bold, lists, links).</p>
                    @error('description')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="room-category" class="mbui-label">Category</label>
                        <select id="room-category" name="learning_category_id" x-model="categoryId" class="mbui-input mt-1 w-full {{ $lockedCls }}" @disabled($locked)>
                            <option value="">No category</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                        <p x-show="courseForcesCategory" x-cloak class="mt-1 text-xs text-gray-500">Set by the selected course.</p>
                        <p x-show="needsCategory" x-cloak class="mt-1 text-xs text-amber-700">A category room needs a category.</p>
                        @error('learning_category_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="room-course" class="mbui-label">Course</label>
                        <select id="room-course" name="learning_course_id" x-model="courseId" class="mbui-input mt-1 w-full {{ $lockedCls }}" @disabled($locked)>
                            <option value="">No course</option>
                            <template x-for="c in filteredCourses" :key="c.id">
                                <option :value="String(c.id)" x-text="c.title"></option>
                            </template>
                        </select>
                        @unless ($anyCourse)
                            <p class="mt-1 text-xs text-gray-500">
                                {{ $courses->isEmpty() ? 'You do not teach a course yet — the room can still sit in any category.' : 'You can link a room only to courses you teach.' }}
                            </p>
                        @endunless
                        <p x-show="needsCourse" x-cloak class="mt-1 text-xs text-amber-700">A course room needs a course.</p>
                        @error('learning_course_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label for="room-topic" class="mbui-label">Topic</label>
                    <select id="room-topic" name="learning_topic_id" x-model="topicId" class="mbui-input mt-1 w-full {{ $lockedCls }}"
                        @disabled($locked) :disabled="locked || !courseId || !filteredTopics.length">
                        <option value="" x-text="!courseId ? 'Choose a course first' : (filteredTopics.length ? 'No topic' : 'This course has no topics')"></option>
                        <template x-for="t in filteredTopics" :key="t.id">
                            <option :value="String(t.id)" x-text="t.title"></option>
                        </template>
                    </select>
                    @error('learning_topic_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>

                @if ($canPickHost)
                    <div>
                        <label for="room-host" class="mbui-label">Host</label>
                        <select id="room-host" name="host_id" class="mbui-input mt-1 w-full {{ $lockedCls }}" @disabled($locked)>
                            @if (! $room->exists)
                                <option value="">Me ({{ auth()->user()->name }})</option>
                            @endif
                            @foreach ($hosts as $host)
                                <option value="{{ $host->id }}" @selected((string) old('host_id', $room->host_id) === (string) $host->id)>
                                    {{ $host->name }}{{ $host->is_admin ? ' · staff' : ' · instructor' }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500">The host runs the call as moderator and can manage this room.</p>
                        @error('host_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                @endif
            </div>
        </x-mbui.card>

        {{-- Audience --}}
        <x-mbui.card>
            <h2 class="mbui-section-label">Who can join</h2>
            <fieldset class="mt-3" @disabled($locked)>
                <legend class="sr-only">Access</legend>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ([
                        'public' => ['All members', 'Anyone signed in can see and join the room.'],
                        'private' => ['Invited members only', 'Only people you invite below.'],
                        'category' => ['A category’s learners', 'Learners enrolled in any course of the category.'],
                        'course' => ['A course’s learners', 'Only learners enrolled in the chosen course.'],
                    ] as $value => [$label, $hint])
                        <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition"
                            :class="access === '{{ $value }}' ? 'border-indigo-600 bg-indigo-50 ring-1 ring-indigo-600' : 'border-gray-200 hover:border-gray-300'">
                            <input type="radio" name="access" value="{{ $value }}" x-model="access"
                                class="mt-0.5 border-gray-300 text-indigo-600 focus:ring-indigo-500" @checked(old('access', $room->access) === $value)>
                            <span class="text-sm">
                                <span class="block font-medium text-gray-900">{{ $label }}</span>
                                <span class="block text-xs text-gray-500">{{ $hint }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <p class="mt-3 text-sm text-gray-600" x-text="accessHint" aria-live="polite"></p>
            @error('access')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror

            <div x-show="showMembers" x-cloak class="mt-5 border-t border-gray-100 pt-5">
                <input type="hidden" name="members_present" value="1" :disabled="!showMembers || locked" @disabled($locked)>
                <label for="room-members" class="mbui-label">Invited members <span class="text-red-600">*</span></label>
                <p class="mb-2 text-xs text-gray-500">Search by name{{ auth()->user()->is_admin ? ' or e-mail' : ' or exact e-mail address' }}. You (the host) are always in.</p>
                @if ($locked)
                    <p class="text-sm text-gray-600">Manage invitations from the <a href="{{ route('studio.rooms.participants', $room) }}" class="mbui-anchor">participants page</a> while the room is live.</p>
                @else
                    <div x-data="learnUserPicker(@js($memberPicker))"></div>
                @endif
                @error('user_ids')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                @error('user_ids.*')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
        </x-mbui.card>
    </div>

    <div class="min-w-0 space-y-6">
        {{-- Schedule --}}
        <x-mbui.card>
            <h2 class="mbui-section-label">When</h2>
            <div class="mt-3 space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="room-date" class="mbui-label">Date</label>
                        <input id="room-date" name="scheduled_date" type="date" x-ref="date" x-model="date" :min="dateMin"
                            value="{{ $defaultDate }}" class="mbui-input mt-1 w-full {{ $lockedCls }}" @disabled($locked)>
                    </div>
                    <div>
                        <label for="room-time" class="mbui-label">Start time</label>
                        <input id="room-time" name="scheduled_time" type="time" x-ref="time" x-model="time"
                            value="{{ $defaultTime }}" class="mbui-input mt-1 w-full {{ $lockedCls }}" @disabled($locked)>
                    </div>
                </div>
                <p class="text-xs text-gray-500">Times are in {{ str_replace('_', ' ', $timezone) }}.</p>
                <p x-show="pastWarning" x-cloak class="text-xs text-amber-700">That time has already passed.</p>
                <p x-show="scheduleError" x-cloak class="text-sm text-red-700" role="alert" x-text="scheduleError"></p>
                @error('scheduled_date')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                @error('scheduled_time')<p class="text-sm text-red-700">{{ $message }}</p>@enderror

                <div>
                    <label for="room-duration" class="mbui-label">Duration (minutes) <span class="text-red-600">*</span></label>
                    <input id="room-duration" name="duration_minutes" type="number" required min="5" max="1440" step="5"
                        value="{{ old('duration_minutes', $room->duration_minutes ?? 60) }}" class="mbui-input mt-1 w-full {{ $lockedCls }}" @disabled($locked)>
                    <p class="mt-1 text-xs text-gray-500">Used for the calendar and reminders — the call is not cut off.</p>
                    @error('duration_minutes')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
            </div>
        </x-mbui.card>

        {{-- In-room settings --}}
        <x-mbui.card>
            <h2 class="mbui-section-label">In the room</h2>
            <div class="mt-3 space-y-4">
                @foreach ([
                    'chat_enabled' => ['Chat', 'Learners can send chat messages during the session.'],
                    'questions_enabled' => ['Questions', 'Learners can post questions for you to mark as answered.'],
                    'allow_participant_media' => ['Learners’ microphone & camera', 'When off, learners join muted with cameras off and cannot turn them on (Jitsi audio/video moderation). You can still ask someone to unmute.'],
                ] as $name => [$label, $hint])
                    <label class="flex items-start gap-3">
                        <input type="hidden" name="{{ $name }}" value="0">
                        <input type="checkbox" name="{{ $name }}" value="1" @checked($toggle($name))
                            class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm">
                            <span class="block font-medium text-gray-900">{{ $label }}</span>
                            <span class="block text-xs text-gray-500">{{ $hint }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </x-mbui.card>
    </div>
</div>
