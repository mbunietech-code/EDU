@props(['id', 'number', 'title'])

<section id="{{ $id }}" class="scroll-mt-24">
    <h2 class="flex items-baseline gap-3 text-xl font-semibold tracking-tight text-gray-900">
        <span class="text-sm font-semibold text-indigo-600">{{ str_pad($number, 2, '0', STR_PAD_LEFT) }}</span>
        {{ $title }}
    </h2>
    <div class="mt-3 space-y-3 [&_ul]:list-disc [&_ul]:space-y-1.5 [&_ul]:pl-5 [&_strong]:font-semibold [&_strong]:text-gray-900">
        {{ $slot }}
    </div>
</section>
