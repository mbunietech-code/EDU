@props(['file', 'href'])

{{-- One downloadable file with an icon by type, its name and size. --}}
@php
    $ext = $file->extension();
    $tone = match (true) {
        $ext === 'pdf' => 'bg-red-50 text-red-600',
        in_array($ext, ['doc', 'docx', 'odt', 'rtf', 'txt'], true) => 'bg-sky-50 text-sky-600',
        in_array($ext, ['xls', 'xlsx', 'csv', 'sav', 'dta'], true) => 'bg-emerald-50 text-emerald-600',
        in_array($ext, ['ppt', 'pptx'], true) => 'bg-orange-50 text-orange-600',
        in_array($ext, ['png', 'jpg', 'jpeg'], true) => 'bg-violet-50 text-violet-600',
        default => 'bg-gray-100 text-gray-600',
    };
@endphp

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'group flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-2.5 transition hover:border-indigo-300 hover:bg-indigo-50/40']) }}>
    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-[10px] font-bold uppercase {{ $tone }}">{{ $ext ?: 'file' }}</span>
    <span class="min-w-0 flex-1">
        <span class="block truncate text-sm font-medium text-gray-900 group-hover:text-indigo-700">{{ $file->original_name }}</span>
        <span class="block text-xs text-gray-500">{{ $file->sizeLabel() }} · {{ $file->created_at->format('d M Y') }}@if ($file->note) · {{ \Illuminate\Support\Str::limit($file->note, 60) }}@endif</span>
    </span>
    <svg class="h-5 w-5 shrink-0 text-gray-400 group-hover:text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
</a>
