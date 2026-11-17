@php($sid = $stage->id ?? 'new')
<div>
    <label for="name-{{ $sid }}" class="mbui-label">Name</label>
    <input id="name-{{ $sid }}" name="name" value="{{ $stage->name }}" required maxlength="80" class="mbui-input mt-1" placeholder="e.g. Data collection">
</div>
<div>
    <label for="progress-{{ $sid }}" class="mbui-label">Progress when reached (%)</label>
    <input id="progress-{{ $sid }}" type="number" name="progress" value="{{ $stage->progress ?? 0 }}" min="0" max="100" required class="mbui-input mt-1">
</div>
<div class="sm:col-span-2">
    <label for="description-{{ $sid }}" class="mbui-label">What it means for the customer</label>
    <input id="description-{{ $sid }}" name="description" value="{{ $stage->description }}" maxlength="255" class="mbui-input mt-1">
</div>
<div class="sm:col-span-2" x-data="{ color: @js($stage->color ?? 'indigo') }">
    <span class="mbui-label">Colour</span>
    <input type="hidden" name="color" :value="color">
    <div class="mt-1 flex flex-wrap gap-2">
        @foreach (\App\Models\ResearchRequestStage::HEX as $name => $hex)
            <button type="button" @click="color = '{{ $name }}'" class="h-7 w-7 rounded-full ring-offset-2 transition" style="background-color: {{ $hex }}"
                :class="color === '{{ $name }}' ? 'ring-2 ring-gray-900 scale-110' : ''" aria-label="{{ ucfirst($name) }}"></button>
        @endforeach
    </div>
</div>
<label class="flex items-center gap-2 text-sm text-gray-700">
    <input type="hidden" name="allows_cancel" value="0">
    <input type="checkbox" name="allows_cancel" value="1" @checked($stage->allows_cancel) class="rounded border-gray-300 text-indigo-600"> Customer may cancel here
</label>
@if (! $stage->isSystem())
    <label class="flex items-center gap-2 text-sm text-gray-700">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked($stage->is_active ?? true) class="rounded border-gray-300 text-indigo-600"> In use
    </label>
@endif
