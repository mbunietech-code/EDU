<x-layouts.admin title="Password vault" header="Password vault">

    <script>
        // One entry's secrets are fetched only when asked for, and forgotten after a minute.
        window.vaultEntry = function (url) {
            return {
                shown: false, data: null, copied: '', timer: null,
                async load(what) {
                    if (this.data) return this.data;
                    const r = await fetch(url, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json', 'Content-Type': 'application/json' },
                        body: JSON.stringify({ what }),
                    });
                    if (r.status === 423 || r.status === 419) { window.location = @json(route('admin.vault.pin')); return null; }
                    if (!r.ok) { alert('Could not open this entry.'); return null; }
                    this.data = await r.json();
                    clearTimeout(this.timer);
                    this.timer = setTimeout(() => { this.data = null; this.shown = false; }, 60000);
                    return this.data;
                },
                async toggle() {
                    if (this.shown) { this.shown = false; this.data = null; return; }
                    if (await this.load('show')) this.shown = true;
                },
                async copy(field) {
                    const d = await this.load('copy ' + field);
                    if (!d) return;
                    try { await navigator.clipboard.writeText(d[field] || ''); this.copied = field; } catch (e) { alert('Copy failed: press Show and copy by hand.'); }
                    setTimeout(() => this.copied = '', 1500);
                },
            };
        };
        // Lock the page itself once the server-side idle time has passed.
        setTimeout(() => window.location.reload(), {{ ($idleMinutes * 60 + 5) * 1000 }});
    </script>

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Password vault</h1>
            <p class="mt-1 text-sm text-gray-500">Company logins, encrypted. Every "Show" and "Copy" is recorded in the activity log. Locks after {{ $idleMinutes }} idle minutes.</p>
        </div>
        <form method="POST" action="{{ route('admin.vault.lock') }}">
            @csrf
            <button type="submit" class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
                Lock now
            </button>
        </form>
    </div>

    @if (session('success'))
        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    <div class="mt-6" x-data="{ adding: {{ $errors->any() && ! old('_entry') ? 'true' : 'false' }} }">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form method="GET" class="flex flex-1 flex-col gap-2 sm:flex-row">
            <input type="search" name="q" value="{{ $q }}" placeholder="Search by name or website…" class="mbui-input w-full sm:w-72">
            <select name="category" class="mbui-input sm:w-56" onchange="this.form.submit()">
                <option value="">All categories</option>
                @foreach (\App\Models\VaultEntry::CATEGORIES as $key => $label)
                    <option value="{{ $key }}" @selected($category === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </form>
        @if ($canManage)
            <button type="button" @click="adding = !adding" class="inline-flex items-center justify-center gap-1 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                Add login
            </button>
        @endif
    </div>

        @if ($canManage)
                <form id="vault-add" x-show="adding" x-cloak method="POST" action="{{ route('admin.vault.store') }}" class="mbui-card mt-4 grid gap-3 p-4 sm:grid-cols-2" autocomplete="off">
                    @csrf
                    <div>
                        <x-input-label for="v-name" value="Name (e.g. Hostinger, Instagram)" />
                        <x-text-input id="v-name" name="name" :value="old('name')" maxlength="150" required class="mbui-input mt-1 w-full" />
                    </div>
                    <div>
                        <x-input-label for="v-category" value="Category" />
                        <select id="v-category" name="category" class="mbui-input mt-1 w-full">
                            @foreach (\App\Models\VaultEntry::CATEGORIES as $key => $label)
                                <option value="{{ $key }}" @selected(old('category', 'other') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="v-url" value="Website / login page" />
                        <x-text-input id="v-url" name="url" :value="old('url')" maxlength="500" placeholder="https://" class="mbui-input mt-1 w-full" />
                    </div>
                    <div>
                        <x-input-label for="v-username" value="Username / email" />
                        <x-text-input id="v-username" name="username" maxlength="500" autocomplete="off" class="mbui-input mt-1 w-full" />
                    </div>
                    <div x-data="{ see: false }">
                        <x-input-label for="v-password" value="Password" />
                        <div class="mt-1 flex gap-2">
                            <input id="v-password" name="password" :type="see ? 'text' : 'password'" maxlength="1000" required autocomplete="new-password" class="mbui-input w-full">
                            <button type="button" @click="see = !see" class="rounded-lg border border-gray-300 px-2 text-xs font-medium text-gray-600" x-text="see ? 'Hide' : 'Show'">Show</button>
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="v-notes" value="Notes (2FA backup codes, recovery email, PIN…) — encrypted too" />
                        <textarea id="v-notes" name="notes" rows="2" maxlength="3000" class="mbui-input mt-1 w-full"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 sm:col-span-2">
                        <button type="button" @click="adding = false" class="text-sm font-medium text-gray-600 hover:text-gray-900">Cancel</button>
                        <x-mbui.button type="submit">Save login</x-mbui.button>
                    </div>
                </form>
        @endif
    </div>

    @if ($entries->isEmpty())
        <div class="mt-6">
            <x-mbui.empty-state title="No logins here yet" message="{{ $canManage ? 'Add the company accounts: hosting, domains, email, social media, payment dashboards…' : 'Nothing saved yet.' }}" />
        </div>
    @else
        <div class="mt-6 space-y-3">
            @foreach ($entries as $entry)
                <div class="mbui-card p-4" x-data="vaultEntry(@js(route('admin.vault.reveal', $entry)))">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-sm font-semibold text-gray-900">{{ $entry->name }}</h3>
                                <x-mbui.badge>{{ $entry->categoryLabel() }}</x-mbui.badge>
                            </div>
                            @if ($entry->url)
                                @php($href = preg_match('#^https?://#i', $entry->url) ? $entry->url : 'https://'.$entry->url)
                                <a href="{{ $href }}" target="_blank" rel="noopener noreferrer" class="mt-1 block truncate text-xs text-indigo-600 hover:underline">{{ $entry->url }}</a>
                            @endif
                            <p class="mt-1 text-xs text-gray-500">Updated {{ $entry->updated_at?->diffForHumans() }}{{ $entry->editor ? ' by '.$entry->editor->name : '' }}</p>
                        </div>
                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            <button type="button" @click="copy('username')" class="rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50" x-text="copied === 'username' ? 'Copied!' : 'Copy user'">Copy user</button>
                            <button type="button" @click="copy('password')" class="rounded-lg bg-indigo-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500" x-text="copied === 'password' ? 'Copied!' : 'Copy password'">Copy password</button>
                            <button type="button" @click="toggle()" class="rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50" x-text="shown ? 'Hide' : 'Show'">Show</button>
                            @if ($canManage)
                                <button type="button" @click="$refs.edit.classList.toggle('hidden')" class="rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">Edit</button>
                                <form method="POST" action="{{ route('admin.vault.destroy', $entry) }}" onsubmit="return confirm('Remove {{ addslashes($entry->name) }} from the vault?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="rounded-lg border border-red-200 px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">Delete</button>
                                </form>
                            @endif
                        </div>
                    </div>

                    <template x-if="shown && data">
                        <dl class="mt-3 grid gap-2 rounded-lg bg-gray-50 p-3 text-sm sm:grid-cols-2">
                            <div><dt class="text-xs text-gray-500">Username</dt><dd class="break-all font-mono text-gray-900" x-text="data.username || '—'"></dd></div>
                            <div><dt class="text-xs text-gray-500">Password</dt><dd class="break-all font-mono text-gray-900" x-text="data.password || '—'"></dd></div>
                            <div class="sm:col-span-2" x-show="data.notes"><dt class="text-xs text-gray-500">Notes</dt><dd class="whitespace-pre-line break-words text-gray-900" x-text="data.notes"></dd></div>
                        </dl>
                    </template>

                    @if ($canManage)
                        <form x-ref="edit" method="POST" action="{{ route('admin.vault.update', $entry) }}" class="hidden mt-3 grid gap-3 border-t border-gray-100 pt-3 sm:grid-cols-2" autocomplete="off">
                            @csrf @method('PUT')
                            <input type="hidden" name="_entry" value="{{ $entry->id }}">
                            <div>
                                <x-input-label value="Name" />
                                <input name="name" value="{{ $entry->name }}" maxlength="150" required class="mbui-input mt-1 w-full">
                            </div>
                            <div>
                                <x-input-label value="Category" />
                                <select name="category" class="mbui-input mt-1 w-full">
                                    @foreach (\App\Models\VaultEntry::CATEGORIES as $key => $label)
                                        <option value="{{ $key }}" @selected($entry->category === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label value="Website / login page" />
                                <input name="url" value="{{ $entry->url }}" maxlength="500" class="mbui-input mt-1 w-full">
                            </div>
                            <div>
                                <x-input-label value="New username (blank = keep)" />
                                <input name="username" maxlength="500" autocomplete="off" class="mbui-input mt-1 w-full">
                            </div>
                            <div>
                                <x-input-label value="New password (blank = keep)" />
                                <input name="password" type="password" maxlength="1000" autocomplete="new-password" class="mbui-input mt-1 w-full">
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label value="New notes (blank = keep)" />
                                <textarea name="notes" rows="2" maxlength="3000" class="mbui-input mt-1 w-full"></textarea>
                                <label class="mt-1 inline-flex items-center gap-2 text-xs text-gray-600"><input type="checkbox" name="clear_notes" value="1" class="rounded border-gray-300"> Remove the saved notes</label>
                            </div>
                            <div class="flex justify-end gap-2 sm:col-span-2">
                                <button type="button" @click="$refs.edit.classList.add('hidden')" class="text-sm font-medium text-gray-600 hover:text-gray-900">Cancel</button>
                                <x-mbui.button type="submit">Save changes</x-mbui.button>
                            </div>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if (auth()->user()->isSuperAdmin())
        <details class="mbui-card mt-8 p-4">
            <summary class="cursor-pointer text-sm font-semibold text-gray-700">Change the vault PIN</summary>
            <form method="POST" action="{{ route('admin.vault.pin.set') }}" class="mt-3 grid gap-3 sm:grid-cols-3">
                @csrf
                <input name="current_pin" type="password" inputmode="numeric" maxlength="6" placeholder="Current PIN" autocomplete="off" required class="mbui-input">
                <input name="new_pin" type="password" inputmode="numeric" maxlength="6" placeholder="New PIN" autocomplete="new-password" required class="mbui-input">
                <input name="new_pin_confirmation" type="password" inputmode="numeric" maxlength="6" placeholder="Repeat new PIN" autocomplete="new-password" required class="mbui-input">
                <div class="sm:col-span-3"><x-mbui.button type="submit">Save new PIN</x-mbui.button></div>
            </form>
            <p class="mt-2 text-xs text-gray-500">Who can open the vault: Team → member permissions → "Password vault". Super admins always can.</p>
        </details>
    @endif

</x-layouts.admin>
