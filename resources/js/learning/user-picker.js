/**
 * Searchable user picker posting hidden inputs.
 *
 * cfg: {
 *   searchUrl,                 // GET ?q= -> [{id, name, email?}] (studio.users.search)
 *   name: 'user_ids[]',        // hidden input name (single mode: e.g. 'user_id')
 *   selected: [{id, name, email?}],
 *   multiple: true,            // false = single select (picking replaces the current choice)
 *   placeholder, minChars: 2, excludeIds: [], inputId, label (accessible name of the search box)
 * }
 *
 * Usage: <div x-data="learnUserPicker(@js($cfg))"></div>
 * When the element is empty the picker renders its own markup (chips + search box + result list),
 * so every page gets the same accessible widget. Pages may instead supply their own markup using
 * the same state/methods (query, results, selected, open, loading, error, highlighted, pick, remove…).
 */
const DEBOUNCE_MS = 250;

let pickerSeq = 0;

const TEMPLATE = `
<div class="relative" @click.outside="open = false" @keydown.escape.stop="open = false">
    <div class="mb-2 flex flex-wrap gap-1.5" x-show="selected.length" x-cloak>
        <template x-for="u in selected" :key="u.id">
            <span class="inline-flex max-w-full items-center gap-1 rounded-full bg-indigo-50 py-1 pl-2.5 pr-1 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-600/20">
                <span class="truncate" x-text="u.name"></span>
                <span class="hidden truncate text-indigo-500 sm:inline" x-show="u.email" x-text="'· ' + (u.email || '')"></span>
                <button type="button" class="rounded-full p-0.5 text-indigo-500 hover:bg-indigo-100 hover:text-indigo-700" @click="remove(u)" :aria-label="'Remove ' + u.name">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
                <input type="hidden" :name="cfg.name" :value="u.id">
            </span>
        </template>
    </div>
    <template x-if="!cfg.multiple && !selected.length">
        <input type="hidden" :name="cfg.name" value="">
    </template>

    <div class="relative">
        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
        <input type="text" x-ref="input" :id="inputId" autocomplete="off" role="combobox" aria-autocomplete="list"
            :aria-expanded="open ? 'true' : 'false'" :aria-controls="listId" :aria-label="cfg.label || 'Search members'"
            :aria-activedescendant="highlighted >= 0 ? listId + '-' + highlighted : null"
            class="mbui-input w-full pl-9" :placeholder="placeholder" x-model="query"
            @input="search()" @keydown="onKeydown($event)" @focus="if (query.trim().length >= minChars) open = true">
        <svg x-show="loading" x-cloak class="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin text-gray-400" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
    </div>

    <div x-show="open" x-cloak class="absolute z-30 mt-1 w-full overflow-hidden rounded-lg bg-white shadow-lg ring-1 ring-black/5">
        <p x-show="loading && !results.length" class="px-3 py-2 text-sm text-gray-500">Searching…</p>
        <p x-show="error" class="px-3 py-2 text-sm text-red-700" role="alert" x-text="error"></p>
        <p x-show="!loading && !error && searched && !results.length" class="px-3 py-2 text-sm text-gray-500">No matching members.</p>
        <ul x-show="results.length" :id="listId" role="listbox" class="max-h-64 overflow-y-auto py-1">
            <template x-for="(u, i) in results" :key="u.id">
                <li :id="listId + '-' + i" role="option" :aria-selected="highlighted === i ? 'true' : 'false'"
                    class="cursor-pointer px-3 py-2 text-sm" :class="highlighted === i ? 'bg-indigo-600 text-white' : 'text-gray-900 hover:bg-gray-50'"
                    @mouseenter="highlighted = i" @mousedown.prevent="pick(u)">
                    <span class="block truncate font-medium" x-text="u.name"></span>
                    <span class="block truncate text-xs" :class="highlighted === i ? 'text-indigo-100' : 'text-gray-500'" x-show="u.email" x-text="u.email"></span>
                </li>
            </template>
        </ul>
    </div>
    <p x-show="query.trim().length > 0 && query.trim().length < minChars" x-cloak class="mt-1 text-xs text-gray-500">Type at least <span x-text="minChars"></span> characters.</p>
</div>`;

window.learnUserPicker = (cfg = {}) => ({
    cfg: { name: 'user_ids[]', multiple: true, ...cfg },
    selected: Array.isArray(cfg.selected) ? cfg.selected.filter((u) => u && u.id) : [],
    query: '',
    results: [],
    open: false,
    loading: false,
    searched: false,
    error: '',
    highlighted: -1,
    listId: '',
    inputId: '',
    _timer: null,
    _controller: null,

    get minChars() {
        return Number(this.cfg.minChars) || 2;
    },

    get placeholder() {
        return this.cfg.placeholder || (this.cfg.multiple ? 'Search members by name…' : 'Search for a member…');
    },

    init() {
        pickerSeq += 1;
        this.listId = `learn-user-picker-${pickerSeq}`;
        this.inputId = this.cfg.inputId || `${this.listId}-input`;
        if (!this.cfg.multiple && this.selected.length > 1) this.selected = this.selected.slice(0, 1);
        if (this.$el.children.length === 0) this.$el.innerHTML = TEMPLATE;
    },

    search() {
        clearTimeout(this._timer);
        this.error = '';
        const q = this.query.trim();
        if (q.length < this.minChars) {
            this._controller?.abort();
            this.results = [];
            this.searched = false;
            this.loading = false;
            this.open = false;
            return;
        }
        this.loading = true;
        this.open = true;
        this._timer = setTimeout(() => this.fetchResults(q), DEBOUNCE_MS);
    },

    async fetchResults(q) {
        this._controller?.abort();
        const controller = new AbortController();
        this._controller = controller;
        try {
            const url = new URL(this.cfg.searchUrl, window.location.origin);
            url.searchParams.set('q', q);
            const res = await fetch(url.toString(), {
                signal: controller.signal,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                },
            });
            if (res.status === 429) throw new Error('Too many searches — wait a moment.');
            if (res.status === 419 || res.status === 401) throw new Error('Your session expired. Reload the page.');
            if (!res.ok) throw new Error('Search failed. Please try again.');
            const data = await res.json();
            const taken = new Set([...this.selected.map((u) => String(u.id)), ...(this.cfg.excludeIds || []).map(String)]);
            this.results = (Array.isArray(data) ? data : []).filter((u) => !taken.has(String(u.id)));
            this.highlighted = this.results.length ? 0 : -1;
            this.searched = true;
            this.error = '';
        } catch (e) {
            if (e.name === 'AbortError') return;
            this.results = [];
            this.error = e.message && !e.message.startsWith('Failed to fetch') ? e.message : 'Search failed. Check your connection.';
        } finally {
            if (this._controller === controller) this.loading = false;
        }
    },

    pick(user) {
        if (!user) return;
        if (this.cfg.multiple) {
            if (!this.selected.some((u) => String(u.id) === String(user.id))) this.selected.push(user);
        } else {
            this.selected = [user];
        }
        this.query = '';
        this.results = [];
        this.searched = false;
        this.open = false;
        this.highlighted = -1;
        this.$dispatch('learn-user-picked', { user, selected: this.selected });
        this.$nextTick(() => this.$refs.input?.focus());
    },

    remove(user) {
        this.selected = this.selected.filter((u) => String(u.id) !== String(user.id));
        this.$dispatch('learn-user-removed', { user, selected: this.selected });
        this.$nextTick(() => this.$refs.input?.focus());
    },

    onKeydown(e) {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (!this.open && this.results.length) this.open = true;
            if (this.results.length) this.highlighted = (this.highlighted + 1) % this.results.length;
            this.scrollToHighlighted();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (this.results.length) this.highlighted = (this.highlighted - 1 + this.results.length) % this.results.length;
            this.scrollToHighlighted();
        } else if (e.key === 'Enter') {
            e.preventDefault(); // never submit the surrounding form from the search box
            if (this.open && this.highlighted >= 0) this.pick(this.results[this.highlighted]);
        } else if (e.key === 'Escape') {
            this.open = false;
        } else if (e.key === 'Backspace' && this.query === '' && this.selected.length && this.cfg.multiple) {
            this.selected.pop();
        }
    },

    scrollToHighlighted() {
        this.$nextTick(() => {
            document.getElementById(`${this.listId}-${this.highlighted}`)?.scrollIntoView({ block: 'nearest' });
        });
    },
});
