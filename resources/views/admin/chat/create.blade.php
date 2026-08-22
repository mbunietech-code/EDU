<x-layouts.admin title="New Message" header="New Message">

    <style>[x-cloak]{display:none!important;}</style>

    <div class="max-w-lg">
        <form method="POST" action="{{ route('admin.chat.start') }}" class="mbui-card p-6 space-y-5">
            @csrf
            <div class="relative"
                x-data='{
                    query: "",
                    open: false,
                    selectedId: "{{ old('user_id', '') }}",
                    users: @json($users->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])),
                    get filtered() {
                        if (!this.query) return this.users;
                        const q = this.query.toLowerCase();
                        return this.users.filter(u => u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q));
                    },
                    select(u) {
                        this.selectedId = u.id;
                        this.query = u.name + " (" + u.email + ")";
                        this.open = false;
                    }
                }'>
                <x-input-label for="user_search" value="Customer" />
                <input type="text" id="user_search" x-model="query" autocomplete="off"
                    @focus="open = true"
                    @input="open = true; selectedId = ''"
                    placeholder="Search by name or email..."
                    class="mbui-input mt-1">
                <input type="hidden" name="user_id" :value="selectedId">

                <div x-show="open" x-cloak @click.outside="open = false"
                    class="absolute z-10 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg">
                    <template x-for="u in filtered" :key="u.id">
                        <button type="button" @click="select(u)"
                            class="block w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-indigo-50"
                            x-text="u.name + ' (' + u.email + ')'"></button>
                    </template>
                    <p x-show="filtered.length === 0" class="px-3 py-2 text-sm text-gray-400">No customers found.</p>
                </div>

                <x-input-error :messages="$errors->get('user_id')" class="mt-2" />
                <p class="mt-1 text-xs text-gray-500">Opens (or starts) a conversation with this customer so you can send the first message.</p>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-5">
                <a href="{{ route('admin.chat.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">Cancel</a>
                <x-mbui.button type="submit">Start conversation</x-mbui.button>
            </div>
        </form>
    </div>

</x-layouts.admin>
