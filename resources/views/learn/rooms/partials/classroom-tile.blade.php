{{-- One video tile (inside an Alpine x-for / x-if that provides `t`). $big = stage tile. --}}
<div class="group relative h-full w-full overflow-hidden rounded-xl bg-gray-800 ring-2 transition"
    :class="t.speaking && t.source === 'camera' ? 'ring-emerald-400' : 'ring-transparent'">
    <video :data-tile-id="t.id" autoplay playsinline muted x-show="t.hasVideo"
        class="h-full w-full bg-black"
        :class="[t.source === 'screen' ? 'object-contain' : 'object-cover', t.isLocal && t.source === 'camera' ? '-scale-x-100' : '']"
        :aria-label="t.source === 'screen' ? t.name + ' is sharing their screen' : 'Video of ' + t.name"></video>

    {{-- No video: initials --}}
    <div x-show="!t.hasVideo" class="absolute inset-0 flex items-center justify-center">
        <div class="flex items-center justify-center rounded-full bg-indigo-600 font-semibold text-white {{ $big ? 'h-20 w-20 text-2xl sm:h-24 sm:w-24 sm:text-3xl' : 'h-10 w-10 text-sm' }}"
            :class="t.speaking ? 'ring-4 ring-emerald-400/70' : ''" aria-hidden="true"
            x-text="(t.name || '?').trim().split(/\s+/).map((w) => w.charAt(0)).slice(0, 2).join('').toUpperCase()"></div>
    </div>

    {{-- Name + state --}}
    <div class="pointer-events-none absolute inset-x-1.5 bottom-1.5 flex items-center gap-1">
        <span class="inline-flex min-w-0 items-center gap-1 rounded-md bg-black/60 px-1.5 py-0.5 text-[11px] font-medium text-white {{ $big ? 'sm:text-xs' : '' }}">
            <span x-show="!t.mic && t.source === 'camera'" class="shrink-0 text-red-400" aria-label="Microphone off">
                @include('learn.rooms.partials.icon', ['name' => 'mic-off', 'class' => 'h-3.5 w-3.5'])
            </span>
            <span x-show="t.source === 'screen'" class="shrink-0 text-indigo-300" aria-hidden="true">
                @include('learn.rooms.partials.icon', ['name' => 'screen', 'class' => 'h-3.5 w-3.5'])
            </span>
            <span class="truncate" x-text="t.name + (t.isLocal ? ' (you)' : '') + (t.source === 'screen' ? ' · screen' : '')"></span>
            <span x-show="t.isHost" class="shrink-0 rounded bg-indigo-600 px-1 text-[10px] font-semibold">Host</span>
        </span>
    </div>

    {{-- Connection quality --}}
    <div class="absolute right-1.5 top-1.5 flex items-center gap-1">
        <span class="inline-flex h-5 items-end gap-0.5 rounded bg-black/50 px-1 py-1" :title="qualityLabel(t.quality)" role="img" :aria-label="qualityLabel(t.quality)">
            <template x-for="bar in [1, 2, 3]" :key="bar">
                <span class="w-0.5 rounded-sm" :style="'height:' + (bar * 3 + 2) + 'px'"
                    :class="qualityBars(t.quality) >= bar ? (t.quality === 'poor' ? 'bg-amber-400' : (t.quality === 'lost' ? 'bg-red-500' : 'bg-emerald-400')) : 'bg-white/30'"></span>
            </template>
        </span>
        <button type="button" @click="pin(t)"
            class="inline-flex h-6 w-6 items-center justify-center rounded bg-black/50 text-white opacity-0 transition hover:bg-black/70 focus:opacity-100 group-hover:opacity-100"
            :class="pinnedId === t.id ? 'opacity-100 text-indigo-300' : ''"
            :aria-pressed="(pinnedId === t.id).toString()"
            :aria-label="(pinnedId === t.id ? 'Unpin ' : 'Pin ') + t.name" :title="pinnedId === t.id ? 'Unpin' : 'Pin to the main view'">
            @include('learn.rooms.partials.icon', ['name' => 'pin', 'class' => 'h-3.5 w-3.5'])
        </button>
    </div>
</div>
