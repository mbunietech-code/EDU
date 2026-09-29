{{-- Camera background choices (none / blur / pictures). Processed on the device; nothing is uploaded. --}}
<div class="mt-2 flex flex-wrap gap-2" role="radiogroup" aria-label="Camera background">
    <button type="button" role="radio" @click="setBackground('none')" :disabled="bg.busy" :aria-checked="(bg.mode === 'none').toString()"
        class="flex h-14 w-20 items-center justify-center rounded-lg bg-gray-800 text-xs font-medium ring-2 disabled:opacity-50"
        :class="bg.mode === 'none' ? 'ring-indigo-500 text-white' : 'ring-transparent text-gray-300 hover:ring-gray-600'">None</button>
    <button type="button" role="radio" @click="setBackground('blur')" :disabled="bg.busy" :aria-checked="(bg.mode === 'blur').toString()"
        class="flex h-14 w-20 items-center justify-center rounded-lg bg-gradient-to-br from-gray-600 to-gray-800 text-xs font-medium ring-2 disabled:opacity-50"
        :class="bg.mode === 'blur' ? 'ring-indigo-500 text-white' : 'ring-transparent text-gray-200 hover:ring-gray-600'">Blur</button>
    <template x-for="img in bgImages" :key="img.url">
        <button type="button" role="radio" @click="setBackground('image', img.url)" :disabled="bg.busy"
            :aria-checked="(bg.mode === 'image' && bg.image === img.url).toString()" :aria-label="img.label + ' background'"
            class="h-14 w-20 overflow-hidden rounded-lg bg-cover bg-center ring-2 disabled:opacity-50"
            :class="bg.mode === 'image' && bg.image === img.url ? 'ring-indigo-500' : 'ring-transparent hover:ring-gray-600'"
            :style="'background-image:url(' + img.url + ')'"></button>
    </template>
</div>
<p x-show="bg.busy" x-cloak class="mt-2 text-xs text-gray-400">Applying… the first time takes a few seconds.</p>
<p x-show="bg.error" x-cloak class="mt-2 text-xs text-red-400" x-text="bg.error" role="alert"></p>
<p class="mt-2 text-[11px] text-gray-500">Done on your device, nothing is uploaded. It uses more battery, so switch it off on older phones.</p>
