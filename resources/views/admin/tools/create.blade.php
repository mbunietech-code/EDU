<x-layouts.admin title="Add Tool" header="Add Tool">

    <div class="max-w-2xl">
        <form method="POST" action="{{ route('admin.tools.store') }}" enctype="multipart/form-data" class="mbui-card p-6 space-y-5">
            @csrf
            <div>
                <x-input-label for="name" value="Name" />
                <x-text-input id="name" class="mbui-input mt-1" type="text" name="name" :value="old('name')" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="slug" value="Slug" />
                <x-text-input id="slug" class="mbui-input mt-1" type="text" name="slug" :value="old('slug')" placeholder="tool-name" required />
                <x-input-error :messages="$errors->get('slug')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="description" value="Description" />
                <textarea id="description" name="description" rows="4" class="mbui-input mt-1">{{ old('description') }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="price" value="Price (TZS)" />
                    <x-text-input id="price" class="mbui-input mt-1" type="number" step="0.01" min="0" name="price" :value="old('price', 0)" required />
                    <x-input-error :messages="$errors->get('price')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="version" value="Version (optional)" />
                    <x-text-input id="version" class="mbui-input mt-1" type="text" name="version" :value="old('version')" placeholder="v1.0" />
                    <x-input-error :messages="$errors->get('version')" class="mt-2" />
                </div>
            </div>
            <div>
                <x-input-label for="tool_file" value="Program file (zip / exe / msi / rar / apk)" />
                <div x-data="{ uploading: false, progress: 0, filePath: '', fileName: '',
                    onFileChange(ev) {
                        const file = ev.target.files[0];
                        if (!file) return;
                        this.filePath = '';
                        this.fileName = '';
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
                    detach() { this.filePath = ''; this.fileName = ''; }
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
                    <p x-show="filePath && !uploading" class="mt-2 text-xs text-emerald-700">
                        Attached: <span class="font-medium" x-text="fileName"></span>
                        <button type="button" class="ml-1 font-medium text-red-600 hover:text-red-800" @click="detach()">Remove</button>
                    </p>
                    <input type="hidden" name="tool_file_path" :value="filePath">
                    <input type="hidden" name="tool_filename" :value="fileName">
                </div>
                <x-input-error :messages="$errors->get('tool_file')" class="mt-2" />
                <p class="mt-1 text-xs text-gray-400">Uploads in the background with progress. Buyers can download it once their payment is approved.</p>
            </div>
            <div>
                <x-input-label for="license_key" value="Product key" />
                <textarea id="license_key" name="license_key" rows="2" class="mbui-input mt-1 font-mono text-xs" placeholder="Paste the key buyers will receive. It is shown to a buyer only after their payment is approved.">{{ old('license_key') }}</textarea>
                <x-input-error :messages="$errors->get('license_key')" class="mt-2" />
                <p class="mt-1 text-xs text-gray-500">Sent to the buyer automatically once you approve their payment.</p>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="status" value="Status" />
                    <select id="status" name="status" class="mbui-input mt-1">
                        @foreach (['draft', 'published', 'archived'] as $status)
                            <option value="{{ $status }}" @selected(old('status', 'draft') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('status')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="sort_order" value="Sort order" />
                    <x-text-input id="sort_order" class="mbui-input mt-1" type="number" name="sort_order" :value="old('sort_order', 0)" />
                    <x-input-error :messages="$errors->get('sort_order')" class="mt-2" />
                </div>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="image" value="Image" />
                    <input id="image" type="file" name="image" accept="image/*" class="mt-1 block w-full text-sm text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
                    <x-input-error :messages="$errors->get('image')" class="mt-2" />
                </div>
                <div class="flex items-end pb-1">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured')) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        Featured tool
                    </label>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-5">
                <a href="{{ route('admin.tools.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">Cancel</a>
                <x-mbui.button type="submit">Create tool</x-mbui.button>
            </div>
        </form>
    </div>

</x-layouts.admin>
