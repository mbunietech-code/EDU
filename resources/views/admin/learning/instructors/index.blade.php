<x-layouts.admin title="Instructors" header="Learning">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Instructors</h1>
            <p class="mt-1 text-sm text-gray-500">Members with instructor access can host live rooms and upload lessons in the Teaching Studio.</p>
        </div>
    </div>

    @if ($canManage)
        <x-mbui.card class="mt-6">
            <h2 class="text-base font-semibold text-gray-900">Grant instructor access</h2>
            <p class="text-sm text-gray-500">The member is notified and can open the Teaching Studio straight away.</p>
            <form method="POST" action="{{ route('admin.learning.instructors.store') }}" class="mt-4 flex flex-col gap-3 md:flex-row md:items-start">
                @csrf
                <div class="min-w-0 flex-1"
                    x-data="learnUserPicker(@js(['searchUrl' => route('studio.users.search'), 'name' => 'user_id', 'selected' => [], 'multiple' => false, 'label' => 'Search for a member to make an instructor', 'placeholder' => 'Search for a member by name…']))"></div>
                <x-mbui.button type="submit" class="md:mt-0">Grant access</x-mbui.button>
            </form>
            @error('user_id')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
        </x-mbui.card>
    @endif

    <form method="GET" action="{{ route('admin.learning.instructors.index') }}" class="mt-6 flex max-w-md gap-2" role="search">
        <label for="instructor-q" class="sr-only">Search instructors</label>
        <input id="instructor-q" type="search" name="q" value="{{ $search }}" placeholder="Search instructors…" class="mbui-input w-full">
        <x-mbui.button type="submit" variant="secondary">Search</x-mbui.button>
    </form>

    @if ($instructors->isEmpty())
        <x-learning.empty class="mt-4" :title="$search !== '' ? 'No instructors match “'.$search.'”' : 'No instructors yet'"
            :message="$search !== '' ? 'Try another name or email.' : ($canManage ? 'Grant instructor access to a member above.' : 'Instructors granted by content managers will be listed here.')" />
    @else
        <x-mbui.card class="mt-4 overflow-hidden p-0">
            <div class="hidden grid-cols-12 gap-4 border-b border-gray-200 bg-gray-50 px-6 py-3 text-xs font-medium uppercase tracking-wide text-gray-500 md:grid">
                <div class="col-span-5">Instructor</div>
                <div class="col-span-2 text-right">Rooms hosted</div>
                <div class="col-span-2 text-right">Lessons</div>
                <div class="col-span-2 text-right">Courses</div>
                <div class="col-span-1"><span class="sr-only">Actions</span></div>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach ($instructors as $instructor)
                    <li class="grid grid-cols-3 gap-x-4 gap-y-2 px-4 py-4 md:grid-cols-12 md:items-center md:px-6">
                        <div class="col-span-3 flex min-w-0 items-center gap-3 md:col-span-5">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-xs font-semibold text-white" aria-hidden="true">{{ strtoupper(mb_substr($instructor->name, 0, 1)) }}</span>
                            <div class="min-w-0">
                                <a href="{{ route('learn.instructors.show', $instructor) }}" class="block truncate font-medium text-gray-900 hover:text-indigo-600">{{ $instructor->name }}</a>
                                <p class="truncate text-xs text-gray-500">{{ $instructor->email }}
                                    @if (! $instructor->isActive()) · <span class="text-amber-700">{{ ucfirst($instructor->status) }}</span>@endif
                                </p>
                            </div>
                        </div>
                        <div class="text-sm md:col-span-2 md:text-right"><span class="block text-xs text-gray-500 md:hidden">Rooms</span>{{ number_format($instructor->hosted_rooms_count) }}</div>
                        <div class="text-sm md:col-span-2 md:text-right"><span class="block text-xs text-gray-500 md:hidden">Lessons</span>{{ number_format($instructor->teaching_videos_count) }}</div>
                        <div class="text-sm md:col-span-2 md:text-right"><span class="block text-xs text-gray-500 md:hidden">Courses</span>{{ number_format($instructor->taught_courses_count) }}</div>
                        <div class="col-span-3 flex justify-end md:col-span-1">
                            @if ($canManage)
                                <x-learning.confirm-delete :action="route('admin.learning.instructors.destroy', $instructor)"
                                    :title="'Remove instructor access from '.$instructor->name.'?'"
                                    :impact="array_values(array_filter([
                                        $instructor->hosted_rooms_count ? $instructor->hosted_rooms_count.' hosted '.Str::plural('room', $instructor->hosted_rooms_count).' stay; only room admins can manage them afterwards' : null,
                                        $instructor->teaching_videos_count ? $instructor->teaching_videos_count.' '.Str::plural('lesson', $instructor->teaching_videos_count).' stay published as they are' : null,
                                        $instructor->taught_courses_count ? $instructor->taught_courses_count.' '.Str::plural('course', $instructor->taught_courses_count).' keep them as instructor' : null,
                                        'They can no longer host new rooms or upload lessons',
                                    ]))"
                                    button-label="Remove access">
                                    <x-slot:trigger>
                                        <x-mbui.button variant="ghost" class="text-red-700" :aria-label="'Remove instructor access from '.$instructor->name">Revoke</x-mbui.button>
                                    </x-slot:trigger>
                                </x-learning.confirm-delete>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-mbui.card>
        <div class="mt-4">{{ $instructors->links() }}</div>
    @endif
</x-layouts.admin>
