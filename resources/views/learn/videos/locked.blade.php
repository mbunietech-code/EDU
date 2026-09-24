{{-- Friendly "locked" lesson page (served with HTTP 403). Expects $video, $course (only when the viewer may
     enrol — never reveals an enrolled-only course), $canEnroll. --}}
<x-layouts.app title="Lesson locked" header="Learning">
    <div class="mx-auto max-w-xl py-6 sm:py-10">
        <div class="mbui-card p-6 text-center sm:p-8">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-indigo-50 text-indigo-600" aria-hidden="true">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                </svg>
            </div>

            @if ($canEnroll && $course)
                <h1 class="mt-4 text-lg font-semibold text-gray-900">This lesson is part of a course</h1>
                <p class="mt-2 text-sm text-gray-600">
                    Enrol in <span class="font-medium text-gray-900">{{ $course->title }}</span> to watch
                    <span class="font-medium text-gray-900">{{ $video->title }}</span> and the rest of the course.
                </p>
                <div class="mt-6 flex flex-col justify-center gap-2 sm:flex-row">
                    <form method="POST" action="{{ route('learn.courses.enroll', $course) }}">
                        @csrf
                        <x-mbui.button type="submit" class="w-full sm:w-auto">Enrol in this course</x-mbui.button>
                    </form>
                    <x-mbui.btn-link :href="route('learn.courses.show', $course)" variant="secondary">View course</x-mbui.btn-link>
                </div>
            @else
                <h1 class="mt-4 text-lg font-semibold text-gray-900">This lesson is for enrolled learners</h1>
                <p class="mt-2 text-sm text-gray-600">
                    It belongs to a course that only enrolled learners can watch. Enrolment in that course is arranged by an administrator.
                </p>
                <div class="mt-6 flex justify-center">
                    <x-mbui.btn-link :href="route('learn.videos.index')" variant="secondary">Browse available lessons</x-mbui.btn-link>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
