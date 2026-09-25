{{-- Classroom top bar: back, title, status, timer, connection, people count, host End. --}}
<header class="flex h-14 shrink-0 items-center gap-2 border-b border-gray-800 bg-gray-900 px-2 sm:gap-3 sm:px-4">
    <a href="{{ route('learn.rooms.show', $room) }}"
        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-500"
        aria-label="Back to class details" title="Back to class details">
        @include('learn.rooms.partials.icon', ['name' => 'arrow-left'])
    </a>

    <div class="flex min-w-0 flex-1 items-center gap-2">
        <h1 class="min-w-0 truncate text-sm font-semibold text-white sm:text-base" x-text="room.title">{{ $room->title }}</h1>
        <span class="shrink-0">
            <span x-show="room.status === 'live'"><x-learning.room-status status="live" /></span>
            <span x-show="room.status === 'scheduled'" x-cloak><x-learning.room-status status="scheduled" /></span>
            <span x-show="room.status === 'completed'" x-cloak><x-learning.room-status status="completed" /></span>
            <span x-show="room.status === 'cancelled'" x-cloak><x-learning.room-status status="cancelled" /></span>
            <span x-show="room.status === 'draft'" x-cloak><x-learning.room-status status="draft" /></span>
        </span>
        <span x-show="elapsed" x-cloak class="hidden shrink-0 font-mono text-xs tabular-nums text-gray-300 sm:inline"
            :aria-label="'Elapsed time ' + elapsed" x-text="elapsed"></span>
    </div>

    <div class="flex shrink-0 items-center gap-2">
        <span x-show="room.is_recording && room.status === 'live'" x-cloak
            class="inline-flex items-center gap-1 rounded-full bg-red-600/20 px-2 py-1 text-xs font-semibold text-red-300 ring-1 ring-inset ring-red-500/40"
            role="status" aria-label="This class is being recorded">
            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-red-500" aria-hidden="true"></span>
            <span class="hidden tabular-nums sm:inline" x-text="rec.active ? 'REC ' + rec.elapsed : 'Recording'">Recording</span>
        </span>
        <span x-show="room.is_locked && room.status === 'live'" x-cloak title="Locked: no new people can join"
            class="inline-flex items-center gap-1 rounded-full bg-gray-800 px-2 py-1 text-xs font-medium text-gray-300 ring-1 ring-inset ring-gray-700"
            role="status" aria-label="The room is locked">
            @include('learn.rooms.partials.icon', ['name' => 'lock', 'class' => 'h-3.5 w-3.5'])
            <span class="hidden md:inline">Locked</span>
        </span>
        <span x-show="inCall" x-cloak class="hidden h-6 items-end gap-0.5 rounded px-1 py-1 sm:inline-flex" role="img"
            :title="'Your network: ' + qualityLabel(myQuality)" :aria-label="'Your network: ' + qualityLabel(myQuality)">
            <template x-for="bar in [1, 2, 3]" :key="bar">
                <span class="w-1 rounded-sm" :style="'height:' + (bar * 4 + 2) + 'px'"
                    :class="qualityBars(myQuality) >= bar ? (myQuality === 'poor' ? 'bg-amber-400' : (myQuality === 'lost' ? 'bg-red-500' : 'bg-emerald-400')) : 'bg-gray-600'"></span>
            </template>
        </span>
        <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset"
            :class="{
                'bg-emerald-500/10 text-emerald-300 ring-emerald-500/30': connection.key === 'connected',
                'bg-sky-500/10 text-sky-300 ring-sky-500/30': connection.key === 'connecting',
                'bg-amber-500/10 text-amber-300 ring-amber-500/30': connection.key === 'reconnecting',
                'bg-red-500/10 text-red-300 ring-red-500/30': connection.key === 'offline',
            }"
            role="status" :aria-label="'Connection: ' + connection.label">
            <span class="h-1.5 w-1.5 rounded-full"
                :class="{
                    'bg-emerald-400': connection.key === 'connected',
                    'bg-sky-400 animate-pulse': connection.key === 'connecting',
                    'bg-amber-400 animate-pulse': connection.key === 'reconnecting',
                    'bg-red-400': connection.key === 'offline',
                }" aria-hidden="true"></span>
            <span class="hidden sm:inline" x-text="connection.label">Connecting</span>
        </span>

        <button type="button" @click="togglePanel('people')"
            class="inline-flex items-center gap-1 rounded-lg px-2 py-1.5 text-sm text-gray-200 hover:bg-gray-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-500"
            :aria-label="counts.participants + ' in the call — show people'">
            @include('learn.rooms.partials.icon', ['name' => 'users', 'class' => 'h-5 w-5'])
            <span class="tabular-nums" x-text="counts.participants">0</span>
        </button>

        <template x-if="isManager && room.status === 'live'">
            <button type="button" @click="askEnd()" :disabled="busy.end"
                class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-red-500 disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-500">
                @include('learn.rooms.partials.icon', ['name' => 'stop', 'class' => 'h-4 w-4'])
                <span class="hidden sm:inline">End session</span>
                <span class="sm:hidden">End</span>
            </button>
        </template>
    </div>
</header>
