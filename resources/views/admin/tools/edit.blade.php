<x-layouts.admin title="Edit Tool" header="Edit Tool">

    <div class="max-w-2xl">
        <form method="POST" action="{{ route('admin.tools.update', $tool) }}" enctype="multipart/form-data" class="mbui-card p-6 space-y-5">
            @csrf
            @method('PUT')
            <div>
                <x-input-label for="name" value="Name" />
                <x-text-input id="name" class="mbui-input mt-1" type="text" name="name" :value="old('name', $tool->name)" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="slug" value="Slug" />
                <x-text-input id="slug" class="mbui-input mt-1" type="text" name="slug" :value="old('slug', $tool->slug)" required />
                <x-input-error :messages="$errors->get('slug')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="description" value="Description" />
                <textarea id="description" name="description" rows="4" class="mbui-input mt-1">{{ old('description', $tool->description) }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="price" value="Price (TZS)" />
                    <x-text-input id="price" class="mbui-input mt-1" type="number" step="0.01" min="0" name="price" :value="old('price', $tool->price)" required />
                    <x-input-error :messages="$errors->get('price')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="version" value="Version (optional)" />
                    <x-text-input id="version" class="mbui-input mt-1" type="text" name="version" :value="old('version', $tool->version)" placeholder="v1.0" />
                    <x-input-error :messages="$errors->get('version')" class="mt-2" />
                </div>
            </div>
            <div>
                <x-input-label for="tool_file" value="Program file (zip / exe / msi / rar / apk)" />
                <div x-data="{ uploading: false, progress: 0, filePath: '', fileName: '{{ $tool->primaryDownload?->file_filename }}', removed: false,
                    onFileChange(ev) {
                        const file = ev.target.files[0];
                        if (!file) return;
                        this.filePath = '';
                        this.fileName = '';
                        this.removed = false;
                        const fd = new FormData();
                        fd.append('tool_file', file);
                        fd.append('_token', '{{ csrf_token() }}');
                        const xhr = new XMLHttpRequest();
                        xhr.open('POST', '{{ route('admin.tool-files.store') }}');
                        xhr.setRequestHeader('Accept', 'application/json');
                        this.uploading = true;
                        this.progress = 0;
                        xhr.upload.onprogress = (e) => { if (e.lengthComputable) this.progress = Math.round((e.loaded / e.total) * 100); };
                        xhr.onload = () => {
                            this.uploading = false;
                            if (xhr.status === 200) {
                                const r = JSON.parse(xhr.responseText);
                                this.filePath = r.path;
                                this.fileName = r.filename;
                            } else {
                                alert('Upload failed. Use a zip/exe/msi/rar/apk file under 150MB.');
                            }
                        };
                        xhr.onerror = () => { this.uploading = false; alert('Upload failed. Check your connection.'); };
                        xhr.send(fd);
                    },
                    detach() { this.filePath = ''; this.fileName = ''; this.removed = true; }
                }">
                    <input type="file" accept=".exe,.zip,.msi,.rar,.apk"
                        class="mt-1 block w-full text-sm text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100"
                        @change="onFileChange($event)">
                    <template x-if="uploading">
                        <div class="mt-2">
                            <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200">
                                <div class="h-full rounded-full bg-indigo-600 transition-all" :style="'width:' + progress + '%'"></div>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Uploading <span x-text="progress + '%'"></span></p>
                        </div>
                    </template>
                    <p x-show="fileName && !uploading" class="mt-2 text-xs text-emerald-700">
                        Attached: <span class="font-medium" x-text="fileName"></span>
                        <button type="button" class="ml-1 font-medium text-red-600 hover:text-red-800" @click="detach()">Remove</button>
                    </p>
                    <input type="hidden" name="tool_file_path" :value="filePath">
                    <input type="hidden" name="tool_filename" :value="fileName">
                    <input type="hidden" name="remove_file" :value="removed ? 1 : 0">
                </div>
                <x-input-error :messages="$errors->get('tool_file')" class="mt-2" />
                <p class="mt-1 text-xs text-gray-400">Uploads in the background with progress. Buyers can download it once their payment is approved.</p>
            </div>
            <div>
                <x-input-label for="license_key" value="Product key" />
                <textarea id="license_key" name="license_key" rows="2" class="mbui-input mt-1 font-mono text-xs" placeholder="Paste the key buyers will receive.">{{ old('license_key', $tool->license_key) }}</textarea>
                <x-input-error :messages="$errors->get('license_key')" class="mt-2" />
                <p class="mt-1 text-xs text-gray-500">Sent to the buyer automatically once you approve their payment.</p>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="status" value="Status" />
                    <select id="status" name="status" class="mbui-input mt-1">
                        @foreach (['draft', 'published', 'archived'] as $status)
                            <option value="{{ $status }}" @selected(old('status', $tool->status) === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('status')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="sort_order" value="Sort order" />
                    <x-text-input id="sort_order" class="mbui-input mt-1" type="number" name="sort_order" :value="old('sort_order', $tool->sort_order)" />
                    <x-input-error :messages="$errors->get('sort_order')" class="mt-2" />
                </div>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="image" value="Image" />
                    @if ($tool->imageUrl())
                        <img src="{{ $tool->imageUrl() }}" alt="{{ $tool->name }}" class="mt-1 mb-2 h-16 w-16 rounded-lg border border-gray-200 object-cover">
                    @endif
                    <input id="image" type="file" name="image" accept="image/*" class="mt-1 block w-full text-sm text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
                    <x-input-error :messages="$errors->get('image')" class="mt-2" />
                </div>
                <div class="flex items-end pb-1">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $tool->is_featured)) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        Featured tool
                    </label>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-5">
                <a href="{{ route('admin.tools.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">Cancel</a>
                <x-mbui.button type="submit">Update tool</x-mbui.button>
            </div>
        </form>

        <x-mbui.table :title="'Recent orders (' . $tool->orders()->count() . ')'" class="mt-6">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">Order</th>
                    <th class="mbui-th">Buyer</th>
                    <th class="mbui-th">Status</th>
                    <th class="mbui-th">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($tool->orders()->with('user')->latest()->take(10)->get() as $order)
                    <tr>
                        <td class="mbui-td">
                            <a href="{{ route('admin.orders.show', $order) }}" class="mbui-anchor">{{ $order->order_number }}</a>
                        </td>
                        <td class="mbui-td">{{ $order->user->name }}</td>
                        <td class="mbui-td"><x-mbui.status-badge :status="$order->status" /></td>
                        <td class="mbui-td text-gray-500">{{ $order->created_at->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="mbui-td text-center text-gray-400">No orders yet</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

</x-layouts.admin>
